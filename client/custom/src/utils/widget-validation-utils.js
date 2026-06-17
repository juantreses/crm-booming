/**
 * Form Validation Utilities
 * 
 * Client-side validation for widget forms
 */

const WESTERN_EUROPE_PHONE_COUNTRIES = [
    'BE', 'AD', 'AT', 'AX', 'CH', 'DE', 'DK', 'ES', 'FI', 'FO',
    'FR', 'GB', 'GG', 'GI', 'IE', 'IM', 'IS', 'IT', 'JE', 'LI',
    'LU', 'MC', 'MT', 'NL', 'NO', 'PT', 'SE', 'SJ', 'SM', 'VA'
];
const DEFAULT_PHONE_COUNTRY = 'BE';

function getPhoneParser() {
    return typeof window !== 'undefined' ? window.libphonenumber : null;
}

function normalizeInternationalPrefix(phone) {
    return phone.trim().replace(/^00/, '+');
}

function parseEuropeanPhone(phone) {
    const libphonenumber = getPhoneParser();

    if (!phone || !libphonenumber) {
        return null;
    }

    const normalizedPhone = normalizeInternationalPrefix(phone);
    const parsePhoneNumberFromString = libphonenumber.parsePhoneNumberFromString;

    if (normalizedPhone.startsWith('+')) {
        const phoneNumber = parsePhoneNumberFromString(normalizedPhone);

        return phoneNumber &&
            phoneNumber.country &&
            WESTERN_EUROPE_PHONE_COUNTRIES.includes(phoneNumber.country)
            ? phoneNumber
            : null;
    }

    const phoneNumber = parsePhoneNumberFromString(normalizedPhone, DEFAULT_PHONE_COUNTRY);

    return phoneNumber && phoneNumber.country === DEFAULT_PHONE_COUNTRY ? phoneNumber : null;
}

const FormValidation = {
    /**
     * Validate email address
     * @param {string} email 
     * @returns {boolean}
     */
    isValidEmail(email) {
        if (!email) return false;
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },

    /**
     * Validate Western European phone number
     * Uses libphonenumber-js when available. National-format numbers are
     * treated as Belgian to avoid ambiguous cross-country formatting.
     * 
     * @param {string} phone 
     * @returns {boolean}
     */
    isValidEuropeanPhone(phone) {
        if (!phone) return false;

        const libphonenumber = getPhoneParser();
        const phoneNumber = parseEuropeanPhone(phone);

        if (libphonenumber) {
            return Boolean(phoneNumber && phoneNumber.isValid());
        }
        
        const cleaned = phone.replace(/[\s\.\-\/]/g, '');
        
        const mobileRegex = /^(?:(?:\+|00)32|0)4[5-9]\d{7}$/;
        
        const landlineRegex = /^(?:(?:\+|00)32|0)[1-9]\d{7,8}$/;
        
        return mobileRegex.test(cleaned) || landlineRegex.test(cleaned);
    },

    /**
     * Backwards-compatible alias for existing widgets.
     * @param {string} phone
     * @returns {boolean}
     */
    isValidBelgianPhone(phone) {
        return this.isValidEuropeanPhone(phone);
    },

    /**
     * Format phone number to E.164 format
     * 
     * @param {string} phone 
     * @returns {string} Formatted phone or original if invalid
     */
    formatEuropeanPhone(phone) {
        if (!phone) return '';

        const phoneNumber = parseEuropeanPhone(phone);

        if (phoneNumber && phoneNumber.isValid()) {
            return phoneNumber.number;
        }

        if (getPhoneParser()) {
            return phone;
        }
        
        let cleaned = phone.replace(/[\s\.\-\/]/g, '');
        
        if (cleaned.startsWith('+32')) {
            return cleaned;
        }
        
        if (cleaned.startsWith('0032')) {
            return '+' + cleaned.substring(2);
        }
        
        if (cleaned.startsWith('0')) {
            return '+32' + cleaned.substring(1);
        }
        
        return '+32' + cleaned;
    },

    /**
     * Backwards-compatible alias for existing widgets.
     * @param {string} phone
     * @returns {string}
     */
    formatBelgianPhone(phone) {
        return this.formatEuropeanPhone(phone);
    },

    /**
     * Validate required field
     * @param {string} value 
     * @returns {boolean}
     */
    isRequired(value) {
        return value && value.trim().length > 0;
    },

    /**
     * Validate name (only letters, spaces, hyphens, apostrophes)
     * @param {string} name 
     * @returns {boolean}
     */
    isValidName(name) {
        if (!name) return false;
        
        const nameRegex = /^[a-zA-ZÀ-ÿ\s\-']+$/;
        return nameRegex.test(name) && name.trim().length >= 2;
    }
};

// Export for use in widgets
if (typeof window !== 'undefined') {
    window.FormValidation = FormValidation;
}
