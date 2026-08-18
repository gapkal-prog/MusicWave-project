<?php
/**
 * Optional protected-audio streaming contract.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Downloads;

interface StreamableDownloadProvider extends DownloadProvider {
	/**
	 * Stream an entitled asset inline with HTTP range support.
	 */
	public function stream( string $asset_id, DownloadTokenClaims $claims ): bool;
}
