/**
 * Form Validation Utilities
 * 
 * Client-side validation for widget forms
 */

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
     * Validate Belgian phone number
     * Accepts formats:
     * - 0470123456
     * - 0470 12 34 56
     * - 0470/12.34.56
     * - +32470123456
     * - 0032470123456
     * 
     * @param {string} phone 
     * @returns {boolean}
     */
    isValidBelgianPhone(phone) {
        if (!phone) return false;
        
        const cleaned = phone.replace(/[\s\.\-\/]/g, '');
        
        const mobileRegex = /^(?:(?:\+|00)32|0)4[5-9]\d{7}$/;
        
        const landlineRegex = /^(?:(?:\+|00)32|0)[1-9]\d{7,8}$/;
        
        return mobileRegex.test(cleaned) || landlineRegex.test(cleaned);
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