<?php

namespace Espo\Custom\EntryPoints;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\EntryPoint\EntryPoint;
use Espo\Core\EntryPoint\Traits\NoAuth;
use Slim\Psr7\Factory\ResponseFactory;

class Enquete implements EntryPoint
{
    use NoAuth;

    public function __construct(
        private readonly ResponseFactory $responseFactory,
    )
    {}

    /**
     * @inheritDoc
     */
    public function run(Request $request, Response $response): void
    {
        $file = 'custom/Espo/Custom/Resources/layouts/Entrypoints/enquete.html';

        if (file_exists($file)) {
            $html = file_get_contents($file);
            $response->writeBody($html);
            $response->setHeader('Content-Type', 'text/html');
        } else {
            $response->writeBody("Enquête layout niet gevonden.");
            $response->setStatus(404, 'Not Found');
        }
    }
}