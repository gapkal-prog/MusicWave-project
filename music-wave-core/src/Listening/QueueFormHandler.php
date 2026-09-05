<?php
/**
 * No-JavaScript playback queue mutations.
 *
 * The durable listening queue is reachable through plain HTML forms posted
 * to `admin-post.php`: remove, reorder, clear, shuffle, and repeat. Every
 * request is nonce-verified, answered with a post/redirect/get round trip
 * carrying a notice code that the queue block renders in a live region.
 * JavaScript only ever makes this faster, never possible — mirroring the
 * playlist form contract (PROJECT_PLAN.md Stage 4 deliverable 6).
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Listening;

final class QueueFormHandler {
	public const ACTION     = 'mw_queue';
	public const NONCE      = 'mw_queue_action';
	public const NOTICE_ARG = 'mw-queue-notice';

	/** @var ListeningRepository */
	private $repository;

	public function __construct( ListeningRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_unauthenticated' ) );
	}

	/**
	 * Handle one queue form submission.
	 *
	 * @return void
	 */
	public function handle(): void {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			$this->handle_unauthenticated();

			return;
		}

		$redirect = $this->redirect_target();
		if ( ! check_admin_referer( self::NONCE ) ) {
			// check_admin_referer() already halts on failure; this keeps static
			// analysis and future refactors honest.
			$this->redirect( $redirect, 'invalid' );

			return;
		}

		$operation  = isset( $_POST['mw_operation'] ) && is_scalar( $_POST['mw_operation'] ) ? sanitize_key( wp_unslash( (string) $_POST['mw_operation'] ) ) : '';
		$release_id = isset( $_POST['mw_release_id'] ) ? absint( wp_unslash( (string) $_POST['mw_release_id'] ) ) : 0;
		$value      = isset( $_POST['mw_value'] ) && is_scalar( $_POST['mw_value'] ) ? sanitize_key( wp_unslash( (string) $_POST['mw_value'] ) ) : '';

		$this->redirect( $redirect, $this->run( $operation, $user_id, $release_id, $value ) );
	}

	/**
	 * Send signed-out visitors to the login screen instead of failing silently.
	 *
	 * @return void
	 */
	public function handle_unauthenticated(): void {
		$target = wp_login_url( $this->redirect_target() );
		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( $target, 302 );
			$this->finish();
		}
	}

	/**
	 * Execute one validated operation.
	 *
	 * Exposed for tests: no superglobals, no redirects, no output.
	 *
	 * @return string Notice code.
	 */
	public function run( string $operation, int $user_id, int $release_id = 0, string $value = '' ): string {
		if ( $user_id < 1 ) {
			return 'guest';
		}

		$queue = $this->repository->queue( $user_id );

		switch ( $operation ) {
			case 'add':
				if ( $release_id < 1 ) {
					return 'add-failed';
				}
				if ( in_array( $release_id, $queue['ids'], true ) ) {
					return 'already-queued';
				}

				$ids = $queue['ids'];
				if ( 'end' === $value ) {
					$ids[] = $release_id;
				} else {
					// "Play next" inserts right after the current position.
					array_splice( $ids, min( count( $ids ), $queue['position'] + 1 ), 0, array( $release_id ) );
				}

				$persisted = $this->persist(
					$user_id,
					array(
						'ids'      => $ids,
						'position' => $queue['position'],
						'shuffle'  => $queue['shuffle'],
						'repeat'   => $queue['repeat'],
					)
				);

				// Re-read through the repository: a release the listener cannot
				// read is dropped during normalization and must never report
				// success it cannot honor.
				return $persisted && in_array( $release_id, $this->repository->queue( $user_id )['ids'], true )
					? 'added'
					: 'add-failed';

			case 'remove-item':
				$index = array_search( $release_id, $queue['ids'], true );
				if ( false === $index ) {
					return 'item-remove-failed';
				}

				$ids      = array_values( array_diff( $queue['ids'], array( $release_id ) ) );
				$position = $queue['position'];
				if ( (int) $index < $position ) {
					// The current track shifted one slot earlier.
					--$position;
				}
				$position = min( $position, max( 0, count( $ids ) - 1 ) );

				return $this->persist(
					$user_id,
					array(
						'ids'      => $ids,
						'position' => $position,
						'shuffle'  => $queue['shuffle'],
						'repeat'   => $queue['repeat'],
					)
				) ? 'item-removed' : 'item-remove-failed';

			case 'move-up':
			case 'move-down':
				return $this->move( $operation, $user_id, $queue, $release_id );

			case 'clear':
				return $this->persist( $user_id, array() ) ? 'cleared' : 'clear-failed';

			case 'shuffle':
				return $this->persist(
					$user_id,
					array(
						'ids'      => $queue['ids'],
						'position' => $queue['position'],
						'shuffle'  => 'on' === $value,
						'repeat'   => $queue['repeat'],
					)
				) ? 'shuffle-updated' : 'shuffle-failed';

			case 'repeat':
				if ( ! in_array( $value, array( 'off', 'all', 'one' ), true ) ) {
					return 'repeat-failed';
				}

				return $this->persist(
					$user_id,
					array(
						'ids'      => $queue['ids'],
						'position' => $queue['position'],
						'shuffle'  => $queue['shuffle'],
						'repeat'   => $value,
					)
				) ? 'repeat-updated' : 'repeat-failed';
		}

		return 'invalid';
	}

	/**
	 * Translated notice text for one notice code.
	 */
	public function notice_message( string $notice ): string {
		$messages = array(
			'added'              => __( 'به صف شما اضافه شد', 'music-wave-core' ),
			'add-failed'         => __( 'آن انتشار را نمی‌توان به صف اضافه کرد.', 'music-wave-core' ),
			'already-queued'     => __( 'در حال حاضر در صف شما.', 'music-wave-core' ),
			'item-removed'       => __( 'از صف حذف شد.', 'music-wave-core' ),
			'item-remove-failed' => __( 'آن انتشار در صف شما نیست.', 'music-wave-core' ),
			'moved'              => __( 'ترتیب صف به‌روز شد.', 'music-wave-core' ),
			'move-failed'        => __( 'ترتیب صف را نمی‌توان تغییر داد.', 'music-wave-core' ),
			'cleared'            => __( 'صف پاک شد.', 'music-wave-core' ),
			'clear-failed'       => __( 'صف پاک نشد.', 'music-wave-core' ),
			'shuffle-updated'    => __( 'اولویت ترکیبی به‌روزرسانی شد.', 'music-wave-core' ),
			'shuffle-failed'     => __( 'ترجیح حالت تصادفی ذخیره نشد.', 'music-wave-core' ),
			'repeat-updated'     => __( 'تکرار اولویت به‌روزرسانی شد.', 'music-wave-core' ),
			'repeat-failed'      => __( 'اولویت تکرار ذخیره نشد.', 'music-wave-core' ),
			'invalid'            => __( 'آن درخواست صف معتبر نبود. دوباره امتحان کنید.', 'music-wave-core' ),
			'guest'              => __( 'برای مدیریت صف خود وارد سیستم شوید.', 'music-wave-core' ),
		);

		return isset( $messages[ $notice ] ) ? $messages[ $notice ] : '';
	}

	/**
	 * Whether one notice code represents a failure.
	 */
	public function notice_is_error( string $notice ): bool {
		return in_array( $notice, array( 'invalid', 'guest' ), true )
			|| false !== strpos( $notice, '-failed' );
	}

	/**
	 * Swap one release with its neighbor inside the durable queue.
	 *
	 * @param array<string, mixed> $queue      Normalized queue snapshot.
	 * @return string Notice code.
	 */
	private function move( string $operation, int $user_id, array $queue, int $release_id ): string {
		$order = $queue['ids'];
		$index = array_search( $release_id, $order, true );
		if ( false === $index ) {
			return 'move-failed';
		}

		$target = 'move-up' === $operation ? (int) $index - 1 : (int) $index + 1;
		if ( $target < 0 || $target > count( $order ) - 1 ) {
			return 'move-failed';
		}

		$swap             = $order[ $target ];
		$order[ $target ] = $release_id;
		$order[ $index ]  = $swap;

		// Keep following the moved track after the swap.
		$position = $queue['position'];
		if ( $position === (int) $index ) {
			$position = $target;
		} elseif ( $position === $target ) {
			$position = (int) $index;
		}

		return $this->persist(
			$user_id,
			array(
				'ids'      => $order,
				'position' => $position,
				'shuffle'  => $queue['shuffle'],
				'repeat'   => $queue['repeat'],
			)
		) ? 'moved' : 'move-failed';
	}

	/**
	 * Persist one normalized queue payload through the repository boundary.
	 *
	 * @param array<string, mixed> $payload Raw payload; the repository revalidates everything.
	 */
	private function persist( int $user_id, array $payload ): bool {
		return $this->repository->save_queue( $user_id, $payload );
	}

	/**
	 * Where to return the visitor after the mutation.
	 */
	private function redirect_target(): string {
		// Only used as a redirect destination, and only after the nonce check in
		// handle(); wp_safe_redirect() additionally restricts it to this host.
		$referer = isset( $_POST['mw_redirect'] ) && is_scalar( $_POST['mw_redirect'] ) ? esc_url_raw( wp_unslash( (string) $_POST['mw_redirect'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $referer ) {
			$referer = (string) wp_get_referer();
		}

		return '' !== $referer ? $referer : home_url( '/' );
	}

	/**
	 * @return void
	 */
	private function redirect( string $target, string $notice ): void {
		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( add_query_arg( array( self::NOTICE_ARG => $notice ), $target ), 303 );
		}

		$this->finish();
	}

	/**
	 * @return void
	 */
	private function finish(): void {
		if ( ! defined( 'MUSIC_WAVE_TESTING' ) ) {
			exit;
		}
	}
}
