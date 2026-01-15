<?php

namespace Espo\Modules\LeadManager\Controllers;

use Espo\Modules\LeadManager\Services\LeadService;
use Espo\ORM\EntityManager;
use Espo\Core\Api\Request;

class WidgetApi
{
    public function __construct(
        private EntityManager $entityManager,
        private LeadService $leadService,
    )
    {}

    public function postActionSubmit(Request $request): array
    {
        $data = $request->getParsedBody();

        if (!$data || !isset($data->email)) {
            return ['success' => false, 'error' => 'Email is verplicht'];
        }

        $person = $this->leadService->findOrCreate((array)$data);

        $surveyResults = [
            'Wat had je vanmorgen als ontbijt?' => $data->ontbijt ?? [],
            'Ontbijt Andere:' => $data->ontbijtAndere ?? '',
            'Gezondheidsscore' => $data->score ?? null,
            'Doe je aan sport?' => $data->sport ?? [],
            'Sport Andere:' => $data->sportAndere ?? '',
            'Welk resultaat kies je?' => $data->resultaten ?? [],
            'Welke ervaring wil je graag bijwonen?' => $data->ervaringen ?? [],
            'Wil je graag iets bijverdienen als coach?' => $data->bijverdienen ?? null,
            'Waarin ben je geïnteresseerd?' => $data->interests ?? [],
            'Opmerking' => $data->opmerking ?? '',
        ];

        $survey = array_filter($surveyResults);

        $person->set('cSurveyData', json_encode($survey, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $person->set('cReferredBy', $data->referredBy ?? '');

        $this->entityManager->saveEntity($person);

        return [
            'success' => true,
            'id' => $person->get('id'),
        ];

    }
}