define('custom:views/lead/modals/log-kickstart', [
    'custom:views/lead/modals/base-lead-event-modal',
    'custom:mixins/meeting-scheduler-mixin',
    'custom:utils/form-validation-utils'
], function (Dep, MeetingSchedulerMixin, ValidationUtils) {
    
    const LogKickstartView = Dep.extend({
        
        template: 'custom:lead/modals/log-kickstart',
        apiEndpoint: 'leadEvent/logKickstart',
        successMessage: 'Kickstart logged successfully',
        errorMessage: 'Failed to log kickstart',

        setup: function () {
            Dep.prototype.setup.call(this);
            
            this.initializeMeetingScheduler();
            
            this.events['change [name="outcome"]'] = 'handleOutcomeChange';
            this.events['change [name="cancellationAction"]'] = 'handleCancellationActionChange';
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.handleOutcomeChange();
        },

        getSaveButtonLabel: function() {
            return 'Log Kickstart';
        },

        getHeaderText: function() {
            return 'Log Kickstart';
        },

        handleOutcomeChange: function() {
            const outcome = this.getFieldValue('outcome');
            
            this.hideField('cancellation-action');
            this.hideField('reschedule-container');
            this.hideField('call-again-date');

            if (outcome === 'cancelled') {
                this.showField('cancellation-action');
                this.$el.find('[name="cancellationAction"]').val('');
            } else if (outcome === 'still_thinking' || outcome === 'no_show') {
                this.showField('call-again-date');
            }
        },

        handleCancellationActionChange: function() {
            const action = this.getFieldValue('cancellationAction');
            
            this.hideField('reschedule-container');
            this.hideField('call-again-date');

            if (action === 'reschedule_now') {
                this.showField('reschedule-container');
                this.showMeetingScheduler();
            } else if (action === 'reschedule_later') {
                this.showField('call-again-date');
            }
        },

        getFormData: function() {
            const outcome = this.getFieldValue('outcome');
            const cancellationAction = this.getFieldValue('cancellationAction');
            const kickstartDateTime = this.getFieldValue('kickstartDateTime');
            const callAgainDateTime = this.getFieldValue('callAgainDateTime');
            const coachNote = this.getFieldValue('coachNote');
            
            const formData = {
                id: this.model.id,
                outcome: outcome,
                cancellationAction: cancellationAction,
                kickstartDateTime: this.formatDateTime(kickstartDateTime),
                callAgainDateTime: this.formatDateTime(callAgainDateTime),
                coachNote: coachNote || null
            };

            if (outcome === 'cancelled' && cancellationAction === 'reschedule_now') {
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
            const cancellationAction = this.getFieldValue('cancellationAction');
            const kickstartDateTime = this.getFieldValue('kickstartDateTime');
            const callAgainDateTime = this.getFieldValue('callAgainDateTime');

            if (!ValidationUtils.validateRequired(outcome, 'Selecteer een uitkomst.')) {
                return false;
            }

            if (outcome === 'cancelled') {
                if (!ValidationUtils.validateRequired(cancellationAction, 'Kies een vervolgactie voor de annulering.')) {
                    return false;
                }

                if (cancellationAction === 'reschedule_now') {
                    if (!this.validateMeetingSelection('Nieuwe Kickstart datum is verplicht.')) {
                        return false;
                    }
                }

                if (cancellationAction === 'reschedule_later') {
                    if (!ValidationUtils.validateCallAgainDateTime(callAgainDateTime, true)) {
                        return false;
                    }
                }
            }

            if (outcome === 'still_thinking') {
                if (!ValidationUtils.validateCallAgainDateTime(callAgainDateTime, true)) {
                    return false;
                }
            }

            if (!ValidationUtils.validateEventDateTime(kickstartDateTime, 'kickstart')) {
                return false;
            }

            return true;
        },

        handleSuccess: function(response) {
            this.trigger('success', response);
            this.close();

            if (response && response.data && response.data.eventIds) {
                const eventCount = response.data.eventIds.length;
                Espo.Ui.success(`Kickstart logged successfully. Created ${eventCount} event(s).`);
            } else {
                Espo.Ui.success(this.successMessage);
            }
        }
    });

    _.extend(Dep.prototype, MeetingSchedulerMixin);

    return LogKickstartView;
});