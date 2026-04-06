define(['action-handler'], (Dep) => {

    return class extends Dep {

        async logMessageOutcome(data, e) {
            this.view.createView('dialog', 'custom:views/lead/modals/log-message-outcome', {
				model: this.view.model
			}, (view) => {
				view.render();
				view.once('success', async () => {
					await this.view.model.fetch();
					this.view.render();
					Espo.Ui.success('Bericht uitkomst opgeslagen.');
					this.view.enableMenuItem('logMessageSent');
				});
				view.once('close', () => {
					this.view.enableMenuItem('logMessageSent');
				});
			});
		}     

        isLogMessageOutcomeVisible() {
			const status = this.view.model.get('status');
            const stage = this.view.model.get('cStage');

            return status === 'Assigned' && ['message_sent'].includes(stage);      
        }
    }
});

