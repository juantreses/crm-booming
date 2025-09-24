<?php

declare(strict_types=1);

namespace Espo\Modules\Vanko\Services;

use Espo\Core\Exceptions\BadRequest;

/**
 * Validates incoming lead data from the external API.
 */
class LeadDataValidator
{
    private const REQUIRED_FIELDS = [
        'contact_id', 
        'first_name', 
        'last_name', 
        'phone'
    ];

    /**
     * @throws BadRequest When validation fails.
     */
    public function validate(object $data): void
    {
        if (!is_object($data)) {
            throw new BadRequest("Input data must be an object.");
        }

        $this->validateRequiredFields($data);
        $this->validateEmailFormat($data);
        $this->validatePhoneFormat($data);
    }

    private function validateRequiredFields(object $data): void
    {
        $missingFields = [];
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($data->$field) || trim((string) $data->$field) === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw new BadRequest("Required fields are missing or empty: " . implode(', ', $missingFields));
        }
    }

    private function validateEmailFormat(object $data): void
    {
        if (isset($data->email) && trim((string) $data->email) !== '') {
            if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
                throw new BadRequest("Invalid email format: {$data->email}");
            }
        }
    }

    private function validatePhoneFormat(object $data): void
    {
        if (isset($data->phone)) {
            $phone = trim((string) $data->phone);
            if (strlen($phone) < 6) {
                throw new BadRequest("Phone number too short: {$phone}");
            }
        }
    }
}