<?php

namespace Espo\Custom\Controllers;

use Espo\Modules\Crm\Controllers\Lead as LeadController;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Custom\Services\LeadEventService;
use Espo\Custom\Enums\CallOutcome;
use Espo\Custom\Enums\KickstartOutcome;
use Espo\Custom\Enums\MessageSentOutcome;
use Espo\Custom\Enums\LeadEventType;
use Espo\Custom\Validators\LogCallValidator;
use Espo\Custom\Validators\LogKickstartValidator;
use Espo\Custom\Validators\LogMessageOutcomeValidator;

class Lead extends LeadController
{
    public function postActionLogCall($params, $data): array
    {
        try {
            $validator = new LogCallValidator();
            $validator->validate($data);

            /** @var LeadEventService $leadEventService */
            $leadEventService = $this->getServiceFactory()->create('LeadEventService');
            $result = $leadEventService->logCall($data);

            return [
                'success' => true,
                'message' => 'Call logged successfully',
                'data' => $result
            ];

        } catch (BadRequest $e) {
            // Re-throw BadRequest exceptions as-is (Validation errors)
            throw $e;
        } catch (\Exception $e) {
            $GLOBALS['log']->error('Lead Log Call Error: ' . $e->getMessage(), [
                'leadId' => $data->id,
                'outcome' => $data->outcome,
                'eventDate' => $data->callDateTime ?? null,
                'callAgainDateTime' => $data->callAgainDateTime ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            throw new BadRequest('Failed to log call: ' . $e->getMessage());
        }
    }

	public function postActionLogKickstart($params, $data): array
    {
        try {
            $validator = new LogKickstartValidator();
            $validator->validate($data);

            /** @var LeadEventService $leadEventService */
            $leadEventService = $this->getServiceFactory()->create('LeadEventService');
            $result = $leadEventService->logKickstart($data);

            return [
                'success' => true,
                'message' => 'Kickstart logged successfully',
                'data' => $result
            ];

        } catch (BadRequest $e) {
            // Re-throw BadRequest exceptions as-is
            throw $e;
        } catch (\Exception $e) {
            $GLOBALS['log']->error('Lead Log Kickstart Error: ' . $e->getMessage(), [
                'leadId' => $data->id,
                'outcome' => $data->outcome,
                'eventDate' => $data->kickstartDateTime ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            throw new BadRequest('Failed to log kickstart: ' . $e->getMessage());
        }
    }

    public function postActionLogMessageSent($params, $data): array
    {
        try {
            if (!isset($data->id)) {
                throw new BadRequest('Lead ID is required');
            }

            /** @var LeadEventService $leadEventService */
            $leadEventService = $this->getServiceFactory()->create('LeadEventService');
            $result = $leadEventService->logMessageSent($data);

            return [
                'success' => true,
                'message' => 'Message sent logged successfully',
                'data' => $result
            ];

        } catch (BadRequest $e) {
            // Re-throw BadRequest exceptions as-is
            throw $e;
        } catch (\Exception $e) {
            $GLOBALS['log']->error('Lead Log Message Sent Error: ' . $e->getMessage(), [
                'leadId' => $leadId,
                'trace' => $e->getTraceAsString()
            ]);
            throw new BadRequest('Failed to log message sent: ' . $e->getMessage());
        }
    }

    public function postActionLogMessageOutcome($params, $data): array
    {
        try {
            $validator = new LogMessageOutcomeValidator();
            $validator->validate($data);
            
            /** @var LeadEventService $leadEventService */
            $leadEventService = $this->getServiceFactory()->create('LeadEventService');

            $result = $leadEventService->logMessageOutcome($data);

            return [
                'success' => true,
                'message' => 'Message outcome logged successfully',
                'data' => $result
            ];

        } catch (BadRequest $e) {
            throw $e;
        } catch (\Exception $e) {
            $GLOBALS['log']->error('Lead Log Message Outcome Error: ' . $e->getMessage(), [
                'leadId' => $data->id,
                'outcome' => $data->outcome,
                'callAgainDateTime' => $data->callAgainDateTime,
                'trace' => $e->getTraceAsString()
            ]);
            throw new BadRequest('Failed to log message outcome: ' . $e->getMessage());
        }
    }

    public function postActionLogKickstartFollowUp($params, $data): array
    {
        try {
            $validator = new LogKickstartValidator();
            $validator->validate($data);
            
            /** @var LeadEventService $leadEventService */
            $leadEventService = $this->getServiceFactory()->create('LeadEventService');
            $result = $leadEventService->logKickstartFollowUp($data);

            return [
                'success' => true,
                'message' => 'Follow-up logged successfully',
                'data' => $result
            ];

        } catch (BadRequest $e) {
            throw $e;
        } catch (\Exception $e) {
            $GLOBALS['log']->error('Lead Log Kickstart Follow Up Error: ' . $e->getMessage(), [
                'leadId' => $data->id,
                'outcome' => $outcome,
                'callAgainDateTime' => $data->callAgainDateTime,
                'trace' => $e->getTraceAsString()
            ]);
            throw new BadRequest('Failed to log kickstart follow up: ' . $e->getMessage());
        }
    }
}

?>

