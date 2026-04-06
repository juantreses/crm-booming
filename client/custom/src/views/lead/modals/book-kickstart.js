define('custom:views/lead/modals/book-kickstart', [
    'custom:views/lead/modals/base-lead-event-modal',
    'custom:mixins/meeting-scheduler-mixin',
    'custom:utils/form-validation-utils'
], function (Dep, MeetingSchedulerMixin, ValidationUtils) {

    const BookKickstartView = Dep.extend({
        template: 'custom:lead/modals/book-kickstart',
        apiEndpoint: 'leadEvent/bookKickstart',
        successMessage: 'Kickstart geboekt.',
        errorMessage: 'Kickstart boeken mislukt.',

        setup: function () {
            Dep.prototype.setup.call(this);
            this.initializeMeetingScheduler();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.showMeetingScheduler();
        },

        getSaveButtonLabel: function() {
            return 'Kickstart boeken';
        },

        getHeaderText: function() {
            return 'Kickstart Inplannen';
        },

        loadBookableCalendars: function() {
            const $calSelect = this.$el.find('[name="selectedCalendar"]');
            const $calField = this.$el.find('[data-field="calendar-select"]');
            $calSelect.prop('disabled', true).empty().append('<option value="">Laden...</option>');

            Espo.Ajax.getRequest('calendar/bookable-list')
                .then(response => {
                    $calSelect.empty().append('<option value="">-- Kies Agenda --</option>');

                    const kickstartCalendars = response.filter(cal => cal.type === 'kickstart');
                    if (kickstartCalendars.length === 0) {
                        Espo.Ui.error('Geen Kickstart agenda beschikbaar.');
                        $calSelect.prop('disabled', true);
                        return;
                    }

                    const selected = kickstartCalendars[0];
                    $calSelect.append(`<option value="${selected.id}">${selected.name}</option>`);
                    $calSelect.val(selected.id);
                    $calSelect.prop('disabled', true);
                    $calField.hide();

                    this.handleCalendarChange();
                })
                .catch(e => {
                    console.error('Failed to load bookable calendars:', e);
                    $calSelect.empty().append('<option value="">Fout bij laden</option>');
                });
        },

        getFormData: function() {
            const coachNote = this.getFieldValue('coachNote');
            const meetingData = this.getSelectedMeetingData();

            return {
                id: this.model.id,
                calendarId: meetingData ? meetingData.calendarId : null,
                selectedDate: meetingData ? meetingData.slotDate : null,
                selectedTime: meetingData ? meetingData.slotTime : null,
                coachNote: coachNote || null
            };
        },

        validateForm: function() {
            if (!this.validateMeetingSelection('Kies een Kickstart agenda en tijdstip.')) {
                return false;
            }

            return true;
        }
    });

    _.extend(Dep.prototype, MeetingSchedulerMixin);

    return BookKickstartView;
});
