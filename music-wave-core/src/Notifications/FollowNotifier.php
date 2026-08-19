<?php
/**
 * Follow notifications for new releases, pre-saves, and podcast episodes.
 *
 * Sending is opt-in, targeted, and bounded: the notifier only looks at
 * listeners who follow one of the release's artists (or the podcast show), only
 * sends to those who enabled the matching channel, only ever links publicly
 * published releases, and hands work to a scheduled batch so a publish request
 * never blocks on mail delivery. Every message carries a signed one-click
 * unsubscribe link (PROJECT_PLAN.md Stage 6 deliverable 2).
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Notifications;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Catalog\ReleaseVisibility;
use ManaCore\MusicWave\Core\Library\LibraryRepository;

final class FollowNotifier {
	public const EVENT          = 'music_wave_notify_release';
	public const UNSUBSCRIBE_QUERY = 'mw-unsubscribe';
	public const MAX_RECIPIENTS = 200;

	/** @var LibraryRepository */
	private $library;

	/** @var NotificationPreferences */
	private $preferences;

	/** @var ReleaseVisibility */
	private $visibility;

	public function __construct( LibraryRepository $library, NotificationPreferences $preferences, ?ReleaseVisibility $visibility = null ) {
		$this->library     = $library;
		$this->preferences = $preferences;
		$this->visibility  = null !== $visibility ? $visibility : new ReleaseVisibility();
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'transition_post_status', array( $this, 'handle_transition' ), 20, 3 );
		add_action( self::EVENT, array( $this, 'notify_release' ) );
		add_action( 'music_wave_presave_fulfilled', array( $this, 'notify_presave' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'handle_unsubscribe' ) );
	}

	/**
	 * Queue notifications once a release becomes public.
	 *
	 * @param string $new_status New post status.
	 * @param string $old_status Previous post status.
	 * @param mixed  $post       Post object.
	 * @return void
	 */
	public function handle_transition( string $new_status, string $old_status, $post ): void {
		if ( 'publish' !== $new_status || $new_status === $old_status ) {
			return;
		}
		if ( ! is_object( $post ) || ! isset( $post->post_type, $post->ID ) || ReleasePostType::KEY !== $post->post_type ) {
			return;
		}

		$release_id = (int) $post->ID;
		if ( ! function_exists( 'wp_schedule_single_event' ) ) {
			$this->notify_release( $release_id );

			return;
		}
		if ( function_exists( 'wp_next_scheduled' ) && false !== wp_next_scheduled( self::EVENT, array( $release_id ) ) ) {
			return;
		}

		// Publishing must not wait on mail delivery.
		wp_schedule_single_event( time() + 60, self::EVENT, array( $release_id ) );
	}

	/**
	 * Notify followers of one newly published release.
	 *
	 * @return int Number of notifications sent.
	 */
	public function notify_release( int $release_id ): int {
		if ( ! $this->visibility->is_public( $release_id ) ) {
			return 0;
		}

		$channel   = $this->is_podcast_episode( $release_id ) ? NotificationPreferences::CHANNEL_PODCAST : NotificationPreferences::CHANNEL_ARTIST_RELEASE;
		$sent      = 0;
		$notified  = array();
		foreach ( $this->followers( $release_id ) as $user_id ) {
			if ( count( $notified ) >= self::MAX_RECIPIENTS ) {
				break;
			}
			if ( isset( $notified[ $user_id ] ) || ! $this->preferences->enabled( $user_id, $channel ) ) {
				continue;
			}
			$notified[ $user_id ] = true;
			if ( $this->send( $user_id, $release_id, $channel ) ) {
				++$sent;
			}
		}

		return $sent;
	}

	/**
	 * Notify one listener that a pre-saved release is available.
	 *
	 * @param int $release_id Released release ID.
	 * @param int $user_id    Listener who pre-saved it.
	 * @return void
	 */
	public function notify_presave( int $release_id, int $user_id ): void {
		if ( ! $this->visibility->is_public( $release_id ) ) {
			return;
		}
		if ( ! $this->preferences->enabled( $user_id, NotificationPreferences::CHANNEL_PRESAVE ) ) {
			return;
		}

		$this->send( $user_id, $release_id, NotificationPreferences::CHANNEL_PRESAVE );
	}

	/**
	 * Handle a signed one-click unsubscribe request.
	 *
	 * @return void
	 */
	public function handle_unsubscribe(): void {
		// Signed request: the HMAC token replaces a nonce for links that must
		// work from an email client without a session.
		$channel = isset( $_GET[ self::UNSUBSCRIBE_QUERY ] ) && is_scalar( $_GET[ self::UNSUBSCRIBE_QUERY ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::UNSUBSCRIBE_QUERY ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $channel ) {
			return;
		}
		$user_id = isset( $_GET['mw-user'] ) ? absint( wp_unslash( (string) $_GET['mw-user'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token   = isset( $_GET['mw-token'] ) && is_scalar( $_GET['mw-token'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['mw-token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->unsubscribe( $user_id, $channel, $token );
	}

	/**
	 * Apply one unsubscribe request. Exposed for tests: no superglobals.
	 */
	public function unsubscribe( int $user_id, string $channel, string $token ): bool {
		if ( ! $this->preferences->verify_token( $user_id, $channel, $token ) ) {
			return false;
		}

		return $this->preferences->disable( $user_id, $channel );
	}

	/**
	 * Listeners following any artist of one release.
	 *
	 * @return array<int, int>
	 */
	private function followers( int $release_id ): array {
		$followers = array();
		$terms     = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'ids' ) );
		foreach ( is_array( $terms ) ? $terms : array() as $term_id ) {
			$term_id = absint( $term_id );
			if ( $term_id < 1 ) {
				continue;
			}
			foreach ( $this->library->users_with( LibraryRepository::TYPE_ARTIST, $term_id ) as $user_id ) {
				if ( ! in_array( $user_id, $followers, true ) ) {
					$followers[] = $user_id;
				}
			}
		}

		return $followers;
	}

	/**
	 * Send one notification email.
	 */
	private function send( int $user_id, int $release_id, string $channel ): bool {
		$user = get_userdata( $user_id );
		if ( ! is_object( $user ) || empty( $user->user_email ) ) {
			return false;
		}

		$title   = get_the_title( $release_id );
		$url     = (string) get_permalink( $release_id );
		$subject = NotificationPreferences::CHANNEL_PRESAVE === $channel
			? sprintf(
				/* translators: %s: release title. */
				__( '%s is out now', 'music-wave-core' ),
				$title
			)
			: sprintf(
				/* translators: %s: release title. */
				__( 'New release: %s', 'music-wave-core' ),
				$title
			);

		$unsubscribe = $this->preferences->unsubscribe_url( $user_id, $channel );
		$body        = $title . "\n" . $url . "\n\n" . __( 'You are receiving this because you enabled these notifications.', 'music-wave-core' ) . "\n" . $unsubscribe;

		/**
		 * Filter one outgoing MusicWave notification.
		 *
		 * Return false to suppress delivery, or replace subject/body/headers to
		 * route notifications through another transport.
		 *
		 * @param array<string, mixed> $message    Message parts.
		 * @param int                  $user_id    Recipient.
		 * @param int                  $release_id Release the message is about.
		 * @param string               $channel    Notification channel.
		 */
		$message = apply_filters(
			'music_wave_notification_message',
			array(
				'to'      => (string) $user->user_email,
				'subject' => $subject,
				'body'    => $body,
				'headers' => array( 'List-Unsubscribe: <' . $unsubscribe . '>' ),
			),
			$user_id,
			$release_id,
			$channel
		);

		if ( false === $message || ! is_array( $message ) || empty( $message['to'] ) ) {
			return false;
		}
		if ( ! function_exists( 'wp_mail' ) ) {
			return false;
		}

		return (bool) wp_mail(
			(string) $message['to'],
			(string) $message['subject'],
			(string) $message['body'],
			isset( $message['headers'] ) && is_array( $message['headers'] ) ? $message['headers'] : array()
		);
	}

	private function is_podcast_episode( int $release_id ): bool {
		$slugs = wp_get_post_terms( $release_id, 'mw_release_type', array( 'fields' => 'slugs' ) );

		return is_array( $slugs ) && in_array( 'podcast_episode', array_map( 'strval', $slugs ), true );
	}
}
