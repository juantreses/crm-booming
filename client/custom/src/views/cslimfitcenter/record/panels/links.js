define('custom:views/cslimfitcenter/record/panels/links', ['custom:views/cteam/record/panels/links'], (TeamLinksView) => {

    return class extends TeamLinksView {

        fetchLinks() {
          
            Espo.Ajax.getRequest(`center/links`)
                .then(response => {
                    this.widgetLinks = response.widgets || [];
                    this.calendarLinks = response.calendars || [];
                    this.hasLinks = this.widgetLinks.length > 0 || this.calendarLinks.length > 0;
                    this.loading = false;
                    this.reRender();
                })
                .catch(err => {
                    console.error("Fout bij ophalen center links:", err);
                    this.loading = false;
                    this.reRender();
                });
        }
    };
});