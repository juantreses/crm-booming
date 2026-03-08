define(['action-handler'], (Dep) => {

    return class extends Dep {

        async logKickstart(data, e) {
            this.view.disableMenuItem('logKickstart');

            this.view.createView('dialog', 'custom:views/lead/modals/log-kickstart', {
                model: this.view.model
            }, (view) => {
                view.render();
                
                view.once('success', (response) => {
                    this.view.model.fetch();
                    this.view.render();
                    
                    if (response && response.data) {
                        const eventCount = response.data.eventIds ? response.data.eventIds.length : 0;
                        Espo.Ui.success(`Kickstart logged successfully. Created ${eventCount} event(s).`);
                    } else {
                        Espo.Ui.success('Kickstart logged successfully');
                    }
                });
                
                view.once('close', () => {
                    this.view.enableMenuItem('logKickstart');
                });
            });
        }       

        isLogKickstartVisible() {        
            const status = this.view.model.get('status');
            const stage = this.view.model.get('cStage');

            return status === 'Assigned' && ['ks_planned'].includes(stage);
        }
    }
});