/**
 * BookWorkoutView
 *
 * There is only one workout calendar, so unlike other booking modals
 * (Kickstart, Intro Meeting, ...) this view has no calendar picker:
 * it resolves the workout calendar on the backend and only lets the
 * user pick a time slot.
 */
define('custom:views/shared/modals/book-workout', [
    'custom:views/lead/modals/base-lead-event-modal'
], function (Dep) {

    const BookWorkoutView = Dep.extend({
        template: 'custom:shared/modals/book-workout',
        apiEndpoint: 'booking/book-workout',
        successMessage: 'Workout geboekt.',
        errorMessage: 'Workout boeken mislukt.',

        workoutCalendarId: null,

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            // SPARK cup is de enige workout die voor leads geboekt kan worden.
            if (this.model.entityType === 'Lead') {
                this.hideField('spark-cup');
            }

            this.loadSlots();
        },

        getSaveButtonLabel: function () {
            return 'Workout boeken';
        },

        getHeaderText: function () {
            return 'Workout Inplannen';
        },

        loadSlots: function () {
            const $slotSelect = this.$el.find('[name="selectedSlot"]');
            $slotSelect.prop('disabled', true).empty().append('<option value="">Laden...</option>');

            Espo.Ajax.getRequest('calendar/type/workout')
                .then(calendar => {
                    this.workoutCalendarId = calendar.id;

                    return Espo.Ajax.getRequest('calendar/upcoming-slots', {
                        id: calendar.id,
                        coach: this.model.assigneUserId
                    });
                })
                .then(response => {
                    $slotSelect.empty().append('<option value="">-- Kies tijdstip --</option>');

                    Object.keys(response).forEach(date => {
                        const rawSlots = response[date];
                        const validSlots = rawSlots.filter(slot => slot.isBookable && !slot.isBlocked);

                        if (validSlots.length === 0) return;

                        const dateObj = new Date(date);
                        const groupLabel = dateObj.toLocaleDateString('nl-BE', {
                            weekday: 'long',
                            day: 'numeric',
                            month: 'long'
                        });
                        const shortDate = dateObj.toLocaleDateString('nl-BE', {
                            day: 'numeric',
                            month: 'short'
                        });

                        const $optgroup = $(`<optgroup label="${groupLabel}"></optgroup>`);

                        validSlots.forEach(slot => {
                            const label = `${shortDate} | ${slot.start} - ${slot.end}`;
                            $optgroup.append(`<option value="${date} ${slot.start}">${label}</option>`);
                        });

                        $slotSelect.append($optgroup);
                    });

                    $slotSelect.prop('disabled', false);
                })
                .catch(e => {
                    console.error('Failed to load workout calendar slots:', e);
                    $slotSelect.empty().append('<option value="">Geen workout agenda beschikbaar</option>');
                });
        },

        getSelectedSlot: function () {
            const selectedSlot = this.$el.find('[name="selectedSlot"]').val();

            if (!this.workoutCalendarId || !selectedSlot) {
                return null;
            }

            const parts = selectedSlot.split(' ');
            return {
                calendarId: this.workoutCalendarId,
                slotDate: parts[0],
                slotTime: parts[1]
            };
        },

        getFormData: function () {
            const coachNote = this.getFieldValue('coachNote');
            const selected = this.getSelectedSlot();
            const isLead = this.model.entityType === 'Lead';

            return {
                entityType: this.model.entityType,
                entityId: this.model.id,
                selectedDate: selected ? selected.slotDate : null,
                selectedTime: selected ? selected.slotTime : null,
                coachNote: coachNote || null,
                isSparkCup: isLead || this.$el.find('[name="isSparkCup"]').prop('checked')
            };
        },

        validateForm: function () {
            if (!this.getSelectedSlot()) {
                Espo.Ui.error('Kies een beschikbaar tijdstip voor de afspraak.');
                return false;
            }

            return true;
        }
    });

    return BookWorkoutView;
});
