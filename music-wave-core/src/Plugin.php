<?php
/**
 * Main plugin composition root.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core;

use ManaCore\MusicWave\Core\Access\AccessPolicyEngine;
use ManaCore\MusicWave\Core\Access\ManualAccessProvider;
use ManaCore\MusicWave\Core\Access\MembershipProvider;
use ManaCore\MusicWave\Core\Access\NullManualAccessProvider;
use ManaCore\MusicWave\Core\Access\NullMembershipProvider;
use ManaCore\MusicWave\Core\Admin\ProductReleasePanel;
use ManaCore\MusicWave\Core\Admin\EditorAssets;
use ManaCore\MusicWave\Core\Admin\DiagnosticsPage;
use ManaCore\MusicWave\Core\Blocks\ReleaseBlocks;
use ManaCore\MusicWave\Core\Blocks\ArtistProfileBlock;
use ManaCore\MusicWave\Core\Blocks\LibraryBlocks;
use ManaCore\MusicWave\Core\Blocks\PreviewPlayer;
use ManaCore\MusicWave\Core\Admin\ReleaseMetaBox;
use ManaCore\MusicWave\Core\Admin\ReleaseReadiness;
use ManaCore\MusicWave\Core\Admin\CollectionCandidateRoutes;
use ManaCore\MusicWave\Core\Admin\BulkAccessManager;
use ManaCore\MusicWave\Core\Admin\SettingsPage;
use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Catalog\ReleaseTaxonomies;
use ManaCore\MusicWave\Core\Catalog\ReleaseVisibility;
use ManaCore\MusicWave\Core\Catalog\ArtistTermMeta;
use ManaCore\MusicWave\Core\Catalog\DemoContentImporter;
use ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery;
use ManaCore\MusicWave\Core\Catalog\ReleaseDefaults;
use ManaCore\MusicWave\Core\Commerce\ProductMapper;
use ManaCore\MusicWave\Core\Commerce\PurchaseChecker;
use ManaCore\MusicWave\Core\Commerce\AccountLibrary;
use ManaCore\MusicWave\Core\Infrastructure\WordPressReleaseRepository;
use ManaCore\MusicWave\Core\Infrastructure\CollectionRestPolicy;
use ManaCore\MusicWave\Core\Infrastructure\ReleaseRestVisibilityPolicy;
use ManaCore\MusicWave\Core\Library\LibraryCatalog;
use ManaCore\MusicWave\Core\Library\LibraryRepository;
use ManaCore\MusicWave\Core\Library\LibraryRoutes;
use ManaCore\MusicWave\Core\Downloads\DownloadResolver;
use ManaCore\MusicWave\Core\Downloads\DownloadRoutes;
use ManaCore\MusicWave\Core\Downloads\DownloadAssetRoutes;
use ManaCore\MusicWave\Core\Downloads\DownloadTokenService;
use ManaCore\MusicWave\Core\Downloads\NullDownloadProvider;
use ManaCore\MusicWave\Core\Downloads\TransientReplayStore;
use ManaCore\MusicWave\Core\Migrations\MigrationRunner;
use ManaCore\MusicWave\Core\Migrations\Schema020;
use ManaCore\MusicWave\Core\Migrations\Schema030;
use ManaCore\MusicWave\Core\Migrations\Schema040;
use ManaCore\MusicWave\Core\Migrations\Schema050;
use ManaCore\MusicWave\Core\Migrations\Schema070;
use ManaCore\MusicWave\Core\Migrations\Schema080;
use ManaCore\MusicWave\Core\Metadata\DiscogsProvider;
use ManaCore\MusicWave\Core\Metadata\MetadataLookupRoutes;
use ManaCore\MusicWave\Core\Metadata\MetadataResolver;
use ManaCore\MusicWave\Core\Metadata\MetadataTaxonomyMapper;
use ManaCore\MusicWave\Core\Metadata\MusicBrainzProvider;
use ManaCore\MusicWave\Core\Metadata\SpotifyProvider;
use ManaCore\MusicWave\Core\Modules\Admin;
use ManaCore\MusicWave\Core\Modules\Catalog;
use ManaCore\MusicWave\Core\Modules\Commerce;
use ManaCore\MusicWave\Core\Modules\Foundation;
use ManaCore\MusicWave\Core\Modules\Downloads;
use ManaCore\MusicWave\Core\Modules\Diagnostics;
use ManaCore\MusicWave\Core\Modules\Library;
use ManaCore\MusicWave\Core\Modules\Rendering;
use ManaCore\MusicWave\Core\Modules\Seo;
use ManaCore\MusicWave\Core\Playback\PlaybackQueueRoutes;
use ManaCore\MusicWave\Core\Schema\ReleaseMetaRegistry;
use ManaCore\MusicWave\Core\Schema\ReleaseMetaSchema;
use ManaCore\MusicWave\Core\Seo\ReleaseJsonLd;
use ManaCore\MusicWave\Core\Seo\ReleaseMetadata;
use ManaCore\MusicWave\Core\Support\ModuleRegistry;
use ManaCore\MusicWave\Core\Support\SiteHealth;

final class Plugin {
	/** @var self|null */
	private static $instance;

	/** @var bool */
	private $booted = false;

	/**
	 * Get the single composition root instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Compose and register all plugin modules once.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$registry            = new ModuleRegistry();
		$schema              = new ReleaseMetaSchema();
		$releases            = new WordPressReleaseRepository( $schema );
		$visibility          = new ReleaseVisibility();
		$mapper              = new ProductMapper();
		$purchase_checker    = new PurchaseChecker( $releases );
		$membership_provider = apply_filters( 'music_wave_membership_provider', new NullMembershipProvider() );
		$manual_provider     = apply_filters( 'music_wave_manual_access_provider', new NullManualAccessProvider() );
		if ( ! $membership_provider instanceof MembershipProvider ) {
			$membership_provider = new NullMembershipProvider();
		}
		if ( ! $manual_provider instanceof ManualAccessProvider ) {
			$manual_provider = new NullManualAccessProvider();
		}
		$policy            = new AccessPolicyEngine( $releases, $purchase_checker, $membership_provider, $manual_provider );
		$download_provider = apply_filters( 'music_wave_download_provider', new NullDownloadProvider() );
		if ( ! $download_provider instanceof \ManaCore\MusicWave\Core\Downloads\DownloadProvider ) {
			$download_provider = new NullDownloadProvider();
		}
		$download_secret = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : hash( 'sha256', MUSIC_WAVE_CORE_FILE );
		$downloads       = new DownloadResolver( $releases, $policy, new DownloadTokenService( $download_secret ), new TransientReplayStore(), $download_provider );

		$metadata_resolver   = new MetadataResolver(
			array(
				new SpotifyProvider( (string) \ManaCore\MusicWave\Core\Support\Settings::get( 'spotify_client_id' ), (string) \ManaCore\MusicWave\Core\Support\Settings::get( 'spotify_client_secret' ) ),
				new DiscogsProvider( (string) \ManaCore\MusicWave\Core\Support\Settings::get( 'discogs_token' ), (string) \ManaCore\MusicWave\Core\Support\Settings::get( 'discogs_secret' ) ),
				new MusicBrainzProvider(),
			)
		);
		$metadata_taxonomies = new MetadataTaxonomyMapper();

		$library_repository = new LibraryRepository( $visibility );
		$library_catalog    = new LibraryCatalog( $library_repository, $visibility );

		$registry->add( new Foundation() );
		$registry->add(
			new Catalog(
				new ReleasePostType(),
				new ReleaseTaxonomies(),
				new ReleaseMetaRegistry( $schema ),
				new MigrationRunner( array( new Schema020(), new Schema030(), new Schema040(), new Schema050(), new Schema070(), new Schema080() ) ),
				new CollectionRestPolicy( $releases ),
				new ArtistTermMeta(),
				new ReleaseArchiveQuery(),
				new ReleaseDefaults( $releases )
			)
		);
		$registry->add( new Admin( new ReleaseMetaBox( $schema, $releases, $mapper ), new EditorAssets(), new ReleaseReadiness( $releases ), new CollectionCandidateRoutes(), new SettingsPage( new BulkAccessManager( $releases ) ) ) );
		$registry->add( new Commerce( $mapper, new ProductReleasePanel( $mapper ), $purchase_checker, new AccountLibrary( $policy, $releases, $library_repository ) ) );
		$registry->add( new Library( $library_repository, new LibraryRoutes( $library_repository, $library_catalog ), new LibraryBlocks( $library_repository, $library_catalog ) ) );
		$registry->add( new Rendering( new ReleaseBlocks( $policy, $releases ), new ArtistProfileBlock(), new PreviewPlayer( $releases, $policy ), new PlaybackQueueRoutes( $policy, $releases ), new ReleaseRestVisibilityPolicy( $policy ) ) );
		$registry->add( new Downloads( new DownloadRoutes( $downloads ), new DownloadAssetRoutes( $releases ) ) );
		$registry->add( new Diagnostics( new DiagnosticsPage( new DemoContentImporter( $releases ) ), new SiteHealth() ) );
		$registry->add( new Seo( new ReleaseJsonLd( $releases, $visibility ), new ReleaseMetadata( $releases ) ) );
		$registry->add( new \ManaCore\MusicWave\Core\Modules\Metadata( new MetadataLookupRoutes( $metadata_resolver, $metadata_taxonomies ), $metadata_taxonomies ) );

		/**
		 * Filter additional MusicWave Core modules.
		 *
		 * @param array $modules Module instances implementing the Module contract.
		 */
		$modules = apply_filters( 'music_wave_core_modules', array() );
		$registry->add_filtered( $modules );
		$registry->register_all();

		$this->booted = true;
		do_action( 'music_wave_core_loaded', $this );
	}

	private function __construct() {}
}
