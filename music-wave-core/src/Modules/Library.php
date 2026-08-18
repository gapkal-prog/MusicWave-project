<?php
/**
 * Personal music library module.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Modules;

use ManaCore\MusicWave\Core\Blocks\LibraryBlocks;
use ManaCore\MusicWave\Core\Contracts\Module;
use ManaCore\MusicWave\Core\Library\LibraryButton;
use ManaCore\MusicWave\Core\Library\LibraryRepository;
use ManaCore\MusicWave\Core\Library\LibraryRoutes;

final class Library implements Module {
	/** @var LibraryRepository */
	private $repository;

	/** @var LibraryRoutes */
	private $routes;

	/** @var LibraryBlocks */
	private $blocks;

	public function __construct( LibraryRepository $repository, LibraryRoutes $routes, LibraryBlocks $blocks ) {
		$this->repository = $repository;
		$this->routes     = $routes;
		$this->blocks     = $blocks;
	}

	public function register(): void {
		LibraryButton::bind( $this->repository );
		$this->repository->register();
		$this->routes->register();
		add_action( 'init', array( $this->blocks, 'register' ), 23 );
	}
}
