<div class="panel panel-default">
    <div class="panel-heading">
        <h4 class="panel-title">🔗 Coach Links</h4>
    </div>
    <div class="panel-body">
        {{#if loading}}
            <div class="text-center text-muted">Laden...</div>
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
                        <button class="btn btn-default btn-xs action-copy" data-url="{{url}}" title="Kopieer">
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
                        <button class="btn btn-default btn-xs action-copy" data-url="{{url}}" title="Kopieer">
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