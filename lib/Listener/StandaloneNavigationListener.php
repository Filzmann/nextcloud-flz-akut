<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Listener;

use OCA\LocalBase\Service\StandaloneAppNavigationService;
use OCP\EventDispatcher\Event;
use OCP\IL10N;
use OCP\EventDispatcher\IEventListener;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

/** @template-implements IEventListener<LoadAdditionalEntriesEvent> */
final class StandaloneNavigationListener implements IEventListener {
    public function __construct(private StandaloneAppNavigationService $navigation, private IL10N $l10n) {}

    public function handle(Event $event): void {
        if (!$event instanceof LoadAdditionalEntriesEvent) {
            return;
        }

        $this->navigation->addCatalogProductWhenStandalone(
            'flz_akut',
            $this->l10n->t('Akutschichtvermittlung'),
            'app.svg',
        );
    }
}
