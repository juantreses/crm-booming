define(['action-handler'], (Dep) => {

    return class extends Dep {

        async logIntroMeeting(data, e) {
            this.view.disableMenuItem('logIntroMeeting');

            this.view.createView('dialog', 'custom:views/lead/modals/log-intro-meeting', {
                model: this.view.model
            }, (view) => {
                view.render();

                view.once('success', (response) => {
                    this.view.model.fetch();
                    this.view.render();

                    if (response && response.data) {
                        const eventCount = response.data.eventIds ? response.data.eventIds.length : 0;
                        Espo.Ui.success(`Intro meeting logged successfully. Created ${eventCount} event(s).`);
                    } else {
                        Espo.Ui.success('Intro meeting logged successfully');
                    }
                });

                view.once('close', () => {
                    this.view.enableMenuItem('logIntroMeeting');
                });
            });
        }

        isLogIntroMeetingVisible() {
            const status = this.view.model.get('status');
            const stage = this.view.model.get('cStage');

            return status === 'Assigned' && ['intro_scheduled'].includes(stage);
        }
    }
});
