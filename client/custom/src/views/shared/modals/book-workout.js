define('custom:views/shared/modals/book-workout', [
    'custom:views/lead/modals/base-lead-event-modal',
    'custom:mixins/meeting-scheduler-mixin',
    'custom:utils/form-validation-utils'
], function (Dep, MeetingSchedulerMixin, ValidationUtils) {

    const BookWorkoutView = Dep.extend({
        template: 'custom:shared/modals/book-workout',
        apiEndpoint: 'booking/book-workout',
        successMessage: 'Workout geboekt.',
        errorMessage: 'Workout boeken mislukt.',

        setup: function () {
            Dep.prototype.setup.call(this);
            this.initializeMeetingScheduler();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            // SPARK cup is de enige workout die voor leads geboekt kan worden.
            if (this.model.entityType === 'Lead') {
                this.hideField('spark-cup');
            }

            this.showMeetingScheduler();
        },

        getSaveButtonLabel: function () {
            return 'Workout boeken';
        },

        getHeaderText: function () {
            return 'Workout Inplannen';
        },

        loadBookableCalendars: function () {
            const $calSelect = this.$el.find('[name="selectedCalendar"]');
            $calSelect.prop('disabled', true).empty().append('<option value="">Laden...</option>');

            Espo.Ajax.getRequest('calendar/bookable-list')
                .then(response => {
                    $calSelect.empty().append('<option value="">-- Kies Agenda --</option>');

                    const workoutCalendars = response.filter(cal => cal.type === 'workout');
                    if (workoutCalendars.length === 0) {
                        Espo.Ui.error('Geen Workout agenda beschikbaar.');
                        $calSelect.prop('disabled', true);
                        return;
                    }

                    workoutCalendars.forEach(cal => {
                        $calSelect.append(`<option value="${cal.id}">${cal.name}</option>`);
                    });

                    $calSelect.prop('disabled', false);
                })
                .catch(e => {
                    console.error('Failed to load bookable calendars:', e);
                    $calSelect.empty().append('<option value="">Fout bij laden</option>');
                });
        },

        getFormData: function () {
            const coachNote = this.getFieldValue('coachNote');
            const meetingData = this.getSelectedMeetingData();
            const isLead = this.model.entityType === 'Lead';

            return {
                entityType: this.model.entityType,
                entityId: this.model.id,
                calendarId: meetingData ? meetingData.calendarId : null,
                selectedDate: meetingData ? meetingData.slotDate : null,
                selectedTime: meetingData ? meetingData.slotTime : null,
                coachNote: coachNote || null,
                isSparkCup: isLead || this.$el.find('[name="isSparkCup"]').prop('checked')
            };
        },

        validateForm: function () {
            if (!this.validateMeetingSelection('Kies een Workout agenda en tijdstip.')) {
                return false;
            }

            return true;
        }
    });

    _.extend(BookWorkoutView.prototype, MeetingSchedulerMixin);

    return BookWorkoutView;
});
