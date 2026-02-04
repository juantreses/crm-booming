define('custom:views/lead/modals/log-kickstart', ['views/modal', 'custom:utils/date-utils'], function (Dep, DateUtils) {
    
    return Dep.extend({
        
        template: 'custom:lead/modals/log-kickstart',
        
        className: 'dialog dialog-record',
        
        setup: function () {
            this.buttonList = [
                {
                    name: 'save',
                    label: 'Log Kickstart',
                    style: 'primary'
                },
                {
                    name: 'cancel',
                    label: 'Annuleer'
                }
            ];
            
            this.headerText = 'Log Kickstart - ' + this.model.get('name');

            this.events['change [name="outcome"]'] = 'handleOutcomeChange';
            this.events['change [name="cancellationAction"]'] = 'handleCancellationActionChange';
            this.events['change [name="selectedCalendar"]'] = 'handleCalendarChange';
        },

        afterRender: function () {
			Dep.prototype.afterRender.call(this);
            this.handleOutcomeChange();
		},

        handleOutcomeChange: function() {
            const outcome = this.$el.find('[name="outcome"]').val();
            
            const $cancelActionDiv = this.$el.find('[data-field="cancellation-action"]');
            const $callAgainField = this.$el.find('[data-field="call-again-date"]');
            const $rescheduleContainer = this.$el.find('[data-field="reschedule-container"]');
            
            $cancelActionDiv.hide();
            $rescheduleContainer.hide();

            if (outcome === 'cancelled') {
                $cancelActionDiv.show();
                $callAgainField.hide(); 
                this.$el.find('[name="cancellationAction"]').val('');
            } 
            else if (outcome === 'still_thinking' || outcome === 'no_show') {
                $callAgainField.show();
                $cancelActionDiv.hide();
            } 
            else {
                $callAgainField.hide();
                $cancelActionDiv.hide();
            }
        },

        handleCancellationActionChange: function() {
            const action = this.$el.find('[name="cancellationAction"]').val();
            const $rescheduleContainer = this.$el.find('[data-field="reschedule-container"]');
            const $callAgainDiv = this.$el.find('[data-field="call-again-date"]');

            $rescheduleContainer.hide();
            $callAgainDiv.hide();

            if (action === 'reschedule_now') {
                $rescheduleContainer.show();
                this.loadBookableCalendars();
            } else if (action === 'reschedule_later') {
                $callAgainDiv.show();
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
            const $outcomeField = this.$el.find('[name="outcome"]');
            const outcome = $outcomeField.val();
            const cancellationAction = this.$el.find('[name="cancellationAction"]').val();
            const kickstartDateTime = this.$el.find('[name="kickstartDateTime"]').val();
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

            if (outcome === 'cancelled') {
                if (!cancellationAction) {
                    Espo.Ui.error('Kies een vervolgactie voor de annulering.');
                    saveButton.prop('disabled', false);
                    return;
                }
                if (cancellationAction === 'reschedule_now' && !selectedSlot) {
                    Espo.Ui.error('Nieuwe Kickstart datum is verplicht.');
                    saveButton.prop('disabled', false);
                    return;
                }
                if (cancellationAction === 'reschedule_later' && !callAgainDateTime) {
                    Espo.Ui.error('Datum opnieuw bellen is verplicht.');
                    saveButton.prop('disabled', false);
                    return;
                }
            }

            if (outcome === 'still_thinking' && !callAgainDateTime) {
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

            if (kickstartDateTime) {
                const now = new Date();
                const ksDate = new Date(kickstartDateTime);
                if (ksDate > now) {
                    Espo.Ui.error('Datum/tijd van kickstart mag niet in de toekomst zijn.');
                    saveButton.prop('disabled', false);
                    return;
                }
            }
            
            Espo.Ajax.postRequest(`leadEvent/logKickstart`, {
                id: this.model.id,
                outcome: outcome,
                cancellationAction: cancellationAction,
                kickstartDateTime: kickstartDateTime ? DateUtils.toOffsetISOString(new Date(kickstartDateTime)) : null,
                callAgainDateTime: callAgainDateTime ? DateUtils.toOffsetISOString(new Date(callAgainDateTime)) : null,
                coachNote: coachNote || null,
                calendarId: calendarId,
                selectedDate: slotDate,
                selectedTime: slotTime
            }).then((response) => {
                this.trigger('success', response);
                this.close();
            }).catch(() => {
                Espo.Ui.error('Failed to log kickstart');
                saveButton.prop('disabled', false);
            });
        }
    });
});