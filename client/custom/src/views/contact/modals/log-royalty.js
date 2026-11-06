/**
 * Log the RI of a client (Contact), booked after HOM outcome C/D,
 * or follow up an RI in doubt. The result is kept in the RI status.
 */
define('custom:views/contact/modals/log-royalty', [
    'custom:views/lead/modals/base-lead-event-modal',
    'custom:mixins/meeting-scheduler-mixin',
    'custom:utils/form-validation-utils'
], function (Dep, MeetingSchedulerMixin, ValidationUtils) {

    const OUTCOMES = {
        doubt: 'Twijfel',
        not_interested: 'Geen interesse',
        distributor: 'Distributeur geworden',
        no_show: 'Niet opgedaagd',
        cancelled: 'Geannuleerd'
    };

    const LogContactRoyaltyView = Dep.extend({

        template: 'custom:contact/modals/log-royalty',
        apiEndpoint: 'contactHom/logRoyalty',
        successMessage: 'RI opgeslagen.',
        errorMessage: 'RI opslaan mislukt.',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.initializeMeetingScheduler();

            this.events['change [name="outcome"]'] = 'handleOutcomeChange';
            this.events['change [name="cancellationAction"]'] = 'handleCancellationActionChange';
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            const $outcome = this.$el.find('[name="outcome"]');
            this.getAllowedOutcomes().forEach(key => {
                $outcome.append(`<option value="${key}">${OUTCOMES[key]}</option>`);
            });

            this.handleOutcomeChange();
        },

        isFollowUp: function() {
            return this.model.get('cRiStatus') === 'doubt';
        },

        getAllowedOutcomes: function() {
            return this.isFollowUp()
                ? ['not_interested', 'distributor']
                : ['doubt', 'not_interested', 'distributor', 'no_show', 'cancelled'];
        },

        getSaveButtonLabel: function() {
            return 'Opslaan';
        },

        getHeaderText: function() {
            return this.isFollowUp() ? 'RI Opvolging' : 'Log RI';
        },

        isNotHeld: function(outcome) {
            return outcome === 'no_show' || outcome === 'cancelled';
        },

        handleOutcomeChange: function() {
            const outcome = this.getFieldValue('outcome');

            this.hideField('cancellation-action');
            this.hideField('booking-container');
            this.hideMeetingScheduler();
            this.$el.find('[name="cancellationAction"]').val('');

            if (this.isNotHeld(outcome)) {
                this.showField('cancellation-action');
            }
        },

        handleCancellationActionChange: function() {
            this.hideField('booking-container');
            this.hideMeetingScheduler();

            if (this.getFieldValue('cancellationAction') === 'reschedule_now') {
                this.showField('booking-container');
                this.showMeetingScheduler();
            }
        },

        loadBookableCalendars: function() {
            const $calSelect = this.$el.find('[name="selectedCalendar"]');
            $calSelect.prop('disabled', true).empty().append('<option value="">Laden...</option>');

            Espo.Ajax.getRequest('calendar/bookable-list')
                .then(response => {
                    $calSelect.empty().append('<option value="">-- Kies Agenda --</option>');

                    response
                        .filter(cal => cal.type === 'royalty')
                        .forEach(cal => {
                            $calSelect.append(`<option value="${cal.id}">${cal.name}</option>`);
                        });

                    $calSelect.prop('disabled', false);
                })
                .catch(e => {
                    console.error('Failed to load bookable calendars:', e);
                    $calSelect.empty().append('<option value="">Fout bij laden</option>');
                });
        },

        getFormData: function() {
            const outcome = this.getFieldValue('outcome');
            const action = this.getFieldValue('cancellationAction') || null;

            const formData = {
                id: this.model.id,
                outcome: outcome,
                cancellationAction: this.isNotHeld(outcome) ? action : null,
                coachNote: this.getFieldValue('coachNote') || null
            };

            if (this.isNotHeld(outcome) && action === 'reschedule_now') {
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

            if (!ValidationUtils.validateRequired(outcome, 'Selecteer een uitkomst.')) {
                return false;
            }

            if (this.isNotHeld(outcome)) {
                const action = this.getFieldValue('cancellationAction');

                if (!ValidationUtils.validateRequired(action, 'Kies of er een nieuwe RI ingepland wordt.')) {
                    return false;
                }

                if (action === 'reschedule_now' && !this.validateMeetingSelection('Kies een RI agenda en tijdstip.')) {
                    return false;
                }
            }

            return true;
        }
    });

    _.extend(Dep.prototype, MeetingSchedulerMixin);

    return LogContactRoyaltyView;
});
