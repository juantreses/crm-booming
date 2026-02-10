define('custom:views/cslimfitcenter/record/panels/links', ['custom:views/shared/links-panel'], (BaseLinksPanel) => {

    /**
     * Center Links Panel View
     * 
     * Extends the base links panel with center-wide API call
     */
    return class extends BaseLinksPanel {
        fetchLinks() {
            Espo.Ajax.getRequest('center/links')
                .then(response => this.handleLinksResponse(response))
                .catch(error => this.handleLinksError(error));
        }
    };
});