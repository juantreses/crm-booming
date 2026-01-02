<?php

namespace Espo\Custom\Hooks\Lead;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Entity;
use Espo\Core\Utils\Log;

/**
 * Hook to process leads coming from the custom Lead Capture form.
 * Handles Team assignment and merges survey data into a JSON field.
 */
class LeadCaptureHook implements BeforeSave
{
    public static int $order = 1;

    public function __construct(
        private readonly Log $log
    )
    {
    }

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->log->info("LeadCaptureHook: Processing entry from Form ID: " . $options->get('leadCaptureId'));

        // Capture raw payload
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];

        $this->log->info("LeadCaptureHook: ". json_encode($payload));


        if (!empty($payload['teamId'])) {
            $entity->set('cTeamId', $payload['teamId']);
        }

        $surveyResults = [];
        $standardFields = [
            'firstName', 'lastName', 'emailAddress', 'phoneNumber',
            'teamId', 'centerId', 'leadCaptureId'
        ];

        foreach ($payload as $key => $value) {
            if (!in_array($key, $standardFields) && !empty($value)) {
                if (is_array($value)) {
                    $surveyResults[$key] = implode(', ', $value);
                } else {
                    $surveyResults[$key] = $value;
                }
            }
        }

        if (!empty($surveyResults)) {
            $jsonString = json_encode($surveyResults, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $entity->set('cSurveyData', $jsonString);
        }

        $entity->set('suppressVankoSync', true);
    }
}