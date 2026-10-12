/**
 * BookFollowUpView
 *
 * Zelfde flow als BookWorkoutView, maar voor de (enige) follow-up agenda.
 * Een follow-up (FU) kan enkel voor klanten (Contact) geboekt worden.
 */
define('custom:views/shared/modals/book-follow-up', [
    'custom:views/shared/modals/book-workout'
], function (Dep) {

    return Dep.extend({
        template: 'custom:shared/modals/book-follow-up',
        apiEndpoint: 'booking/book-follow-up',
        successMessage: 'Follow-up geboekt.',
        errorMessage: 'Follow-up boeken mislukt.',

        calendarType: 'followup',

        getSaveButtonLabel: function () {
            return 'FU boeken';
        },

        getHeaderText: function () {
            return 'Follow-up Inplannen';
        },

        getNoCalendarText: function () {
            return 'Geen follow-up agenda beschikbaar';
        },

        getFormData: function () {
            const coachNote = this.getFieldValue('coachNote');
            const selected = this.getSelectedSlot();

            return {
                entityType: this.model.entityType,
                entityId: this.model.id,
                selectedDate: selected ? selected.slotDate : null,
                selectedTime: selected ? selected.slotTime : null,
                coachNote: coachNote || null
            };
        }
    });
});
