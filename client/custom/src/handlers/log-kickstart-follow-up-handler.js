define(['action-handler'], (Dep) => {

    return class extends Dep {

        async logKickstartFollowUp(data, e) {
            this.view.createView('dialog', 'custom:views/lead/modals/log-kickstart-follow-up', {
				model: this.view.model
			}, (view) => {
				view.render();
				view.once('success', async () => {
					await this.view.model.fetch();
					this.view.reRender();
					Espo.Ui.success('KS Opvolging opgeslagen.');
					this.view.enableMenuItem('logKickstartFollowUp');
				});
				view.once('close', () => {
					this.view.enableMenuItem('logKickstartFollowUp');
				});
			});
		}     

        isLogKickstartFollowUpVisible() {        
            return ['still_thinking'].includes(this.view.model.attributes.status);
        }
    }
});

