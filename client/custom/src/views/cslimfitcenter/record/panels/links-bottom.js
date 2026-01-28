define('custom:views/cSlimFitCenter/record/panels/links-bottom', ['views/record/panels/bottom'], (BottomPanelView) => {

    return class extends BottomPanelView {
        templateContent = `
            <div class="row">
                <div class="col-md-3">
                    <h4 style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                        <i class="fas fa-magic"></i> Widgets
                    </h4>
                    
                    {{#if loading}}
                        <div class="text-muted">Laden...</div>
                    {{else}}
                        <div class="list-group">
                            {{#each widgetLinks}}
                            <div class="list-group-item" style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <i class="{{icon}} fa-fw text-muted"></i> <strong>{{label}}</strong>
                                </div>
                                <button class="btn btn-default btn-sm action-copy" data-url="{{url}}" title="Kopieer" type="button">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            {{/each}}
                        </div>
                    {{/if}}
                </div>

                <div class="col-md-3">
                    <h4 style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                        <i class="fas fa-calendar-alt"></i> Kalenders
                    </h4>

                    {{#if loading}}
                        <div class="text-muted">Laden...</div>
                    {{else}}
                        <div class="list-group" style="max-height: 300px; overflow-y: auto;">
                            {{#each calendarLinks}}
                            <div class="list-group-item" style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    {{#if isLocation}}
                                        <i class="fas fa-map-marker-alt fa-fw text-danger" style="margin-left: 10px;"></i> 
                                    {{else}}
                                        <i class="fas fa-calendar fa-fw text-primary"></i> 
                                    {{/if}}
                                    
                                    {{label}}
                                    {{#if subtext}} <span class="text-muted small">({{subtext}})</span>{{/if}}
                                </div>
                                <button class="btn btn-default btn-sm action-copy" data-url="{{url}}" title="Kopieer" type="button">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            {{/each}}
                        </div>
                    {{/if}}
                </div>
            </div>
        `;

        events = {
            'click .action-copy': 'handleCopyClick'
        };

        setup() {
            super.setup();
            
            this.loading = true;
            this.widgetLinks = [];
            this.calendarLinks = [];
            
            this.fetchLinks();
        }

        fetchLinks() {
            const centerId = this.model.id;

            Espo.Ajax.getRequest(`center/links`)
                .then(response => {
                    this.widgetLinks = response.widgets || [];
                    this.calendarLinks = response.calendars || [];
                    this.loading = false;
                    this.reRender();
                })
                .catch(err => {
                    console.error("Fout bij ophalen links:", err);
                    this.loading = false;
                    this.reRender();
                });
        }

        data() {
            return {
                loading: this.loading,
                widgetLinks: this.widgetLinks,
                calendarLinks: this.calendarLinks
            };
        }

        async handleCopyClick(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const target = e.currentTarget; 
            const url = target.dataset.url;

            if (!url) return;

            try {
                await navigator.clipboard.writeText(url);
                Espo.Ui.notify('Link gekopieerd!', 'success');
            } catch (err) {
                console.error('Clipboard error:', err);
                Espo.Ui.notify('Kopiëren mislukt', 'error');
            }
        }
    };
});