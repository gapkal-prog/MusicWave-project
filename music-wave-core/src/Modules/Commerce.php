<?php
/**
 * Optional WooCommerce integration module.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Modules;

use ManaCore\MusicWave\Core\Admin\ProductReleasePanel;
use ManaCore\MusicWave\Core\Commerce\ProductMapper;
use ManaCore\MusicWave\Core\Commerce\PurchaseChecker;
use ManaCore\MusicWave\Core\Commerce\AccountLibrary;
use ManaCore\MusicWave\Core\Contracts\Module;

final class Commerce implements Module {
	/** @var ProductMapper */
	private $mapper;

	/** @var ProductReleasePanel */
	private $panel;

	/** @var PurchaseChecker */
	private $purchase_checker;

	/** @var AccountLibrary|null */
	private $account_library;

	public function __construct( ProductMapper $mapper, ProductReleasePanel $panel, PurchaseChecker $purchase_checker, ?AccountLibrary $account_library = null ) {
		$this->mapper           = $mapper;
		$this->panel            = $panel;
		$this->purchase_checker = $purchase_checker;
		$this->account_library  = $account_library;
	}

	public function register(): void {
		add_action( 'before_delete_post', array( $this->mapper, 'handle_deleted_post' ) );
		add_action( 'add_meta_boxes_product', array( $this->panel, 'register' ) );
		add_filter( 'music_wave_user_owns_release', array( $this, 'filter_ownership' ), 10, 3 );
		if ( null !== $this->account_library ) {
			$this->account_library->register();
		}
	}

	public function filter_ownership( bool $owns, int $user_id, int $release_id ): bool {
		return $owns || $this->purchase_checker->user_owns_release( $user_id, $release_id );
	}
}
