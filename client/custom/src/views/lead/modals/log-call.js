define('custom:views/lead/modals/log-call', ['views/modal', 'custom:utils/date-utils'], function (Dep, DateUtils) {
    
    return Dep.extend({
        
        template: 'custom:lead/modals/log-call',
        
        className: 'dialog dialog-record',
        
        setup: function () {
            this.buttonList = [
                {
                    name: 'save',
                    label: 'Log Gesprek',
                    style: 'primary'
                },
                {
                    name: 'cancel',
                    label: 'Annuleer'
                }
            ];
            
            this.events['change [name="outcome"]'] = 'handleOutcomeChange';
            this.events['change [name="selectedCalendar"]'] = 'handleCalendarChange';
        },

        handleOutcomeChange: function() {
            const outcome = this.$el.find('[name="outcome"]').val();
            
            const $callAgainField = this.$el.find('[data-field="call-again-date"]');
            const $calDiv = this.$el.find('[data-field="calendar-select"]');
            const $slotDiv = this.$el.find('[data-field="meeting-slot"]');
            
            if (outcome === 'call_again') {
                $callAgainField.show();
            } else {
                $callAgainField.hide();
            }
        
            if (outcome === 'invited') {
                $calDiv.show();
                this.loadBookableCalendars();
            } else {
                $calDiv.hide();
                $slotDiv.hide();
                this.$el.find('[name="selectedCalendar"]').val('');
                this.$el.find('[name="selectedSlot"]').val('');
            }
        },
        
        loadBookableCalendars: function() {
            const $calSelect = this.$el.find('[name="selectedCalendar"]');
            $calSelect.prop('disabled', true).empty().append('<option value="">Laden...</option>');
        
            Espo.Ajax.getRequest('calendar/bookable-list')
                .then(response => {
                    $calSelect.empty().append('<option value="">-- Kies Agenda --</option>');
                    
                    response.forEach(cal => {
                        $calSelect.append(`<option value="${cal.id}">${cal.name}</option>`)
                    });
        
                    $calSelect.prop('disabled', false);
                })
                .catch(e => {
                    console.error(e);
                    $calSelect.empty().append('<option value="">Fout bij laden</option>');
                });
        },
        
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

            Espo.Ajax.getRequest('calendar/upcoming-slots', { id: calendarIdentifier })
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
                });
        },
        
        actionSave: function () {
            const outcome = this.$el.find('[name="outcome"]').val();
            const callDateTime = this.$el.find('[name="callDateTime"]').val();
            const callAgainDateTime = this.$el.find('[name="callAgainDateTime"]').val();
            const coachNote = this.$el.find('[name="coachNote"]').val();
            const calendarId = this.$el.find('[name="selectedCalendar"] option:selected').val();
            const selectedSlot = this.$el.find('[name="selectedSlot"]').val();

            let slotDate = null;
            let slotTime = null;

            if (selectedSlot) {
                const parts = selectedSlot.split(' ');
                slotDate = parts[0];
                slotTime = parts[1];
            }

            const saveButton = this.$el.find('button[data-name="save"]');
            saveButton.prop('disabled', true);
        
            if (!outcome) {
                Espo.Ui.error('Selecteer een uitkomst.');
                saveButton.prop('disabled', false);
                return;
            }

            if (outcome === 'invited') {
                if (!calendarId) {
                    Espo.Ui.error('Selecteer een agenda om de afspraak in te boeken.');
                    saveButton.prop('disabled', false);
                    return;
                }
            
                if (!selectedSlot) {
                    Espo.Ui.error('Kies een beschikbaar tijdstip voor de afspraak.');
                    saveButton.prop('disabled', false);
                    return;
                }
            }

            if (outcome === 'call_again' && !callAgainDateTime) {
				if (!callAgainDateTime) {
                    Espo.Ui.error('Datum/tijd opnieuw bellen is verplicht.');
                    saveButton.prop('disabled', false);
                    return;
                }
                const now = new Date();
                const callAgainDate = new Date(callAgainDateTime);
                if (callAgainDate <= now) {
                    Espo.Ui.error('Datum/tijd opnieuw bellen moet in de toekomst zijn.');
                    saveButton.prop('disabled', false);
                    return;
                }
			}

            if (callDateTime) {
                const now = new Date();
                const callDate = new Date(callDateTime);
                if (callDate > now) {
                    Espo.Ui.error('Datum/tijd van gesprek mag niet in de toekomst zijn.');
                    saveButton.prop('disabled', false);
                    return;
                }
            }
            
            Espo.Ajax.postRequest(`leadEvent/logCall`, {
                id: this.model.id,
		        outcome: outcome,
                callDateTime: callDateTime ? DateUtils.toOffsetISOString(new Date(callDateTime)) : null,
                callAgainDateTime: callAgainDateTime ? DateUtils.toOffsetISOString(new Date(callAgainDateTime)) : null,
                coachNote: coachNote || null,
                calendarId: calendarId,
                selectedDate: slotDate,
                selectedTime: slotTime
            }).then(() => {
                this.trigger('success');
                this.close();
            }).catch(() => {
                Espo.Ui.error('Failed to log call');
                saveButton.prop('disabled', false);
            });
        }
    });
});
