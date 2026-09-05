<?php
/**
 * Playback queue block: the listener's durable "up next" manager.
 *
 * Renders the signed-in listener's durable queue with reorder, remove,
 * clear, shuffle, and repeat controls that work as plain form posts —
 * zero JavaScript required — plus a sign-in panel for guests. Mirrors the
 * playlist manager contract so both surfaces behave identically
 * (PROJECT_PLAN.md Stage 4 deliverable 6, Stage 5 deliverable 2).
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Listening\ListeningRepository;
use ManaCore\MusicWave\Core\Listening\QueueFormHandler;

final class QueueBlock {
	/** @var ListeningRepository */
	private $repository;

	/** @var QueueFormHandler */
	private $forms;

	/** @var \WP_Block|null Block instance of the add-control currently rendering. */
	private $render_context;

	public function __construct( ListeningRepository $repository, QueueFormHandler $forms ) {
		$this->repository = $repository;
		$this->forms      = $forms;
	}

	/**
	 * Register the server-rendered playback queue block.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		BlockSupport::register_dynamic(
			'music-wave/playback-queue',
			array( $this, 'render' ),
			array(
				'api_version' => 3,
				'supports'    => BlockSupport::appearance_tools(),
			)
		);
		BlockSupport::register_dynamic(
			'music-wave/add-to-queue',
			function ( $attributes, $content, $block ): string {
				unset( $content );
				$previous             = $this->render_context;
				$this->render_context = is_object( $block ) ? $block : null;

				try {
					return $this->render_add( is_array( $attributes ) ? $attributes : array() );
				} finally {
					$this->render_context = $previous;
				}
			},
			array(
				'api_version'  => 3,
				'uses_context' => array( 'postId', 'postType' ),
				'supports'     => BlockSupport::appearance_tools(),
			)
		);
	}

	/**
	 * Render the "add to queue" control for one release.
	 *
	 * Mirrors the add-to-playlist picker: resolves the current or pinned
	 * release, shows a plain form post for signed-in listeners, a sign-in
	 * button for guests, and an in-queue badge once queued.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_add( array $attributes ): string {
		$release_id = $this->resolve_release_id( $attributes );
		if ( $release_id < 1 ) {
			return '';
		}

		$position = isset( $attributes['position'] ) ? sanitize_key( (string) $attributes['position'] ) : 'next';
		$position = in_array( $position, array( 'next', 'end' ), true ) ? $position : 'next';
		$user_id  = get_current_user_id();

		if ( $user_id < 1 ) {
			return '<div class="mw-add-to-queue mw-add-to-queue--guest"><a class="wp-element-button" href="'
				. esc_url( wp_login_url( BlockSupport::current_url() ) ) . '">'
				. esc_html( __( 'ورود به صف', 'music-wave-core' ) ) . '</a></div>';
		}

		$queued = in_array( $release_id, $this->repository->queue( $user_id )['ids'], true );
		if ( $queued ) {
			return '<div class="mw-add-to-queue mw-add-to-queue--queued"><span class="mw-add-to-queue__in">'
				. '<span aria-hidden="true">&#10003;</span> '
				. esc_html( __( 'در صف شما', 'music-wave-core' ) )
				. '</span></div>';
		}

		$label = isset( $attributes['label'] ) && '' !== sanitize_text_field( (string) $attributes['label'] )
			? sanitize_text_field( (string) $attributes['label'] )
			: ( 'end' === $position
				? __( 'افزودن به صف', 'music-wave-core' )
				: __( 'پخش بعدی', 'music-wave-core' ) );

		return '<div class="mw-add-to-queue"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="mw_operation" value="add">'
			. '<input type="hidden" name="mw_value" value="' . esc_attr( $position ) . '">'
			. '<input type="hidden" name="mw_release_id" value="' . esc_attr( (string) $release_id ) . '">'
			. wp_nonce_field( QueueFormHandler::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="' . esc_attr( QueueFormHandler::ACTION ) . '">'
			. '<input type="hidden" name="mw_redirect" value="' . esc_attr( BlockSupport::current_url() ) . '">'
			. '<button type="submit" class="wp-element-button">' . esc_html( $label ) . '</button>'
			. '</form></div>';
	}

	/**
	 * Resolve the release the add-control acts on.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function resolve_release_id( array $attributes ): int {
		$explicit = isset( $attributes['releaseId'] ) ? absint( $attributes['releaseId'] ) : 0;
		if ( $explicit > 0 && ReleasePostType::KEY === get_post_type( $explicit ) ) {
			return $explicit;
		}

		$context = $this->render_context;
		if ( is_object( $context ) && isset( $context->context ) && is_array( $context->context ) ) {
			$post_type = isset( $context->context['postType'] ) ? (string) $context->context['postType'] : '';
			$post_id   = isset( $context->context['postId'] ) ? absint( $context->context['postId'] ) : 0;
			if ( ReleasePostType::KEY === $post_type && $post_id > 0 ) {
				return $post_id;
			}
		}

		$current = function_exists( 'get_the_ID' ) ? absint( get_the_ID() ) : 0;

		return ReleasePostType::KEY === get_post_type( $current ) ? $current : 0;
	}

	/**
	 * Render the queue surface for the current viewer.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render( array $attributes ): string {
		$user_id = get_current_user_id();

		if ( $user_id < 1 ) {
			return $this->render_guest( $attributes );
		}

		$queue = $this->repository->queue( $user_id );
		$ids   = $queue['ids'];

		$options = array(
			'heading'       => BlockSupport::text_attribute( $attributes, 'heading', __( 'بعدی', 'music-wave-core' ) ),
			'show_artwork'  => ! isset( $attributes['showArtwork'] ) || (bool) $attributes['showArtwork'],
			'show_position' => ! isset( $attributes['showPosition'] ) || (bool) $attributes['showPosition'],
			'show_artist'   => ! isset( $attributes['showArtist'] ) || (bool) $attributes['showArtist'],
			'show_controls' => ! isset( $attributes['showControls'] ) || (bool) $attributes['showControls'],
			'show_clear'    => ! isset( $attributes['showClear'] ) || (bool) $attributes['showClear'],
		);

		$header = '<header class="mw-playback-queue__header"><div>'
			. '<h2 class="mw-playback-queue__title">' . esc_html( (string) $options['heading'] ) . '</h2>'
			. '</div>'
			. /* translators: %d: number of queued releases. */
			'<span class="mw-playback-queue__count" aria-label="' . esc_attr( sprintf( __( '%d در صف', 'music-wave-core' ), count( $ids ) ) ) . '">' . esc_html( number_format_i18n( count( $ids ) ) ) . '</span>'
			. '</header>';

		if ( array() === $ids ) {
			$empty = BlockSupport::text_attribute( $attributes, 'emptyMessage', __( 'صف شما خالی است. برای ساختن آن، روی هر انتشار «پخش بعدی» را انتخاب کنید.', 'music-wave-core' ) );

			return '<section ' . BlockSupport::wrapper_attributes( 'mw-playback-queue mw-playback-queue--empty' ) . '>'
				. $header
				. $this->notice_markup()
				. '<p class="mw-playback-queue__empty">' . esc_html( $empty ) . '</p>'
				. '</section>';
		}

		$rows = array();
		foreach ( array_values( $ids ) as $index => $release_id ) {
			$rows[] = $this->row( (int) $release_id, $index, count( $ids ), $options );
		}

		return '<section ' . BlockSupport::wrapper_attributes( 'mw-playback-queue' ) . ' data-mw-queue>'
			. $header
			. $this->notice_markup()
			. ( $options['show_controls'] ? $this->controls_markup( $queue ) : '' )
			. '<ol class="mw-playback-queue__list">' . implode( '', $rows ) . '</ol>'
			. ( $options['show_clear'] ? $this->clear_markup() : '' )
			. '</section>';
	}

	/**
	 * Guest state: a compact sign-in prompt keeps the layout reserved.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function render_guest( array $attributes ): string {
		$heading = BlockSupport::text_attribute( $attributes, 'heading', __( 'بعدی', 'music-wave-core' ) );

		return '<section ' . BlockSupport::wrapper_attributes( 'mw-playback-queue mw-playback-queue--guest' ) . '>'
			. '<div class="mw-playback-queue__panel"><span class="mw-playback-queue__panel-icon" aria-hidden="true">☰</span>'
			. '<div><h2 class="mw-playback-queue__title">' . esc_html( $heading ) . '</h2>'
			. '<p class="mw-playback-queue__guest-text">' . esc_html__( 'برای حفظ صف پخش در بین صفحات و بازدیدها وارد سیستم شوید.', 'music-wave-core' ) . '</p></div>'
			. '<a class="wp-element-button mw-playback-queue__guest-cta" href="' . esc_url( wp_login_url( BlockSupport::current_url() ) ) . '">' . esc_html__( 'وارد شوید', 'music-wave-core' ) . '</a></div>'
			. '</section>';
	}

	/**
	 * One queued release row with its per-item forms.
	 *
	 * @param int                  $release_id Queued release.
	 * @param int                  $index      Zero-based position.
	 * @param int                  $total      Row count for edge detection.
	 * @param array<string, mixed> $options    Resolved options.
	 */
	private function row( int $release_id, int $index, int $total, array $options ): string {
		$title = get_the_title( $release_id );
		$link  = get_permalink( $release_id );
		if ( $release_id < 1 || ! is_string( $link ) || '' === $link ) {
			return '';
		}
		$title = '' !== $title ? $title : __( 'انتشار بدون عنوان', 'music-wave-core' );

		/* translators: %s: release title. */
		$move_up_label = sprintf( __( '%s را به بالا منتقل کنید', 'music-wave-core' ), $title );
		/* translators: %s: release title. */
		$move_down_label = sprintf( __( '%s را به پایین حرکت دهید', 'music-wave-core' ), $title );
		/* translators: %s: release title. */
		$remove_label = sprintf( __( '%s را از صف حذف کنید', 'music-wave-core' ), $title );

		$art = '';
		if ( $options['show_artwork'] ) {
			$image   = get_the_post_thumbnail(
				$release_id,
				'thumbnail',
				array(
					'class'    => 'mw-playback-queue__image',
					'alt'      => '',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			);
			$initial = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 1 ) : substr( $title, 0, 1 );
			$art     = '<a class="mw-playback-queue__art" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">'
				. ( '' !== $image ? $image : '<span class="mw-playback-queue__initial">' . esc_html( $initial ) . '</span>' )
				. '</a>';
		}

		$position = '';
		if ( $options['show_position'] ) {
			$position = '<span class="mw-playback-queue__position" aria-hidden="true">' . esc_html( number_format_i18n( $index + 1 ) ) . '</span>';
		}

		$artist = '';
		if ( $options['show_artist'] ) {
			$artists = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'names' ) );
			if ( is_array( $artists ) && ! empty( $artists ) ) {
				$artist = '<span class="mw-playback-queue__artist">' . esc_html( implode( ', ', $artists ) ) . '</span>';
			}
		}

		$actions = '<form class="mw-playback-queue__actions" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		if ( $index > 0 ) {
			$actions .= '<button type="submit" name="mw_operation" value="move-up" aria-label="' . esc_attr( $move_up_label ) . '" title="' . esc_attr( $move_up_label ) . '">&uarr;</button>';
		}
		if ( $index < $total - 1 ) {
			$actions .= '<button type="submit" name="mw_operation" value="move-down" aria-label="' . esc_attr( $move_down_label ) . '" title="' . esc_attr( $move_down_label ) . '">&darr;</button>';
		}
		$actions .= '<input type="hidden" name="mw_release_id" value="' . esc_attr( (string) $release_id ) . '">'
			. '<button type="submit" name="mw_operation" value="remove-item" class="mw-playback-queue__remove" aria-label="' . esc_attr( $remove_label ) . '" title="' . esc_attr( $remove_label ) . '">&times;</button>'
			. $this->hidden_fields()
			. '</form>';

		return '<li class="mw-playback-queue__item">'
			. $position
			. $art
			. '<div class="mw-playback-queue__body"><h3 class="mw-playback-queue__name"><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></h3>' . $artist . '</div>'
			. $actions
			. '</li>';
	}

	/**
	 * Shuffle and repeat preference forms.
	 *
	 * @param array<string, mixed> $queue Normalized queue snapshot.
	 */
	private function controls_markup( array $queue ): string {
		$shuffle_label = $queue['shuffle'] ? __( 'تصادفی: روشن', 'music-wave-core' ) : __( 'پخش تصادفی: خاموش', 'music-wave-core' );

		return '<div class="mw-playback-queue__controls">'
			. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="mw_operation" value="shuffle">'
			. '<input type="hidden" name="mw_value" value="' . ( $queue['shuffle'] ? 'off' : 'on' ) . '">'
			. '<button type="submit"' . ( $queue['shuffle'] ? ' aria-pressed="true"' : ' aria-pressed="false"' ) . '>' . esc_html( $shuffle_label ) . '</button>'
			. $this->hidden_fields()
			. '</form>'
			. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<label class="screen-reader-text" for="mw-queue-repeat">' . esc_html__( 'حالت تکرار', 'music-wave-core' ) . '</label>'
			. '<select id="mw-queue-repeat" name="mw_value">'
			. '<option value="off"' . selected( 'off', $queue['repeat'], false ) . '>' . esc_html__( 'تکرار: خاموش', 'music-wave-core' ) . '</option>'
			. '<option value="all"' . selected( 'all', $queue['repeat'], false ) . '>' . esc_html__( 'تکرار: همه', 'music-wave-core' ) . '</option>'
			. '<option value="one"' . selected( 'one', $queue['repeat'], false ) . '>' . esc_html__( 'تکرار: یک', 'music-wave-core' ) . '</option>'
			. '</select>'
			. '<input type="hidden" name="mw_operation" value="repeat">'
			. '<button type="submit">' . esc_html__( 'ذخیره تکرار', 'music-wave-core' ) . '</button>'
			. $this->hidden_fields()
			. '</form>'
			. '</div>';
	}

	/**
	 * Clear-the-whole-queue form.
	 */
	private function clear_markup(): string {
		return '<form class="mw-playback-queue__clear" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="mw_operation" value="clear">'
			. '<button type="submit">' . esc_html__( 'پاک کردن صف', 'music-wave-core' ) . '</button>'
			. $this->hidden_fields()
			. '</form>';
	}

	/**
	 * Shared hidden fields for every queue form.
	 */
	private function hidden_fields(): string {
		return wp_nonce_field( QueueFormHandler::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="' . esc_attr( QueueFormHandler::ACTION ) . '">'
			. '<input type="hidden" name="mw_redirect" value="' . esc_attr( BlockSupport::current_url() ) . '">';
	}

	/**
	 * Post/redirect/get notice rendered in a polite live region.
	 */
	private function notice_markup(): string {
		$notice  = isset( $_GET[ QueueFormHandler::NOTICE_ARG ] ) && is_scalar( $_GET[ QueueFormHandler::NOTICE_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ QueueFormHandler::NOTICE_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = '' !== $notice ? $this->forms->notice_message( $notice ) : '';
		if ( '' === $message ) {
			return '';
		}

		$class = 'mw-playback-queue__notice' . ( $this->forms->notice_is_error( $notice ) ? ' mw-playback-queue__notice--error' : '' );

		return '<p class="' . esc_attr( $class ) . '" role="status" aria-live="polite">' . esc_html( $message ) . '</p>';
	}
}
