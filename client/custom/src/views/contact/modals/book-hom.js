define('custom:views/contact/modals/book-hom', [
    'custom:views/lead/modals/book-kickstart'
], function (Dep) {

    return Dep.extend({

        template: 'custom:contact/modals/book-hom',
        apiEndpoint: 'contactHom/book',
        successMessage: 'HOM geboekt.',
        errorMessage: 'HOM boeken mislukt.',
        calendarType: 'hom',
        meetingLabel: 'HOM',

        loadBookableCalendars: function() {
            const $calSelect = this.$el.find('[name="selectedCalendar"]');
            const $calField = this.$el.find('[data-field="calendar-select"]');
            $calSelect.prop('disabled', true).empty().append('<option value="">Laden...</option>');

            Espo.Ajax.getRequest('calendar/bookable-list')
                .then(response => {
                    const calendars = response.filter(cal => cal.type === 'hom' || cal.type === 'iom');

                    $calSelect.empty().append('<option value="">-- Kies Agenda --</option>');

                    if (calendars.length === 0) {
                        Espo.Ui.error('Geen HOM agenda beschikbaar.');
                        return;
                    }

                    calendars.forEach(cal => {
                        $calSelect.append(`<option value="${cal.id}">${cal.name}</option>`);
                    });
                    $calSelect.prop('disabled', false);

                    // Only one HOM calendar: no need to choose
                    if (calendars.length === 1) {
                        $calSelect.val(calendars[0].id);
                        $calField.hide();
                        this.handleCalendarChange();
                    }
                })
                .catch(e => {
                    console.error('Failed to load bookable calendars:', e);
                    $calSelect.empty().append('<option value="">Fout bij laden</option>');
                });
        }
    });
});
