/**
 * FormValidationUtils
 * 
 * Centralized validation utilities for lead event forms.
 * Provides common validation patterns used across multiple views.
 */
define('custom:utils/form-validation-utils', [], function () {

    const FormValidationUtils = {

        /**
         * Validate that a datetime is in the future
         * @param {string} dateTimeValue - The datetime-local input value
         * @param {string} fieldName - Human-readable field name for error message
         * @returns {boolean} true if valid (future date) or empty
         */
        validateFutureDateTime: function(dateTimeValue, fieldName) {
            if (!dateTimeValue) {
                return true; // Empty is allowed
            }

            const now = new Date();
            const selectedDate = new Date(dateTimeValue);
            
            if (selectedDate <= now) {
                Espo.Ui.error(`${fieldName} moet in de toekomst zijn.`);
                return false;
            }

            return true;
        },

        /**
         * Validate that a datetime is not in the future (past or present only)
         * @param {string} dateTimeValue - The datetime-local input value
         * @param {string} fieldName - Human-readable field name for error message
         * @returns {boolean} true if valid (past/present date) or empty
         */
        validatePastOrPresentDateTime: function(dateTimeValue, fieldName) {
            if (!dateTimeValue) {
                return true; // Empty is allowed
            }

            const now = new Date();
            const selectedDate = new Date(dateTimeValue);
            
            if (selectedDate > now) {
                Espo.Ui.error(`${fieldName} mag niet in de toekomst zijn.`);
                return false;
            }

            return true;
        },

        /**
         * Validate that a required field is filled
         * @param {string|null|undefined} value - The field value
         * @param {string} errorMessage - Error message to display
         * @returns {boolean} true if valid (not empty)
         */
        validateRequired: function(value, errorMessage) {
            if (!value || value.trim() === '') {
                Espo.Ui.error(errorMessage);
                return false;
            }
            return true;
        },

        /**
         * Validate call again datetime (must be future and required)
         * @param {string} dateTimeValue - The datetime-local input value
         * @param {boolean} isRequired - Whether the field is required
         * @returns {boolean} true if valid
         */
        validateCallAgainDateTime: function(dateTimeValue, isRequired = true) {
            if (isRequired && !dateTimeValue) {
                Espo.Ui.error('Datum/tijd opnieuw bellen is verplicht.');
                return false;
            }

            if (dateTimeValue) {
                return this.validateFutureDateTime(dateTimeValue, 'Datum/tijd opnieuw bellen');
            }

            return true;
        },

        /**
         * Validate event datetime (must be past/present)
         * @param {string} dateTimeValue - The datetime-local input value
         * @param {string} eventName - Name of the event (e.g., "gesprek", "kickstart")
         * @returns {boolean} true if valid
         */
        validateEventDateTime: function(dateTimeValue, eventName) {
            return this.validatePastOrPresentDateTime(
                dateTimeValue, 
                `Datum/tijd van ${eventName}`
            );
        },

        /**
         * Batch validation - runs multiple validations and returns overall result
         * Stops at first failure
         * @param {Array<Function>} validationFunctions - Array of validation functions
         * @returns {boolean} true if all pass
         */
        validateAll: function(validationFunctions) {
            for (let validate of validationFunctions) {
                if (!validate()) {
                    return false;
                }
            }
            return true;
        },

        /**
         * Disable a button (typically save button)
         * @param {jQuery} $button - The button element
         */
        disableButton: function($button) {
            $button.prop('disabled', true);
        },

        /**
         * Enable a button (typically save button)
         * @param {jQuery} $button - The button element
         */
        enableButton: function($button) {
            $button.prop('disabled', false);
        },

        /**
         * Get the save button from a view's dialog
         * @param {jQuery} $el - The dialog element
         * @returns {jQuery} The save button
         */
        getSaveButton: function($el) {
            return $el.find('button[data-name="save"]');
        }
    };

    return FormValidationUtils;
});