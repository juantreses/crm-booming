<?php

namespace Espo\Modules\ClientManager\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\ClientManager\Services\ContactHomService;

readonly class ContactHomApi
{
    public function __construct(
        private ContactHomService $contactHomService,
    ) {}

    public function postActionBook(Request $request): array
    {
        try {
            return $this->contactHomService->book($request->getParsedBody());
        } catch (Conflict $e) {
            throw new BadRequest("Helaas, dit tijdstip is zojuist volgeboekt. Kies een ander moment.");
        } catch (BadRequest | Forbidden | NotFound $e) {
            throw $e;
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('Contact HOM Book Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw new BadRequest('HOM boeken mislukt: ' . $e->getMessage());
        }
    }

    public function postActionLogRoyalty(Request $request): array
    {
        try {
            return $this->contactHomService->logRoyalty($request->getParsedBody());
        } catch (Conflict $e) {
            throw new BadRequest("Helaas, dit tijdstip is zojuist volgeboekt. Kies een ander moment.");
        } catch (BadRequest | Forbidden | NotFound $e) {
            throw $e;
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('Contact RI Log Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw new BadRequest('RI loggen mislukt: ' . $e->getMessage());
        }
    }

    public function postActionLog(Request $request): array
    {
        try {
            return $this->contactHomService->log($request->getParsedBody());
        } catch (Conflict $e) {
            throw new BadRequest("Helaas, dit tijdstip is zojuist volgeboekt. Kies een ander moment.");
        } catch (BadRequest | Forbidden | NotFound $e) {
            throw $e;
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('Contact HOM Log Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw new BadRequest('HOM loggen mislukt: ' . $e->getMessage());
        }
    }
}
