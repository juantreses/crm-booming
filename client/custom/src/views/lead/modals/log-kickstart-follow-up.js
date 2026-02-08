define('custom:views/lead/modals/log-kickstart-follow-up', [
    'custom:views/lead/modals/base-lead-event-modal',
    'custom:utils/form-validation-utils'
], function (Dep, ValidationUtils) {

    return Dep.extend({
        
        template: 'custom:lead/modals/log-kickstart-follow-up',
        
        apiEndpoint: 'leadEvent/logKickstartFollowUp',
        successMessage: 'KS Opvolging opgeslagen.',
        errorMessage: 'KS Opvolging opslaan mislukt.',

        getSaveButtonLabel: function() {
            return 'Opslaan';
        },

        getHeaderText: function() {
            return 'Log KS Opvolging';
        },

        getFormData: function() {
            const outcome = this.getFieldValue('outcome');
            const coachNote = this.getFieldValue('coachNote');
            const followUpDateTime = this.getFieldValue('followUpDateTime');
            
            return {
                id: this.model.id,
                outcome: outcome,
                followUpDateTime: this.formatDateTime(followUpDateTime),
                coachNote: coachNote || null
            };
        },

        validateForm: function() {
            const outcome = this.getFieldValue('outcome');
            const followUpDateTime = this.getFieldValue('followUpDateTime');

            if (!ValidationUtils.validateRequired(outcome, 'Selecteer een uitkomst.')) {
                return false;
            }

            if (!ValidationUtils.validateEventDateTime(followUpDateTime, 'opvolging')) {
                return false;
            }

            return true;
        }
    });
});