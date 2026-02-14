/**
 * Form Validation Utilities
 * 
 * Client-side validation for widget forms with detailed error messages
 */

const FormValidation = {
    /**
     * Validate email address
     * @param {string} email 
     * @returns {{isValid: boolean, error: string|null}}
     */
    validateEmail(email) {
        if (!email || !email.trim()) {
            return { isValid: false, error: 'E-mailadres is verplicht' };
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            return { isValid: false, error: 'Voer een geldig e-mailadres in' };
        }
        
        return { isValid: true, error: null };
    },

    /**
     * Validate Belgian phone number
     * Accepts formats:
     * - 0470123456
     * - 0470 12 34 56
     * - 0470/12.34.56
     * - +32470123456
     * - 0032470123456
     * 
     * @param {string} phone 
     * @returns {{isValid: boolean, error: string|null}}
     */
    validateBelgianPhone(phone) {
        if (!phone || !phone.trim()) {
            return { isValid: false, error: 'Telefoonnummer is verplicht' };
        }
        
        const cleaned = phone.replace(/[\s\.\-\/]/g, '');
        
        // Mobile regex: starts with 04[5-9] followed by 7 digits
        const mobileRegex = /^(?:(?:\+|00)32|0)4[5-9]\d{7}$/;
        
        // Landline regex: starts with 0 (or +32/0032) followed by [1-9] and 7-8 more digits
        const landlineRegex = /^(?:(?:\+|00)32|0)[1-9]\d{7,8}$/;
        
        if (!mobileRegex.test(cleaned) && !landlineRegex.test(cleaned)) {
            return { isValid: false, error: 'Voer een geldig Belgisch telefoonnummer in' };
        }
        
        return { isValid: true, error: null };
    },

    /**
     * Validate name (first name or last name)
     * @param {string} name 
     * @param {string} fieldLabel - e.g., 'Voornaam' or 'Achternaam'
     * @returns {{isValid: boolean, error: string|null}}
     */
    validateName(name, fieldLabel = 'Naam') {
        if (!name || !name.trim()) {
            return { isValid: false, error: `${fieldLabel} is verplicht` };
        }
        
        if (name.trim().length < 2) {
            return { isValid: false, error: `${fieldLabel} moet minimaal 2 tekens zijn` };
        }
        
        const nameRegex = /^[a-zA-ZÀ-ÿ\s\-']+$/;
        if (!nameRegex.test(name)) {
            return { isValid: false, error: `${fieldLabel} mag alleen letters bevatten` };
        }
        
        return { isValid: true, error: null };
    },

    /**
     * Validate required field (generic)
     * @param {string} value 
     * @param {string} fieldLabel
     * @returns {{isValid: boolean, error: string|null}}
     */
    validateRequired(value, fieldLabel = 'Dit veld') {
        if (!value || !value.trim()) {
            return { isValid: false, error: `${fieldLabel} is verplicht` };
        }
        
        return { isValid: true, error: null };
    },

    /**
     * Format phone number to E.164 format for Belgium
     * Normalizes to +32XXXXXXXXX
     * 
     * @param {string} phone 
     * @returns {string} Formatted phone or original if invalid
     */
    formatBelgianPhone(phone) {
        if (!phone) return '';
        
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

    // Legacy methods for backward compatibility
    isValidEmail(email) {
        return this.validateEmail(email).isValid;
    },

    isValidBelgianPhone(phone) {
        return this.validateBelgianPhone(phone).isValid;
    },

    isValidName(name) {
        return this.validateName(name).isValid;
    },

    isRequired(value) {
        return this.validateRequired(value).isValid;
    }
};

// Export for use in widgets
if (typeof window !== 'undefined') {
    window.FormValidation = FormValidation;
}