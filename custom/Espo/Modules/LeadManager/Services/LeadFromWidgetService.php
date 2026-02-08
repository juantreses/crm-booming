<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\ORM\EntityManager;

readonly class LeadFromWidgetService
{
    public function __construct(
        private EntityManager $entityManager,
        private LeadService $leadService,
    ) {}

    /**
     * Create or update a lead from widget submission
     * 
     * @param array $data Raw form data from widget
     * @return array Result with lead ID and success status
     */
    public function createFromSubmission(array $data): array
    {
        if (empty($data['email'])) {
            return [
                'success' => false, 
                'error' => 'Email is verplicht'
            ];
        }

        $lead = $this->leadService->findOrCreate($data);

        $surveyData = $this->extractSurveyData($data);
        $lead->set('cSurveyData', json_encode($surveyData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if (!empty($data['referredBy'])) {
            $lead->set('cReferredBy', $data['referredBy']);
        }

        $this->entityManager->saveEntity($lead);

        return [
            'success' => true,
            'id' => $lead->getId(),
        ];
    }

    private function extractSurveyData($data) {
        $surveyResults = [
            'Wat had je vanmorgen als ontbijt?' => $data['ontbijt'] ?? [],
            'Ontbijt Andere:' => $data['ontbijtAndere'] ?? '',
            'Gezondheidsscore' => $data['score'] ?? null,
            'Doe je aan sport?' => $data['sport'] ?? [],
            'Sport Andere:' => $data['sportAndere'] ?? '',
            'Welk resultaat kies je?' => $data['resultaten'] ?? [],
            'Welke ervaring wil je graag bijwonen?' => $data['ervaringen'] ?? [],
            'Wil je graag iets bijverdienen als coach?' => $data['bijverdienen'] ?? null,
            'Waarin ben je geïnteresseerd?' => $data['interests'] ?? [],
            'Opmerking' => $data['opmerking'] ?? '',
        ];

        return array_filter($surveyResults);
    }
}