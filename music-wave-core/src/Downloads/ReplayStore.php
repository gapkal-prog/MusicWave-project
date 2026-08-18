<?php
declare(strict_types=1);
namespace ManaCore\MusicWave\Core\Downloads;

interface ReplayStore {
	public function consume( string $token_id, int $expires_at ): bool;
}
