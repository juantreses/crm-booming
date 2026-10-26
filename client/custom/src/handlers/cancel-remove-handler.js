define('custom:handlers/cancel-remove-handler', ['action-handler'], (Dep) => {

    return class extends Dep {

        async cancelAndRemove(data, e) {
            this.view.disableMenuItem('cancelAndRemove'); 

            const model = this.view.model;

            const message = 'Weet je zeker dat je deze afspraak wilt annuleren?<br><br>Er wordt direct een <b>annulatiemail</b> naar de klant gestuurd en de afspraak wordt definitief uit EspoCRM en Google Calendar verwijderd.';

            Espo.Ui.confirm(
                message, 
                {
                    confirmText: 'Ja, annuleer & stuur mail',
                    cancelText: 'Terug',
                    confirmStyle: 'danger',
                    isHtml: true,
                    cancelCallback: () => this.view.enableMenuItem('cancelAndRemove')
                }, 
                () => { 
                    Espo.Ui.notify('Bezig met annuleren...', 'info');

                    Espo.Ajax.postRequest('booking/cancel-meeting', {
                        id: model.id
                    })
                    .then(response => {
                        if (response.mailError) {
                            Espo.Ui.error('Afspraak verwijderd, maar de annulatiemail is niet verstuurd: ' + response.mailError, true);
                        } else {
                            Espo.Ui.notify('Afspraak succesvol geannuleerd en verwijderd.', 'success');
                        }

                        this.view.getRouter().navigate('#Meeting', { trigger: true });
                    })
                    .catch(() => {
                        this.view.enableMenuItem('cancelAndRemove');
                    });
                }
            );
        }

        isCancelAndRemoveVisible() {
            const status = this.view.model.get('status');
            return status === 'Tentative' || status === 'Planned'; 
        }
    }
});