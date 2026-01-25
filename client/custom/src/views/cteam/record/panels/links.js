define('custom:views/cteam/record/panels/links', ['views/base'], (Dep) => {

    return class extends Dep {
        templateContent = `
            <div class="panel panel-default" style="display: none;">
                <div class="panel-heading">
                    <h4 class="panel-title">🔗 Links</h4>
                </div>
                <div class="panel-body">
                    {{#if loading}}
                        <div class="text-center text-muted" style="padding: 10px;">
                            <i class="fas fa-spinner fa-spin"></i> Laden...
                        </div>
                    {{else}}
                        {{#if hasLinks}}
                            
                            <div class="list-group">
                                <div class="list-group-item active" style="background: #f5f5f5; color: #333; border-color: #ddd;">
                                    <strong>Widgets</strong>
                                </div>
                                {{#each widgetLinks}}
                                <div class="list-group-item" style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <i class="{{icon}}"></i> &nbsp; {{label}}
                                    </div>
                                    <button class="btn btn-default btn-xs action-copy" data-url="{{url}}" title="Kopieer" type="button">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                                {{/each}}
                            </div>

                            <div class="list-group" style="margin-top: 10px;">
                                <div class="list-group-item active" style="background: #f5f5f5; color: #333; border-color: #ddd;">
                                    <strong>Kalenders</strong>
                                </div>
                                {{#each calendarLinks}}
                                <div class="list-group-item" style="display: flex; justify-content: space-between; align-items: center; {{#if isLocation}}padding-left: 25px; font-size: 0.9em;{{/if}}">
                                    <div>
                                        {{#if isLocation}}<i class="fas fa-map-marker-alt text-muted"></i>{{else}}<i class="fas fa-calendar-alt"></i>{{/if}} 
                                        &nbsp; {{label}}
                                        {{#if subtext}}<br><small class="text-muted">{{subtext}}</small>{{/if}}
                                    </div>
                                    <button class="btn btn-default btn-xs action-copy" data-url="{{url}}" title="Kopieer" type="button">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                                {{/each}}
                            </div>

                        {{else}}
                            <div class="text-muted">Geen links beschikbaar. Check of de coach een slug heeft.</div>
                        {{/if}}
                    {{/if}}
                </div>
            </div>
        `;

        events = {
            'click .action-copy': 'handleCopyClick'
        };

        setup() {
            this.loading = true;
            this.widgetLinks = [];
            this.calendarLinks = [];
            this.hasLinks = false;

            this.fetchLinks();
        }

        fetchLinks() {
            const teamId = this.model.id;

            Espo.Ajax.getRequest(`team/${teamId}/links`)
                .then(response => {
                    this.widgetLinks = response.widgets || [];
                    this.calendarLinks = response.calendars || [];
                    this.hasLinks = this.widgetLinks.length > 0 || this.calendarLinks.length > 0;
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
                calendarLinks: this.calendarLinks,
                hasLinks: this.hasLinks
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
                Espo.Ui.notify('Kopiëren mislukt: ' + err, 'error');
            }
        }
    };
});