define('custom:views/lead/modals/log-message-outcome', [
    'custom:views/lead/modals/base-lead-event-modal',
    'custom:mixins/meeting-scheduler-mixin',
    'custom:utils/form-validation-utils'
], function (Dep, MeetingSchedulerMixin, ValidationUtils) {

    const LogMessageOutcomeView = Dep.extend({
        
        template: 'custom:lead/modals/log-message-outcome',
        
        apiEndpoint: 'leadEvent/logMessageOutcome',
        successMessage: 'Bericht uitkomst opgeslagen.',
        errorMessage: 'Bericht uitkomst opslaan mislukt.',

        setup: function () {
            Dep.prototype.setup.call(this);
            
            this.initializeMeetingScheduler();
            
            this.events['change [name="outcome"]'] = 'handleOutcomeChange';
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.handleOutcomeChange();
        },

        getSaveButtonLabel: function() {
            return 'Opslaan';
        },

        getHeaderText: function() {
            return 'Log Bericht Uitkomst';
        },

        handleOutcomeChange: function() {
            const outcome = this.getFieldValue('outcome');
            
            this.toggleField('call-again-date', outcome === 'call_again');
            this.toggleField('meeting-type', outcome === 'invited');
            
            if (outcome === 'invited') {
                this.showMeetingScheduler();
            } else {
                this.hideMeetingScheduler();
            }
        },

        getFormData: function() {
            const outcome = this.getFieldValue('outcome');
            const callAgainDateTime = this.getFieldValue('callAgainDateTime');
            const coachNote = this.getFieldValue('coachNote');
            const meetingType = this.getFieldValue('meetingType');
            
            const formData = {
                id: this.model.id,
                outcome: outcome,
                callAgainDateTime: this.formatDateTime(callAgainDateTime),
                coachNote: coachNote || null,
                meetingType: meetingType || null
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
            const callAgainDateTime = this.getFieldValue('callAgainDateTime');
            const meetingType = this.getFieldValue('meetingType');

            if (!ValidationUtils.validateRequired(outcome, 'Selecteer een uitkomst.')) {
                return false;
            }

            if (outcome === 'invited') {
                if (!ValidationUtils.validateRequired(meetingType, 'Type afspraak is verplicht voor een uitnodiging.')) {
                    return false;
                }

                if (!this.validateMeetingSelection()) {
                    return false;
                }
            }

            if (outcome === 'call_again') {
                if (!ValidationUtils.validateCallAgainDateTime(callAgainDateTime, true)) {
                    return false;
                }
            }

            return true;
        }
    });

    _.extend(Dep.prototype, MeetingSchedulerMixin);

    return LogMessageOutcomeView;
});