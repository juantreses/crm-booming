define(['action-handler'], (Dep) => {

    return class extends Dep {

        async logMessageOutcome(data, e) {
            this.view.createView('dialog', 'custom:views/lead/modals/log-message-outcome', {
				model: this.view.model
			}, (view) => {
				view.render();
				view.once('success', async () => {
					await this.view.model.fetch();
					this.view.reRender();
					Espo.Ui.success('Bericht uitkomst opgeslagen.');
					this.view.enableMenuItem('logMessageSent');
				});
				view.once('close', () => {
					this.view.enableMenuItem('logMessageSent');
				});
			});
		}     

        isLogMessageOutcomeVisible() {        
            return ['message_sent'].includes(this.view.model.attributes.status);
        }
    }
});

