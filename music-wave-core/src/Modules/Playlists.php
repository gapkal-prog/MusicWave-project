<?php
/**
 * User playlists module.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Modules;

use ManaCore\MusicWave\Core\Contracts\Module;
use ManaCore\MusicWave\Core\Playlists\PlaylistRepository;
use ManaCore\MusicWave\Core\Playlists\PlaylistRoutes;

final class Playlists implements Module {
	/** @var PlaylistRepository */
	private $repository;

	/** @var PlaylistRoutes */
	private $routes;

	public function __construct( PlaylistRepository $repository, PlaylistRoutes $routes ) {
		$this->repository = $repository;
		$this->routes     = $routes;
	}

	public function register(): void {
		$this->repository->register();
		$this->routes->register();
	}
}
