define('custom:views/lead/modals/book-royalty', [
    'custom:views/lead/modals/book-kickstart'
], function (Dep) {

    return Dep.extend({

        template: 'custom:lead/modals/book-royalty',
        apiEndpoint: 'leadEvent/bookRoyalty',
        successMessage: 'RI geboekt.',
        errorMessage: 'RI boeken mislukt.',
        calendarType: 'royalty',
        meetingLabel: 'RI'
    });
});
