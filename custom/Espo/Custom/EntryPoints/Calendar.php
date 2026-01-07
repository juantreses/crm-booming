<?php

namespace Espo\Custom\EntryPoints;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\EntryPoint\EntryPoint;
use Espo\Core\EntryPoint\Traits\NoAuth;
use Espo\Modules\Calendar\Services\CalendarService;

class Calendar implements EntryPoint
{
    use NoAuth;

    public function __construct(
        private readonly CalendarService $calendarSlotService,
    )
    {}

    public function run(Request $request, Response $response): void
    {
        $file = 'custom/Espo/Custom/Resources/layouts/EntryPoints/calendar.html';

        if (file_exists($file)) {
            $html = file_get_contents($file);

            // We schrijven de HTML naar de body
            $response->writeBody($html);
            $response->setHeader('Content-Type', 'text/html');
        } else {
            // Foutafhandeling als het bestand ontbreekt
            $response->writeBody("Fout: Calendar layout bestand niet gevonden op pad: " . $file);
            $response->setStatus(404);
        }
    }
}