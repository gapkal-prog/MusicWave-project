<?php
/**
 * Frontend release rendering module.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Modules;

use ManaCore\MusicWave\Core\Blocks\ReleaseBlocks;
use ManaCore\MusicWave\Core\Blocks\ArtistProfileBlock;
use ManaCore\MusicWave\Core\Blocks\ArtistShelfBlock;
use ManaCore\MusicWave\Core\Blocks\BlockMetadata;
use ManaCore\MusicWave\Core\Blocks\BlockSupport;
use ManaCore\MusicWave\Core\Blocks\PreviewPlayer;
use ManaCore\MusicWave\Core\Blocks\TaxonomyShelfBlock;
use ManaCore\MusicWave\Core\Blocks\TermHeroBlock;
use ManaCore\MusicWave\Core\Contracts\Module;
use ManaCore\MusicWave\Core\Infrastructure\ReleaseRestVisibilityPolicy;
use ManaCore\MusicWave\Core\Playback\PlaybackQueueRoutes;

final class Rendering implements Module {
	/** @var ReleaseBlocks */
	private $blocks;

	/** @var ArtistProfileBlock|null */
	private $artist_profile;

	/** @var ArtistShelfBlock|null */
	private $artist_shelf;

	/** @var TaxonomyShelfBlock|null */
	private $taxonomy_shelf;

	/** @var TermHeroBlock|null */
	private $term_hero;

	/** @var PreviewPlayer|null */
	private $preview_player;

	/** @var PlaybackQueueRoutes|null */
	private $playback_queue;

	/** @var ReleaseRestVisibilityPolicy|null */
	private $rest_visibility;

	public function __construct( ReleaseBlocks $blocks, ?ArtistProfileBlock $artist_profile = null, ?PreviewPlayer $preview_player = null, ?PlaybackQueueRoutes $playback_queue = null, ?ReleaseRestVisibilityPolicy $rest_visibility = null, ?ArtistShelfBlock $artist_shelf = null, ?TaxonomyShelfBlock $taxonomy_shelf = null, ?TermHeroBlock $term_hero = null ) {
		$this->blocks          = $blocks;
		$this->artist_profile  = $artist_profile;
		$this->artist_shelf    = $artist_shelf;
		$this->taxonomy_shelf  = $taxonomy_shelf;
		$this->term_hero       = $term_hero;
		$this->preview_player  = $preview_player;
		$this->playback_queue  = $playback_queue;
		$this->rest_visibility = $rest_visibility;
	}

	public function register(): void {
		add_action( 'init', array( $this->blocks, 'register' ), 20 );
		if ( null !== $this->artist_profile ) {
			add_action( 'init', array( $this->artist_profile, 'register' ), 21 );
		}
		if ( null !== $this->artist_shelf ) {
			add_action( 'init', array( $this->artist_shelf, 'register' ), 21 );
		}
		if ( null !== $this->taxonomy_shelf ) {
			add_action( 'init', array( $this->taxonomy_shelf, 'register' ), 21 );
		}
		if ( null !== $this->term_hero ) {
			add_action( 'init', array( $this->term_hero, 'register' ), 21 );
		}
		if ( null !== $this->preview_player ) {
			add_action( 'init', array( $this->preview_player, 'register' ), 22 );
		}
		if ( null !== $this->playback_queue ) {
			$this->playback_queue->register();
		}
		if ( null !== $this->rest_visibility ) {
			$this->rest_visibility->register();
		}
		add_action( 'init', array( $this, 'register_block_styles' ), 30 );
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ) );
		add_filter( 'the_content', array( $this->blocks, 'filter_content' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_blocks' ) );
	}

	/**
	 * Add the dedicated MusicWave category to the block inserter.
	 *
	 * @param mixed $categories Existing block categories.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_block_category( $categories ): array {
		$categories = is_array( $categories ) ? $categories : array();
		foreach ( $categories as $category ) {
			if ( is_array( $category ) && isset( $category['slug'] ) && 'music-wave' === $category['slug'] ) {
				return $categories;
			}
		}

		array_unshift(
			$categories,
			array(
				'slug'  => 'music-wave',
				'title' => __( 'MusicWave', 'music-wave-core' ),
				'icon'  => 'format-audio',
			)
		);

		return $categories;
	}

	/**
	 * Expose existing layout modifiers as editor style variations.
	 *
	 * The renderers translate the chosen is-style-* class into the same
	 * modifier classes produced by the inspector toggles, so both surfaces
	 * stay visually identical.
	 *
	 * @return void
	 */
	public function register_block_styles(): void {
		if ( ! function_exists( 'register_block_style' ) ) {
			return;
		}

		$styles = array(
			'music-wave/preview-player'    => array(
				array(
					'name'  => 'outline',
					'label' => __( 'Outline', 'music-wave-core' ),
				),
				array(
					'name'  => 'ghost',
					'label' => __( 'Ghost', 'music-wave-core' ),
				),
			),
			'music-wave/account-dashboard' => array(
				array(
					'name'       => 'tabs',
					'label'      => __( 'Tabs (one section at a time)', 'music-wave-core' ),
					'is_default' => true,
				),
				array(
					'name'  => 'stacked',
					'label' => __( 'Stacked (all sections visible)', 'music-wave-core' ),
				),
			),
			'music-wave/preview-button'    => array(
				array(
					'name'  => 'outline',
					'label' => __( 'Outline', 'music-wave-core' ),
				),
				array(
					'name'  => 'ghost',
					'label' => __( 'Ghost', 'music-wave-core' ),
				),
			),
			'music-wave/release-meta'      => array(
				array(
					'name'  => 'inline',
					'label' => __( 'Inline', 'music-wave-core' ),
				),
				array(
					'name'  => 'stack',
					'label' => __( 'Stacked rows', 'music-wave-core' ),
				),
			),
			'music-wave/catalog-filters'   => array(
				array(
					'name'  => 'stacked',
					'label' => __( 'Stacked', 'music-wave-core' ),
				),
			),
			'music-wave/public-playlists'  => array(
				array(
					'name'       => 'cards',
					'label'      => __( 'Cards (surface)', 'music-wave-core' ),
					'is_default' => true,
				),
				array(
					'name'  => 'minimal',
					'label' => __( 'Minimal (no surface)', 'music-wave-core' ),
				),
			),
		);

		foreach ( $styles as $block_name => $variations ) {
			foreach ( $variations as $variation ) {
				register_block_style( $block_name, $variation );
			}
		}
	}

	/**
	 * Register JavaScript editor counterparts for PHP-rendered blocks.
	 *
	 * WordPress 6.6 does not automatically make PHP-only blocks available to
	 * the browser block registry. These counterparts preserve dynamic rendering
	 * while allowing templates to open in the Site Editor without an unsupported
	 * block warning.
	 *
	 * @return void
	 */
	public function enqueue_editor_blocks(): void {
		wp_enqueue_script(
			'music-wave-dynamic-blocks',
			MUSIC_WAVE_CORE_URL . 'assets/blocks.js',
			array( 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-data', 'wp-server-side-render' ),
			MUSIC_WAVE_CORE_VERSION,
			true
		);
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'music-wave-dynamic-blocks', 'music-wave-core' );
		}
		wp_localize_script(
			'music-wave-dynamic-blocks',
			'musicWaveDynamicBlocks',
			$this->editor_blocks()
		);
		wp_enqueue_style(
			'music-wave-editor',
			MUSIC_WAVE_CORE_URL . 'assets/editor.css',
			array(),
			MUSIC_WAVE_CORE_VERSION
		);
	}

	/**
	 * Return the client registration metadata for every Core dynamic block.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function editor_blocks(): array {
		$release_attributes         = array(
			'releaseId' => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'compact'   => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
		$metadata_attributes        = array_merge(
			$release_attributes,
			array(
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
				'showReleaseType'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
			)
		);
		$release_id_attributes      = array(
			'releaseId' => array(
				'type'    => 'integer',
				'default' => 0,
			),
		);
		$access_panel_attributes    = array_merge(
			$release_id_attributes,
			array(
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
			)
		);
		$credits_attributes         = array_merge(
			$release_id_attributes,
			array(
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
			)
		);
		$collection_attributes      = array_merge(
			$release_id_attributes,
			array(
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
			)
		);
		$catalog_filter_attributes  = array(
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
		);
		$catalog_results_attributes = array(
			'showCount' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showChips' => array(
				'type'    => 'boolean',
				'default' => true,
			),
		);
		$preview_attributes         = array_merge(
			$release_id_attributes,
			array(
				'label'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'style'    => array(
					'type'    => 'string',
					'default' => 'solid',
				),
				'showIcon' => array(
					'type'    => 'boolean',
					'default' => true,
				),
			)
		);
		$download_attributes        = array_merge(
			$release_attributes,
			array(
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
			)
		);
		$related_attributes         = array_merge(
			$release_id_attributes,
			array(
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
			)
		);
		$preview_button_attributes  = array_merge(
			$preview_attributes,
			array(
				'compact'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'fullWidth' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			)
		);
		$artist_profile_attributes  = array(
			'termId'            => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'layout'            => array(
				'type'    => 'string',
				'default' => 'card',
			),
			'showImage'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showTitle'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showBio'           => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showLink'          => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showLibraryButton' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'imageSize'         => array(
				'type'    => 'string',
				'default' => 'medium',
			),
			'imageShape'        => array(
				'type'    => 'string',
				'default' => 'rounded',
			),
			'ctaLabel'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'bioLength'         => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'showReleaseCount'  => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'accentColor'       => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$supports                   = BlockSupport::appearance_tools();
		$artist_shelf_attributes    = array(
			'eyebrow'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'heading'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'description'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'showHeading'      => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'source'           => array(
				'type'    => 'string',
				'default' => 'all',
			),
			'termSlug'         => array(
				'type'    => 'string',
				'default' => '',
			),
			'artistIds'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderBy'          => array(
				'type'    => 'string',
				'default' => 'count',
			),
			'order'            => array(
				'type'    => 'string',
				'default' => 'DESC',
			),
			'itemsToShow'      => array(
				'type'    => 'integer',
				'default' => 8,
			),
			'columns'          => array(
				'type'    => 'integer',
				'default' => 4,
			),
			'layout'           => array(
				'type'    => 'string',
				'default' => 'grid',
			),
			'imageShape'       => array(
				'type'    => 'string',
				'default' => 'circle',
			),
			'imageSize'        => array(
				'type'    => 'string',
				'default' => 'medium',
			),
			'showImage'        => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showName'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showBio'          => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'showReleaseCount' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showFollowButton' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'bioLength'        => array(
				'type'    => 'integer',
				'default' => 20,
			),
			'sectionUrl'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'sectionLinkLabel' => array(
				'type'    => 'string',
				'default' => '',
			),
			'emptyMessage'     => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$taxonomy_shelf_attributes  = array(
			'taxonomy'         => array(
				'type'    => 'string',
				'default' => 'mw_genre',
			),
			'source'           => array(
				'type'    => 'string',
				'default' => 'all',
			),
			'termIds'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderBy'          => array(
				'type'    => 'string',
				'default' => 'count',
			),
			'itemsToShow'      => array(
				'type'    => 'integer',
				'default' => 8,
			),
			'columns'          => array(
				'type'    => 'integer',
				'default' => 4,
			),
			'layout'           => array(
				'type'    => 'string',
				'default' => 'grid',
			),
			'cardStyle'        => array(
				'type'    => 'string',
				'default' => 'colorful',
			),
			'showCount'        => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'eyebrow'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'heading'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'description'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'showHeading'      => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'sectionUrl'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'sectionLinkLabel' => array(
				'type'    => 'string',
				'default' => '',
			),
			'emptyMessage'     => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$term_hero_attributes       = array(
			'taxonomy'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'termId'            => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'layout'            => array(
				'type'    => 'string',
				'default' => 'banner',
			),
			'size'              => array(
				'type'    => 'string',
				'default' => 'medium',
			),
			'imageShape'        => array(
				'type'    => 'string',
				'default' => 'rounded',
			),
			'showImage'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showEyebrow'       => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showName'          => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showDescription'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showCount'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showFollowButton'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'descriptionLength' => array(
				'type'    => 'integer',
				'default' => 40,
			),
			'accentColor'       => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$queue_block_attributes     = array(
			'heading'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'showArtwork'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showPosition' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showArtist'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showControls' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showClear'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'emptyMessage' => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$queue_add_attributes       = array(
			'releaseId' => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'position'  => array(
				'type'    => 'string',
				'default' => 'next',
			),
			'label'     => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$music_library_attributes   = array(
			'heading'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'showHeading'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'intro'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'showFilters'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showCounts'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'layout'       => array(
				'type'    => 'string',
				'default' => 'list',
			),
			'columns'      => array(
				'type'    => 'integer',
				'default' => 4,
			),
			'itemsToShow'  => array(
				'type'    => 'integer',
				'default' => 24,
			),
			'showArtist'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showType'     => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showYear'     => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'showRemove'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'emptyMessage' => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$library_button_attributes  = array(
			'releaseId'  => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'itemType'   => array(
				'type'    => 'string',
				'default' => 'release',
			),
			'termId'     => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'label'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'addedLabel' => array(
				'type'    => 'string',
				'default' => '',
			),
			'style'      => array(
				'type'    => 'string',
				'default' => 'solid',
			),
			'compact'    => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);

		$editor_blocks = array(
			array(
				'name'        => 'music-wave/release-meta',
				'title'       => __( 'Release metadata', 'music-wave-core' ),
				'description' => __( 'Displays release metadata from MusicWave Core.', 'music-wave-core' ),
				'icon'        => 'list-view',
				'keywords'    => array( __( 'catalog number', 'music-wave-core' ), __( 'bpm', 'music-wave-core' ), __( 'details', 'music-wave-core' ) ),
				'attributes'  => $metadata_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/access-panel',
				'title'       => __( 'Release access panel', 'music-wave-core' ),
				'description' => __( 'Displays the current release access state and actions.', 'music-wave-core' ),
				'icon'        => 'lock',
				'keywords'    => array( __( 'purchase', 'music-wave-core' ), __( 'membership', 'music-wave-core' ), __( 'restricted', 'music-wave-core' ) ),
				'attributes'  => $access_panel_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/release-credits',
				'title'       => __( 'Release credits', 'music-wave-core' ),
				'description' => __( 'Displays release credits.', 'music-wave-core' ),
				'icon'        => 'id',
				'keywords'    => array( __( 'roles', 'music-wave-core' ), __( 'producers', 'music-wave-core' ) ),
				'attributes'  => $credits_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/collection-list',
				'title'       => __( 'Collection track list', 'music-wave-core' ),
				'description' => __( 'Displays child releases in a collection.', 'music-wave-core' ),
				'icon'        => 'playlist-audio',
				'keywords'    => array( __( 'tracks', 'music-wave-core' ), __( 'episodes', 'music-wave-core' ), __( 'discs', 'music-wave-core' ) ),
				'attributes'  => $collection_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/catalog-filters',
				'title'       => __( 'Catalog filters', 'music-wave-core' ),
				'description' => __( 'Displays release catalog filters.', 'music-wave-core' ),
				'icon'        => 'filter',
				'keywords'    => array( __( 'search', 'music-wave-core' ), __( 'archive', 'music-wave-core' ) ),
				'attributes'  => $catalog_filter_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/catalog-results',
				'title'       => __( 'Catalog results', 'music-wave-core' ),
				'description' => __( 'Displays the current catalog result summary.', 'music-wave-core' ),
				'icon'        => 'chart-bar',
				'keywords'    => array( __( 'count', 'music-wave-core' ), __( 'active filters', 'music-wave-core' ) ),
				'attributes'  => $catalog_results_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/preview-player',
				'title'       => __( 'Release preview player', 'music-wave-core' ),
				'description' => __( 'Displays an audio preview player for a release.', 'music-wave-core' ),
				'icon'        => 'controls-play',
				'keywords'    => array( __( 'audio', 'music-wave-core' ), __( 'listen', 'music-wave-core' ) ),
				'attributes'  => $preview_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/download-button',
				'title'       => __( 'Secure download', 'music-wave-core' ),
				'description' => __( 'Displays authorized download actions for a release.', 'music-wave-core' ),
				'icon'        => 'download',
				'keywords'    => array( __( 'quality', 'music-wave-core' ), __( 'stream', 'music-wave-core' ), __( 'files', 'music-wave-core' ) ),
				'attributes'  => $download_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/related-releases',
				'title'       => __( 'Related releases', 'music-wave-core' ),
				'description' => __( 'Displays related MusicWave releases.', 'music-wave-core' ),
				'icon'        => 'share',
				'keywords'    => array( __( 'similar', 'music-wave-core' ), __( 'same artist', 'music-wave-core' ), __( 'shelf', 'music-wave-core' ) ),
				'attributes'  => $related_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/artist-profile',
				'title'       => __( 'Artist profile', 'music-wave-core' ),
				'description' => __( 'Displays the artist taxonomy profile on archive pages or any other template.', 'music-wave-core' ),
				'icon'        => 'admin-users',
				'keywords'    => array( __( 'biography', 'music-wave-core' ), __( 'artist', 'music-wave-core' ) ),
				'attributes'  => $artist_profile_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/artists-shelf',
				'title'       => __( 'Artists shelf', 'music-wave-core' ),
				'description' => __( 'Displays catalog artists as a responsive grid, horizontal scroll shelf, or compact list with follow buttons and release counts.', 'music-wave-core' ),
				'icon'        => 'admin-users',
				'keywords'    => array( __( 'artists', 'music-wave-core' ), __( 'singers', 'music-wave-core' ), __( 'shelf', 'music-wave-core' ), __( 'grid', 'music-wave-core' ), __( 'follow', 'music-wave-core' ) ),
				'attributes'  => $artist_shelf_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/taxonomy-shelf',
				'title'       => __( 'Taxonomy shelf', 'music-wave-core' ),
				'description' => __( 'Displays genres, moods, or labels as tappable browse tiles with release counts, in a grid, rail, or list.', 'music-wave-core' ),
				'icon'        => 'tag',
				'keywords'    => array( __( 'genres', 'music-wave-core' ), __( 'moods', 'music-wave-core' ), __( 'labels', 'music-wave-core' ), __( 'browse', 'music-wave-core' ), __( 'categories', 'music-wave-core' ), __( 'shelf', 'music-wave-core' ) ),
				'attributes'  => $taxonomy_shelf_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/term-hero',
				'title'       => __( 'Term hero', 'music-wave-core' ),
				'description' => __( 'Archive header for artist, genre, mood, and label pages: cover banner or compact row with name, description, release count, and follow.', 'music-wave-core' ),
				'icon'        => 'format-image',
				'keywords'    => array( __( 'hero', 'music-wave-core' ), __( 'header', 'music-wave-core' ), __( 'artist', 'music-wave-core' ), __( 'genre', 'music-wave-core' ), __( 'archive', 'music-wave-core' ), __( 'banner', 'music-wave-core' ) ),
				'attributes'  => $term_hero_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/playback-queue',
				'title'       => __( 'Playback queue', 'music-wave-core' ),
				'description' => __( "The signed-in listener's durable play queue with reorder, remove, clear, shuffle, and repeat controls that work without JavaScript.", 'music-wave-core' ),
				'icon'        => 'playlist-audio',
				'keywords'    => array( __( 'queue', 'music-wave-core' ), __( 'up next', 'music-wave-core' ), __( 'player', 'music-wave-core' ), __( 'shuffle', 'music-wave-core' ), __( 'repeat', 'music-wave-core' ) ),
				'attributes'  => $queue_block_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/add-to-queue',
				'title'       => __( 'Add to queue', 'music-wave-core' ),
				'description' => __( "Adds the current or pinned release to the listener's play queue — next in line or at the end — without JavaScript.", 'music-wave-core' ),
				'icon'        => 'controls-play',
				'keywords'    => array( __( 'queue', 'music-wave-core' ), __( 'play next', 'music-wave-core' ), __( 'add', 'music-wave-core' ), __( 'player', 'music-wave-core' ) ),
				'attributes'  => $queue_add_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/account-dashboard',
				'title'       => __( 'User music dashboard', 'music-wave-core' ),
				'description' => __( 'The unified account area: music library, orders, downloads, addresses, payment methods, membership, and playlists in one navigation.', 'music-wave-core' ),
				'icon'        => 'dashboard',
				'keywords'    => array( __( 'library', 'music-wave-core' ), __( 'account', 'music-wave-core' ) ),
				'attributes'  => array(
					'showLibrary'          => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showQuickLinks'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showStats'            => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showMembershipPanel'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showOrders'           => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showDownloads'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showAddresses'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPaymentMethods'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showAccountDetails'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showSignOut'          => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'introText'            => array(
						'type'    => 'string',
						'default' => '',
					),
					'membershipHeading'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'libraryHeading'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'ordersHeading'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'downloadsHeading'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'addressesHeading'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'paymentHeading'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'accountHeading'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'playlistsHeading'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'notificationsHeading' => array(
						'type'    => 'string',
						'default' => '',
					),
					'panelOrder'           => array(
						'type'    => 'string',
						'default' => 'default',
					),
				),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/membership-panel',
				'title'       => __( 'Membership & plans', 'music-wave-core' ),
				'description' => __( "Shows the customer's active membership levels with expiry and the purchasable VIP plan products.", 'music-wave-core' ),
				'icon'        => 'awards',
				'keywords'    => array( __( 'membership', 'music-wave-core' ), __( 'vip', 'music-wave-core' ), __( 'plans', 'music-wave-core' ), __( 'subscription', 'music-wave-core' ) ),
				'attributes'  => array(
					'heading'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'showActive'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPlans'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showBuyButtons' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'emptyText'      => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/music-library',
				'title'       => __( 'Personal music library', 'music-wave-core' ),
				'description' => __( 'Displays the visitor\'s saved songs, albums, podcasts, and followed artists.', 'music-wave-core' ),
				'icon'        => 'albums',
				'keywords'    => array( __( 'favorites', 'music-wave-core' ), __( 'saved', 'music-wave-core' ), __( 'collection', 'music-wave-core' ) ),
				'attributes'  => $music_library_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/library-button',
				'title'       => __( 'Add to library button', 'music-wave-core' ),
				'description' => __( 'Lets visitors save a release, wishlist it, pre-save an upcoming release, or follow an artist.', 'music-wave-core' ),
				'icon'        => 'plus-alt',
				'keywords'    => array( __( 'save', 'music-wave-core' ), __( 'follow', 'music-wave-core' ), __( 'favorite', 'music-wave-core' ) ),
				'attributes'  => $library_button_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/playlists',
				'title'       => __( 'Playlists', 'music-wave-core' ),
				'description' => __( 'Lets signed-in listeners create, order, share, and delete their playlists.', 'music-wave-core' ),
				'icon'        => 'playlist-audio',
				'keywords'    => array( __( 'playlist', 'music-wave-core' ), __( 'queue', 'music-wave-core' ), __( 'collection', 'music-wave-core' ) ),
				'attributes'  => array(
					'heading' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/add-to-playlist',
				'title'       => __( 'Add to playlist', 'music-wave-core' ),
				'description' => __( 'Adds the current release to one of the listener\'s playlists.', 'music-wave-core' ),
				'icon'        => 'plus-alt',
				'keywords'    => array( __( 'playlist', 'music-wave-core' ), __( 'save', 'music-wave-core' ), __( 'queue', 'music-wave-core' ) ),
				'attributes'  => array(
					'releaseId' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'label'     => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/public-playlists',
				'title'       => __( 'Public playlists', 'music-wave-core' ),
				'description' => __( 'Browse and play community public playlists with search and pagination.', 'music-wave-core' ),
				'icon'        => 'groups',
				'keywords'    => array( __( 'playlist', 'music-wave-core' ), __( 'community', 'music-wave-core' ), __( 'public', 'music-wave-core' ) ),
				'attributes'  => array(
					'eyebrow'           => array(
						'type'    => 'string',
						'default' => '',
					),
					'heading'           => array(
						'type'    => 'string',
						'default' => '',
					),
					'showHeading'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'intro'             => array(
						'type'    => 'string',
						'default' => '',
					),
					'itemsToShow'       => array(
						'type'    => 'integer',
						'default' => 12,
					),
					'columns'           => array(
						'type'    => 'integer',
						'default' => 3,
					),
					'layout'            => array(
						'type'    => 'string',
						'default' => 'grid',
					),
					'orderby'           => array(
						'type'    => 'string',
						'default' => 'updated_at',
					),
					'imageShape'        => array(
						'type'    => 'string',
						'default' => 'square',
					),
					'showSearch'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showCount'         => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'searchPlaceholder' => array(
						'type'    => 'string',
						'default' => '',
					),
					'emptyMessage'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'showArt'           => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showAuthor'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showUpdated'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPlayButton'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showToggle'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPagination'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'sectionUrl'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'sectionLinkLabel'  => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/continue-listening',
				'title'       => __( 'Continue listening', 'music-wave-core' ),
				'description' => __( 'Shows each listener their recently played and in-progress releases, with a consent-first opt-in for visitors who have not enabled listening history.', 'music-wave-core' ),
				'icon'        => 'controls-back',
				'keywords'    => array( __( 'continue', 'music-wave-core' ), __( 'recently played', 'music-wave-core' ), __( 'history', 'music-wave-core' ), __( 'resume', 'music-wave-core' ), __( 'listening', 'music-wave-core' ) ),
				'attributes'  => array(
					'heading'            => array(
						'type'    => 'string',
						'default' => '',
					),
					'showHeading'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'intro'              => array(
						'type'    => 'string',
						'default' => '',
					),
					'source'             => array(
						'type'    => 'string',
						'default' => 'continue',
					),
					'itemsToShow'        => array(
						'type'    => 'integer',
						'default' => 8,
					),
					'columns'            => array(
						'type'    => 'integer',
						'default' => 4,
					),
					'layout'             => array(
						'type'    => 'string',
						'default' => 'scroll',
					),
					'imageShape'         => array(
						'type'    => 'string',
						'default' => 'square',
					),
					'showArtwork'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showArtist'         => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showWhen'           => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPreview'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'sectionUrl'         => array(
						'type'    => 'string',
						'default' => '',
					),
					'sectionLinkLabel'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'emptyMessage'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'guestMessage'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'consentMessage'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'consentButtonLabel' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/preview-button',
				'title'       => __( 'Preview button', 'music-wave-core' ),
				'description' => __( 'Displays a release audio preview button.', 'music-wave-core' ),
				'icon'        => 'controls-play',
				'keywords'    => array( __( 'play', 'music-wave-core' ), __( 'listen', 'music-wave-core' ) ),
				'attributes'  => $preview_button_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
		);

		// Enrich the hand-rolled editor registry with block.json metadata that
		// must stay defined in exactly one place: the metadata file itself.
		foreach ( $editor_blocks as $index => $editor_block ) {
			$metadata = BlockMetadata::load( (string) $editor_block['name'] );
			if ( ! is_array( $metadata ) ) {
				continue;
			}
			if ( isset( $metadata['category'] ) ) {
				$editor_blocks[ $index ]['category'] = (string) $metadata['category'];
			}
			if ( isset( $metadata['example'] ) && is_array( $metadata['example'] ) ) {
				$editor_blocks[ $index ]['example'] = $metadata['example'];
			}
		}

		return $editor_blocks;
	}
}
