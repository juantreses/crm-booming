define('custom:handlers/log-message-sent-handler', ['action-handler'], (Dep) => {

    return class extends Dep {

        async logMessageSent(data, e) {
            this.view.disableMenuItem('logMessageSent');

            Espo.Ajax.postRequest('lead/action/logMessageSent', {
                id: this.view.model.id
            })
            .then((response) => {
                this.view.model.fetch();
                this.view.reRender();
                Espo.Ui.success('Bericht gestuurd.');
            })
            .catch(() => {
                Espo.Ui.error('Bericht verstuurd kon niet worden gelogd.');
            })
            .finally(() => {
                this.view.enableMenuItem('logMessageSent');
            });
        }

        isLogMessageSentVisible() {
            return this.view.model.attributes.status === 'message_to_be_sent';
        }
    }
});