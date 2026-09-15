<?php

namespace Espo\Custom\EntryPoints;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Authentication\AuthToken\Manager as AuthTokenManager;
use Espo\Core\EntryPoint\EntryPoint;
use Espo\Core\EntryPoint\Traits\NoAuth;
use Espo\Core\Exceptions\NotFound;
use Espo\Entities\User;
use Espo\Modules\Calendar\Services\PublicCalendarAccess;
use Espo\ORM\EntityManager;

class Widget implements EntryPoint
{
    use NoAuth;

    private const NOT_FOUND_LAYOUT = 'custom/Espo/Custom/Resources/layouts/EntryPoints/not-found.html';

    public function __construct(
        private PublicCalendarAccess $publicCalendarAccess,
        private AuthTokenManager $authTokenManager,
        private EntityManager $entityManager,
    ) {}

    public function run(Request $request, Response $response): void
    {
        $type = $request->getQueryParam('type');

        $allowedTypes = ['survey', 'referral', 'calendar', 'direct', 'voucher'];

        if (!in_array($type, $allowedTypes)) {
            $response->writeBody("Fout: Ongeldig widget type.");
            $response->setStatus(400);
            return;
        }

        if ($type === 'calendar') {
            $calendarIdentifier = $request->getQueryParam('id');

            if (!$calendarIdentifier) {
                $this->serveLayout(self::NOT_FOUND_LAYOUT, $response, 404);
                return;
            }

            try {
                $this->publicCalendarAccess->resolveAccessible(
                    $calendarIdentifier,
                    $this->hasAuthenticatedCrmUser($request)
                );
            } catch (NotFound) {
                $this->serveLayout(self::NOT_FOUND_LAYOUT, $response, 404);
                return;
            }
        }

        $file = "custom/Espo/Custom/Resources/layouts/EntryPoints/{$type}.html";

        if (file_exists($file)) {
            $this->serveLayout($file, $response, 200);
        } else {
            $response->writeBody("Fout: Layout niet gevonden.");
            $response->setStatus(404);
        }
    }

    private function serveLayout(string $file, Response $response, int $status): void
    {
        $html = file_get_contents($file);
        $response->writeBody($html);
        $response->setHeader('Content-Type', 'text/html');
        $response->setStatus($status);
    }

    private function hasAuthenticatedCrmUser(Request $request): bool
    {
        $token = $request->getCookieParam('auth-token');

        if (!$token) {
            return false;
        }

        $authToken = $this->authTokenManager->get($token);

        if (!$authToken || !$authToken->isActive() || $authToken->getPortalId()) {
            return false;
        }

        if ($authToken->getSecret() && $request->getCookieParam('auth-token-secret') !== $authToken->getSecret()) {
            return false;
        }

        /** @var ?User $user */
        $user = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->getById($authToken->getUserId());

        if (!$user || !$user->isActive() || $user->isSystem() || $user->isPortal() || $user->isApi()) {
            return false;
        }

        return $user->isRegular() || $user->isAdmin();
    }
}
