/**
 * Log a HOM for a client (Contact).
 * Outcome A redeems savings card points, B makes the client PCX or distributor,
 * C/D book a Royalty (RI) meeting right away.
 */
define('custom:views/contact/modals/log-hom', [
    'custom:views/lead/modals/base-lead-event-modal',
    'custom:mixins/meeting-scheduler-mixin',
    'custom:utils/form-validation-utils'
], function (Dep, MeetingSchedulerMixin, ValidationUtils) {

    const LogContactHomView = Dep.extend({

        template: 'custom:contact/modals/log-hom',
        apiEndpoint: 'contactHom/log',
        successMessage: 'HOM opgeslagen.',
        errorMessage: 'HOM opslaan mislukt.',

        // 'reschedule' books a new HOM, 'royalty' books the RI after C/D
        bookingMode: null,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.initializeMeetingScheduler();

            this.events['change [name="outcome"]'] = 'handleOutcomeChange';
            this.events['change [name="cancellationAction"]'] = 'handleCancellationActionChange';
            this.events['change [name="homRating"]'] = 'handleHomRatingChange';
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.$el.find('[data-role="savings-balance"]').text(this.model.get('cSavingsPointsBalance') || 0);
            this.handleOutcomeChange();
        },

        getSaveButtonLabel: function() {
            return 'Log HOM';
        },

        getHeaderText: function() {
            return 'Log HOM';
        },

        homRatingLeadsToRoyalty: function(rating) {
            return rating === 'C' || rating === 'D';
        },

        resetBooking: function() {
            this.bookingMode = null;
            this.hideField('booking-container');
            this.hideMeetingScheduler();
        },

        startBooking: function(mode) {
            this.bookingMode = mode;
            this.$el.find('[data-role="booking-label"]').text(mode === 'royalty' ? 'RI-afspraak *' : 'Nieuwe HOM *');
            this.showField('booking-container');
            this.showMeetingScheduler();
        },

        handleOutcomeChange: function() {
            const outcome = this.getFieldValue('outcome');

            this.hideField('cancellation-action');
            this.hideField('hom-rating');
            this.hideField('client-type');
            this.hideField('redeem-info');
            this.resetBooking();
            this.$el.find('[name="cancellationAction"]').val('');
            this.$el.find('[name="homRating"]').val('');
            this.$el.find('[name="clientType"]').val('');

            if (outcome === 'cancelled') {
                this.showField('cancellation-action');
            } else if (outcome === 'attended') {
                this.showField('hom-rating');
            }
        },

        handleCancellationActionChange: function() {
            this.resetBooking();

            if (this.getFieldValue('cancellationAction') === 'reschedule_now') {
                this.startBooking('reschedule');
            }
        },

        handleHomRatingChange: function() {
            const rating = this.getFieldValue('homRating');

            this.hideField('client-type');
            this.hideField('redeem-info');
            this.resetBooking();

            if (rating === 'A') {
                this.showField('redeem-info');
            } else if (rating === 'B') {
                this.showField('client-type');
            } else if (this.homRatingLeadsToRoyalty(rating)) {
                this.startBooking('royalty');
            }
        },

        loadBookableCalendars: function() {
            const $calSelect = this.$el.find('[name="selectedCalendar"]');
            const allowed = this.bookingMode === 'royalty' ? ['royalty'] : ['hom', 'iom'];
            $calSelect.prop('disabled', true).empty().append('<option value="">Laden...</option>');

            Espo.Ajax.getRequest('calendar/bookable-list')
                .then(response => {
                    $calSelect.empty().append('<option value="">-- Kies Agenda --</option>');

                    response
                        .filter(cal => allowed.includes(cal.type))
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
            const meetingData = this.bookingMode ? this.getSelectedMeetingData() : null;

            const formData = {
                id: this.model.id,
                outcome: outcome,
                cancellationAction: this.getFieldValue('cancellationAction') || null,
                homRating: outcome === 'attended' ? (this.getFieldValue('homRating') || null) : null,
                clientType: this.getFieldValue('clientType') || null,
                coachNote: this.getFieldValue('coachNote') || null
            };

            if (meetingData && this.bookingMode === 'reschedule') {
                formData.calendarId = meetingData.calendarId;
                formData.selectedDate = meetingData.slotDate;
                formData.selectedTime = meetingData.slotTime;
            }

            if (meetingData && this.bookingMode === 'royalty') {
                formData.riBooking = {
                    calendarId: meetingData.calendarId,
                    selectedDate: meetingData.slotDate,
                    selectedTime: meetingData.slotTime
                };
            }

            return formData;
        },

        validateForm: function() {
            const outcome = this.getFieldValue('outcome');
            const rating = this.getFieldValue('homRating');

            if (!ValidationUtils.validateRequired(outcome, 'Selecteer een uitkomst.')) {
                return false;
            }

            if (outcome === 'cancelled') {
                const action = this.getFieldValue('cancellationAction');

                if (!ValidationUtils.validateRequired(action, 'Kies een vervolgactie voor de annulering.')) {
                    return false;
                }

                if (action === 'reschedule_now' && !this.validateMeetingSelection('Kies een HOM agenda en tijdstip.')) {
                    return false;
                }
            }

            if (outcome === 'attended') {
                if (!ValidationUtils.validateRequired(rating, 'Kies de HOM-uitkomst (A/B/C/D).')) {
                    return false;
                }

                if (rating === 'B' && !ValidationUtils.validateRequired(this.getFieldValue('clientType'), 'Kies of de klant PCX of distributeur wordt.')) {
                    return false;
                }

                if (this.homRatingLeadsToRoyalty(rating) && !this.validateMeetingSelection('Kies een RI agenda en tijdstip.')) {
                    return false;
                }
            }

            return true;
        }
    });

    _.extend(Dep.prototype, MeetingSchedulerMixin);

    return LogContactHomView;
});
