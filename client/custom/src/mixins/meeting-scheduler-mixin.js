/**
 * MeetingSchedulerMixin
 * 
 * Provides calendar and time slot selection functionality.
 * Handles loading bookable calendars and their available time slots.
 * 
 * Usage:
 *   _.extend(YourView.prototype, MeetingSchedulerMixin);
 * 
 * Required DOM structure:
 *   - [name="selectedCalendar"] - Calendar dropdown
 *   - [name="selectedSlot"] - Time slot dropdown
 *   - [data-field="calendar-select"] - Container for calendar field
 *   - [data-field="meeting-slot"] - Container for slot field
 */
define('custom:mixins/meeting-scheduler-mixin', [], function () {

    const MeetingSchedulerMixin = {

        /**
         * Initialize the meeting scheduler
         * Call this in your view's setup() or afterRender()
         */
        initializeMeetingScheduler: function() {
            this.events = this.events || {};
            this.events['change [name="selectedCalendar"]'] = 'handleCalendarChange';
        },

        /**
         * Show the calendar/slot selection UI
         */
        showMeetingScheduler: function() {
            const $calDiv = this.$el.find('[data-field="calendar-select"]');
            $calDiv.show();
            this.loadBookableCalendars();
        },

        /**
         * Hide the calendar/slot selection UI and reset values
         */
        hideMeetingScheduler: function() {
            const $calDiv = this.$el.find('[data-field="calendar-select"]');
            const $slotDiv = this.$el.find('[data-field="meeting-slot"]');
            
            $calDiv.hide();
            $slotDiv.hide();
            
            this.$el.find('[name="selectedCalendar"]').val('');
            this.$el.find('[name="selectedSlot"]').val('');
        },

        /**
         * Load available bookable calendars from the API
         */
        loadBookableCalendars: function() {
            const $calSelect = this.$el.find('[name="selectedCalendar"]');
            $calSelect.prop('disabled', true).empty().append('<option value="">Laden...</option>');

            Espo.Ajax.getRequest('calendar/bookable-list')
                .then(response => {
                    $calSelect.empty().append('<option value="">-- Kies Agenda --</option>');
                    
                    response.forEach(cal => {
                        $calSelect.append(`<option value="${cal.id}">${cal.name}</option>`);
                    });

                    $calSelect.prop('disabled', false);
                })
                .catch(e => {
                    console.error('Failed to load bookable calendars:', e);
                    $calSelect.empty().append('<option value="">Fout bij laden</option>');
                });
        },

        /**
         * Handle calendar selection change
         * Loads available time slots for the selected calendar
         */
        handleCalendarChange: function() {
            const calendarIdentifier = this.$el.find('[name="selectedCalendar"]').val();
            const $slotDiv = this.$el.find('[data-field="meeting-slot"]');
            const $slotSelect = this.$el.find('[name="selectedSlot"]');

            $slotSelect.empty().append('<option value="">Laden...</option>');
            
            if (!calendarIdentifier) {
                $slotDiv.hide();
                return;
            }

            $slotDiv.show();
            $slotSelect.prop('disabled', true);

            Espo.Ajax.getRequest('calendar/upcoming-slots', { id: calendarIdentifier, coach: this.model.assigneUserId })
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
                    console.error('Failed to load calendar slots:', e);
                    $slotSelect.empty().append('<option value="">Fout bij laden</option>');
                    $slotSelect.prop('disabled', false);
                });
        },

        /**
         * Get the currently selected calendar and slot data
         * @returns {Object|null} { calendarId, slotDate, slotTime } or null if nothing selected
         */
        getSelectedMeetingData: function() {
            const calendarId = this.$el.find('[name="selectedCalendar"]').val();
            const selectedSlot = this.$el.find('[name="selectedSlot"]').val();

            if (!calendarId || !selectedSlot) {
                return null;
            }

            const parts = selectedSlot.split(' ');
            return {
                calendarId: calendarId,
                slotDate: parts[0],
                slotTime: parts[1]
            };
        },

        /**
         * Validate that calendar and slot are selected
         * @param {string} errorMessage - Custom error message
         * @returns {boolean} true if valid
         */
        validateMeetingSelection: function(errorMessage) {
            const calendarId = this.$el.find('[name="selectedCalendar"]').val();
            const selectedSlot = this.$el.find('[name="selectedSlot"]').val();

            if (!calendarId) {
                Espo.Ui.error(errorMessage || 'Selecteer een agenda om de afspraak in te boeken.');
                return false;
            }

            if (!selectedSlot) {
                Espo.Ui.error(errorMessage || 'Kies een beschikbaar tijdstip voor de afspraak.');
                return false;
            }

            return true;
        }
    };

    return MeetingSchedulerMixin;
});