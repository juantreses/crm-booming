define(['action-handler'], (Dep) => {

    // Keep in sync with SavingsCardService (backend)
    const HOM_POINTS = 40;
    const NO_ORDER_HISTORY_TYPES = ['pcx_15', 'pcx_25', 'pcx_35', 'distributor', 'distributeur'];

    return class extends Dep {

        async bookContactHom(data, e) {
            this.openModal('bookContactHom', 'custom:views/contact/modals/book-hom');
        }

        async logContactHom(data, e) {
            this.openModal('logContactHom', 'custom:views/contact/modals/log-hom');
        }

        openModal(menuItem, viewName) {
            this.view.disableMenuItem(menuItem);

            this.view.createView('dialog', viewName, {
                model: this.view.model
            }, (view) => {
                view.render();

                view.once('success', () => {
                    this.view.model.fetch();
                    this.view.render();
                });

                view.once('close', () => {
                    this.view.enableMenuItem(menuItem);
                });
            });
        }

        isBookContactHomVisible() {
            const model = this.view.model;

            return !model.get('cHomPlanned')
                && !NO_ORDER_HISTORY_TYPES.includes(model.get('cTypeKlant'))
                && (model.get('cSavingsPointsBalance') || 0) >= HOM_POINTS;
        }

        isLogContactHomVisible() {
            return !!this.view.model.get('cHomPlanned');
        }
    }
});
