define('custom:views/lead/modals/log-call', [
    'custom:views/lead/modals/base-lead-event-modal',
    'custom:mixins/meeting-scheduler-mixin',
    'custom:utils/form-validation-utils'
], function (Dep, MeetingSchedulerMixin, ValidationUtils) {
    
    const LogCallView = Dep.extend({
        
        template: 'custom:lead/modals/log-call',
        
        apiEndpoint: 'leadEvent/logCall',
        successMessage: 'Call logged successfully',
        errorMessage: 'Failed to log call',

        setup: function () {
            console.log('LogCallView setup called!', this);
            Dep.prototype.setup.call(this);
            
            this.initializeMeetingScheduler();
            
            this.events['change [name="outcome"]'] = 'handleOutcomeChange';
        },

        getSaveButtonLabel: function() {
            return 'Log Gesprek';
        },

        getHeaderText: function() {
            return 'Log Gesprek';
        },

        handleOutcomeChange: function() {
            const outcome = this.getFieldValue('outcome');
            
            this.toggleField('call-again-date', outcome === 'call_again');
            
            if (outcome === 'invited') {
                this.showMeetingScheduler();
            } else {
                this.hideMeetingScheduler();
            }
        },

        getFormData: function() {
            const outcome = this.getFieldValue('outcome');
            const callDateTime = this.getFieldValue('callDateTime');
            const callAgainDateTime = this.getFieldValue('callAgainDateTime');
            const coachNote = this.getFieldValue('coachNote');
            
            const formData = {
                id: this.model.id,
                outcome: outcome,
                callDateTime: this.formatDateTime(callDateTime),
                callAgainDateTime: this.formatDateTime(callAgainDateTime),
                coachNote: coachNote || null
            };

            if (outcome === 'invited') {
                const meetingData = this.getSelectedMeetingData();
                if (meetingData) {
                    formData.calendarId = meetingData.calendarId;
                    formData.selectedDate = meetingData.slotDate;
                    formData.selectedTime = meetingData.slotTime;
                }
            }

            return formData;
        },

        validateForm: function() {
            const outcome = this.getFieldValue('outcome');
            const callDateTime = this.getFieldValue('callDateTime');
            const callAgainDateTime = this.getFieldValue('callAgainDateTime');

            if (!ValidationUtils.validateRequired(outcome, 'Selecteer een uitkomst.')) {
                return false;
            }

            if (outcome === 'invited') {
                if (!this.validateMeetingSelection()) {
                    return false;
                }
            }

            if (outcome === 'call_again') {
                if (!ValidationUtils.validateCallAgainDateTime(callAgainDateTime, true)) {
                    return false;
                }
            }

            if (!ValidationUtils.validateEventDateTime(callDateTime, 'gesprek')) {
                return false;
            }

            return true;
        },

        handleSuccess: function(response) {
            this.trigger('success', response);
            this.close();

            if (response && response.data && response.data.eventIds) {
                const eventCount = response.data.eventIds.length;
                Espo.Ui.success(`Call logged successfully. Created ${eventCount} event(s).`);
            } else {
                Espo.Ui.success(this.successMessage);
            }
        }
    });

    _.extend(LogCallView.prototype, MeetingSchedulerMixin);

    return LogCallView;
});