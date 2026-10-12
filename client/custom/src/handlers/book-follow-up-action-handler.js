define(['action-handler'], (Dep) => {

    return class extends Dep {

        async bookFollowUp(data, e) {
            this.view.disableMenuItem('bookFollowUp');

            this.view.createView('dialog', 'custom:views/shared/modals/book-follow-up', {
                model: this.view.model
            }, (view) => {
                view.render();

                view.once('success', () => {
                    this.view.model.fetch();
                    this.view.render();

                    Espo.Ui.success('Follow-up geboekt.');
                });

                view.once('close', () => {
                    this.view.enableMenuItem('bookFollowUp');
                });
            });
        }

        isBookFollowUpVisible() {
            return true;
        }
    }
});
