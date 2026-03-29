define(['action-handler'], (Dep) => {

    return class extends Dep {

        async bookKickstart(data, e) {
            this.view.disableMenuItem('bookKickstart');

            this.view.createView('dialog', 'custom:views/lead/modals/book-kickstart', {
                model: this.view.model
            }, (view) => {
                view.render();

                view.once('success', (response) => {
                    this.view.model.fetch();
                    this.view.render();

                    if (response && response.data) {
                        const eventCount = response.data.eventIds ? response.data.eventIds.length : 0;
                        Espo.Ui.success(`Kickstart geboekt. ${eventCount} event(s) aangemaakt.`);
                    } else {
                        Espo.Ui.success('Kickstart geboekt.');
                    }
                });

                view.once('close', () => {
                    this.view.enableMenuItem('bookKickstart');
                });
            });
        }

        isBookKickstartVisible() {
            const status = this.view.model.get('status');
            const stage = this.view.model.get('cStage');

            return status === 'Assigned' && ['book_ks'].includes(stage);
        }
    }
});
