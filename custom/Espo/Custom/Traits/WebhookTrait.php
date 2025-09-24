<?php

namespace Espo\Custom\Traits;

use Espo\ORM\EntityManager;
use Espo\Core\Utils\Log;

trait WebhookTrait
{
    protected function sendWebhookSync(
        string $endpoint,
        array $payload,
        string $serviceName = 'Generic',
        string $method = 'POST',
        array $headers = ['Content-Type: application/json', 'Accept: application/json'],
        int $timeout = 30,
        int $connectTimeout = 5
    ): bool {
        try {
            if (!$endpoint) {
                $this->getLog()->warning($serviceName . ': Webhook endpoint is empty');
                return false;
            }

            $this->getLog()->info($serviceName . ': Sending webhook synchronously to ' . $endpoint);

            // Create WebhookService directly
            $webhookService = new \Espo\Custom\Services\WebhookService($this->getLog());
            
            $webhookService->sendWebhook([
                'endpoint' => $endpoint,
                'payload' => $payload,
                'service_name' => $serviceName,
                'method' => $method,
                'headers' => $headers,
                'timeout' => $timeout,
                'connect_timeout' => $connectTimeout,
            ]);

            return true;
            
        } catch (\Exception $e) {
            $this->getLog()->error($serviceName . ': Failed to send webhook synchronously: ' . $e->getMessage());
            return false;
        }
    }

    abstract protected function getEntityManager(): EntityManager;
    abstract protected function getLog(): Log;
}