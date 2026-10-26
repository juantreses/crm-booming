define('custom:views/lead/modals/log-intro-meeting', [
    'custom:views/lead/modals/base-lead-event-modal',
    'custom:mixins/meeting-scheduler-mixin',
    'custom:utils/form-validation-utils'
], function (Dep, MeetingSchedulerMixin, ValidationUtils) {

    const LogIntroMeetingView = Dep.extend({
        template: 'custom:lead/modals/log-intro-meeting',
        apiEndpoint: 'leadEvent/logIntroMeeting',
        successMessage: 'Intro meeting logged successfully',
        errorMessage: 'Failed to log intro meeting',

        bookingMode: null,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.initializeMeetingScheduler();

            this.events['change [name="outcome"]'] = 'handleOutcomeChange';
            this.events['change [name="cancellationAction"]'] = 'handleCancellationActionChange';
            this.events['change [name="bookNextMeeting"]'] = 'handleBookNextMeetingChange';
            this.events['change [name="homRating"]'] = 'handleHomRatingChange';
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.updateIntroLabels();
            this.handleOutcomeChange();
        },

        getSaveButtonLabel: function() {
            return 'Log Intro';
        },

        getHeaderText: function() {
            const label = this.getIntroTypeLabel(this.getFieldValue('cMeetingType'));
            return label ? `Log ${label}` : 'Log Intro';
        },

        getIntroMeetingType: function() {
            const type = this.model.get('cMeetingType');

            // Legacy value for HOM
            return type === 'iom' ? 'hom' : type;
        },

        isHom: function() {
            return this.getIntroMeetingType() === 'hom';
        },

        // C/D go straight to a Royalty (RI), A/B continue to SPARK/Kickstart
        homRatingLeadsToRoyalty: function(rating) {
            return rating === 'C' || rating === 'D';
        },

        getSparksUsed: function() {
            return parseInt(this.model.get('cSparksUsed') || 0, 10);
        },

        getIntroTypeLabel: function(type) {
            switch (type) {
                case 'spark':
                    return 'SPARK';
                case 'bws':
                    return 'BWS';
                case 'hom':
                    return 'HOM';
                default:
                    return 'Intro';
            }
        },

        getCalendarTypeLabel: function(type) {
            switch (type) {
                case 'spark':
                    return 'SPARK';
                case 'bws':
                    return 'BWS';
                case 'hom':
                    return 'HOM';
                case 'kickstart':
                    return 'Kickstart';
                case 'royalty':
                    return 'RI';
                default:
                    return type;
            }
        },

        updateIntroLabels: function() {
            const typeLabel = this.getIntroTypeLabel(this.getIntroMeetingType());

            this.$el.find('[data-role="intro-outcome-label"]').text(`${typeLabel} uitkomst *`);
            this.$el.find('[data-role="intro-date-label"]').text(`${typeLabel} datum/tijd`);
            this.$el.find('[data-role="intro-context-label"]').text(typeLabel);
        },

        handleOutcomeChange: function() {
            const outcome = this.getFieldValue('outcome');

            this.hideField('cancellation-action');
            this.hideField('call-again-date');
            this.hideField('hom-rating');
            this.hideField('book-next-meeting');
            this.hideField('booking-container');

            this.bookingMode = null;
            this.hideMeetingScheduler();
            this.$el.find('[name="bookNextMeeting"]').prop('checked', false);
            this.$el.find('[name="homRating"]').val('').trigger('change');

            if (outcome === 'cancelled') {
                this.showField('cancellation-action');
                this.$el.find('[name="cancellationAction"]').val('');
            } else if (outcome === 'no_show') {
                this.showField('call-again-date');
            } else if (outcome === 'attended' && this.isHom()) {
                this.showField('hom-rating');
            } else if (outcome === 'attended') {
                this.showField('book-next-meeting');
            }
        },

        handleHomRatingChange: function() {
            const rating = this.getFieldValue('homRating');

            this.hideField('booking-container');
            this.bookingMode = null;
            this.hideMeetingScheduler();
            this.$el.find('[name="bookNextMeeting"]').prop('checked', false);

            const $hint = this.$el.find('[data-role="hom-rating-hint"]');

            if (!rating) {
                $hint.text('A/B: SPARK of Kickstart, C/D: RI');
                this.hideField('book-next-meeting');
                return;
            }

            const nextLabel = this.homRatingLeadsToRoyalty(rating) ? 'RI' : 'SPARK of Kickstart';
            $hint.text(`Volgende stap: ${nextLabel}`);
            this.showField('book-next-meeting');
        },

        handleCancellationActionChange: function() {
            const action = this.getFieldValue('cancellationAction');

            this.hideField('booking-container');
            this.hideField('call-again-date');
            this.bookingMode = null;
            this.hideMeetingScheduler();

            if (action === 'reschedule_now') {
                this.bookingMode = 'reschedule';
                this.showField('booking-container');
                this.showMeetingSchedulerWithFilter();
            } else if (action === 'reschedule_later') {
                this.showField('call-again-date');
            }
        },

        handleBookNextMeetingChange: function() {
            const shouldBook = this.$el.find('[name="bookNextMeeting"]').is(':checked');

            this.hideField('booking-container');
            this.bookingMode = null;
            this.hideMeetingScheduler();

            if (shouldBook) {
                this.bookingMode = 'next';
                this.showField('booking-container');
                this.showMeetingSchedulerWithFilter();
            }
        },

        showMeetingSchedulerWithFilter: function() {
            this.updateBookingHint();
            this.showMeetingScheduler();
        },

        updateBookingHint: function() {
            const $hint = this.$el.find('[data-role="booking-hint"]');
            if (!$hint.length) {
                return;
            }

            const allowed = this.getAllowedCalendarTypes();
            if (!allowed.length) {
                $hint.text('');
                return;
            }

            const labelList = allowed.map(type => this.getCalendarTypeLabel(type)).join(' / ');
            $hint.text(`Beschikbare agenda's: ${labelList}`);
        },

        getAllowedCalendarTypes: function() {
            const introType = this.getIntroMeetingType();
            const sparksUsed = this.getSparksUsed();
            const outcome = this.getFieldValue('outcome');

            if (this.bookingMode === 'reschedule') {
                if (introType === 'spark') {
                    if (sparksUsed >= 2) {
                        return ['kickstart'];
                    }
                    return ['spark'];
                }

                if (introType === 'bws' || introType === 'hom') {
                    return [introType];
                }

                return ['kickstart'];
            }

            if (this.bookingMode === 'next') {
                if (introType === 'hom') {
                    if (this.homRatingLeadsToRoyalty(this.getFieldValue('homRating'))) {
                        return ['royalty'];
                    }
                    // HOM does not count as a SPARK session
                    return sparksUsed >= 2 ? ['kickstart'] : ['spark', 'kickstart'];
                }

                if (introType === 'spark') {
                    const usedAfterAttendance = sparksUsed + (outcome === 'attended' ? 1 : 0);
                    if (usedAfterAttendance < 2) {
                        return ['spark', 'kickstart'];
                    }
                    return ['kickstart'];
                }

                return ['kickstart'];
            }

            return [];
        },

        loadBookableCalendars: function() {
            const $calSelect = this.$el.find('[name="selectedCalendar"]');
            $calSelect.prop('disabled', true).empty().append('<option value="">Laden...</option>');

            Espo.Ajax.getRequest('calendar/bookable-list')
                .then(response => {
                    const allowed = this.getAllowedCalendarTypes();
                    const shouldFilter = allowed.length > 0;

                    $calSelect.empty().append('<option value="">-- Kies Agenda --</option>');

                    response.forEach(cal => {
                        if (shouldFilter && !allowed.includes(cal.type)) {
                            return;
                        }
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
            const cancellationAction = this.getFieldValue('cancellationAction');
            const introDateTime = this.getFieldValue('introDateTime');
            const callAgainDateTime = this.getFieldValue('callAgainDateTime');
            const coachNote = this.getFieldValue('coachNote');
            const bookNextMeeting = this.$el.find('[name="bookNextMeeting"]').is(':checked');

            const formData = {
                id: this.model.id,
                outcome: outcome,
                cancellationAction: cancellationAction,
                introDateTime: this.formatDateTime(introDateTime),
                callAgainDateTime: this.formatDateTime(callAgainDateTime),
                coachNote: coachNote || null
            };

            if (outcome === 'cancelled' && cancellationAction === 'reschedule_now') {
                const meetingData = this.getSelectedMeetingData();
                if (meetingData) {
                    formData.calendarId = meetingData.calendarId;
                    formData.selectedDate = meetingData.slotDate;
                    formData.selectedTime = meetingData.slotTime;
                }
            }

            if (outcome === 'attended' && this.isHom()) {
                formData.homRating = this.getFieldValue('homRating') || null;
            }

            if (outcome === 'attended' && bookNextMeeting) {
                const meetingData = this.getSelectedMeetingData();
                if (meetingData) {
                    formData.nextBooking = {
                        calendarId: meetingData.calendarId,
                        selectedDate: meetingData.slotDate,
                        selectedTime: meetingData.slotTime
                    };
                }
            }

            return formData;
        },

        validateForm: function() {
            const outcome = this.getFieldValue('outcome');
            const cancellationAction = this.getFieldValue('cancellationAction');
            const introDateTime = this.getFieldValue('introDateTime');
            const callAgainDateTime = this.getFieldValue('callAgainDateTime');
            const bookNextMeeting = this.$el.find('[name="bookNextMeeting"]').is(':checked');

            if (!ValidationUtils.validateRequired(outcome, 'Selecteer een uitkomst.')) {
                return false;
            }

            if (outcome === 'cancelled') {
                if (!ValidationUtils.validateRequired(cancellationAction, 'Kies een vervolgactie voor de annulering.')) {
                    return false;
                }

                if (cancellationAction === 'reschedule_now') {
                    if (!this.validateMeetingSelection('Nieuwe afspraak is verplicht.')) {
                        return false;
                    }
                }

                if (cancellationAction === 'reschedule_later') {
                    if (!ValidationUtils.validateCallAgainDateTime(callAgainDateTime, true)) {
                        return false;
                    }
                }
            }

            if (outcome === 'no_show') {
                if (!ValidationUtils.validateCallAgainDateTime(callAgainDateTime, false)) {
                    return false;
                }
            }

            if (outcome === 'attended' && this.isHom()) {
                if (!ValidationUtils.validateRequired(this.getFieldValue('homRating'), 'Kies de HOM-uitkomst (A/B/C/D).')) {
                    return false;
                }
            }

            if (outcome === 'attended' && bookNextMeeting) {
                if (!this.validateMeetingSelection('Selecteer een agenda en tijdstip voor de volgende afspraak.')) {
                    return false;
                }
            }

            if (!ValidationUtils.validateEventDateTime(introDateTime, 'intro')) {
                return false;
            }

            return true;
        },

        handleSuccess: function(response) {
            this.trigger('success', response);
            this.close();

            if (response && response.data && response.data.eventIds) {
                Espo.Ui.success('Intro meeting logged successfully.');
            } else {
                Espo.Ui.success(this.successMessage);
            }
        }
    });

    _.extend(Dep.prototype, MeetingSchedulerMixin);

    return LogIntroMeetingView;
});
