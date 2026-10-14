define('custom:handlers/reset-lead-handler', ['action-handler'], (Dep) => {

    return class extends Dep {

        async resetLead(data, e) {
            const model = this.view.model;

            const statusLabel = this.view.getLanguage().translateOption('Assigned', 'status', 'Lead');
            const stageLabel = this.view.getLanguage().translateOption('to_call', 'cStage', 'Lead');

            const message = 'Weet je zeker dat je deze lead wilt <b>resetten</b>?<br><br>' +
                'De lead wordt teruggezet naar de startstatus:<br>' +
                '&bull; Status: <b>' + this.view.getHelper().escapeString(statusLabel) + '</b><br>' +
                '&bull; Fase: <b>' + this.view.getHelper().escapeString(stageLabel) + '</b><br><br>' +
                'Het aantal belpogingen wordt op 0 gezet. Deze actie wordt gelogd in de lead events met jouw naam.';

            Espo.Ui.confirm(
                message,
                {
                    confirmText: 'Ja, reset lead',
                    cancelText: 'Annuleren',
                    confirmStyle: 'danger',
                    isHtml: true
                },
                () => {
                    this.view.disableMenuItem('resetLead');

                    Espo.Ajax.postRequest('leadEvent/resetLead', {
                        id: model.id
                    })
                    .then(() => {
                        model.fetch();
                        this.view.reRender();
                        Espo.Ui.success('Lead gereset naar Nieuw / Te bellen.');
                    })
                    .catch(() => {
                        Espo.Ui.error('Lead kon niet worden gereset.');
                    })
                    .finally(() => {
                        this.view.enableMenuItem('resetLead');
                    });
                }
            );
        }

        isResetLeadVisible() {
            const status = this.view.model.get('status');
            const stage = this.view.model.get('cStage');

            if (status === 'Converted') {
                return false;
            }

            return !(status === 'Assigned' && stage === 'to_call');
        }
    }
});
