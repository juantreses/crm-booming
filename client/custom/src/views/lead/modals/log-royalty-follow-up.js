define('custom:views/lead/modals/log-royalty-follow-up', [
    'custom:views/lead/modals/log-kickstart-follow-up'
], function (Dep) {

    return Dep.extend({

        apiEndpoint: 'leadEvent/logRoyaltyFollowUp',
        successMessage: 'RI Opvolging opgeslagen.',
        errorMessage: 'RI Opvolging opslaan mislukt.',
        meetingLabel: 'RI'
    });
});
