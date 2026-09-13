<?php
/**
 * Public playback queue endpoint for the global release player.
 *
 * Resolves an ordered queue for any release — the release itself or the
 * ordered children of an album, EP, mix, playlist, or podcast show — and
 * pairs every entry with its access-aware playback source: a protected
 * stream when the visitor is allowed, or the public preview URL otherwise.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Playback;

use ManaCore\MusicWave\Core\Access\AccessDecision;
use ManaCore\MusicWave\Core\Access\AccessPolicyEngine;
use ManaCore\MusicWave\Core\Access\AccessSubject;
use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;
use ManaCore\MusicWave\Core\Support\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class PlaybackQueueRoutes {
	private const MAX_TRACKS = 50;

	private const STREAMABLE_FORMATS = array( 'mp3', 'm4a', 'aac', 'ogg', 'wav', 'flac' );

	/** @var AccessPolicyEngine */
	private $policy;

	/** @var ReleaseRepository */
	private $repository;

	public function __construct( AccessPolicyEngine $policy, ReleaseRepository $repository ) {
		$this->policy     = $policy;
		$this->repository = $repository;
	}

	/**
	 * Register the playback queue route.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the route; the payload itself never leaks protected URLs,
	 * so anonymous visitors may read it too.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'music-wave/v1',
			'/releases/(?P<id>\d+)/playback-queue',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'queue' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Build the ordered playback queue for one release.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function queue( WP_REST_Request $request ) {
		$release_id = absint( $request->get_param( 'id' ) );
		if ( $release_id < 1 || ReleasePostType::KEY !== get_post_type( $release_id ) || 'publish' !== get_post_status( $release_id ) ) {
			return new WP_Error(
				'mw_playback_unavailable',
				__( 'این انتشار برای پخش در دسترس نیست.', 'music-wave-core' ),
				array( 'status' => 404 )
			);
		}

		$subject          = AccessSubject::current();
		$release_decision = $this->policy->decide( $release_id, $subject );

		$tracks = array();
		foreach ( $this->queue_release_ids( $release_id ) as $queued_id ) {
			$track = $this->track_payload( $release_id, $queued_id, $subject );
			if ( null !== $track ) {
				$tracks[] = $track;
			}
		}
		if ( empty( $tracks ) ) {
			return new WP_Error(
				'mw_playback_unavailable',
				__( 'این انتشار در حال حاضر صدای قابل پخش ندارد.', 'music-wave-core' ),
				array( 'status' => 404 )
			);
		}

		$preview_only = true;
		foreach ( $tracks as $track ) {
			if ( ! empty( $track['full'] ) ) {
				$preview_only = false;
				break;
			}
		}

		$image = get_the_post_thumbnail_url( $release_id, 'medium' );
		$link  = get_permalink( $release_id );

		return new WP_REST_Response(
			array(
				'release'     => array(
					'id'    => $release_id,
					'title' => get_the_title( $release_id ),
					'link'  => is_string( $link ) ? $link : '',
					'image' => is_string( $image ) ? $image : '',
				),
				'previewOnly' => $preview_only,
				'tracks'      => $tracks,
				'upsell'      => $this->upsell_payload( $release_id, $release_decision ),
			),
			200
		);
	}

	/**
	 * Resolve the ordered release IDs behind one queue request.
	 *
	 * Collections queue their ordered children; everything else queues
	 * itself. Invalid children are skipped silently.
	 *
	 * @return array<int, int>
	 */
	private function queue_release_ids( int $release_id ): array {
		$ids = array();
		foreach ( $this->repository->collection_items( $release_id ) as $item ) {
			$child_id = is_array( $item ) && isset( $item['release_id'] ) ? absint( $item['release_id'] ) : 0;
			if ( $child_id > 0 ) {
				$ids[] = $child_id;
			}
		}
		if ( empty( $ids ) ) {
			$ids = array( $release_id );
		}

		return array_slice( array_values( array_unique( $ids ) ), 0, self::MAX_TRACKS );
	}

	/**
	 * Build one access-aware queue entry, or null when nothing is playable.
	 *
	 * @return array<string, mixed>|null
	 */
	private function track_payload( int $root_id, int $release_id, AccessSubject $subject ): ?array {
		if ( ReleasePostType::KEY !== get_post_type( $release_id ) || 'publish' !== get_post_status( $release_id ) ) {
			return null;
		}

		$quality = '';
		if ( $this->policy->decide( $release_id, $subject )->is_allowed() ) {
			$quality = $this->streamable_quality( $release_id );
		}

		$preview_url = $this->preview_url( $release_id );
		if ( '' === $quality && '' === $preview_url ) {
			return null;
		}

		$artists = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'names' ) );
		$image   = get_the_post_thumbnail_url( $release_id, 'thumbnail' );
		if ( ! is_string( $image ) || '' === $image ) {
			$root_image = get_the_post_thumbnail_url( $root_id, 'thumbnail' );
			$image      = is_string( $root_image ) ? $root_image : '';
		}
		$link = get_permalink( $release_id );
		$full = '' !== $quality;
		$lrc  = (string) get_post_meta( $release_id, 'mw_lyrics_lrc', true );

		return array(
			'releaseId'    => $release_id,
			'rootId'       => $root_id,
			'title'        => get_the_title( $release_id ),
			'artist'       => is_array( $artists ) && ! empty( $artists ) ? implode( ', ', $artists ) : '',
			'image'        => is_string( $image ) ? $image : '',
			'link'         => is_string( $link ) ? $link : '',
			'previewUrl'   => $preview_url,
			'previewLimit' => $full ? 0 : $this->preview_limit( $release_id ),
			'full'         => $full,
			'quality'      => $quality,
			'lyricsLrc'    => $lrc,
			'lyricsOffset' => (int) get_post_meta( $release_id, 'mw_lyrics_offset', true ),
			'hasLyrics'    => '' !== trim( $lrc ),
		);
	}

	/**
	 * Return the first streamable download quality key for a release.
	 */
	private function streamable_quality( int $release_id ): string {
		try {
			$assets = $this->repository->get( $release_id, 'mw_download_assets' );
		} catch ( \InvalidArgumentException $exception ) {
			return '';
		}
		if ( ! is_array( $assets ) ) {
			return '';
		}

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || ! isset( $asset['key'] ) ) {
				continue;
			}
			$format = isset( $asset['format'] ) && is_scalar( $asset['format'] ) ? sanitize_key( strtolower( (string) $asset['format'] ) ) : '';
			if ( in_array( $format, self::STREAMABLE_FORMATS, true ) ) {
				return sanitize_key( (string) $asset['key'] );
			}
		}

		return '';
	}

	/**
	 * Return the public HTTPS preview URL for a release.
	 */
	private function preview_url( int $release_id ): string {
		try {
			$url = $this->repository->get( $release_id, 'mw_preview_url' );
		} catch ( \InvalidArgumentException $exception ) {
			return '';
		}

		return is_string( $url ) && 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ? $url : '';
	}

	/**
	 * Resolve the editor-configured preview cutoff with safe bounds.
	 */
	private function preview_limit( int $release_id ): int {
		$limit = absint( $this->repository->get( $release_id, 'mw_preview_duration' ) );

		return $limit >= 10 && $limit <= 120 ? $limit : 30;
	}

	/**
	 * Build the subscription/purchase prompt shown while previews play.
	 *
	 * @return array<string, string>
	 */
	private function upsell_payload( int $release_id, AccessDecision $decision ): array {
		$link = get_permalink( $release_id );
		$link = is_string( $link ) ? $link : '';

		$message   = '';
		$cta_url   = '';
		$cta_label = '';

		if ( ! $decision->is_allowed() ) {
			switch ( $decision->reason() ) {
				case 'purchase_required':
					$message   = (string) Settings::get( 'purchase_message' );
					$message   = '' !== $message ? $message : __( 'این انتشار را بخرید تا قفل پخش کامل باز شود.', 'music-wave-core' );
					$cta_label = (string) Settings::get( 'purchase_cta_label' );
					$cta_label = '' !== $cta_label ? $cta_label : __( 'مشاهده گزینه‌های خرید', 'music-wave-core' );
					foreach ( $this->repository->product_ids( $release_id ) as $product_id ) {
						if ( 'product' !== get_post_type( $product_id ) ) {
							continue;
						}
						$product_link = get_permalink( $product_id );
						if ( is_string( $product_link ) && '' !== $product_link ) {
							$cta_url = $product_link;
							break;
						}
					}
					$cta_url = '' !== $cta_url ? $cta_url : $link;
					break;
				case 'membership_required':
					$message   = (string) Settings::get( 'membership_message' );
					$message   = '' !== $message ? $message : __( 'برای گوش‌دادن به قطعه‌های کامل بدون محدودیت مشترک شوید.', 'music-wave-core' );
					$cta_label = (string) Settings::get( 'membership_cta_label' );
					$cta_label = '' !== $cta_label ? $cta_label : __( 'مشاهده گزینه‌های عضویت', 'music-wave-core' );
					$cta_url   = (string) Settings::get( 'membership_cta_url' );
					break;
				default:
					$message = (string) Settings::get( 'restricted_message' );
					$message = '' !== $message ? $message : __( 'این انتشار در حال حاضر در دسترس نیست.', 'music-wave-core' );
					break;
			}
		}

		$payload = array(
			'message'     => $message,
			'ctaLabel'    => $cta_label,
			'ctaUrl'      => $cta_url,
			'previewNote' => __( 'شما در حال گوش‌دادن به یک پیش‌نمایش محدود هستید. دسترسی داشته باشید تا از پخش کامل لذت ببرید.', 'music-wave-core' ),
			'loginUrl'    => '',
			'loginLabel'  => '',
		);

		if ( get_current_user_id() < 1 && '' !== $link ) {
			$payload['loginUrl']   = wp_login_url( $link );
			$payload['loginLabel'] = __( 'وارد شوید', 'music-wave-core' );
		}

		return $payload;
	}
}
