define(['action-handler'], (Dep) => {

    return class extends Dep {

        async logCall(data, e) {
            this.view.disableMenuItem('logCall');

            this.view.createView('dialog', 'custom:views/lead/modals/log-call', {
                model: this.view.model
            }, (view) => {
                view.render();
                
                view.once('success', (response) => {
                    this.view.model.fetch();
                    this.view.reRender();
                    
                    // Show success message with more details if available
                    if (response && response.data) {
                        const eventCount = response.data.eventIds ? response.data.eventIds.length : 0;
                        Espo.Ui.success(`Call logged successfully. Created ${eventCount} event(s).`);
                    } else {
                        Espo.Ui.success('Call logged successfully');
                    }
                });
                
                view.once('close', () => {
                    this.view.enableMenuItem('logCall');
                });
            });
        }       

        isLogCallVisible() {        
            return ['assigned', 'call_again'].includes(this.view.model.attributes.status);
        }
    }
});

