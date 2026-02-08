<?php

namespace Espo\Modules\LeadManager\Services;

/**
 * Phone Number Formatting Service
 * 
 * Formats phone numbers to E.164 international format
 * Primarily handles Belgian phone numbers (+32)
 */
class PhoneFormatterService
{
    private const DEFAULT_COUNTRY_CODE = '32'; // Belgium
    
    /**
     * Format phone number to E.164 format
     * 
     * @param string|null $phone Raw phone number
     * @param string $countryCode Default country code (default: 32 for Belgium)
     * @return string Formatted phone number or empty string
     */
    public function format(?string $phone, string $countryCode = self::DEFAULT_COUNTRY_CODE): string
    {
        if (!$phone) {
            return '';
        }

        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        if (str_starts_with($cleaned, '+')) {
            return $this->validateE164($cleaned) ? $cleaned : '';
        }

        if (str_starts_with($cleaned, '00')) {
            $formatted = '+' . substr($cleaned, 2);
            return $this->validateE164($formatted) ? $formatted : '';
        }

        if (str_starts_with($cleaned, '0')) {
            $formatted = '+' . $countryCode . substr($cleaned, 1);
            return $this->validateE164($formatted) ? $formatted : '';
        }

        $formatted = '+' . $countryCode . $cleaned;
        //Return original string if not valid so we don't lose the input.
        return $this->validateE164($formatted) ? $formatted : $phone;
    }

    /**
     * Validate E.164 format
     * Must be: +[country code][number] (max 15 digits total)
     * 
     * @param string $phone Phone number to validate
     * @return bool
     */
    private function validateE164(string $phone): bool
    {
        return preg_match('/^\+\d{1,15}$/', $phone) === 1;
    }

    /**
     * Validate Belgian phone number specifically
     * 
     * @param string $phone Phone number to validate
     * @return bool
     */
    public function isValidBelgianNumber(string $phone): bool
    {
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        
        // Belgian mobile: +324XXXXXXXX (9 digits after country code)
        // Belgian landline: +32[1-9]XXXXXXX or XXXXXXXX (7-8 digits after area code)
        
        if (str_starts_with($cleaned, '+32')) {
            $number = substr($cleaned, 3);
            
            if (preg_match('/^4[5-9]\d{7}$/', $number)) {
                return true;
            }
            
            if (preg_match('/^[1-9]\d{7,8}$/', $number)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format multiple phone numbers (separated by comma, semicolon, or newline)
     * 
     * @param string|null $phones Multiple phone numbers
     * @param string $countryCode Default country code
     * @return string Formatted phone numbers separated by semicolon
     */
    public function formatMultiple(?string $phones, string $countryCode = self::DEFAULT_COUNTRY_CODE): string
    {
        if (!$phones) {
            return '';
        }

        $phoneArray = preg_split('/[,;\n\r]+/', $phones);
        $formatted = [];

        foreach ($phoneArray as $phone) {
            $phone = trim($phone);
            if (!empty($phone)) {
                $formattedPhone = $this->format($phone, $countryCode);
                if (!empty($formattedPhone)) {
                    $formatted[] = $formattedPhone;
                }
            }
        }

        return implode('; ', $formatted);
    }
}