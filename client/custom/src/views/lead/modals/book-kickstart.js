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
        calendarType: 'kickstart',
        // Online kickstarts follow the exact same flow as regular kickstarts
        calendarTypes: ['kickstart', 'online'],
        meetingLabel: 'Kickstart',

        setup: function () {
            Dep.prototype.setup.call(this);
            this.initializeMeetingScheduler();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.showMeetingScheduler();
        },

        getSaveButtonLabel: function() {
            return `${this.meetingLabel} boeken`;
        },

        getHeaderText: function() {
            return `${this.meetingLabel} Inplannen`;
        },

        loadBookableCalendars: function() {
            const $calSelect = this.$el.find('[name="selectedCalendar"]');
            const $calField = this.$el.find('[data-field="calendar-select"]');
            $calSelect.prop('disabled', true).empty().append('<option value="">Laden...</option>');

            Espo.Ajax.getRequest('calendar/bookable-list')
                .then(response => {
                    $calSelect.empty().append('<option value="">-- Kies Agenda --</option>');

                    const allowedTypes = this.getAllowedCalendarTypes();
                    const calendars = response.filter(cal => allowedTypes.includes(cal.type));
                    if (calendars.length === 0) {
                        Espo.Ui.error(`Geen ${this.meetingLabel} agenda beschikbaar.`);
                        $calSelect.prop('disabled', true);
                        return;
                    }

                    if (calendars.length > 1) {
                        calendars.forEach(cal => {
                            $calSelect.append($('<option>').val(cal.id).text(cal.name));
                        });
                        $calSelect.prop('disabled', false);
                        $calField.show();
                        return;
                    }

                    const selected = calendars[0];
                    $calSelect.append($('<option>').val(selected.id).text(selected.name));
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

        getAllowedCalendarTypes: function() {
            return this.calendarTypes || [this.calendarType];
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
            if (!this.validateMeetingSelection(`Kies een ${this.meetingLabel} agenda en tijdstip.`)) {
                return false;
            }

            return true;
        }
    });

    _.extend(Dep.prototype, MeetingSchedulerMixin);

    return BookKickstartView;
});
