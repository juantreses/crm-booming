<?php

namespace Espo\Modules\Vanko\Controllers;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\ORM\EntityManager;
use Espo\Custom\Enums\LeadEventType;
use Espo\Custom\Services\AppointmentService;
use Espo\Custom\Services\LeadEventService;
use Espo\Modules\Crm\Entities\Lead;
use Espo\Modules\Vanko\Services\LeadService;

class VankoApi
{
    public function __construct(
        private readonly LeadService $leadService,
        private readonly LeadEventService $leadEventService,
        private readonly AppointmentService $appointmentService,
        private readonly EntityManager $entityManager,
    ) {}

    public function postActionLead($params, $data)
    {
        // Log all data to test.txt
        /*$logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'params' => $params,
            'data' => json_encode($data),
        ];
        file_put_contents('test.txt', print_r($logData, true) . "\n\n", FILE_APPEND);*/
        
        try {
            if (!$data) {
                throw new BadRequest('No data provided');
            }
            // Validate required fields
            $required = ['contact_id', 'first_name', 'phone'];
            $missing = [];
            foreach ($required as $field) {
                if (empty($data->$field)) {
                    $missing[] = $field;
                }
            }
            if (count($missing) > 0) {
                throw new BadRequest("Missing required field(s): " . implode(', ', $missing));
            }

            return $this->leadService->processLead($data);
        } catch (BadRequest $e) {
            // Return proper error response
            http_response_code(400);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 400
            ];
        } catch (\Exception $e) {
            // Log unexpected errors
            $GLOBALS['log']->error('Vanko API Error: ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500
            ];
        }
    }

    public function postActionAppointment($params, $data)
    {
$logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'params' => $params,
            'data' => json_encode($data),
        ];
        file_put_contents('test.txt', print_r($logData, true) . "\n\n", FILE_APPEND);
        try {
            if (!$data) {
                throw new BadRequest('No data provided');
            }

            $lead = $this->findLeadByData($data);
            if (!$lead) {
                throw new BadRequest("Could not find a matching lead with the provided identifiers.");
            }

            return $this->entityManager->getTransactionManager()->run(
                function () use ($lead, $data) {
                    $result = $this->leadEventService->logEvent($lead->getId(), LeadEventType::APPOINTMENT_BOOKED);
                    if (!empty($data->customData)) {
                        $this->appointmentService->createAppointment($lead, $data->customData);
                    }
                    return $result;
                }
            );

        } catch (BadRequest $e) {
            http_response_code(400);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 400,
            ];
        } catch (\Exception $e) {
            $GLOBALS['log']->error('Vanko API Kickstart Error: ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500,
            ];
        }
    }

    public function postActionCancelAppointment($params, $data)
    {
        try {
            if (!$data) {
                throw new BadRequest('No data provided');
            }

            $lead = $this->findLeadByData($data);

            if (!$lead) {
                throw new BadRequest("Could not find a matching lead with the provided identifiers.");
            }

            return $this->entityManager->getTransactionManager()->run(function () use ($data) {
                $lead = $this->findLeadByData($data);

                if (!$lead) {
                    throw new BadRequest("Could not find a matching lead.");
                }

                $this->leadEventService->logEvent($lead->getId(), LeadEventType::APPOINTMENT_CANCELLED);

                if (!empty($data->customData->vankoMeetingId)) {
                    $found = $this->appointmentService->cancelAppointment($lead, $data->customData->vankoMeetingId);

                    if (!$found) {
                        $this->logInfo("Status updated for Lead {$lead->getId()}, but VankoAppointment record was not found.");
                    }
                }

                return ['success' => true, 'appointmentUpdated' => $found ?? false];
            });

        } catch (BadRequest $e) {
            http_response_code(400);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 400,
            ];
        } catch (\Exception $e) {
            $GLOBALS['log']->error('Vanko API CancelKickstart Error: ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500,
            ];
        }
    }

    private function findLeadByData($data): ?Lead
    {
        $entityManager = $this->entityManager;

        // Strategy 1: Find by the internal ID
        if (!empty($data->SFC_CRM_Lead_Identity)) {
            $lead = $entityManager->getRepository('Lead')->where([
                'id' => $data->SFC_CRM_Lead_Identity
            ])->findOne();
            if ($lead) {
                $this->logInfo("Found lead by SFC_CRM_Lead_Identity: " . $lead->getId());
                return $lead;
            }
        }

        // Strategy 2: Find by vanko crm ID
        if (!empty($data->contact_id)) {
            $lead = $entityManager->getRepository('Lead')->where([
                'cVankoCRM' => $data->contact_id
            ])->findOne();
            if ($lead) {
                $this->logInfo("Found lead by contact_id: " . $lead->getId());
                return $lead;
            }
        }
        
        // Strategy 3: Find by name and contact details
        if (!empty($data->first_name) && !empty($data->last_name)) {
            $potentialLead = null;

            // First, try to find a matching ID by email
            if (!empty($data->email)) {
                $potentialLead = $entityManager->getRepository('EmailAddress')->getEntityByAddress($data->email, 'Lead');
            }

            if ($potentialLead) {
                // We found a lead, now let's double-check the name matches to avoid ambiguity.
                if ($potentialLead->get('firstName') == $data->first_name && $potentialLead->get('lastName') == $data->last_name) {
                    $lead = $potentialLead;
                }
            }
            
            if ($lead) {
                $this->logInfo("Found lead by name/contact details: " . $lead->getId());
                return $lead;
            }
        }
        
        $this->logInfo("Could not find a matching lead for the provided data.");
        return null;
    }

    /**
     * Centralized logging helper
     */
    private function logInfo(string $message): void
    {
        $GLOBALS['log']->info("Vanko: {$message}");
    }

}
