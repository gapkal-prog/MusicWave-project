<?php
/**
 * Search and social metadata fallback for public release pages.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Seo;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;
use WP_Post;

final class ReleaseMetadata {
	/** @var ReleaseRepository */
	private $releases;

	public function __construct( ReleaseRepository $releases ) {
		$this->releases = $releases;
	}

	public function register(): void {
		add_filter( 'document_title_parts', array( $this, 'filter_title' ) );
		add_action( 'wp_head', array( $this, 'render' ), 5 );
	}

	/**
	 * Add the primary artist to WordPress' native document title.
	 *
	 * @param array<string, string> $parts Document title parts.
	 * @return array<string, string>
	 */
	public function filter_title( array $parts ): array {
		$post = $this->current_release();
		if ( ! $post instanceof WP_Post || ! $this->is_enabled() ) {
			return $parts;
		}

		$artists = $this->artist_names( $post->ID );
		if ( ! empty( $artists ) ) {
			$parts['title'] = sprintf(
				/* translators: 1: release title, 2: artist names. */
				__( '%1$s by %2$s', 'music-wave-core' ),
				get_the_title( $post ),
				implode( ', ', $artists )
			);
		}

		return $parts;
	}

	/**
	 * Render Open Graph, Twitter Card and description fallback metadata.
	 */
	public function render(): void {
		$post = $this->current_release();
		if ( ! $post instanceof WP_Post || ! $this->is_enabled() ) {
			return;
		}

		$metadata = $this->metadata( $post->ID );
		if ( empty( $metadata ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- meta() esc_attr()s every attribute, key, and value.
		echo $this->meta( 'name', 'description', $metadata['description'] );
		echo $this->meta( 'property', 'og:type', $metadata['type'] );
		echo $this->meta( 'property', 'og:site_name', get_bloginfo( 'name' ) );
		echo $this->meta( 'property', 'og:title', $metadata['title'] );
		echo $this->meta( 'property', 'og:description', $metadata['description'] );
		echo $this->meta( 'property', 'og:url', $metadata['url'] );
		echo $this->meta( 'name', 'twitter:card', empty( $metadata['image'] ) ? 'summary' : 'summary_large_image' );
		echo $this->meta( 'name', 'twitter:title', $metadata['title'] );
		echo $this->meta( 'name', 'twitter:description', $metadata['description'] );

		if ( ! empty( $metadata['image'] ) ) {
			echo $this->meta( 'property', 'og:image', $metadata['image'] );
			echo $this->meta( 'name', 'twitter:image', $metadata['image'] );
		}
		if ( ! empty( $metadata['release_date'] ) ) {
			$release_property = 'article' === $metadata['type'] ? 'article:published_time' : 'music:release_date';
			echo $this->meta( 'property', $release_property, $metadata['release_date'] );
		}
		if ( 'article' !== $metadata['type'] ) {
			foreach ( $metadata['artist_urls'] as $artist_url ) {
				echo $this->meta( 'property', 'music:musician', $artist_url );
			}
		}
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build a public-only metadata payload suitable for tests and extensions.
	 *
	 * @return array<string, mixed>
	 */
	public function metadata( int $release_id ): array {
		$link = get_permalink( $release_id );
		if ( ReleasePostType::KEY !== get_post_type( $release_id ) || ! is_string( $link ) || '' === $link ) {
			return array();
		}

		$title        = get_the_title( $release_id );
		$artists      = $this->artist_names( $release_id );
		$social_title = empty( $artists )
			? $title
			: sprintf(
				/* translators: 1: release title, 2: artist names. */
				__( '%1$s by %2$s', 'music-wave-core' ),
				$title,
				implode( ', ', $artists )
			);
		$description = $this->description( $release_id, $title, $artists );
		$image       = get_the_post_thumbnail_url( $release_id, 'full' );
		$types       = wp_get_post_terms( $release_id, 'mw_release_type', array( 'fields' => 'slugs' ) );
		$types       = is_array( $types ) ? array_map( 'sanitize_key', $types ) : array();
		$artist_urls = array();
		$terms       = wp_get_post_terms( $release_id, 'mw_artist' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_link = get_term_link( $term );
				if ( is_string( $term_link ) && '' !== $term_link ) {
					$artist_urls[] = esc_url_raw( $term_link );
				}
			}
		}

		return array(
			'title'        => $social_title,
			'description'  => $description,
			'url'          => esc_url_raw( $link ),
			'image'        => is_string( $image ) ? esc_url_raw( $image ) : '',
			'type'         => $this->open_graph_type( $types ),
			'release_date' => sanitize_text_field( (string) $this->releases->get( $release_id, 'mw_release_date' ) ),
			'artist_urls'  => array_values( array_unique( array_filter( $artist_urls ) ) ),
		);
	}

	public function is_enabled(): bool {
		$enabled = ! SeoCompatibility::has_competing_plugin();

		/**
		 * Filter MusicWave's fallback title and social metadata output.
		 *
		 * @param bool $enabled Whether fallback metadata is enabled.
		 */
		return (bool) apply_filters( 'music_wave_public_metadata_enabled', $enabled );
	}

	/** @return WP_Post|null */
	private function current_release() {
		if ( is_admin() || is_feed() || ! is_singular( ReleasePostType::KEY ) ) {
			return null;
		}

		$post = get_post();

		return $post instanceof WP_Post && 'publish' === $post->post_status ? $post : null;
	}

	/** @return array<int, string> */
	private function artist_names( int $release_id ): array {
		$artists = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'names' ) );

		return is_array( $artists ) ? array_values( array_filter( array_map( 'sanitize_text_field', $artists ) ) ) : array();
	}

	/** @param array<int, string> $artists */
	private function description( int $release_id, string $title, array $artists ): string {
		$excerpt = get_the_excerpt( $release_id );
		if ( is_string( $excerpt ) && '' !== trim( wp_strip_all_tags( $excerpt ) ) ) {
			return wp_trim_words( wp_strip_all_tags( $excerpt ), 35, '' );
		}

		if ( ! empty( $artists ) ) {
			return sprintf(
				/* translators: 1: release title, 2: artist names, 3: site name. */
				__( 'Listen to %1$s by %2$s on %3$s.', 'music-wave-core' ),
				$title,
				implode( ', ', $artists ),
				get_bloginfo( 'name' )
			);
		}

		return sprintf(
			/* translators: 1: release title, 2: site name. */
			__( 'Listen to %1$s on %2$s.', 'music-wave-core' ),
			$title,
			get_bloginfo( 'name' )
		);
	}

	private function meta( string $attribute, string $key, string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		return '<meta ' . esc_attr( $attribute ) . '="' . esc_attr( $key ) . '" content="' . esc_attr( $value ) . '">' . "\n";
	}

	/** @param array<int, string> $types Release type slugs. */
	private function open_graph_type( array $types ): string {
		if ( count( array_intersect( $types, array( 'podcast_show', 'podcast_episode' ) ) ) > 0 ) {
			return 'article';
		}
		if ( count( array_intersect( $types, array( 'mix', 'playlist' ) ) ) > 0 ) {
			return 'music.playlist';
		}
		if ( count( array_intersect( $types, array( 'album', 'ep' ) ) ) > 0 ) {
			return 'music.album';
		}

		return 'music.song';
	}
}
