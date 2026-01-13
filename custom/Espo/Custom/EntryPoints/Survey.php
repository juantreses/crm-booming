<?php

namespace Espo\Custom\EntryPoints;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\EntryPoint\EntryPoint;
use Espo\Core\EntryPoint\Traits\NoAuth;

class Survey implements EntryPoint
{
    use NoAuth;

    public function run(Request $request, Response $response): void
    {
        $file = 'custom/Espo/Custom/Resources/layouts/EntryPoints/survey.html';

        if (file_exists($file)) {
            $html = file_get_contents($file);

            // We schrijven de HTML naar de body
            $response->writeBody($html);
            $response->setHeader('Content-Type', 'text/html');
        } else {
            // Foutafhandeling als het bestand ontbreekt
            $response->writeBody("Fout: Survey layout bestand niet gevonden op pad: " . $file);
            $response->setStatus(404);
        }
    }
}