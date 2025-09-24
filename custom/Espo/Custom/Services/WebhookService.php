<?php

namespace Espo\Custom\Services;

use Espo\Core\Utils\Log;

class WebhookService
{
    public function __construct(
        private readonly Log $log
    ) {}

    public function sendWebhook($data): void
    {
        try {
            // Convert stdClass to array if needed
            if ($data instanceof \stdClass) {
                $data = json_decode(json_encode($data), true);
            }
            
            if (!is_array($data)) {
                $this->log->error('WebhookService: Invalid data type provided');
                return;
            }

            $endpoint = $data['endpoint'] ?? null;
            $payload = $data['payload'] ?? [];
            $headers = $data['headers'] ?? ['Content-Type: application/json', 'Accept: application/json'];
            $method = $data['method'] ?? 'POST';
            $timeout = $data['timeout'] ?? 30;
            $connectTimeout = $data['connect_timeout'] ?? 5;
            $serviceName = $data['service_name'] ?? 'Generic';

            if (!$endpoint) {
                $this->log->error($serviceName . ' Webhook: Missing endpoint');
                return;
            }

            if (empty($payload) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
                $this->log->warning($serviceName . ' Webhook: Empty payload for ' . $method . ' request');
            }

            $this->makeHttpCall($endpoint, $payload, $headers, $method, $timeout, $connectTimeout, $serviceName);
        } catch (\Exception $e) {
            $this->log->error('WebhookService: Exception caught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        } catch (\Throwable $e) {
            $this->log->error('WebhookService: Fatal error caught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    private function makeHttpCall(
        string $endpoint,
        array $payload,
        array $headers,
        string $method,
        int $timeout,
        int $connectTimeout,
        string $serviceName,
    ): void {
        $this->log->info($serviceName . ': Starting HTTP call to ' . $endpoint . ' with method ' . $method);
        
        $ch = curl_init();
        
        $curlOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        ];

        $curlOptions = $curlOptions + $this->setMethodOptions($curlOptions, $method, $payload);

        // Set cURL options and check for errors
        if (!curl_setopt_array($ch, $curlOptions)) {
            $this->log->error($serviceName . ' Webhook: Failed to set cURL options');
            curl_close($ch);
            return;
        }

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        // Check if cURL execution failed
        if ($response === false) {
            $this->log->error($serviceName . ' Webhook: cURL execution failed. Error: ' . $curlError . ' | HTTP Code: ' . $httpCode);
            curl_close($ch);
            return;
        }
        
        // Get additional curl info for debugging
        $curlInfo = curl_getinfo($ch);

        curl_close($ch);

        // Log the result with more details
        $logContext = [
            'service' => $serviceName,
            'endpoint' => $endpoint,
            'method' => $method,
            'execution_time_ms' => $executionTime,
            'http_code' => $httpCode,
            'total_time' => $curlInfo['total_time'] ?? 0,
            'connect_time' => $curlInfo['connect_time'] ?? 0,
        ];

        if ($curlError) {
            $this->log->error($serviceName . ' Webhook cURL error: ' . $curlError, $logContext);
        } elseif ($httpCode >= 200 && $httpCode < 300) {
            $this->log->info($serviceName . ' Webhook: Success (' . $httpCode . ') in ' . $executionTime . 'ms. Response: ' . substr($response, 0, 200), $logContext);
        } elseif ($httpCode >= 400 && $httpCode < 500) {
            $this->log->warning($serviceName . ' Webhook: Client error (' . $httpCode . '). Response: ' . $response, $logContext);
        } elseif ($httpCode >= 500) {
            $this->log->error($serviceName . ' Webhook: Server error (' . $httpCode . '). Response: ' . $response, $logContext);
        } elseif ($httpCode === 0) {
            $this->log->error($serviceName . ' Webhook: Connection failed. No HTTP response received.', $logContext);
        } else {
            $this->log->warning($serviceName . ' Webhook: Unexpected response (' . $httpCode . '). Response: ' . $response, $logContext);
        }
    }

    private function setMethodOptions(array $options, string $method, array $payload): array
    {
        switch (strtoupper($method)) {
            case 'POST':
                $options[CURLOPT_POST] = true;
                if (!empty($payload)) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($payload);
                }
                return $options;
            case 'PUT':
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if (!empty($payload)) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($payload);
                }
                return $options;
            case 'PATCH':
                $options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
                if (!empty($payload)) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($payload);
                }
                return $options;
            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                return $options;
            case 'GET':
            default:
                return $options;
        }
    }

}