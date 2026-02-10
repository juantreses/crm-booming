define('custom:views/cteam/record/panels/links', ['custom:views/shared/links-panel'], (BaseLinksPanel) => {


    return class extends BaseLinksPanel {
        fetchLinks() {
            const teamId = this.model.id;

            Espo.Ajax.getRequest(`team/${teamId}/links`)
                .then(response => this.handleLinksResponse(response))
                .catch(error => this.handleLinksError(error));
        }
    };
});