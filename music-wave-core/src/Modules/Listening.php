<?php
/**
 * Listening and discovery module: consented history, durable queue,
 * explainable recommendations, retention pruning.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Modules;

use ManaCore\MusicWave\Core\Contracts\Module;
use ManaCore\MusicWave\Core\Discovery\DiscoveryRoutes;
use ManaCore\MusicWave\Core\Listening\ListeningRepository;
use ManaCore\MusicWave\Core\Listening\ListeningRoutes;

final class Listening implements Module {
	/** @var ListeningRepository */
	private $repository;

	/** @var ListeningRoutes */
	private $routes;

	/** @var DiscoveryRoutes */
	private $discovery;

	public function __construct( ListeningRepository $repository, ListeningRoutes $routes, DiscoveryRoutes $discovery ) {
		$this->repository = $repository;
		$this->routes     = $routes;
		$this->discovery  = $discovery;
	}

	public function register(): void {
		$this->routes->register();
		$this->discovery->register();
		// Reuse the daily hygiene event so listening rows honor retention.
		add_action( Downloads::CLEANUP_EVENT, array( $this->repository, 'prune' ) );
	}
}
