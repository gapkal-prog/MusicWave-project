<?php
/**
 * Safe batched access-mode management for releases.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Admin;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;
use ManaCore\MusicWave\Core\Support\Settings;
use WP_Query;

final class BulkAccessManager {
	private const BATCH_SIZE = 200;
	private const JOB_KEY    = 'music_wave_bulk_access_job_';
	private const NOTICE_KEY = 'music_wave_bulk_access_notice_';

	/** @var ReleaseRepository */
	private $releases;

	/** @var int */
	private $query_cursor = 0;

	public function __construct( ReleaseRepository $releases ) {
		$this->releases = $releases;
	}

	public function register(): void {
		add_action( 'admin_post_music_wave_bulk_access_start', array( $this, 'start' ) );
		add_action( 'admin_post_music_wave_bulk_access_continue', array( $this, 'continue_job' ) );
		add_action( 'admin_post_music_wave_bulk_access_cancel', array( $this, 'cancel' ) );
	}

	public function start(): void {
		$this->authorize( 'music_wave_bulk_access_start' );

		$target       = $this->request_key( 'target_mode' );
		$current      = $this->request_key( 'current_mode' );
		$status       = $this->request_key( 'post_status' );
		$release_type = $this->request_key( 'release_type' );

		if ( ! in_array( $target, Settings::access_modes(), true ) ) {
			$this->fail( __( 'یک حالت دسترسی هدف معتبر را انتخاب کنید.', 'music-wave-core' ) );
		}
		if ( 'any' !== $current && ! in_array( $current, Settings::access_modes(), true ) ) {
			$current = 'any';
		}
		if ( ! in_array( $status, array( 'any', 'publish', 'draft', 'pending', 'private', 'future' ), true ) ) {
			$status = 'any';
		}

		$job = array(
			'target_mode'  => $target,
			'current_mode' => $current,
			'post_status'  => $status,
			'release_type' => $release_type,
			'cursor'       => 0,
			'updated'      => 0,
			'skipped'      => 0,
		);
		set_transient( $this->job_key(), $job, HOUR_IN_SECONDS );
		$this->process( $job );
	}

	public function continue_job(): void {
		$this->authorize( 'music_wave_bulk_access_continue' );
		$job = get_transient( $this->job_key() );
		if ( ! is_array( $job ) ) {
			$this->fail( __( 'عملیات دسترسی گروهی منقضی شده است. آن را دوباره اجرا کنید.', 'music-wave-core' ) );
		}

		$this->process( $job );
	}

	public function cancel(): void {
		$this->authorize( 'music_wave_bulk_access_cancel' );
		delete_transient( $this->job_key() );
		$this->set_notice( __( 'عملیات دسترسی گروهی لغو شد.', 'music-wave-core' ), 'warning', false );
		$this->redirect();
	}

	/**
	 * Return and consume the current user's operation notice.
	 *
	 * @return array<string, bool|string>|null
	 */
	public function consume_notice(): ?array {
		$notice = get_transient( self::NOTICE_KEY . get_current_user_id() );
		if ( ! is_array( $notice ) ) {
			return null;
		}

		delete_transient( self::NOTICE_KEY . get_current_user_id() );

		return $notice;
	}

	/**
	 * Return the active batch job for the current user.
	 *
	 * @return array<string, int|string>|null
	 */
	public function active_job(): ?array {
		$job = get_transient( $this->job_key() );

		return is_array( $job ) ? $job : null;
	}

	/**
	 * @param array<string, int|string> $job Current operation.
	 */
	private function process( array $job ): void {
		$query = $this->query( $job );
		$ids   = is_array( $query->posts ) ? array_map( 'absint', $query->posts ) : array();

		foreach ( $ids as $release_id ) {
			$job['cursor'] = max( (int) $job['cursor'], $release_id );
			$mode          = $this->resolved_mode( $release_id, (string) $job['target_mode'] );
			if ( null === $mode ) {
				++$job['skipped'];
				continue;
			}
			if ( $this->releases->update( $release_id, 'mw_access_mode', $mode ) ) {
				++$job['updated'];
			} else {
				++$job['skipped'];
			}
		}

		if ( count( $ids ) >= self::BATCH_SIZE ) {
			set_transient( $this->job_key(), $job, HOUR_IN_SECONDS );
			$message = sprintf(
				/* translators: 1: number of releases updated so far, 2: number of releases skipped so far. */
				__( 'دسترسی انبوه همچنان در حال اجرا است: %1$d به‌روزرسانی شد و %2$d تاکنون نادیده گرفته شده است.', 'music-wave-core' ),
				(int) $job['updated'],
				(int) $job['skipped']
			);
			$this->set_notice( $message, 'info', true );
		} else {
			delete_transient( $this->job_key() );
			$message = sprintf(
				/* translators: 1: number of releases updated, 2: number of releases skipped. */
				__( 'دسترسی انبوه تکمیل شد: انتشارهای %1$d به‌روزرسانی شدند و %2$d به دلیل ناقص بودن شرایط خرید یا عضویت آن‌ها نادیده گرفته شد.', 'music-wave-core' ),
				(int) $job['updated'],
				(int) $job['skipped']
			);
			$this->set_notice( $message, 'success', false );
		}

		$this->redirect();
	}

	/**
	 * @param array<string, int|string> $job Current operation.
	 */
	private function query( array $job ): WP_Query {
		$arguments = array(
			'post_type'              => ReleasePostType::KEY,
			'post_status'            => 'any' === $job['post_status'] ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : (string) $job['post_status'],
			'posts_per_page'         => self::BATCH_SIZE,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'cache_results'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( 'any' !== $job['current_mode'] ) {
			$registered_default = 'public';
			if ( $registered_default === $job['current_mode'] ) {
				$arguments['meta_query'] = array(
					'relation' => 'OR',
					array(
						'key'     => 'mw_access_mode',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => 'mw_access_mode',
						'value' => $registered_default,
					),
				);
			} else {
				$arguments['meta_key']   = 'mw_access_mode';
				$arguments['meta_value'] = (string) $job['current_mode'];
			}
		}

		if ( '' !== $job['release_type'] && 'any' !== $job['release_type'] ) {
			$arguments['tax_query'] = array(
				array(
					'taxonomy' => 'mw_release_type',
					'field'    => 'slug',
					'terms'    => (string) $job['release_type'],
				),
			);
		}

		$this->query_cursor = (int) $job['cursor'];
		add_filter( 'posts_where', array( $this, 'filter_after_cursor' ), 10, 2 );
		$query = new WP_Query( $arguments );
		remove_filter( 'posts_where', array( $this, 'filter_after_cursor' ), 10 );
		$this->query_cursor = 0;

		return $query;
	}

	/**
	 * Restrict a bulk batch to IDs after the last processed release.
	 *
	 * @param string $where Existing SQL clause.
	 * @param mixed  $query Query instance.
	 */
	public function filter_after_cursor( string $where, $query ): string {
		unset( $query );
		if ( $this->query_cursor < 1 ) {
			return $where;
		}

		global $wpdb;

		return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $this->query_cursor );
	}

	private function resolved_mode( int $release_id, string $target ): ?string {
		if ( 'purchase' === $target ) {
			return empty( $this->releases->product_ids( $release_id ) ) ? null : $target;
		}

		$levels         = $this->releases->get( $release_id, 'mw_membership_levels' );
		$has_membership = is_array( $levels ) && ! empty( $levels );
		if ( 'membership' === $target ) {
			return $has_membership ? $target : null;
		}
		if ( 'purchase_or_membership' === $target ) {
			$has_purchase = ! empty( $this->releases->product_ids( $release_id ) );
			if ( $has_purchase && $has_membership ) {
				return $target;
			}
			if ( $has_purchase ) {
				return 'purchase';
			}
			if ( $has_membership ) {
				return 'membership';
			}

			return null;
		}

		return $target;
	}

	private function authorize( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( $action ) ) {
			wp_die( esc_html__( 'شما مجاز به تغییر تنظیمات دسترسی MusicWave نیستید.', 'music-wave-core' ) );
		}
	}

	private function request_key( string $key ): string {
		// Nonce and capability are verified by authorize() via check_admin_referer() before any read.
		return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_key( wp_unslash( (string) $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	private function fail( string $message ): void {
		$this->set_notice( $message, 'error', false );
		$this->redirect();
	}

	private function set_notice( string $message, string $type, bool $pending ): void {
		set_transient(
			self::NOTICE_KEY . get_current_user_id(),
			array(
				'message' => $message,
				'type'    => $type,
				'pending' => $pending,
			),
			MINUTE_IN_SECONDS
		);
	}

	private function job_key(): string {
		return self::JOB_KEY . get_current_user_id();
	}

	private function redirect(): void {
		wp_safe_redirect( admin_url( 'edit.php?post_type=mw_release&page=music-wave-settings&tab=access' ) );
		exit;
	}
}
