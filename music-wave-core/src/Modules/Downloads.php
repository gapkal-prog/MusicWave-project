<?php
declare(strict_types=1);
namespace ManaCore\MusicWave\Core\Modules;

use ManaCore\MusicWave\Core\Contracts\Module;
use ManaCore\MusicWave\Core\Downloads\DownloadRoutes;
use ManaCore\MusicWave\Core\Downloads\DownloadAssetRoutes;
final class Downloads implements Module {
	/** @var DownloadRoutes */ private $routes;
	/** @var DownloadAssetRoutes */ private $asset_routes;
	public function __construct( DownloadRoutes $routes, DownloadAssetRoutes $asset_routes ) {
		$this->routes       = $routes;
		$this->asset_routes = $asset_routes; }
	public function register(): void {
		$this->routes->register();
		$this->asset_routes->register(); }
}
