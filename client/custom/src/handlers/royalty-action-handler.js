define(['action-handler'], (Dep) => {

    return class extends Dep {

        async logRoyalty(data, e) {
            this.view.disableMenuItem('logRoyalty');

            this.view.createView('dialog', 'custom:views/lead/modals/log-royalty', {
                model: this.view.model
            }, (view) => {
                view.render();

                view.once('success', () => {
                    this.view.model.fetch();
                    this.view.render();
                });

                view.once('close', () => {
                    this.view.enableMenuItem('logRoyalty');
                });
            });
        }

        isLogRoyaltyVisible() {
            const status = this.view.model.get('status');
            const stage = this.view.model.get('cStage');

            return status === 'Assigned' && ['ri_planned'].includes(stage);
        }
    }
});
