/**
 * BaseLeadEventModal
 * 
 * Base class for all lead event modals (log-call, log-kickstart, etc.)
 * Provides common functionality like form data collection, button management,
 * and API request handling.
 */
define('custom:views/lead/modals/base-lead-event-modal', [
    'views/modal', 
    'custom:utils/date-utils',
    'custom:utils/form-validation-utils'
], function (Dep, DateUtils, ValidationUtils) {
    
    return Dep.extend({
        
        className: 'dialog dialog-record',

        partialList: [
            'custom:lead/partials/meeting-scheduler',
            'custom:lead/partials/call-again-datetime',
            'custom:lead/partials/coach-note'
        ],

        /**
         * Override in child classes to specify the API endpoint
         * e.g., 'leadEvent/logCall'
         */
        apiEndpoint: null,

        /**
         * Override in child classes to specify success message
         * e.g., 'Call logged successfully'
         */
        successMessage: null,

        /**
         * Override in child classes to specify error message
         * e.g., 'Failed to log call'
         */
        errorMessage: null,

        setup: function () {
            Dep.prototype.setup.call(this);
            
            this.buttonList = [
                {
                    name: 'save',
                    label: this.getSaveButtonLabel(),
                    style: 'primary'
                },
                {
                    name: 'cancel',
                    label: 'Annuleer'
                }
            ];

            if (!this.headerText) {
                this.headerText = this.getHeaderText() + ' - ' + this.model.get('name');
            }
        },

        getSaveButtonLabel: function() {
            return 'Opslaan';
        },

        getHeaderText: function() {
            return 'Log Event';
        },

        getFormData: function() {
            return {
                id: this.model.id
            };
        },

        validateForm: function() {
            return true;
        },

        getFieldValue: function(fieldName) {
            return this.$el.find(`[name="${fieldName}"]`).val();
        },

        formatDateTime: function(dateTimeValue) {
            if (!dateTimeValue) {
                return null;
            }
            return DateUtils.toOffsetISOString(new Date(dateTimeValue));
        },

        actionSave: function () {
            const $saveButton = ValidationUtils.getSaveButton(this.$el);
            ValidationUtils.disableButton($saveButton);

            if (!this.validateForm()) {
                ValidationUtils.enableButton($saveButton);
                return;
            }

            const formData = this.getFormData();

            this.makeApiRequest(formData, $saveButton);
        },

        makeApiRequest: function(formData, $saveButton) {
            if (!this.apiEndpoint) {
                console.error('apiEndpoint not defined in view');
                ValidationUtils.enableButton($saveButton);
                return;
            }

            Espo.Ajax.postRequest(this.apiEndpoint, formData)
                .then((response) => {
                    this.handleSuccess(response);
                })
                .catch(() => {
                    this.handleError();
                    ValidationUtils.enableButton($saveButton);
                });
        },

        handleSuccess: function(response) {
            this.trigger('success', response);
            this.close();
            
            if (this.successMessage) {
                Espo.Ui.success(this.successMessage);
            }
        },

        handleError: function() {
            if (this.errorMessage) {
                Espo.Ui.error(this.errorMessage);
            }
        },

        showField: function(fieldName) {
            this.$el.find(`[data-field="${fieldName}"]`).show();
        },

        hideField: function(fieldName) {
            this.$el.find(`[data-field="${fieldName}"]`).hide();
        },

        toggleField: function(fieldName, shouldShow) {
            if (shouldShow) {
                this.showField(fieldName);
            } else {
                this.hideField(fieldName);
            }
        }
    });
});