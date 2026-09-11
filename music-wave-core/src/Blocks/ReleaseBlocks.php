<?php
/**
 * Server-rendered MusicWave release blocks and gated content boundary.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Access\AccessPolicyEngine;
use ManaCore\MusicWave\Core\Access\AccessSubject;
use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Catalog\ReleaseTermIndex;
use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;
use ManaCore\MusicWave\Core\Library\LibraryButton;
use ManaCore\MusicWave\Core\Library\LibraryRepository;
use ManaCore\MusicWave\Core\Support\Settings;
use WP_Post;

final class ReleaseBlocks {
	/** @var AccessPolicyEngine */
	private $policy;

	/**
	 * Release IDs whose canonical denied-access gate already rendered in this
	 * request, preventing duplicate gate output across body, meta, and panel
	 * surfaces (PROJECT_PLAN.md Stage 1 deliverable 6).
	 *
	 * @var array<int, bool>
	 */
	private $denied_gate_rendered = array();

	/** @var ReleaseRepository */
	private $repository;

	/** @var object|null */
	private $render_context;

	/** @var ReleaseTermIndex */
	private $term_index;

	public function __construct( AccessPolicyEngine $policy, ReleaseRepository $repository, ?ReleaseTermIndex $term_index = null ) {
		$this->policy     = $policy;
		$this->repository = $repository;
		$this->term_index = null !== $term_index ? $term_index : new ReleaseTermIndex();
	}

	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$this->register_dynamic_block(
			'release-meta',
			array(
				'releaseId'         => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'compact'           => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showCatalogNumber' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showReleaseDate'   => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showDuration'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showBpm'           => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showKey'           => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showArtist'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showGenre'         => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showLibraryButton' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showTaxonomyChips' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showActions'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'layout'            => array(
					'type'    => 'string',
					'default' => 'grid',
				),
				'showLabels'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'linkTerms'         => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showMood'          => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showLabel'         => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showReleaseType'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			'render_meta'
		);
		$this->register_dynamic_block(
			'access-panel',
			array(
				'releaseId'          => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'layout'             => array(
					'type'    => 'string',
					'default' => 'banner',
				),
				'showWhenGranted'    => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'grantedMessage'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'restrictedMessage'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'purchaseMessage'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'purchaseCtaLabel'   => array(
					'type'    => 'string',
					'default' => '',
				),
				'membershipMessage'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'membershipCtaLabel' => array(
					'type'    => 'string',
					'default' => '',
				),
				'membershipCtaUrl'   => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'render_access_panel'
		);
		$this->register_dynamic_block(
			'release-credits',
			array(
				'releaseId'   => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'heading'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'showHeading' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showRole'    => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'groupByRole' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'layout'      => array(
					'type'    => 'string',
					'default' => 'list',
				),
			),
			'render_credits'
		);
		$this->register_dynamic_block(
			'collection-list',
			array(
				'releaseId'         => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'heading'           => array(
					'type'    => 'string',
					'default' => '',
				),
				'showHeading'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showPosition'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showArtwork'       => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showDuration'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showTotalDuration' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showPreview'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showDownload'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'groupByDisc'       => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			'render_collection_list'
		);
		$this->register_dynamic_block(
			'catalog-filters',
			array(
				'layout'            => array(
					'type'    => 'string',
					'default' => 'inline',
				),
				'showSearch'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showArtistFilter'  => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showGenreFilter'   => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showMoodFilter'    => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showTypeFilter'    => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showSort'          => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showReset'         => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'maxTerms'          => array(
					'type'    => 'integer',
					'default' => 50,
				),
				'searchPlaceholder' => array(
					'type'    => 'string',
					'default' => '',
				),
				'submitLabel'       => array(
					'type'    => 'string',
					'default' => '',
				),
				'resetLabel'        => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'render_catalog_filters'
		);
		$this->register_dynamic_block(
			'catalog-results',
			array(
				'showCount' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showChips' => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
			'render_catalog_results'
		);
		$this->register_dynamic_block(
			'preview-player',
			array(
				'releaseId' => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'label'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'style'     => array(
					'type'    => 'string',
					'default' => 'solid',
				),
				'showIcon'  => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
			'render_preview_player'
		);
		$this->register_dynamic_block(
			'download-button',
			array(
				'releaseId'       => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'compact'         => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'heading'         => array(
					'type'    => 'string',
					'default' => '',
				),
				'description'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'showHeading'     => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showDescription' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showQuality'     => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showStream'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'downloadLabel'   => array(
					'type'    => 'string',
					'default' => '',
				),
				'playLabel'       => array(
					'type'    => 'string',
					'default' => '',
				),
				'loginLabel'      => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'render_download_button'
		);
		$this->register_dynamic_block(
			'related-releases',
			array(
				'releaseId'         => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'itemsToShow'       => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'orderBy'           => array(
					'type'    => 'string',
					'default' => 'date',
				),
				'order'             => array(
					'type'    => 'string',
					'default' => 'DESC',
				),
				'sameArtistSection' => array(
					'type'    => 'string',
					'default' => 'inherit',
				),
				'similarSection'    => array(
					'type'    => 'string',
					'default' => 'inherit',
				),
				'matchGenre'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'matchMood'         => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'matchType'         => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'sameArtistHeading' => array(
					'type'    => 'string',
					'default' => '',
				),
				'similarHeading'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'showSectionLink'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'sectionLinkLabel'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'layout'            => array(
					'type'    => 'string',
					'default' => 'grid',
				),
				'columns'           => array(
					'type'    => 'integer',
					'default' => 4,
				),
				'imageShape'        => array(
					'type'    => 'string',
					'default' => 'square',
				),
				'showArtwork'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showArtist'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showDate'          => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showExcerpt'       => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showPreview'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showAction'        => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'actionLabel'       => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'render_related_releases'
		);
	}

	/**
	 * Register one API v3 dynamic block with the shared editor appearance tools.
	 *
	 * Metadata, attributes, and supports load from the bundled block.json when
	 * available; the PHP attribute map remains the fallback registration source.
	 *
	 * @param string               $name Block name without the namespace.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $callback Renderer method name.
	 * @return void
	 */
	private function register_dynamic_block( string $name, array $attributes, string $callback ): void {
		BlockSupport::register_dynamic(
			'music-wave/' . $name,
			function ( $attributes, $content, $block ) use ( $callback ): string {
				unset( $content );
				$previous_context     = $this->render_context;
				$this->render_context = is_object( $block ) ? $block : null;

				try {
					return (string) $this->{$callback}( is_array( $attributes ) ? $attributes : array() );
				} finally {
					$this->render_context = $previous_context;
				}
			},
			array(
				'api_version'  => 3,
				'attributes'   => $attributes,
				'uses_context' => array( 'postId', 'postType' ),
				'supports'     => BlockSupport::appearance_tools(),
			)
		);
	}

	/**
	 * Read a message override: block attribute, then global setting, then fallback.
	 *
	 * @param array<string, mixed> $attributes  Block attributes.
	 * @param string               $attribute   Block attribute name.
	 * @param string               $setting_key Global MusicWave setting key.
	 * @param string               $fallback    Translated default message.
	 */
	private function message_attribute( array $attributes, string $attribute, string $setting_key, string $fallback ): string {
		$value = isset( $attributes[ $attribute ] ) && is_scalar( $attributes[ $attribute ] ) ? sanitize_text_field( (string) $attributes[ $attribute ] ) : '';
		if ( '' !== $value ) {
			return $value;
		}

		$setting = (string) Settings::get( $setting_key );

		return '' !== $setting ? $setting : $fallback;
	}

	/**
	 * Read an HTTP(S) URL attribute, rejecting every other scheme.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $key        Attribute name.
	 */
	private function url_attribute( array $attributes, string $key ): string {
		$value = isset( $attributes[ $key ] ) && is_scalar( $attributes[ $key ] ) ? esc_url_raw( (string) $attributes[ $key ], array( 'http', 'https' ) ) : '';

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Resolve a tri-state block override against a global enabled/disabled setting.
	 *
	 * @param array<string, mixed> $attributes  Block attributes.
	 * @param string               $key         Tri-state attribute name.
	 * @param string               $setting_key Global MusicWave setting key.
	 */
	private function visibility_attribute( array $attributes, string $key, string $setting_key ): bool {
		$value = BlockSupport::key_attribute( $attributes, $key, array( 'inherit', 'enabled', 'disabled' ), 'inherit' );
		if ( 'enabled' === $value ) {
			return true;
		}
		if ( 'disabled' === $value ) {
			return false;
		}

		return 'enabled' === Settings::get( $setting_key );
	}

	/**
	 * Prevent restricted release body content from reaching the frontend.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function filter_content( string $content ): string {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post || ReleasePostType::KEY !== $post->post_type ) {
			return $content;
		}

		$decision = $this->policy->decide( $post->ID, AccessSubject::current() );
		if ( $decision->is_allowed() ) {
			return $content;
		}

		// The access panel is the single canonical gate: it carries the
		// editor-configured message and purchase/membership CTA. Rendering it
		// here (in the body position) marks the gate as shown, so a template's
		// dedicated access-panel block will not duplicate it
		// (PROJECT_PLAN.md Stage 1 deliverable 6).
		return $this->render_access_panel( array( 'releaseId' => $post->ID ) );
	}

	/**
	 * Render the release detail panel without exposing private access fields.
	 *
	 * The full-size panel groups the release identity: linked taxonomy
	 * chips, metadata facts, and the library and playlist actions. Downloads
	 * and track lists are separate template blocks rendered below the panel.
	 * Compact placements inside loops stay metadata-only. Empty fields are
	 * skipped so the panel stays compact.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_meta( array $attributes ): string {
		$release_id = $this->release_id( $attributes );
		if ( $release_id <= 0 ) {
			return '';
		}

		$decision = $this->policy->decide( $release_id, AccessSubject::current() );
		if ( ! $decision->is_allowed() ) {
			// No duplicate gate: the canonical access panel explains the
			// restriction; gated metadata simply does not render.
			return '';
		}

		$compact   = ! empty( $attributes['compact'] );
		$layout    = BlockSupport::key_attribute( $attributes, 'layout', array( 'grid', 'inline', 'stack' ), 'grid' );
		$variation = BlockSupport::style_variation( $attributes, array( 'inline', 'stack' ) );
		if ( '' !== $variation ) {
			$layout = $variation;
		}
		$show_labels = BlockSupport::bool_attribute( $attributes, 'showLabels', true );
		$link_terms  = BlockSupport::bool_attribute( $attributes, 'linkTerms', false );
		$use_chips   = ! $compact && BlockSupport::bool_attribute( $attributes, 'showTaxonomyChips', true );
		$items       = array();
		$fields      = array(
			'mw_catalog_number' => array( __( 'شماره کاتالوگ', 'music-wave-core' ), 'showCatalogNumber' ),
			'mw_release_date'   => array( __( 'تاریخ انتشار', 'music-wave-core' ), 'showReleaseDate' ),
			'mw_duration'       => array( __( 'مدت زمان', 'music-wave-core' ), 'showDuration' ),
			'mw_bpm'            => array( __( 'BPM', 'music-wave-core' ), 'showBpm' ),
			'mw_musical_key'    => array( __( 'کلید', 'music-wave-core' ), 'showKey' ),
		);
		foreach ( $fields as $key => $field ) {
			if ( ! BlockSupport::bool_attribute( $attributes, (string) $field[1], true ) ) {
				continue;
			}
			$value = $this->repository->get( $release_id, $key );
			if ( 'mw_duration' === $key && (int) $value > 0 ) {
				$value = gmdate( 'i:s', (int) $value );
			}
			if ( '' === (string) $value || '0' === (string) $value ) {
				continue;
			}
			$items[] = $this->meta_item_markup( (string) $field[0], esc_html( (string) $value ), $show_labels );
		}

		$taxonomies = array(
			'mw_artist'       => array( __( 'هنرمند', 'music-wave-core' ), 'showArtist' ),
			'mw_genre'        => array( __( 'سبک', 'music-wave-core' ), 'showGenre' ),
			'mw_mood'         => array( __( 'حال‌وهوا', 'music-wave-core' ), 'showMood' ),
			'mw_label'        => array( __( 'برچسب', 'music-wave-core' ), 'showLabel' ),
			'mw_release_type' => array( __( 'نوع انتشار', 'music-wave-core' ), 'showReleaseType' ),
		);
		foreach ( $taxonomies as $taxonomy => $field ) {
			if ( ! BlockSupport::bool_attribute( $attributes, (string) $field[1], true ) ) {
				continue;
			}
			// The full panel shows linked chips instead of plain text rows.
			if ( $use_chips ) {
				continue;
			}
			$terms = wp_get_post_terms( $release_id, $taxonomy, array( 'fields' => $link_terms ? 'all' : 'names' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$value = $this->meta_terms_markup( $terms, $link_terms );
			if ( '' === $value ) {
				continue;
			}
			$items[] = $this->meta_item_markup( (string) $field[0], $value, $show_labels );
		}

		if ( $compact ) {
			if ( empty( $items ) ) {
				return '';
			}

			$class = 'mw-release-meta mw-release-meta--compact' . ( 'grid' !== $layout ? ' mw-release-meta--' . $layout : '' );

			return '<dl ' . BlockSupport::wrapper_attributes( $class ) . '>' . implode( '', $items ) . '</dl>';
		}

		$chips = $use_chips ? $this->meta_taxonomy_chips( $release_id, $attributes ) : '';

		$facts = '';
		if ( ! empty( $items ) ) {
			$facts_class = 'mw-release-meta__facts' . ( 'grid' !== $layout ? ' mw-release-meta__facts--' . $layout : '' );
			$facts       = '<dl class="' . esc_attr( $facts_class ) . '">' . implode( '', $items ) . '</dl>';
		}

		$buttons = '';
		if ( BlockSupport::bool_attribute( $attributes, 'showLibraryButton', true ) ) {
			$buttons .= LibraryButton::markup( LibraryRepository::TYPE_RELEASE, $release_id, array( 'style' => 'heart' ) );
		}
		if ( BlockSupport::bool_attribute( $attributes, 'showActions', true ) ) {
			$buttons .= do_blocks( '<!-- wp:music-wave/add-to-playlist {"releaseId":' . $release_id . '} /-->' );
		}
		$actions = '' !== trim( $buttons ) ? '<div class="mw-release-meta__actions">' . $buttons . '</div>' : '';

		$body = $chips . $facts . $actions;
		if ( '' === $body ) {
			return '';
		}

		// Editorial stamp variation: uppercase label/value pairs with hairline
		// rules, driven entirely by the stylesheet.
		$variation     = BlockSupport::style_variation( $attributes, array( 'stamp' ) );
		$wrapper_class = 'mw-release-meta mw-release-meta--panel' . ( '' !== $variation ? ' mw-release-meta--' . $variation : '' );

		return '<section ' . BlockSupport::wrapper_attributes( $wrapper_class ) . ' aria-label="' . esc_attr__( 'جزئیات انتشار', 'music-wave-core' ) . '">' . $body . '</section>';
	}

	/**
	 * Render linked taxonomy chips for the full-size release panel.
	 *
	 * Every chip links to its archive so visitors can jump straight from a
	 * release into the matching artist, genre, mood, or type catalog.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function meta_taxonomy_chips( int $release_id, array $attributes ): string {
		$taxonomies = array(
			'mw_artist'       => 'showArtist',
			'mw_genre'        => 'showGenre',
			'mw_mood'         => 'showMood',
			'mw_label'        => 'showLabel',
			'mw_release_type' => 'showReleaseType',
		);
		$chips      = array();
		foreach ( $taxonomies as $taxonomy => $attribute ) {
			if ( ! BlockSupport::bool_attribute( $attributes, $attribute, true ) ) {
				continue;
			}
			$terms = wp_get_post_terms( $release_id, $taxonomy, array( 'fields' => 'all' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$variant = sanitize_html_class( str_replace( 'mw_', '', $taxonomy ) );
			foreach ( $terms as $term ) {
				if ( ! is_object( $term ) || empty( $term->name ) ) {
					continue;
				}
				$url     = get_term_link( $term );
				$chips[] = ! is_wp_error( $url ) && is_string( $url ) && '' !== $url
					? '<a class="mw-release-meta__chip mw-release-meta__chip--' . $variant . '" href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>'
					: '<span class="mw-release-meta__chip mw-release-meta__chip--' . $variant . '">' . esc_html( $term->name ) . '</span>';
			}
		}

		return empty( $chips ) ? '' : '<div class="mw-release-meta__chips">' . implode( '', $chips ) . '</div>';
	}

	/**
	 * Render one metadata row; hidden labels stay readable for screen readers.
	 *
	 * @param string $label      Field label.
	 * @param string $value_html Pre-escaped field value markup.
	 * @param bool   $show_label Whether the label is visually shown.
	 */
	private function meta_item_markup( string $label, string $value_html, bool $show_label ): string {
		$label_html = $show_label
			? '<dt>' . esc_html( $label ) . '</dt>'
			: '<dt class="screen-reader-text">' . esc_html( $label ) . '</dt>';

		return '<div class="mw-release-meta__item">' . $label_html . '<dd>' . $value_html . '</dd></div>';
	}

	/**
	 * Render term names as plain text or as links to their archives.
	 *
	 * @param array<int, mixed> $terms      Term names or WP_Term objects.
	 * @param bool              $link_terms Whether terms link to their archive.
	 */
	private function meta_terms_markup( array $terms, bool $link_terms ): string {
		if ( ! $link_terms ) {
			$names = array();
			foreach ( $terms as $term ) {
				$name = is_object( $term ) && isset( $term->name ) ? (string) $term->name : (string) $term;
				if ( '' !== $name ) {
					$names[] = $name;
				}
			}

			return empty( $names ) ? '' : esc_html( implode( ', ', $names ) );
		}

		$links = array();
		foreach ( $terms as $term ) {
			if ( ! is_object( $term ) || empty( $term->name ) ) {
				continue;
			}
			$url     = get_term_link( $term );
			$links[] = ! is_wp_error( $url ) && is_string( $url ) && '' !== $url
				? '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>'
				: esc_html( $term->name );
		}

		return implode( ', ', $links );
	}

	/**
	 * Render a purchase/membership/restricted state without direct asset URLs.
	 *
	 * Every message and call to action falls back to the global MusicWave
	 * settings when the block does not override it, so editors can customize
	 * one template without losing centrally managed copy.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_access_panel( array $attributes ): string {
		$release_id = $this->release_id( $attributes );
		if ( $release_id <= 0 ) {
			return '';
		}

		$layout   = BlockSupport::key_attribute( $attributes, 'layout', array( 'banner', 'stack' ), 'banner' );
		$decision = $this->policy->decide( $release_id, AccessSubject::current() );
		if ( $decision->is_allowed() && 'public' === $decision->mode() ) {
			return '';
		}
		if ( $decision->is_allowed() ) {
			if ( ! BlockSupport::bool_attribute( $attributes, 'showWhenGranted', true ) ) {
				return '';
			}
			$message = $this->message_attribute( $attributes, 'grantedMessage', 'access_granted_message', __( 'دسترسی اعطا شد', 'music-wave-core' ) );
			return '<aside ' . BlockSupport::wrapper_attributes( 'mw-access-panel mw-access-panel--' . $layout . ' mw-access-panel--granted' ) . '><strong class="mw-access-panel__message">' . esc_html( $message ) . '</strong></aside>';
		}

		if ( isset( $this->denied_gate_rendered[ $release_id ] ) ) {
			return '';
		}
		$this->denied_gate_rendered[ $release_id ] = true;

		$message = $this->message_attribute( $attributes, 'restrictedMessage', 'restricted_message', __( 'این انتشار در حال حاضر در دسترس نیست.', 'music-wave-core' ) );
		$cta     = '';
		if ( 'purchase_required' === $decision->reason() ) {
			$message        = $this->message_attribute( $attributes, 'purchaseMessage', 'purchase_message', __( 'برای باز کردن تجربهٔ کامل، این انتشار را خریداری کنید.', 'music-wave-core' ) );
			$purchase_label = $this->message_attribute( $attributes, 'purchaseCtaLabel', 'purchase_cta_label', __( 'مشاهده گزینه‌های خرید', 'music-wave-core' ) );
			$product_ids    = $this->repository->product_ids( $release_id );
			foreach ( $product_ids as $product_id ) {
				if ( 'product' !== get_post_type( $product_id ) ) {
					continue;
				}
				$link = get_permalink( $product_id );
				if ( is_string( $link ) && '' !== $link ) {
					$cta = '<a class="wp-element-button mw-access-panel__cta" href="' . esc_url( $link ) . '">' . esc_html( $purchase_label ) . '</a>';
					break;
				}
			}
		} elseif ( 'membership_required' === $decision->reason() ) {
			$message        = $this->message_attribute( $attributes, 'membershipMessage', 'membership_message', __( 'برای دسترسی به این انتشار به عضویت واجد شرایط نیاز است.', 'music-wave-core' ) );
			$membership_url = $this->url_attribute( $attributes, 'membershipCtaUrl' );
			if ( '' === $membership_url ) {
				$membership_url = (string) Settings::get( 'membership_cta_url' );
			}
			if ( '' !== $membership_url ) {
				$membership_label = $this->message_attribute( $attributes, 'membershipCtaLabel', 'membership_cta_label', __( 'مشاهده گزینه‌های عضویت', 'music-wave-core' ) );
				$cta              = '<a class="wp-element-button mw-access-panel__cta" href="' . esc_url( $membership_url ) . '">' . esc_html( $membership_label ) . '</a>';
			}
		}

		return '<aside ' . BlockSupport::wrapper_attributes( 'mw-access-panel mw-access-panel--' . $layout . ' mw-access-panel--' . sanitize_html_class( $decision->reason() ) ) . '><strong class="mw-access-panel__message">' . esc_html( $message ) . '</strong>' . $cta . '</aside>';
	}

	/**
	 * Render the release credits with list, grid, inline, and role-grouped layouts.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_credits( array $attributes ): string {
		$release_id = $this->release_id( $attributes );
		if ( $release_id <= 0 || ! $this->policy->decide( $release_id, AccessSubject::current() )->is_allowed() ) {
			return '';
		}
		$credits = $this->repository->get( $release_id, 'mw_credits' );
		if ( ! is_array( $credits ) || empty( $credits ) ) {
			return '';
		}

		$show_role = BlockSupport::bool_attribute( $attributes, 'showRole', true );
		$names     = array();
		foreach ( $credits as $credit ) {
			if ( ! is_array( $credit ) || empty( $credit['name'] ) || ! is_scalar( $credit['name'] ) ) {
				continue;
			}
			$role    = $show_role && isset( $credit['role'] ) && is_scalar( $credit['role'] ) && '' !== (string) $credit['role'] ? (string) $credit['role'] : '';
			$names[] = array(
				'name' => (string) $credit['name'],
				'role' => $role,
			);
		}
		if ( empty( $names ) ) {
			return '';
		}

		$layout = BlockSupport::key_attribute( $attributes, 'layout', array( 'list', 'grid', 'inline' ), 'list' );
		if ( $show_role && BlockSupport::bool_attribute( $attributes, 'groupByRole', false ) ) {
			$body = $this->credit_groups_markup( $names );
		} else {
			$body = '<ul class="mw-release-credits__list mw-release-credits__list--' . esc_attr( $layout ) . '">' . implode( '', array_map( array( $this, 'credit_item_markup' ), $names ) ) . '</ul>';
		}
		if ( '' === $body ) {
			return '';
		}

		$heading      = BlockSupport::text_attribute( $attributes, 'heading', __( 'اعتبارات', 'music-wave-core' ) );
		$show_heading = BlockSupport::bool_attribute( $attributes, 'showHeading', true );
		$heading_html = $show_heading ? '<h2 class="mw-release-credits__heading">' . esc_html( $heading ) . '</h2>' : '';
		$aria_label   = $show_heading ? '' : ' aria-label="' . esc_attr( $heading ) . '"';

		return '<section ' . BlockSupport::wrapper_attributes( 'mw-release-credits' ) . $aria_label . '>' . $heading_html . $body . '</section>';
	}

	/**
	 * Render one credit line; the role stays associated with the name.
	 *
	 * @param array<string, string> $credit Normalized credit entry.
	 */
	private function credit_item_markup( array $credit ): string {
		$role = '' !== $credit['role'] ? ' <span class="mw-release-credits__role">' . esc_html( $credit['role'] ) . '</span>' : '';

		return '<li><strong>' . esc_html( $credit['name'] ) . '</strong>' . $role . '</li>';
	}

	/**
	 * Group credits under their role in first-seen order.
	 *
	 * @param array<int, array<string, string>> $credits Normalized credit entries.
	 */
	private function credit_groups_markup( array $credits ): string {
		$groups = array();
		foreach ( $credits as $credit ) {
			$role = '' !== $credit['role'] ? $credit['role'] : __( 'اعتبارات اضافی', 'music-wave-core' );
			if ( ! isset( $groups[ $role ] ) ) {
				$groups[ $role ] = array();
			}
			$groups[ $role ][] = array(
				'name' => $credit['name'],
				'role' => '',
			);
		}

		$sections = array();
		foreach ( $groups as $role => $items ) {
			$sections[] = '<section class="mw-release-credits__group"><h3 class="mw-release-credits__role-heading">' . esc_html( $role ) . '</h3><ul class="mw-release-credits__list">' . implode( '', array_map( array( $this, 'credit_item_markup' ), $items ) ) . '</ul></section>';
		}

		return empty( $sections ) ? '' : '<div class="mw-release-credits__groups">' . implode( '', $sections ) . '</div>';
	}

	/**
	 * Render the ordered children of a collection release.
	 *
	 * Rows can show position, artwork, and per-track duration; multi-disc
	 * collections can be grouped per disc, and each row keeps its preview
	 * and secure-download actions unless the block disables them.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_collection_list( array $attributes ): string {
		$release_id = $this->release_id( $attributes );
		if ( $release_id <= 0 || ! $this->policy->decide( $release_id, AccessSubject::current() )->is_allowed() ) {
			return '';
		}
		$items = $this->repository->collection_items( $release_id );
		if ( empty( $items ) ) {
			return '';
		}

		$options = array(
			'show_position' => BlockSupport::bool_attribute( $attributes, 'showPosition', true ),
			'show_artwork'  => BlockSupport::bool_attribute( $attributes, 'showArtwork', false ),
			'show_duration' => BlockSupport::bool_attribute( $attributes, 'showDuration', true ),
			'show_preview'  => BlockSupport::bool_attribute( $attributes, 'showPreview', true ),
			'show_download' => BlockSupport::bool_attribute( $attributes, 'showDownload', true ),
		);

		$rows           = array();
		$total_duration = 0;
		foreach ( $items as $item ) {
			$child_id = is_array( $item ) && isset( $item['release_id'] ) ? absint( $item['release_id'] ) : 0;
			if ( $child_id < 1 || ReleasePostType::KEY !== get_post_type( $child_id ) ) {
				continue;
			}
			$row = $this->collection_row_markup( $child_id, is_array( $item ) ? $item : array(), $options );
			if ( '' === $row ) {
				continue;
			}
			$total_duration += absint( $this->repository->get( $child_id, 'mw_duration' ) );
			$rows[]          = $row;
		}
		if ( empty( $rows ) ) {
			return '';
		}

		$grouped = '';
		if ( BlockSupport::bool_attribute( $attributes, 'groupByDisc', false ) ) {
			$grouped = $this->collection_disc_groups( $items, $options );
		}

		$heading      = BlockSupport::text_attribute( $attributes, 'heading', __( 'لیست قطعه', 'music-wave-core' ) );
		$show_heading = BlockSupport::bool_attribute( $attributes, 'showHeading', true );
		$heading_html = $show_heading ? '<h2 class="mw-collection-list__heading">' . esc_html( $heading ) . '</h2>' : '';
		$aria_label   = $show_heading ? '' : ' aria-label="' . esc_attr( $heading ) . '"';
		// SonicStream track-list table header: quiet uppercase column labels
		// above the rows on desktop widths.
		$table_header = '<div class="mw-collection-list__table-header" aria-hidden="true"><span class="mw-collection-list__table-position">#</span><span class="mw-collection-list__table-title">' . esc_html__( 'عنوان', 'music-wave-core' ) . '</span><span class="mw-collection-list__table-artist">' . esc_html__( 'هنرمند', 'music-wave-core' ) . '</span><span class="mw-collection-list__table-duration">' . esc_html__( 'زمان', 'music-wave-core' ) . '</span><span class="mw-collection-list__table-quality">' . esc_html__( 'کیفیت', 'music-wave-core' ) . '</span></div>';
		$list_markup  = '' !== $grouped ? $grouped : $table_header . '<ol class="mw-collection-list__items">' . implode( '', $rows ) . '</ol>';
		$total_html   = '';
		if ( BlockSupport::bool_attribute( $attributes, 'showTotalDuration', false ) && $total_duration > 0 ) {
			/* translators: %s: formatted running time, such as 42:10. */
			$total_html = '<p class="mw-collection-list__total">' . esc_html( sprintf( __( 'طول کل: %s', 'music-wave-core' ), $this->format_duration( $total_duration ) ) ) . '</p>';
		}

		// Vinyl tracklist variation: keeps the table markup (and every hook the
		// player relies on) and only appends a modifier class the stylesheet
		// turns into the numbered, hairline-ruled listing of the album page.
		$variation     = BlockSupport::style_variation( $attributes, array( 'tracklist' ) );
		$wrapper_class = 'mw-collection-list' . ( '' !== $variation ? ' mw-collection-list--' . $variation : '' );

		return '<section ' . BlockSupport::wrapper_attributes( $wrapper_class ) . ' data-mw-collection-list' . $aria_label . '>' . $heading_html . $list_markup . $total_html . '</section>';
	}

	/**
	 * Render one collection row with its optional metadata and actions.
	 *
	 * @param int                  $child_id Child release ID.
	 * @param array<string, mixed> $item     Collection relation entry.
	 * @param array<string, bool>  $options  Resolved display options.
	 */
	private function collection_row_markup( int $child_id, array $item, array $options ): string {
		$title = get_the_title( $child_id );
		$link  = get_permalink( $child_id );

		// SonicStream track-row: the position number is swapped for a play
		// glyph on row hover, so both stay part of the same leading column.
		// The CSS-only equalizer lights up while the row owns the active
		// preview (data-mw-playing is synced by preview-player.js).
		$position = '';
		if ( $options['show_position'] ) {
			$number   = isset( $item['position'] ) ? absint( $item['position'] ) : 0;
			$position = '<span class="mw-collection-list__position"><span class="mw-collection-list__position-num">' . esc_html( $number > 0 ? (string) $number : '–' ) . '</span><span class="mw-collection-list__position-play" aria-hidden="true">▶</span><span class="mw-equalizer" aria-hidden="true"><i></i><i></i><i></i><i></i></span></span>';
		}
		$label = $position;
		if ( $options['show_artwork'] ) {
			$thumbnail = get_the_post_thumbnail(
				$child_id,
				'thumbnail',
				array(
					'class' => 'mw-collection-list__artwork-image',
					'alt'   => '',
				)
			);
			if ( '' !== $thumbnail ) {
				$label .= '<span class="mw-collection-list__artwork" aria-hidden="true">' . $thumbnail . '</span>';
			}
		}

			$label .= '<span class="mw-collection-list__title-block"><span class="mw-collection-list__title">' . esc_html( $title ) . '</span></span>';

		$explicit      = sanitize_text_field( (string) $this->repository->get( $child_id, 'mw_explicit' ) );
		$is_explicit   = '1' === $explicit || 'yes' === strtolower( $explicit );
		$explicit_html = $is_explicit ? '<span class="mw-collection-list__explicit" aria-label="' . esc_attr__( 'محتوای صریح', 'music-wave-core' ) . '">E</span>' : '';

		$main_link = is_string( $link ) && '' !== $link
			? '<a class="mw-collection-list__link" href="' . esc_url( $link ) . '">' . $label . $explicit_html . '</a>'
			: '<span class="mw-collection-list__link">' . $label . $explicit_html . '</span>';

		$artist_links = $this->collection_artist_links( $child_id );
		$artist_html  = '' !== $artist_links ? '<span class="mw-collection-list__artist">' . $artist_links . '</span>' : '<span class="mw-collection-list__artist" aria-hidden="true">—</span>';

		$duration_html = '';
		if ( $options['show_duration'] ) {
			$duration = absint( $this->repository->get( $child_id, 'mw_duration' ) );
			if ( $duration > 0 ) {
				$duration_html = '<span class="mw-collection-list__duration">' . esc_html( $this->format_duration( $duration ) ) . '</span>';
			} else {
				$duration_html = '<span class="mw-collection-list__duration" aria-hidden="true">—</span>';
			}
		}

		$quality_hint = '';
		$assets       = $this->repository->get( $child_id, 'mw_download_assets' );
		if ( is_array( $assets ) ) {
			foreach ( $assets as $a ) {
				if ( is_array( $a ) && ! empty( $a['label'] ) ) {
					$quality_hint = sanitize_text_field( (string) $a['label'] );
					break;
				}
			}
		}
		$quality_html = '' !== $quality_hint
			? '<span class="mw-collection-list__quality" aria-hidden="true">' . esc_html( $quality_hint ) . '</span>'
			: '<span class="mw-collection-list__quality" aria-hidden="true">—</span>';

		$main    = '<div class="mw-collection-list__main">' . $main_link . $artist_html . $duration_html . $quality_html . '</div>';
		$actions = '';
		if ( $options['show_preview'] ) {
			$actions .= $this->collection_preview_button( $child_id );
		}
		if ( $options['show_download'] ) {
			// Compact structural variant: no screen-reader-only CSS, the
			// selector carries its own accessible name.
			$actions .= $this->render_download_button(
				array(
					'releaseId' => $child_id,
					'compact'   => true,
				)
			);
		}

		// Hook for row-level interactions (tap-to-play, love, more).
		$row_attrs = ' data-mw-track-id="' . esc_attr( (string) $child_id ) . '"' . ( $is_explicit ? ' data-explicit="1"' : '' );
		return '<li' . $row_attrs . '><div class="mw-collection-list__item">' . $main . ( '' === $actions ? '' : '<div class="mw-collection-list__actions">' . $actions . '</div>' ) . '</div></li>';
	}

	/**
	 * Linked artist names for a collection row, each pointing at its artist
	 * archive. Artists without a resolvable link degrade to plain text.
	 *
	 * @param int $release_id Child release ID.
	 */
	private function collection_artist_links( int $release_id ): string {
		$terms = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'all' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		$links = array();
		foreach ( $terms as $term ) {
			if ( ! is_object( $term ) || empty( $term->name ) ) {
				continue;
			}
			$url     = get_term_link( $term );
			$links[] = ! is_wp_error( $url ) && is_string( $url ) && '' !== $url
				? '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>'
				: esc_html( $term->name );
		}

		return empty( $links ) ? '' : implode( ', ', $links );
	}

	/**
	 * Group collection rows under disc headings when every item carries a disc
	 * number and at least two discs are present; otherwise return an empty
	 * string so the caller keeps the flat list.
	 *
	 * @param array<int, mixed>   $items   Raw collection relation entries.
	 * @param array<string, bool> $options Resolved display options.
	 */
	private function collection_disc_groups( array $items, array $options ): string {
		$discs = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				return '';
			}
			$disc = isset( $item['disc'] ) ? absint( $item['disc'] ) : 0;
			if ( $disc < 1 ) {
				return '';
			}
			if ( ! isset( $discs[ $disc ] ) ) {
				$discs[ $disc ] = array();
			}
			$discs[ $disc ][] = $item;
		}
		if ( count( $discs ) < 2 ) {
			return '';
		}
		ksort( $discs, SORT_NUMERIC );

		$sections = array();
		foreach ( $discs as $disc => $disc_items ) {
			$rows = array();
			foreach ( $disc_items as $item ) {
				$child_id = isset( $item['release_id'] ) ? absint( $item['release_id'] ) : 0;
				if ( $child_id < 1 || ReleasePostType::KEY !== get_post_type( $child_id ) ) {
					continue;
				}
				$row = $this->collection_row_markup( $child_id, $item, $options );
				if ( '' !== $row ) {
					$rows[] = $row;
				}
			}
			if ( empty( $rows ) ) {
				continue;
			}
			/* translators: %d: disc number inside a multi-disc collection. */
			$sections[] = '<section class="mw-collection-list__disc"><h3 class="mw-collection-list__disc-heading">' . esc_html( sprintf( __( 'دیسک %d', 'music-wave-core' ), (int) $disc ) ) . '</h3><ol class="mw-collection-list__items">' . implode( '', $rows ) . '</ol></section>';
		}

		return empty( $sections ) ? '' : '<div class="mw-collection-list__discs">' . implode( '', $sections ) . '</div>';
	}

	/**
	 * Format a duration in seconds as m:ss or h:mm:ss.
	 */
	private function format_duration( int $seconds ): string {
		return $seconds >= 3600 ? gmdate( 'G:i:s', $seconds ) : gmdate( 'i:s', $seconds );
	}

	private function collection_preview_button( int $release_id ): string {
		$url = $this->repository->get( $release_id, 'mw_preview_url' );
		if ( ! is_string( $url ) || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return '';
		}

		$title   = get_the_title( $release_id );
		$artists = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'names' ) );
		$artist  = is_array( $artists ) && ! empty( $artists ) ? implode( ', ', $artists ) : '';
		$image   = get_the_post_thumbnail_url( $release_id, 'thumbnail' );
		$link    = get_permalink( $release_id );
		$limit   = absint( $this->repository->get( $release_id, 'mw_preview_duration' ) );
		$limit   = $limit >= 10 && $limit <= 120 ? $limit : 30;

		return '<button class="mw-preview-button mw-preview-button--compact" type="button" aria-pressed="false" data-preview-url="' . esc_url( $url ) . '" data-preview-title="' . esc_attr( $title ) . '" data-preview-artist="' . esc_attr( $artist ) . '" data-preview-image="' . esc_url( is_string( $image ) ? $image : '' ) . '" data-preview-link="' . esc_url( is_string( $link ) ? $link : '' ) . '" data-preview-limit="' . esc_attr( (string) $limit ) . '"><span class="mw-preview-button__icon" aria-hidden="true">▶</span><span>' . esc_html__( 'پیش‌نمایش', 'music-wave-core' ) . '</span></button>';
	}

	/**
	 * Render the public catalog filter form.
	 *
	 * Every field, the sort selector, the reset link, and all labels are
	 * editor-controlled; the default sort follows the global MusicWave
	 * archive setting when no sort is selected.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_catalog_filters( array $attributes ): string {
		$archive_url = get_post_type_archive_link( ReleasePostType::KEY );
		if ( ! is_string( $archive_url ) || '' === $archive_url ) {
			return '';
		}

		$show_search = BlockSupport::bool_attribute( $attributes, 'showSearch', true );
		$show_sort   = BlockSupport::bool_attribute( $attributes, 'showSort', true );
		$show_reset  = BlockSupport::bool_attribute( $attributes, 'showReset', true );
		$max_terms   = BlockSupport::range_attribute( $attributes, 'maxTerms', 10, 200, 50 );

		$filters = array(
			'mw_artist'       => array( __( 'هنرمند', 'music-wave-core' ), 'showArtistFilter' ),
			'mw_genre'        => array( __( 'سبک', 'music-wave-core' ), 'showGenreFilter' ),
			'mw_mood'         => array( __( 'حال‌وهوا', 'music-wave-core' ), 'showMoodFilter' ),
			'mw_release_type' => array( __( 'نوع انتشار', 'music-wave-core' ), 'showTypeFilter' ),
		);
		$fields  = array();
		foreach ( $filters as $taxonomy => $field ) {
			if ( ! BlockSupport::bool_attribute( $attributes, (string) $field[1], true ) ) {
				continue;
			}
			$label = (string) $field[0];
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
					'number'     => $max_terms,
					'orderby'    => 'name',
					'order'      => 'ASC',
				)
			);
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			// Public catalog query string; sanitized at read, no nonce required for GET archive browsing.
			$selected = isset( $_GET[ $taxonomy ] ) && is_scalar( $_GET[ $taxonomy ] ) ? sanitize_title( wp_unslash( (string) $_GET[ $taxonomy ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$options  = '<option value="">' . esc_html(
				sprintf(
					/* translators: %s: filter label, e.g. Artist or Genre. */
					__( 'همه %s', 'music-wave-core' ),
					$label
				)
			) . '</option>';
			foreach ( $terms as $term ) {
				$options .= '<option value="' . esc_attr( $term->slug ) . '" ' . selected( $selected, $term->slug, false ) . '>' . esc_html( $term->name ) . '</option>';
			}
			$fields[] = '<label class="mw-catalog-filters__field"><span class="screen-reader-text">' . esc_html( $label ) . '</span><select name="' . esc_attr( $taxonomy ) . '">' . $options . '</select></label>';
		}
		if ( empty( $fields ) && ! $show_search && ! $show_sort ) {
			return '';
		}

		$search_html = '';
		if ( $show_search ) {
			$search      = isset( $_GET['s'] ) && is_scalar( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$placeholder = BlockSupport::text_attribute( $attributes, 'searchPlaceholder', __( 'جست‌وجوی انتشار', 'music-wave-core' ) );
			// The field is a plain input for no-JS visitors; catalog-suggest.js
			// upgrades it to an ARIA combobox in place.
			$search_html = '<div class="mw-catalog-suggest mw-catalog-filters__search">'
				. '<label class="mw-catalog-filters__field"><span class="screen-reader-text">' . esc_html__( 'جست‌وجوی انتشار', 'music-wave-core' ) . '</span>'
				. '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr( $placeholder ) . '"></label>'
				. '<span class="screen-reader-text" role="status" aria-live="polite" data-mw-suggest-status></span>'
				. '</div>';
			$this->enqueue_catalog_suggest_assets();
		}

		$sort_markup = '';
		if ( $show_sort ) {
			$sort_options = \ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery::sort_options();
			$sort_var     = \ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery::SORT_QUERY_VAR;
			// Public archive query string; sanitized and allow-listed by normalize_sort().
			$has_sort    = isset( $_GET[ $sort_var ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw_sort    = $has_sort && is_scalar( $_GET[ $sort_var ] ) ? sanitize_key( wp_unslash( (string) $_GET[ $sort_var ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$sort        = \ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery::normalize_sort( $has_sort ? $raw_sort : Settings::get( 'archive_default_sort' ) );
			$sort_markup = '<label class="mw-catalog-filters__sort mw-catalog-filters__field"><span class="screen-reader-text">' . esc_html__( 'مرتب‌سازی انتشارات', 'music-wave-core' ) . '</span><select name="' . esc_attr( $sort_var ) . '">';
			foreach ( $sort_options as $value => $label ) {
				$sort_markup .= '<option value="' . esc_attr( $value ) . '" ' . selected( $sort, $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			$sort_markup .= '</select></label>';
		}

		$submit_label = BlockSupport::text_attribute( $attributes, 'submitLabel', __( 'اعمال فیلتر', 'music-wave-core' ) );
		$actions      = '<div class="mw-catalog-filters__actions"><button type="submit">' . esc_html( $submit_label ) . '</button>';
		if ( $show_reset ) {
			$reset_label = BlockSupport::text_attribute( $attributes, 'resetLabel', __( 'بازنشانی', 'music-wave-core' ) );
			$actions    .= '<a href="' . esc_url( $archive_url ) . '">' . esc_html( $reset_label ) . '</a>';
		}
		$actions .= '</div>';

		$layout    = BlockSupport::key_attribute( $attributes, 'layout', array( 'inline', 'stacked' ), 'inline' );
		$variation = BlockSupport::style_variation( $attributes, array( 'stacked', 'chips' ) );
		if ( 'stacked' === $variation ) {
			$layout = $variation;
		}
		$class = 'mw-catalog-filters' . ( 'stacked' === $layout ? ' mw-catalog-filters--stacked' : '' );
		if ( 'chips' === $variation ) {
			$class .= ' mw-catalog-filters--chips';
		}

		$this->enqueue_catalog_filter_assets();

		// Keeps release-scoped searches resolvable on plain permalink setups.
		$hidden = '<input type="hidden" name="post_type" value="' . esc_attr( ReleasePostType::KEY ) . '">';

		return '<form ' . BlockSupport::wrapper_attributes( $class ) . ' method="get" action="' . esc_url( $archive_url ) . '">' . $hidden . $search_html . implode( '', $fields ) . $sort_markup . $actions . '</form>';
	}

	/**
	 * Enqueue the accessible catalog autocomplete enhancement.
	 *
	 * @return void
	 */
	private function enqueue_catalog_suggest_assets(): void {
		if ( is_admin() || ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}

		wp_enqueue_script( 'music-wave-catalog-suggest', MUSIC_WAVE_CORE_URL . 'assets/catalog-suggest.js', array(), MUSIC_WAVE_CORE_VERSION, true );
		wp_localize_script(
			'music-wave-catalog-suggest',
			'musicWaveCatalogSuggest',
			array(
				'endpoint'     => esc_url_raw( rest_url( 'music-wave/v1/catalog/suggest' ) ),
				'minLength'    => \ManaCore\MusicWave\Core\Discovery\CatalogSearch::MIN_TERM_LENGTH,
				'noResults'    => __( 'هیچ منطبق با کاتالوگ یافت نشد.', 'music-wave-core' ),
				/* translators: %d: number of autocomplete suggestions. */
				'resultsCount' => __( 'پیشنهادها کاتالوگ %d در دسترس است.', 'music-wave-core' ),
				'typeLabels'   => array(
					'release'      => __( 'انتشار', 'music-wave-core' ),
					'artist'       => __( 'هنرمند', 'music-wave-core' ),
					'genre'        => __( 'سبک', 'music-wave-core' ),
					'mood'         => __( 'حال‌وهوا', 'music-wave-core' ),
					'release_type' => __( 'نوع انتشار', 'music-wave-core' ),
				),
			)
		);
	}

	/**
	 * Enqueue the progressive-enhancement script for instant catalog filtering.
	 *
	 * The form stays a standard GET form for no-JS visitors; the script
	 * intercepts submissions and swaps only the catalog regions in place.
	 *
	 * @return void
	 */
	private function enqueue_catalog_filter_assets(): void {
		if ( is_admin() || ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}

		wp_enqueue_script( 'music-wave-catalog-filters', MUSIC_WAVE_CORE_URL . 'assets/catalog-filters.js', array(), MUSIC_WAVE_CORE_VERSION, true );
		wp_localize_script(
			'music-wave-catalog-filters',
			'musicWaveCatalogFilters',
			array(
				'applying' => __( 'در حال اعمال…', 'music-wave-core' ),
			)
		);
	}

	/**
	 * Render the current archive result count and removable active filters.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_catalog_results( array $attributes ): string {
		$show_count = BlockSupport::bool_attribute( $attributes, 'showCount', true );
		$show_chips = BlockSupport::bool_attribute( $attributes, 'showChips', true );
		if ( ! $show_count && ! $show_chips ) {
			return '';
		}

		$archive_url = get_post_type_archive_link( ReleasePostType::KEY );
		if ( ! is_string( $archive_url ) || '' === $archive_url ) {
			return '';
		}

		$count_html = '';
		if ( $show_count ) {
			global $wp_query;
			$count   = is_object( $wp_query ) && isset( $wp_query->found_posts ) ? absint( $wp_query->found_posts ) : 0;
			$summary = sprintf(
				/* translators: %s: number of releases matching the current catalog filters. */
				_n( 'انتشار %s پیدا شد', 'انتشارهای %s پیدا شد', $count, 'music-wave-core' ),
				number_format_i18n( $count )
			);
			$count_html = '<p class="mw-catalog-results__count">' . esc_html( $summary ) . '</p>';
		}

		$chips      = $show_chips ? $this->active_catalog_filter_chips( $archive_url ) : array();
		$chips_html = empty( $chips ) ? '' : '<ul class="mw-catalog-results__filters" aria-label="' . esc_attr__( 'فیلترهای کاتالوگ فعال', 'music-wave-core' ) . '">' . implode( '', $chips ) . '</ul>';

		$variation     = BlockSupport::style_variation( $attributes, array( 'editorial' ) );
		$wrapper_class = 'mw-catalog-results' . ( '' !== $variation ? ' mw-catalog-results--' . $variation : '' );

		return '<div ' . BlockSupport::wrapper_attributes( $wrapper_class ) . ' role="status" aria-live="polite">' . $count_html . $chips_html . '</div>';
	}

	/** @param array<string, mixed> $attributes */
	public function render_preview_player( array $attributes ): string {
		$release_id = $this->release_id( $attributes );
		if ( $release_id <= 0 ) {
			return '';
		}
		$preview_url = $this->repository->get( $release_id, 'mw_preview_url' );
		if ( ! is_string( $preview_url ) || 'https' !== wp_parse_url( $preview_url, PHP_URL_SCHEME ) ) {
			// Releases without a public preview URL still play through the
			// secure playback-queue flow when the visitor has access (the
			// same path the global card play buttons use), so the primary
			// action bar keeps its play trigger instead of losing it.
			return $this->release_play_button( $release_id, $attributes );
		}

		$title     = get_the_title( $release_id );
		$artists   = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'names' ) );
		$artist    = is_array( $artists ) && ! empty( $artists ) ? implode( ', ', $artists ) : '';
		$image     = get_the_post_thumbnail_url( $release_id, 'thumbnail' );
		$link      = get_permalink( $release_id );
		$limit     = absint( $this->repository->get( $release_id, 'mw_preview_duration' ) );
		$limit     = $limit >= 10 && $limit <= 120 ? $limit : 30;
		$label     = isset( $attributes['label'] ) ? sanitize_text_field( (string) $attributes['label'] ) : '';
		$label     = '' !== $label ? $label : __( 'پخش پیش‌نمایش', 'music-wave-core' );
		$style     = BlockSupport::key_attribute( $attributes, 'style', array( 'solid', 'outline', 'ghost', 'vinyl' ), 'solid' );
		$variation = BlockSupport::style_variation( $attributes, array( 'outline', 'ghost', 'vinyl' ) );
		if ( '' !== $variation ) {
			$style = $variation;
		}
		$button = 'mw-preview-button' . ( 'solid' !== $style ? ' mw-preview-button--' . $style : '' );
		$icon   = BlockSupport::bool_attribute( $attributes, 'showIcon', true ) ? '<span class="mw-preview-button__icon" aria-hidden="true">&#9654;</span>' : '';

		return '<section ' . BlockSupport::wrapper_attributes( 'mw-preview-player' ) . '><h2 class="screen-reader-text">' . esc_html__( 'پیش‌نمایش صوتی', 'music-wave-core' ) . '</h2><button class="' . esc_attr( $button ) . '" type="button" aria-pressed="false" data-preview-url="' . esc_url( $preview_url ) . '" data-preview-title="' . esc_attr( $title ) . '" data-preview-artist="' . esc_attr( $artist ) . '" data-preview-image="' . esc_url( is_string( $image ) ? $image : '' ) . '" data-preview-link="' . esc_url( $link ) . '" data-preview-limit="' . esc_attr( (string) $limit ) . '">' . $icon . '<span>' . esc_html( $label ) . '</span></button></section>';
	}

	/**
	 * Fallback play trigger for releases without a public preview URL.
	 *
	 * Delegates to preview-player.js through the shared `mw-card-play`
	 * contract: the script resolves the release's playback queue and streams
	 * each track via the signed-token endpoint. Renders nothing when the
	 * release has no streamable audio for the current visitor.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function release_play_button( int $release_id, array $attributes ): string {
		if ( null === $this->policy || ! $this->policy->decide( $release_id, AccessSubject::current() )->is_allowed() ) {
			return '';
		}

		$assets = $this->repository->get( $release_id, 'mw_download_assets' );
		if ( ! is_array( $assets ) ) {
			return '';
		}
		$streamable = false;
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['asset_id'] ) ) {
				continue;
			}
			$format = isset( $asset['format'] ) && is_scalar( $asset['format'] ) ? sanitize_key( strtolower( (string) $asset['format'] ) ) : '';
			if ( in_array( $format, array( 'mp3', 'm4a', 'aac', 'ogg', 'wav', 'flac' ), true ) ) {
				$streamable = true;
				break;
			}
		}
		if ( ! $streamable ) {
			return '';
		}

		$visible = BlockSupport::text_attribute( $attributes, 'label', __( 'پخش الان', 'music-wave-core' ) );
		/* translators: %s: release title. */
		$label = sprintf( __( 'پخش %s', 'music-wave-core' ), get_the_title( $release_id ) );
		$icon  = BlockSupport::bool_attribute( $attributes, 'showIcon', true ) ? '<span class="mw-preview-button__icon" aria-hidden="true">&#9654;</span>' : '';

		return '<section ' . BlockSupport::wrapper_attributes( 'mw-preview-player' ) . '><h2 class="screen-reader-text">' . esc_html__( 'پخش انتشار', 'music-wave-core' ) . '</h2><button class="mw-preview-button" type="button" data-mw-release-id="' . esc_attr( (string) $release_id ) . '" aria-label="' . esc_attr( $label ) . '" aria-pressed="false">' . $icon . '<span>' . esc_html( $visible ) . '</span></button></section>';
	}

	/**
	 * Render the authorized secure-download controls for a release.
	 *
	 * The panel variant is the standalone "download this release" card; the
	 * compact variant renders the same signed-token controls as an inline
	 * action group for embedding inside collection track rows. Neither
	 * exposes a direct private asset URL: rows carry public quality labels
	 * only, and the actual file is resolved through the signed token REST
	 * flow in download.js.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_download_button( array $attributes ): string {
		$release_id = $this->release_id( $attributes );
		// Policy-driven rendering: guests see the secure controls whenever the
		// decision itself allows them (public releases or a policy-level open
		// gate such as the VIP "everyone including guests" mode); the sign-in
		// prompt only belongs on the access panel for denied visitors.
		if ( $release_id <= 0 || ! $this->policy->decide( $release_id, AccessSubject::current() )->is_allowed() ) {
			return '';
		}
		$assets = $this->download_assets( $release_id );
		if ( empty( $assets ) ) {
			return '';
		}
		$this->enqueue_download_runtime();

		$files = $this->download_files( $assets );
		if ( empty( $files ) ) {
			return '';
		}

		$row_options = array(
			'show_quality'   => BlockSupport::bool_attribute( $attributes, 'showQuality', true ),
			'show_stream'    => BlockSupport::bool_attribute( $attributes, 'showStream', true ),
			'download_label' => BlockSupport::text_attribute( $attributes, 'downloadLabel', __( 'دانلود ایمن', 'music-wave-core' ) ),
			'play_label'     => isset( $attributes['playLabel'] ) && is_scalar( $attributes['playLabel'] ) ? sanitize_text_field( (string) $attributes['playLabel'] ) : '',
		);

		if ( ! empty( $attributes['compact'] ) ) {
			return $this->download_inline_markup( $release_id, $files, $row_options );
		}

		return $this->download_panel_markup( $release_id, $files, $row_options, $attributes );
	}

	/**
	 * Enqueue the signed-token download runtime once per response.
	 *
	 * Every rendered panel and inline group shares one script bundle and one
	 * localized config; a collection page can embed dozens of copies.
	 */
	private function enqueue_download_runtime(): void {
		if ( wp_script_is( 'music-wave-download', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_script( 'music-wave-download', MUSIC_WAVE_CORE_URL . 'assets/download.js', array( 'wp-api-fetch' ), MUSIC_WAVE_CORE_VERSION, true );
		wp_localize_script(
			'music-wave-download',
			'musicWaveDownload',
			array(
				'restUrl'      => esc_url_raw( rest_url() ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'errorMessage' => __( 'دانلود شروع نشد.', 'music-wave-core' ),
				'streamError'  => __( 'پخش امن شروع نشد.', 'music-wave-core' ),
				'sessionError' => __( 'جلسه شما تمام شده است. صفحه را تازه کنید یا دوباره وارد شوید.', 'music-wave-core' ),
				'playLabel'    => __( 'پخش', 'music-wave-core' ),
				'pauseLabel'   => __( 'مکث', 'music-wave-core' ),
			)
		);
	}

	/**
	 * Render the standalone "download this release" panel.
	 *
	 * @param int                                 $release_id Release post ID.
	 * @param array<string, array<string, mixed>> $files      Downloadable files.
	 * @param array<string, mixed>                $options    Resolved row options.
	 * @param array<string, mixed>                $attributes Block attributes.
	 */
	private function download_panel_markup( int $release_id, array $files, array $options, array $attributes ): string {
		$heading_html     = BlockSupport::bool_attribute( $attributes, 'showHeading', true )
			? '<strong>' . esc_html( BlockSupport::text_attribute( $attributes, 'heading', __( 'دانلود این انتشار', 'music-wave-core' ) ) ) . '</strong>'
			: '';
		$description_html = BlockSupport::bool_attribute( $attributes, 'showDescription', true )
			? '<span>' . esc_html( BlockSupport::text_attribute( $attributes, 'description', __( 'یک فایل صوتی موجود را پخش کنید یا کیفیت دلخواه خود را دانلود کنید.', 'music-wave-core' ) ) ) . '</span>'
			: '';
		$heading          = '' !== $heading_html || '' !== $description_html
			? '<div class="mw-download-action__heading">' . $heading_html . $description_html . '</div>'
			: '';

		$protected = (bool) has_filter( 'music_wave_download_provider' );
		$message   = $protected
			? __( 'حفاظت‌شده توسط MusicWave VIP: فایل‌ها از پوشه‌ای حفاظت‌شده و از طریق پیوندهای امضاشده و زمان‌دار پخش می‌شوند. نشانی مستقیم فایل هرگز نمایش داده نمی‌شود.', 'music-wave-core' )
			: __( 'از طریق پیوندهای امضاشده و منقضی‌شده تحویل داده می‌شود. نشانی مستقیم فایل هرگز در معرض دید قرار نمی‌گیرد.', 'music-wave-core' );
		$badge     = $protected ? '<span class="mw-download-action__security-badge">' . esc_html__( 'VIP حفاظت‌شده', 'music-wave-core' ) . '</span>' : '';
		$security  = '<p class="mw-download-action__security"><svg class="mw-download-action__security-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg><span>' . esc_html( $message ) . '</span>' . $badge . '</p>';

		$panel_extra  = count( $files ) === 1 ? ' mw-download-action--single' : '';
		$panel_extra .= ' mw-download-action--unified';
		$unified_head = '<div class="mw-download-action__table-header" aria-hidden="true"><span>' . esc_html__( 'فایل', 'music-wave-core' ) . '</span><span>' . esc_html__( 'کیفیت', 'music-wave-core' ) . '</span><span>' . esc_html__( 'اقدام', 'music-wave-core' ) . '</span></div>';
		return '<aside ' . BlockSupport::wrapper_attributes( 'mw-download-action mw-download-action--panel' . $panel_extra ) . '>' . $heading . $unified_head . $this->download_file_rows( $release_id, $files, $options ) . '<audio class="mw-secure-audio" preload="metadata" hidden></audio><span class="mw-download-status" role="status" aria-live="polite"></span>' . $security . '</aside>';
	}

	/**
	 * Render the compact inline download group embedded in track rows.
	 *
	 * Compactness is structural, not CSS-hidden: the file name stays in the
	 * quality option labels, the selector carries its accessible name on
	 * aria-label, and the live status renders as a floating notice anchored
	 * to the group so it never shifts the row layout.
	 *
	 * @param int                                 $release_id Release post ID.
	 * @param array<string, array<string, mixed>> $files      Downloadable files.
	 * @param array<string, mixed>                $options    Resolved row options.
	 */
	private function download_inline_markup( int $release_id, array $files, array $options ): string {
		$show_quality   = ! empty( $options['show_quality'] );
		$show_stream    = ! empty( $options['show_stream'] );
		$download_label = isset( $options['download_label'] ) && '' !== (string) $options['download_label'] ? (string) $options['download_label'] : __( 'دانلود ایمن', 'music-wave-core' );
		$play_label     = isset( $options['play_label'] ) ? (string) $options['play_label'] : '';

		$groups = array();
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) || empty( $file['qualities'] ) || ! is_array( $file['qualities'] ) ) {
				continue;
			}
			$qualities = $this->download_quality_options( $file['qualities'] );
			if ( '' === $qualities['html'] ) {
				continue;
			}

			$select = '';
			if ( $show_quality ) {
				/* translators: %s: downloadable file label, such as "Main download". */
				$select = '<select class="mw-download-quality" aria-label="' . esc_attr( sprintf( __( 'انتخاب کیفیت برای %s', 'music-wave-core' ), (string) $file['label'] ) ) . '">' . $qualities['html'] . '</select>';
			}

			$play = '';
			if ( $show_stream && $qualities['has_streamable'] ) {
				$play_labels = '' !== $play_label
					? ' data-play-label="' . esc_attr( $play_label ) . '" data-pause-label="' . esc_attr( __( 'مکث', 'music-wave-core' ) ) . '"'
					: '';
				$play_title  = '' !== $play_label ? $play_label : __( 'پخش', 'music-wave-core' );
				$play        = '<button class="mw-secure-play-button" type="button" aria-label="' . esc_attr( $play_title ) . '" data-release-id="' . esc_attr( (string) $release_id ) . '"' . ( $show_quality ? '' : ' data-download-quality="' . esc_attr( $qualities['first_key'] ) . '"' ) . $play_labels . ( $qualities['first_streamable'] ? '' : ' disabled' ) . '><span class="mw-secure-play-button__icon" aria-hidden="true">▶</span></button>';
			}

			$groups[] = '<div class="mw-download-inline">' . $select . $play . '<button class="wp-element-button mw-download-button" type="button" aria-label="' . esc_attr( $download_label ) . '" data-release-id="' . esc_attr( (string) $release_id ) . '"' . ( $show_quality ? '' : ' data-download-quality="' . esc_attr( $qualities['first_key'] ) . '"' ) . '><span class="mw-download-button__icon" aria-hidden="true">⤓</span></button></div>';
		}
		if ( empty( $groups ) ) {
			return '';
		}

		return '<div class="mw-download-action mw-download-action--inline"><div class="mw-download-inline-list" role="group" aria-label="' . esc_attr__( 'اقدامات دانلود', 'music-wave-core' ) . '">' . implode( '', $groups ) . '</div><audio class="mw-secure-audio" preload="metadata" hidden></audio><span class="mw-download-status" role="status" aria-live="polite"></span></div>';
	}

	/**
	 * Render bounded same-artist and similarity sections for a release.
	 *
	 * Sections, card content, layout, and query behavior are configurable per
	 * block; section visibility falls back to the global MusicWave settings
	 * unless the block overrides it.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_related_releases( array $attributes ): string {
		$release_id = $this->release_id( $attributes );
		if ( $release_id < 1 ) {
			return '';
		}

		$limit = BlockSupport::range_attribute( $attributes, 'itemsToShow', 2, 12, 0 );
		if ( $limit < 2 ) {
			$limit = absint( Settings::get( 'related_items_per_section' ) );
		}
		$limit    = $limit >= 2 && $limit <= 12 ? $limit : 4;
		$order_by = BlockSupport::key_attribute( $attributes, 'orderBy', array( 'date', 'title', 'rand', 'modified' ), 'date' );
		$order    = isset( $attributes['order'] ) && 'ASC' === strtoupper( (string) $attributes['order'] ) ? 'ASC' : 'DESC';
		$options  = $this->related_card_options( $attributes );
		$excluded = array( $release_id );
		$sections = array();

		$same_artist_heading = BlockSupport::text_attribute( $attributes, 'sameArtistHeading', __( 'انتشارهای بیشتر از این هنرمند', 'music-wave-core' ) );
		$similar_heading     = BlockSupport::text_attribute( $attributes, 'similarHeading', __( 'انتشارهای مشابه', 'music-wave-core' ) );
		$show_section_link   = BlockSupport::bool_attribute( $attributes, 'showSectionLink', false );
		$section_link_label  = BlockSupport::text_attribute( $attributes, 'sectionLinkLabel', __( 'همه را ببینید', 'music-wave-core' ) );

		if ( $this->visibility_attribute( $attributes, 'sameArtistSection', 'show_same_artist_releases' ) ) {
			$artist_ids = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'ids' ) );
			$artist_ids = is_array( $artist_ids ) ? array_values( array_filter( array_map( 'absint', $artist_ids ) ) ) : array();
			if ( ! empty( $artist_ids ) ) {
				$artist_releases = $this->related_query(
					$excluded,
					$limit,
					array(
						array(
							'taxonomy' => 'mw_artist',
							'field'    => 'term_id',
							'terms'    => $artist_ids,
						),
					),
					$order_by,
					$order
				);
				if ( ! empty( $artist_releases ) ) {
					$artist_link = '';
					if ( $show_section_link ) {
						$term_link   = get_term_link( $artist_ids[0] );
						$artist_link = ! is_wp_error( $term_link ) && is_string( $term_link ) ? $term_link : '';
					}
					$sections[] = $this->related_section( $same_artist_heading, $artist_releases, $options, $artist_link, $section_link_label );
					$excluded   = array_merge( $excluded, $artist_releases );
				}
			}
		}

		if ( $this->visibility_attribute( $attributes, 'similarSection', 'show_similar_releases' ) ) {
			$signals   = array();
			$tax_query = array( 'relation' => 'OR' );
			$match     = array(
				'mw_genre'        => array(
					'weight'  => 4,
					'enabled' => BlockSupport::bool_attribute( $attributes, 'matchGenre', true ),
				),
				'mw_mood'         => array(
					'weight'  => 2,
					'enabled' => BlockSupport::bool_attribute( $attributes, 'matchMood', true ),
				),
				'mw_release_type' => array(
					'weight'  => 1,
					'enabled' => BlockSupport::bool_attribute( $attributes, 'matchType', true ),
				),
			);
			foreach ( $match as $taxonomy => $config ) {
				if ( ! $config['enabled'] ) {
					continue;
				}
				$term_ids = wp_get_post_terms( $release_id, $taxonomy, array( 'fields' => 'ids' ) );
				$term_ids = is_array( $term_ids ) ? array_values( array_filter( array_map( 'absint', $term_ids ) ) ) : array();
				if ( ! empty( $term_ids ) ) {
					$signals[ $taxonomy ] = array(
						'terms'  => $term_ids,
						'weight' => (int) $config['weight'],
					);
					$tax_query[]          = array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $term_ids,
					);
				}
			}
			if ( count( $tax_query ) > 1 ) {
				$similar = $this->similar_query( $excluded, $limit, $tax_query, $signals, $order_by, $order );
				if ( ! empty( $similar ) ) {
					$archive_link = '';
					if ( $show_section_link ) {
						$archive      = get_post_type_archive_link( ReleasePostType::KEY );
						$archive_link = is_string( $archive ) ? $archive : '';
					}
					$sections[] = $this->related_section( $similar_heading, $similar, $options, $archive_link, $section_link_label );
				}
			}
		}

		$root_class = 'mw-related-releases mw-related-releases--' . $options['layout']
			. ( '' !== $options['variation'] ? ' mw-related-releases--' . $options['variation'] : '' );

		return empty( $sections ) ? '' : '<div ' . BlockSupport::wrapper_attributes( $root_class ) . '>' . implode( '', $sections ) . '</div>';
	}

	/**
	 * Resolve the shared card presentation options for related sections.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, mixed>
	 */
	private function related_card_options( array $attributes ): array {
		return array(
			'layout'       => BlockSupport::key_attribute( $attributes, 'layout', array( 'grid', 'scroll', 'list' ), 'grid' ),
			'columns'      => BlockSupport::range_attribute( $attributes, 'columns', 2, 6, 4 ),
			'shape'        => BlockSupport::key_attribute( $attributes, 'imageShape', array( 'square', 'landscape', 'portrait', 'circle' ), 'square' ),
			'show_artwork' => BlockSupport::bool_attribute( $attributes, 'showArtwork', true ),
			'show_artist'  => BlockSupport::bool_attribute( $attributes, 'showArtist', true ),
			'show_date'    => BlockSupport::bool_attribute( $attributes, 'showDate', false ),
			'show_excerpt' => BlockSupport::bool_attribute( $attributes, 'showExcerpt', false ),
			'show_preview' => BlockSupport::bool_attribute( $attributes, 'showPreview', true ),
			'show_action'  => BlockSupport::bool_attribute( $attributes, 'showAction', false ),
			'action_label' => BlockSupport::text_attribute( $attributes, 'actionLabel', __( 'انتشار را باز کنید', 'music-wave-core' ) ),
			// Editorial / vinyl restyle of the shelf cards, resolved once for
			// every section and for the block root.
			'variation'    => BlockSupport::style_variation( $attributes, array( 'editorial', 'vinyl' ) ),
		);
	}

	/**
	 * Query published related releases with an allow-listed ordering.
	 *
	 * @param array<int, int>                 $excluded  Excluded post IDs.
	 * @param array<int|string, array|string> $tax_query Taxonomy query.
	 * @return array<int, int>
	 */
	private function related_query( array $excluded, int $limit, array $tax_query, string $order_by = 'date', string $order = 'DESC' ): array {
		$order_by = in_array( $order_by, array( 'date', 'title', 'rand', 'modified' ), true ) ? $order_by : 'date';
		$order    = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$posts    = get_posts(
			array(
				'post_type'              => ReleasePostType::KEY,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'post__not_in'           => array_values( array_unique( array_map( 'absint', $excluded ) ) ),
				'fields'                 => 'ids',
				'orderby'                => $order_by,
				'order'                  => $order,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => true,
				'tax_query'              => $tax_query,
				'suppress_filters'       => false,
			)
		);

		return is_array( $posts ) ? array_values( array_filter( array_map( 'absint', $posts ) ) ) : array();
	}

	/**
	 * Rank a bounded candidate set by shared genre, mood, then release type.
	 *
	 * A plain OR taxonomy query tends to label every album as similar merely
	 * because it is an album. Weighted scoring keeps genre as the strongest
	 * signal while retaining mood and type as useful tie-breakers.
	 *
	 * @param array<int, int>                     $excluded Excluded post IDs.
	 * @param array<int|string, array|string>     $tax_query Candidate taxonomy query.
	 * @param array<string, array<string, mixed>> $signals Shared taxonomy signals.
	 * @return array<int, int>
	 */
	private function similar_query( array $excluded, int $limit, array $tax_query, array $signals, string $order_by = 'date', string $order = 'DESC' ): array {
		$candidate_limit = min( 60, max( 16, $limit * 8 ) );
		$candidates      = $this->related_query( $excluded, $candidate_limit, $tax_query, $order_by, $order );
		$scores          = array();
		$order           = array_flip( $candidates );

		// Resolve every candidate's signal taxonomies in one batched query
		// instead of one query per candidate per taxonomy.
		$this->term_index->prime( $candidates, array_map( 'strval', array_keys( $signals ) ) );

		foreach ( $candidates as $candidate_id ) {
			$score = 0;
			foreach ( $signals as $taxonomy => $signal ) {
				$term_ids = $this->term_index->ids( $candidate_id, (string) $taxonomy );
				$shared   = count( array_intersect( $signal['terms'], $term_ids ) );
				$score   += $shared * (int) $signal['weight'];
			}
			$scores[ $candidate_id ] = $score;
		}

		usort(
			$candidates,
			static function ( int $left, int $right ) use ( $scores, $order ): int {
				if ( $scores[ $left ] === $scores[ $right ] ) {
					return $order[ $left ] <=> $order[ $right ];
				}

				return $scores[ $right ] <=> $scores[ $left ];
			}
		);

		return array_slice( $candidates, 0, $limit );
	}

	/**
	 * Render one related-releases section reusing the release-shelf layout system.
	 *
	 * Sections share the theme's `mw-release-shelf` grid, horizontal-scroll, and
	 * compact-list presentation so related rails match the MusicWave release
	 * shelf block exactly.
	 *
	 * @param array<int, int>      $release_ids        Related release IDs.
	 * @param array<string, mixed> $options            Resolved card options.
	 * @param string               $section_url        Optional "See all" URL.
	 * @param string               $section_link_label Label for the section link.
	 */
	private function related_section( string $heading, array $release_ids, array $options, string $section_url = '', string $section_link_label = '' ): string {
		$cards = array();
		foreach ( $release_ids as $release_id ) {
			$card = $this->related_card( $release_id, $options );
			if ( '' !== $card ) {
				$cards[] = $card;
			}
		}
		if ( empty( $cards ) ) {
			return '';
		}

		$more = '';
		if ( '' !== $section_url && '' !== $section_link_label ) {
			$more = '<a class="mw-related-releases__more" href="' . esc_url( $section_url ) . '">' . esc_html( $section_link_label ) . '<span aria-hidden="true">&rarr;</span></a>';
		}

		$shelf_class = 'mw-release-shelf mw-release-shelf--' . $options['layout'];
		if ( '' !== $options['variation'] ) {
			$shelf_class .= ' mw-release-shelf--' . $options['variation'];
		}
		if ( 'grid' === $options['layout'] ) {
			$shelf_class .= ' mw-release-shelf--columns-' . $options['columns'];
		}

		return '<section class="mw-related-releases__section ' . esc_attr( $shelf_class ) . '"><div class="mw-related-releases__section-header"><h2>' . esc_html( $heading ) . '</h2>' . $more . '</div><div class="mw-release-shelf__items">' . implode( '', $cards ) . '</div></section>';
	}

	/**
	 * Render one related-release card with the release-shelf card markup.
	 *
	 * @param array<string, mixed> $options Resolved card options.
	 */
	private function related_card( int $release_id, array $options ): string {
		$title = get_the_title( $release_id );
		$link  = get_permalink( $release_id );
		if ( ! is_string( $link ) || '' === $link ) {
			return '';
		}

		$art = '';
		if ( $options['show_artwork'] ) {
			$image   = get_the_post_thumbnail(
				$release_id,
				'medium_large',
				array(
					'class' => 'mw-release-shelf__image',
					'alt'   => '',
				)
			);
			$initial = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 1 ) : substr( $title, 0, 1 );
			/* translators: %s: music release title. */
			$open_label = sprintf( __( 'باز کردن %s', 'music-wave-core' ), $title );
			$overlay    = '';
			if ( $options['show_preview'] ) {
				$overlay = apply_filters( 'music_wave_card_play_button', '', $release_id, 'mw-release-shelf__play' );
				$overlay = is_string( $overlay ) ? $overlay : '';
				if ( '' === $overlay && $this->has_preview( $release_id ) ) {
					$overlay = '<span class="mw-release-shelf__play" aria-hidden="true">&#9654;</span>';
				}
			}
			$art = '<div class="mw-release-shelf__artwrap"><a class="mw-release-shelf__art mw-release-shelf__art--' . esc_attr( (string) $options['shape'] ) . '" href="' . esc_url( $link ) . '" aria-label="' . esc_attr( $open_label ) . '">' . ( '' !== $image ? $image : '<span class="mw-release-shelf__placeholder" aria-hidden="true">' . esc_html( $initial ) . '</span>' ) . '</a>' . $overlay . '</div>';
		}

		$artist = '';
		if ( $options['show_artist'] ) {
			$artists = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'names' ) );
			if ( is_array( $artists ) && ! empty( $artists ) ) {
				$artist = '<span class="mw-release-shelf__artist">' . esc_html( implode( ', ', $artists ) ) . '</span>';
			}
		}

		$date = '';
		if ( $options['show_date'] ) {
			$date = '<time datetime="' . esc_attr( get_the_date( 'c', $release_id ) ) . '">' . esc_html( get_the_date( '', $release_id ) ) . '</time>';
		}

		$excerpt = '';
		if ( $options['show_excerpt'] ) {
			$excerpt_text = get_the_excerpt( $release_id );
			if ( '' !== $excerpt_text ) {
				$excerpt = '<p>' . esc_html( wp_trim_words( $excerpt_text, 18 ) ) . '</p>';
			}
		}

		$preview = $options['show_preview'] ? $this->collection_preview_button( $release_id ) : '';
		$action  = $options['show_action']
			? '<a class="mw-release-shelf__action" href="' . esc_url( $link ) . '">' . esc_html( (string) $options['action_label'] ) . '</a>'
			: '';

		return '<article class="mw-release-shelf__item">' . $art . '<div class="mw-release-shelf__body"><h3><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></h3>' . $artist . $date . $excerpt . $preview . $action . '</div></article>';
	}

	/**
	 * Whether a release exposes a usable HTTPS preview URL.
	 */
	private function has_preview( int $release_id ): bool {
		$url = $this->repository->get( $release_id, 'mw_preview_url' );

		return is_string( $url ) && 'https' === wp_parse_url( $url, PHP_URL_SCHEME );
	}

	/**
	 * Group customer-facing variants by their downloadable file.
	 *
	 * @param array<int, array<string, string>> $assets Variants.
	 * @return array<string, array<string, mixed>>
	 */
	private function download_files( array $assets ): array {
		$files = array();
		foreach ( $assets as $asset ) {
			$file_key   = isset( $asset['file_key'] ) && '' !== $asset['file_key'] ? sanitize_key( $asset['file_key'] ) : 'main-download';
			$file_label = isset( $asset['file_label'] ) && '' !== $asset['file_label'] ? sanitize_text_field( $asset['file_label'] ) : __( 'دانلود اصلی', 'music-wave-core' );
			if ( ! isset( $files[ $file_key ] ) ) {
				$files[ $file_key ] = array(
					'key'       => $file_key,
					'label'     => $file_label,
					'qualities' => array(),
				);
			}
			$files[ $file_key ]['qualities'][] = array(
				'key'        => $asset['key'],
				'label'      => $asset['label'],
				'streamable' => ! empty( $asset['streamable'] ),
			);
		}

		return $files;
	}

	/**
	 * Build one quality <option> per downloadable variant plus row flags.
	 *
	 * @param array<int, mixed> $qualities File variants.
	 * @return array{html: string, has_streamable: bool, first_key: string, first_streamable: bool}
	 */
	private function download_quality_options( array $qualities ): array {
		$options        = array();
		$has_streamable = false;
		foreach ( $qualities as $quality ) {
			if ( ! is_array( $quality ) || empty( $quality['key'] ) ) {
				continue;
			}
			$streamable     = ! empty( $quality['streamable'] );
			$has_streamable = $has_streamable || $streamable;
			$options[]      = '<option value="' . esc_attr( (string) $quality['key'] ) . '" data-streamable="' . esc_attr( $streamable ? '1' : '0' ) . '">' . esc_html( (string) ( $quality['label'] ?? $quality['key'] ) ) . '</option>';
		}

		return array(
			'html'             => implode( '', $options ),
			'has_streamable'   => $has_streamable,
			'first_key'        => isset( $qualities[0]['key'] ) && is_array( $qualities[0] ) ? (string) $qualities[0]['key'] : '',
			'first_streamable' => ! empty( $qualities[0]['streamable'] ),
		);
	}

	/**
	 * Render one row per downloadable file with its quality and action controls.
	 *
	 * @param int                                 $release_id Release post ID.
	 * @param array<string, array<string, mixed>> $files   Downloadable files.
	 * @param array<string, mixed>                $options Resolved row options.
	 * @return string
	 */
	private function download_file_rows( int $release_id, array $files, array $options ): string {
		$show_quality   = ! empty( $options['show_quality'] );
		$show_stream    = ! empty( $options['show_stream'] );
		$download_label = isset( $options['download_label'] ) && '' !== (string) $options['download_label'] ? (string) $options['download_label'] : __( 'دانلود ایمن', 'music-wave-core' );
		$play_label     = isset( $options['play_label'] ) ? (string) $options['play_label'] : '';

		$rows = array();
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) || empty( $file['qualities'] ) || ! is_array( $file['qualities'] ) ) {
				continue;
			}
			$qualities      = $this->download_quality_options( $file['qualities'] );
			$has_streamable = $qualities['has_streamable'];
			if ( '' === $qualities['html'] ) {
				continue;
			}

			$default_quality = $show_quality ? '' : ' data-download-quality="' . esc_attr( $qualities['first_key'] ) . '"';
			$quality_select  = $show_quality
				? '<label class="mw-download-action__quality"><span>' . esc_html__( 'کیفیت', 'music-wave-core' ) . '</span><select class="mw-download-quality">' . $qualities['html'] . '</select></label>'
				: '';

			$play = '';
			if ( $show_stream && $has_streamable ) {
				$play_labels = '' !== $play_label
					? ' data-play-label="' . esc_attr( $play_label ) . '" data-pause-label="' . esc_attr( __( 'مکث', 'music-wave-core' ) ) . '"'
					: '';
				$play_title  = '' !== $play_label ? $play_label : __( 'پخش', 'music-wave-core' );
				$play        = '<button class="mw-secure-play-button" type="button" aria-label="' . esc_attr( $play_title ) . '" data-release-id="' . esc_attr( (string) $release_id ) . '"' . $default_quality . $play_labels . ( $qualities['first_streamable'] || ! $show_quality ? '' : ' disabled' ) . '><span class="mw-secure-play-button__icon" aria-hidden="true">▶</span></button>';
			}

			$row_class = 'mw-download-file-row' . ( $show_quality ? '' : ' mw-download-file-row--no-quality' );
			$icon      = '<span class="mw-download-file-row__icon" aria-hidden="true">♫</span>';
			if ( 'mp3' === strtolower( (string) ( $file['qualities'][0]['key'] ?? '' ) ) || ! empty( $file['qualities'][0]['streamable'] ) ) {
				$icon = '<span class="mw-download-file-row__icon mw-download-file-row__icon--wave" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M3 12h2.5l2-6 3 12 2.5-8H21"/></svg></span>';
			}
			$rows[] = '<div class="' . esc_attr( $row_class ) . '" role="listitem"><div class="mw-download-file-row__title">' . $icon . '<strong>' . esc_html( (string) $file['label'] ) . '</strong></div>' . $quality_select . '<div class="mw-download-file-row__actions">' . $play . '<button class="wp-element-button mw-download-button" type="button" aria-label="' . esc_attr( $download_label ) . '" data-release-id="' . esc_attr( (string) $release_id ) . '"' . $default_quality . '><span class="mw-download-button__icon" aria-hidden="true">⤓</span></button></div></div>';
		}

		return empty( $rows ) ? '' : '<div class="mw-download-file-list" role="list">' . implode( '', $rows ) . '</div>';
	}

	/** @param array<string, mixed> $attributes */
	private function release_id( array $attributes ): int {
		$requested = isset( $attributes['releaseId'] ) ? absint( $attributes['releaseId'] ) : 0;
		if ( $requested > 0 && ReleasePostType::KEY === get_post_type( $requested ) ) {
			return $requested;
		}

		if (
			is_object( $this->render_context )
			&& isset( $this->render_context->context )
			&& is_array( $this->render_context->context )
		) {
			$context_id   = isset( $this->render_context->context['postId'] ) ? absint( $this->render_context->context['postId'] ) : 0;
			$context_type = isset( $this->render_context->context['postType'] ) ? (string) $this->render_context->context['postType'] : '';
			if ( $context_id > 0 && ReleasePostType::KEY === $context_type && ReleasePostType::KEY === get_post_type( $context_id ) ) {
				return $context_id;
			}
		}

		$post = get_post();
		return $post instanceof WP_Post && ReleasePostType::KEY === $post->post_type ? $post->ID : 0;
	}

	/**
	 * Build removable, validated filter chips from the public query string.
	 *
	 * @param string $archive_url Release archive URL.
	 * @return array<int, string>
	 */
	private function active_catalog_filter_chips( string $archive_url ): array {
		$args    = $this->current_catalog_query_args();
		$chips   = array();
		$filters = array(
			'mw_artist'       => __( 'هنرمند', 'music-wave-core' ),
			'mw_genre'        => __( 'سبک', 'music-wave-core' ),
			'mw_mood'         => __( 'حال‌وهوا', 'music-wave-core' ),
			'mw_release_type' => __( 'نوع انتشار', 'music-wave-core' ),
		);

		if ( isset( $args['s'] ) ) {
			$without_search = $args;
			unset( $without_search['s'] );
			$chips[] = $this->catalog_filter_chip(
				sprintf(
					/* translators: %s: current search phrase. */
					__( 'جست‌وجو: %s', 'music-wave-core' ),
					$args['s']
				),
				$without_search,
				$archive_url
			);
		}

		foreach ( $filters as $taxonomy => $label ) {
			if ( empty( $args[ $taxonomy ] ) ) {
				continue;
			}
			$term = get_term_by( 'slug', $args[ $taxonomy ], $taxonomy );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$without_filter = $args;
			unset( $without_filter[ $taxonomy ] );
			$chips[] = $this->catalog_filter_chip(
				sprintf(
					/* translators: 1: filter label such as Artist, 2: selected term name. */
					__( '%1$s: %2$s', 'music-wave-core' ),
					$label,
					$term->name
				),
				$without_filter,
				$archive_url
			);
		}

		return $chips;
	}

	/**
	 * Return only allow-listed catalog query values.
	 *
	 * @return array<string, string>
	 */
	private function current_catalog_query_args(): array {
		$args       = array();
		$taxonomies = array( 'mw_artist', 'mw_genre', 'mw_mood', 'mw_release_type' );
		foreach ( $taxonomies as $taxonomy ) {
			// Public catalog query string; sanitized at read, no nonce required for GET archive browsing.
			$value = isset( $_GET[ $taxonomy ] ) && is_scalar( $_GET[ $taxonomy ] ) ? sanitize_title( wp_unslash( (string) $_GET[ $taxonomy ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( '' !== $value ) {
				$args[ $taxonomy ] = $value;
			}
		}

		$search = isset( $_GET['s'] ) && is_scalar( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$sort_key = \ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery::SORT_QUERY_VAR;
		$sort     = isset( $_GET[ $sort_key ] ) && is_scalar( $_GET[ $sort_key ] ) ? sanitize_key( wp_unslash( (string) $_GET[ $sort_key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sort     = \ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery::normalize_sort( $sort );
		if ( 'latest' !== $sort && isset( $_GET[ $sort_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args[ $sort_key ] = $sort;
		}

		return $args;
	}

	/**
	 * Render one removable active-filter link.
	 *
	 * @param string                $label Filter label.
	 * @param array<string, string> $args Remaining query arguments.
	 * @param string                $archive_url Release archive URL.
	 * @return string
	 */
	private function catalog_filter_chip( string $label, array $args, string $archive_url ): string {
		$url = empty( $args ) ? $archive_url : add_query_arg( $args, $archive_url );

		return '<li><a href="' . esc_url( $url ) . '"><span>' . esc_html( $label ) . '</span><span class="screen-reader-text"> ' . esc_html__( 'حذف فیلتر', 'music-wave-core' ) . '</span><span aria-hidden="true">×</span></a></li>';
	}

	/**
	 * Read valid private download variants without exposing asset identifiers.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function download_assets( int $release_id ): array {
		$variants = $this->repository->get( $release_id, 'mw_download_assets' );
		$legacy   = $this->repository->get( $release_id, 'mw_download_asset_id' );
		$assets   = is_array( $variants ) ? $variants : array();
		if ( empty( $assets ) && is_string( $legacy ) && '' !== $legacy ) {
			$assets[] = array(
				'key'      => 'standard',
				'label'    => __( 'دانلود استاندارد', 'music-wave-core' ),
				'asset_id' => $legacy,
			);
		}

		$valid = array();
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['key'] ) || empty( $asset['label'] ) || empty( $asset['asset_id'] ) ) {
				continue;
			}
			$valid[] = array(
				'key'        => sanitize_key( (string) $asset['key'] ),
				'label'      => sanitize_text_field( (string) $asset['label'] ),
				'file_key'   => isset( $asset['file_key'] ) ? sanitize_key( (string) $asset['file_key'] ) : '',
				'file_label' => isset( $asset['file_label'] ) ? sanitize_text_field( (string) $asset['file_label'] ) : '',
				'streamable' => isset( $asset['format'] ) && in_array( sanitize_key( strtolower( (string) $asset['format'] ) ), array( 'mp3', 'm4a', 'aac', 'ogg', 'wav', 'flac' ), true ) ? '1' : '',
			);
		}

		return array_values(
			array_filter(
				$valid,
				static function ( array $asset ): bool {
					return '' !== $asset['key'] && '' !== $asset['label'];
				}
			)
		);
	}
}
