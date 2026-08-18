<?php
/**
 * Public search metadata module.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Modules;

use ManaCore\MusicWave\Core\Contracts\Module;
use ManaCore\MusicWave\Core\Seo\ReleaseJsonLd;
use ManaCore\MusicWave\Core\Seo\ReleaseMetadata;

final class Seo implements Module {
	/** @var ReleaseJsonLd */
	private $json_ld;
	/** @var ReleaseMetadata */
	private $metadata;

	public function __construct( ReleaseJsonLd $json_ld, ReleaseMetadata $metadata ) {
		$this->json_ld  = $json_ld;
		$this->metadata = $metadata;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		$this->json_ld->register();
		$this->metadata->register();
	}
}
