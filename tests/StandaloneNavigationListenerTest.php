<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    class Event {}
    interface IEventListener { public function handle(Event $event): void; }
}
namespace OCP\Navigation\Events {
    class LoadAdditionalEntriesEvent extends \OCP\EventDispatcher\Event {}
}
namespace OCP {
    interface IUser {}
    interface IUserSession { public function getUser(): ?IUser; }
    interface IURLGenerator {
        public function linkToRoute(string $routeName, array $arguments = []): string;
        public function imagePath(string $appName, string $file): string;
    }
    interface INavigationManager {
        public const TYPE_APPS = 'link';
        public function add(callable $entry): void;
    }
}
namespace OCP\App {
    interface IAppManager { public function isEnabledForUser($appId, $user = null); }
}

namespace {
    use OCA\FlzAkut\Listener\StandaloneNavigationListener;
    use OCA\FlzAkut\Tests\Support\FakeL10N;
    use OCA\LocalBase\Catalog\FlzProductCatalog;
    use OCA\LocalBase\Service\StandaloneAppNavigationService;
    use OCP\App\IAppManager;
    use OCP\EventDispatcher\Event;
    use OCP\INavigationManager;
    use OCP\IURLGenerator;
    use OCP\IUser;
    use OCP\IUserSession;
    use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

    require_once __DIR__ . '/bootstrap.php';

    $user = new class implements IUser {};
    $session = new class($user) implements IUserSession {
        public function __construct(private ?IUser $user) {}
        public function getUser(): ?IUser { return $this->user; }
    };
    $apps = new class implements IAppManager {
        public bool $suiteEnabled = false;
        public function isEnabledForUser($appId, $user = null): bool {
            return $appId === 'orgsuite' && $this->suiteEnabled;
        }
    };
    $navigation = new class implements INavigationManager {
        public array $entries = [];
        public function add(callable $entry): void { $this->entries[] = $entry; }
    };
    $url = new class implements IURLGenerator {
        public function linkToRoute(string $routeName, array $arguments = []): string {
            return '/route/' . $routeName;
        }
        public function imagePath(string $appName, string $file): string {
            return '/image/' . $appName . '/' . $file;
        }
    };

    $listener = new StandaloneNavigationListener(new StandaloneAppNavigationService(
        $session,
        $apps,
        $navigation,
        $url,
        new FlzProductCatalog(),
    ), new FakeL10N());
    $listener->handle(new Event());
    if ($navigation->entries !== []) {
        throw new RuntimeException('Ein fremdes Event registriert Akut-Navigation.');
    }

    $listener->handle(new LoadAdditionalEntriesEvent());
    $entry = ($navigation->entries[0] ?? static fn(): array => [])();
    if (($entry['id'] ?? null) !== 'flz_akut'
        || ($entry['href'] ?? null) !== '/route/flz_akut.page.index'
        || ($entry['icon'] ?? null) !== '/image/flz_akut/app.svg'
        || ($entry['order'] ?? null) !== 86) {
        throw new RuntimeException('Akut erhält keinen katalogisierten Standalone-Einstieg.');
    }

    $apps->suiteEnabled = true;
    $listener->handle(new LoadAdditionalEntriesEvent());
    if (count($navigation->entries) !== 1) {
        throw new RuntimeException('Aktive OrgSuite unterdrückt den Akut-Standalone-Einstieg nicht.');
    }

    $info = (string)file_get_contents(dirname(__DIR__) . '/appinfo/info.xml');
    if (str_contains($info, '<navigations>')) {
        throw new RuntimeException('Statische Navigation würde den dynamischen Standalone-Vertrag duplizieren.');
    }

    echo "flz_akut standalone navigation tests passed\n";
}
