<?php

namespace Espo\Custom\EntryPoints;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\EntryPoint\EntryPoint;
use Espo\Core\EntryPoint\Traits\NoAuth;

class Widget implements EntryPoint
{
    use NoAuth;

    public function run(Request $request, Response $response): void
    {
        $type = $request->getQueryParam('type');
        
        $allowedTypes = ['survey', 'referral', 'calendar', 'direct', 'voucher'];
        
        if (!in_array($type, $allowedTypes)) {
            $response->writeBody("Fout: Ongeldig widget type.");
            $response->setStatus(400);
            return;
        }

        // We centraliseren de bestanden in de module resources
        $file = "custom/Espo/Custom/Resources/layouts/EntryPoints/{$type}.html";

        if (file_exists($file)) {
            $html = file_get_contents($file);
            $response->writeBody($html);
            $response->setHeader('Content-Type', 'text/html');
        } else {
            $response->writeBody("Fout: Layout niet gevonden.");
            $response->setStatus(404);
        }
    }
}