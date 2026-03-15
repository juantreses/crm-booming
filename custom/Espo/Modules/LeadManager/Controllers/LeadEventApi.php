<?php

namespace Espo\Modules\LeadManager\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Modules\LeadManager\Services\LeadEventService;
use Espo\Modules\LeadManager\Validators\LogCallValidator;
use Espo\Modules\LeadManager\Validators\LogIntroMeetingValidator;
use Espo\Modules\LeadManager\Validators\LogKickstartValidator;
use Espo\Modules\LeadManager\Validators\LogMessageOutcomeValidator;

readonly class LeadEventApi
{

    public function __construct(
        private LeadEventService $leadEventService
    ) {}

    public function postActionLogCall(Request $request): array
    {
        try {
            $data = $request->getParsedBody();
            $validator = new LogCallValidator();
            $validator->validate($data);
            
            $result = $this->leadEventService->logCall($data);

            return [
                'success' => true,
                'message' => 'Call logged successfully',
                'data' => $result
            ];

        } catch (Conflict $e) {
            throw new BadRequest("Helaas, dit tijdstip is zojuist volgeboekt. Kies een ander moment.");
        } catch (BadRequest $e) {
            // Re-throw BadRequest exceptions as-is (Validation errors)
            throw $e;
        } catch (\Throwable $e) {
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

	public function postActionLogKickstart(Request $request): array
    {
        try {
            $data = $request->getParsedBody();

            $validator = new LogKickstartValidator();
            $validator->validate($data);

            $result = $this->leadEventService->logKickstart($data);

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

    public function postActionLogMessageSent(Request $request): array
    {
        try {
            $data = $request->getParsedBody();

            if (!isset($data->id)) {
                throw new BadRequest('Lead ID is required');
            }

            $result = $this->leadEventService->logMessageSent($data);

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
                'leadId' => $data->id,
                'trace' => $e->getTraceAsString()
            ]);
            throw new BadRequest('Failed to log message sent: ' . $e->getMessage());
        }
    }

    public function postActionLogMessageOutcome(Request $request): array
    {
        try {
            $data = $request->getParsedBody();

            $validator = new LogMessageOutcomeValidator();
            $validator->validate($data);

            $result = $this->leadEventService->logMessageOutcome($data);

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

    public function postActionLogKickstartFollowUp(Request $request): array
    {
        try {
            $data = $request->getParsedBody();

            $validator = new LogKickstartValidator();
            $validator->validate($data);
            
            $result = $this->leadEventService->logKickstartFollowUp($data);

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
                'callAgainDateTime' => $data->callAgainDateTime,
                'trace' => $e->getTraceAsString()
            ]);
            throw new BadRequest('Failed to log kickstart follow up: ' . $e->getMessage());
        }
    }

    public function postActionLogIntroMeeting(Request $request): array
    {
        try {
            $data = $request->getParsedBody();

            $validator = new LogIntroMeetingValidator();
            $validator->validate($data);

            $result = $this->leadEventService->logIntroMeeting($data);

            return [
                'success' => true,
                'message' => 'Intro meeting logged successfully',
                'data' => $result
            ];

        } catch (Conflict $e) {
            throw new BadRequest("Helaas, dit tijdstip is zojuist volgeboekt. Kies een ander moment.");
        } catch (BadRequest $e) {
            throw $e;
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('Lead Log Intro Meeting Error: ' . $e->getMessage(), [
                'leadId' => $data->id ?? null,
                'outcome' => $data->outcome ?? null,
                'eventDate' => $data->introDateTime ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            throw new BadRequest('Failed to log intro meeting: ' . $e->getMessage());
        }
    }
}

?>
