define('coach-manager:create-coach-team-handler', ['action-handler'], (Dep) => {

    return class extends Dep {

        async createCoachTeam() {
            this.view.disableMenuItem('createCoachTeam');

            Espo.Ui.notify('Coach team wordt aangemaakt...', 'info');

            Espo.Ajax.postRequest(`coach/contact/${this.view.model.id}/team`)
                .then((response) => {
                    this.view.model.fetch();
                    this.view.reRender();

                    const teamName = response && response.cTeamName ? response.cTeamName : 'team';

                    Espo.Ui.success(`Coach ${teamName} aangemaakt.`);
                })
                .catch((xhr) => {
                    const message = xhr && xhr.responseJSON && xhr.responseJSON.message ?
                        xhr.responseJSON.message :
                        'Coach team kon niet worden aangemaakt.';

                    Espo.Ui.error(message);
                })
                .finally(() => {
                    this.view.enableMenuItem('createCoachTeam');
                });
        }

        isCreateCoachTeamVisible() {
            return !this.view.model.get('cTeamId') || !this.view.model.get('cUserId');
        }
    };
});
