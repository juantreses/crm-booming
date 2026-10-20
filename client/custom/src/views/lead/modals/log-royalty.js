define('custom:views/lead/modals/log-royalty', [
    'custom:views/lead/modals/log-kickstart'
], function (Dep) {

    return Dep.extend({

        template: 'custom:lead/modals/log-royalty',
        apiEndpoint: 'leadEvent/logRoyalty',
        successMessage: 'RI logged successfully',
        errorMessage: 'Failed to log RI',
        meetingLabel: 'RI',

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
        }
    });
});
