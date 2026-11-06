define(['action-handler'], (Dep) => {

    return class extends Dep {

        async logRoyaltyFollowUp(data, e) {
            this.view.createView('dialog', 'custom:views/lead/modals/log-royalty-follow-up', {
                model: this.view.model
            }, (view) => {
                view.render();
                view.once('success', async () => {
                    await this.view.model.fetch();
                    this.view.render();
                    Espo.Ui.success('RI Opvolging opgeslagen.');
                    this.view.enableMenuItem('logRoyaltyFollowUp');
                });
                view.once('close', () => {
                    this.view.enableMenuItem('logRoyaltyFollowUp');
                });
            });
        }

        isLogRoyaltyFollowUpVisible() {
            const status = this.view.model.get('status');
            const stage = this.view.model.get('cStage');

            return status === 'Assigned' && ['ri_doubt'].includes(stage);
        }
    }
});
