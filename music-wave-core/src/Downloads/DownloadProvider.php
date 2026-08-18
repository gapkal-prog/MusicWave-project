<?php
declare(strict_types=1);
namespace ManaCore\MusicWave\Core\Downloads;

interface DownloadProvider {
	/** Provider must stream the private asset and terminate the request on success. */
	public function deliver( string $asset_id, DownloadTokenClaims $claims ): bool;
}
