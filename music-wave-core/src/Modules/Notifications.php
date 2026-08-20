<?php
/**
 * Follow notification module.
 *
 * Wires the opt-in notification surfaces into WordPress: the publish/pre-save
 * listener that queues messages, the signed one-click unsubscribe route, and
 * the account panel that lets a listener manage every channel without
 * JavaScript (PROJECT_PLAN.md Stage 6 deliverable 2).
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Modules;

use ManaCore\MusicWave\Core\Contracts\Module;
use ManaCore\MusicWave\Core\Notifications\FollowNotifier;
use ManaCore\MusicWave\Core\Notifications\NotificationSettings;

final class Notifications implements Module {
	/** @var FollowNotifier */
	private $notifier;

	/** @var NotificationSettings|null */
	private $settings;

	public function __construct( FollowNotifier $notifier, ?NotificationSettings $settings = null ) {
		$this->notifier = $notifier;
		$this->settings = $settings;
	}

	public function register(): void {
		$this->notifier->register();
		if ( null !== $this->settings ) {
			$this->settings->register();
		}
	}
}
