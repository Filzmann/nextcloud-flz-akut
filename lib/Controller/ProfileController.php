<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Controller;

use OCA\FlzAkut\AppInfo\Application;
use OCA\FlzAkut\Service\DuplicateKuerzelException;
use OCA\FlzAkut\Service\MaKuerzelService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

final class ProfileController extends Controller {
    public function __construct(IRequest $request, private IUserSession $session, private MaKuerzelService $kuerzel) { parent::__construct(Application::APP_ID, $request); }

    #[NoAdminRequired]
    public function saveKuerzel(string $kuerzel = ''): DataResponse {
        return $this->respond(function () use ($kuerzel): array {
            $user = $this->session->getUser();
            if ($user === null) throw new \DomainException('Anmeldung erforderlich.');
            return ['kuerzel' => $this->kuerzel->save($user->getUID(), $kuerzel, $user->getDisplayName())];
        });
    }

    private function respond(callable $callback): DataResponse {
        try {
            return new DataResponse(['ok' => true] + $callback());
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        } catch (DuplicateKuerzelException $e) {
            return new DataResponse(['ok' => false, 'error' => $e->getMessage(), 'suggestions' => $e->suggestions], 409);
        } catch (\DomainException $e) {
            return new DataResponse(['ok' => false, 'error' => $e->getMessage()], 403);
        } catch (\Throwable) {
            return new DataResponse(['ok' => false, 'error' => 'Profil konnte nicht gespeichert werden.'], 503);
        }
    }
}
