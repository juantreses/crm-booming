define([
    'views/record/panels/bottom',
    'lib!chart'
], (BottomPanelView, Chart) => {

    const MEASUREMENTS = [
        { key: 'bovenarm',  label: 'Re Bovenarm',  color: 'rgb(75, 192, 192)',   bgColor: 'rgba(75, 192, 192, 0.15)' },
        { key: 'borst',     label: 'Borst',         color: 'rgb(255, 99, 132)',   bgColor: 'rgba(255, 99, 132, 0.15)' },
        { key: 'taille',    label: 'Taille',        color: 'rgb(54, 162, 235)',   bgColor: 'rgba(54, 162, 235, 0.15)' },
        { key: 'buik',      label: 'Buik',          color: 'rgb(255, 205, 86)',   bgColor: 'rgba(255, 205, 86, 0.15)' },
        { key: 'heup',      label: 'Heup',          color: 'rgb(153, 102, 255)',  bgColor: 'rgba(153, 102, 255, 0.15)' },
        { key: 'bovenbeen', label: 'Re Bovenbeen',  color: 'rgb(255, 159, 64)',   bgColor: 'rgba(255, 159, 64, 0.15)' },
    ];

    return class extends BottomPanelView {

        templateContent = `
            <div class="body-measurements-panel">
                <div class="panel-header" style="margin-bottom: 15px;">
                    <h4>Lichaamsmetingen evolutie</h4>
                </div>

                <!-- Filter Form -->
                <div class="filter-form" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <div class="row">
                        <div class="col-sm-3">
                            <label class="control-label">Aantal metingen</label>
                            <input type="number" class="form-control" name="limit" value="{{limit}}" min="1" max="200">
                        </div>
                        <div class="col-sm-3">
                            <label class="control-label">Grafiek start</label>
                            <input type="date" class="form-control" name="startDate" value="{{startDate}}">
                        </div>
                        <div class="col-sm-3">
                            <label class="control-label">Grafiek stop</label>
                            <input type="date" class="form-control" name="endDate" value="{{endDate}}">
                        </div>
                        <div class="col-sm-3" style="display: flex; align-items: end;">
                            <button type="button" class="btn btn-primary" data-action="applyFilters">
                                <span class="fas fa-filter"></span> Filter toepassen
                            </button>
                        </div>
                    </div>
                </div>

                <div class="measurements-data-container">
                    {{#if hasData}}
                        <!-- Overview table: first vs last -->
                        <div class="overview-table" style="margin-bottom: 20px;">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr style="background: #f8f9fa;">
                                        <th>Meting</th>
                                        <th>Eerste meting<br><small style="font-weight:normal;">{{firstDate}}</small></th>
                                        <th>Laatste meting<br><small style="font-weight:normal;">{{lastDate}}</small></th>
                                        <th>Evolutie (cm)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{#each measurements}}
                                    <tr>
                                        <td><strong>{{label}}</strong></td>
                                        <td>{{firstValue}} cm</td>
                                        <td>{{lastValue}} cm</td>
                                        <td style="color: {{evolutionColor}}; font-weight: bold;">{{evolution}} cm</td>
                                    </tr>
                                    {{/each}}
                                    <tr>
                                        <td colspan="4" style="text-align: right; font-size: 0.85em; color: #666;">
                                            Periode: {{daysDiff}} dagen
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Individual charts -->
                        <div class="row">
                            {{#each measurementCharts}}
                            <div class="col-sm-6" style="margin-bottom: 20px;">
                                <h5 style="text-align:center; margin-bottom: 8px;">{{label}}</h5>
                                <div style="position: relative; height: 200px;">
                                    <canvas id="{{../panelId}}_{{key}}"></canvas>
                                </div>
                            </div>
                            {{/each}}
                        </div>
                    {{else}}
                        <div class="alert alert-info">
                            <span class="fas fa-info-circle"></span>
                            Geen lichaamsmetingen beschikbaar voor dit contact.
                        </div>
                    {{/if}}
                </div>
                {{#if loading}}
                    <div class="loading-indicator">
                        <span class="fas fa-spinner fa-spin"></span> Metingen laden...
                    </div>
                {{/if}}
            </div>
        `

        setup() {
            super.setup();
            this.panelId = 'bodyMeasurementsChart' + this.model.attributes.id;

            this.hasData = false;
            this.loading = false;
            this.measurementData = [];
            this.measurements = [];
            this.measurementCharts = MEASUREMENTS.map(m => ({ key: m.key, label: m.label }));

            this.limit = 60;
            this.startDate = '';
            this.endDate = '';

            this._charts = {};

            this.loadData();
        }

        loadData() {
            this.loading = true;
            this.reRender();

            const params = {
                where: [
                    {
                        type: 'equals',
                        attribute: 'contactId',
                        value: this.model.id
                    }
                ],
                orderBy: 'datum',
                order: 'asc',
                maxSize: this.limit
            };

            if (this.startDate) {
                params.where.push({ type: 'greaterThanOrEquals', attribute: 'datum', value: this.startDate });
            }
            if (this.endDate) {
                params.where.push({ type: 'lessThanOrEquals', attribute: 'datum', value: this.endDate });
            }

            Espo.Ajax.getRequest('CBodyMeasurement', params)
                .then(response => {
                    const sorted = (response.list || []).sort((a, b) => new Date(a.datum) - new Date(b.datum));
                    this.processData(sorted);
                })
                .catch(error => {
                    console.error('Error loading body measurement data:', error);
                })
                .finally(() => {
                    this.loading = false;
                    this.reRender();
                });
        }

        processData(rawData) {
            if (!rawData || rawData.length === 0) {
                this.hasData = false;
                return;
            }

            this.measurementData = rawData;
            this.hasData = true;

            const first = rawData[0];
            const last = rawData[rawData.length - 1];

            this.firstDate = this.formatDate(first.datum);
            this.lastDate = this.formatDate(last.datum);

            const firstDateObj = new Date(first.datum);
            const lastDateObj = new Date(last.datum);
            this.daysDiff = Math.abs(Math.ceil((lastDateObj - firstDateObj) / (1000 * 60 * 60 * 24)));

            this.measurements = MEASUREMENTS.map(m => {
                const firstVal = parseFloat(first[m.key] || 0);
                const lastVal = parseFloat(last[m.key] || 0);
                const diff = lastVal - firstVal;
                const isPositive = diff <= 0;
                const sign = diff >= 0 ? '+' : '-';
                return {
                    label: m.label,
                    firstValue: firstVal.toFixed(1),
                    lastValue: lastVal.toFixed(1),
                    evolution: sign + ' ' + Math.abs(diff).toFixed(1),
                    evolutionColor: isPositive ? 'green' : 'red'
                };
            });
        }

        afterRender() {
            super.afterRender();
            if (this.hasData && !this.loading) {
                this.initializeCharts();
            }
            this.$el.find('[data-action="applyFilters"]').on('click', () => this.applyFilters());
        }

        applyFilters() {
            this.limit = parseInt(this.$el.find('input[name="limit"]').val()) || 60;
            this.startDate = this.$el.find('input[name="startDate"]').val() || '';
            this.endDate = this.$el.find('input[name="endDate"]').val() || '';
            this.loadData();
        }

        initializeCharts() {
            Object.values(this._charts).forEach(c => c.destroy());
            this._charts = {};

            require(['lib!client/custom/lib/chartjs-adapter-date-fns.js'], () => {
                const labels = this.measurementData.map(item => new Date(item.datum));

                const timeScaleOptions = {
                    type: 'time',
                    time: {
                        unit: 'month',
                        stepSize: 2,
                        displayFormats: { month: 'MMM yyyy', day: 'dd/MM/yyyy' }
                    },
                    ticks: { autoSkip: false }
                };

                const commonOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { left: 20, right: 20, top: 0, bottom: 0 } },
                    scales: {
                        x: timeScaleOptions,
                        y: { beginAtZero: false }
                    },
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    interaction: { mode: 'nearest', axis: 'x', intersect: false }
                };

                // Individual chart per measurement
                MEASUREMENTS.forEach(m => {
                    const canvas = this.$el.find(`#${this.panelId}_${m.key}`)[0];
                    if (!canvas) return;

                    const values = this.measurementData.map(item => parseFloat(item[m.key] || 0));
                    const validVals = values.filter(v => v > 0);
                    const minY = validVals.length ? Math.floor(Math.min(...validVals)) - 2 : 0;
                    const maxY = validVals.length ? Math.ceil(Math.max(...validVals)) + 2 : 100;

                    this._charts[m.key] = new Chart(canvas.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [{
                                label: m.label + ' (cm)',
                                data: values,
                                borderColor: m.color,
                                backgroundColor: m.bgColor,
                                tension: 0.1,
                                fill: true,
                                pointRadius: 4,
                                pointHoverRadius: 7
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            layout: { padding: { left: 10, right: 10, top: 0, bottom: 0 } },
                            scales: {
                                x: timeScaleOptions,
                                y: { beginAtZero: false, min: minY, max: maxY }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: { mode: 'index', intersect: false }
                            },
                            interaction: { mode: 'nearest', axis: 'x', intersect: false }
                        }
                    });
                });
            });
        }

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-GB');
        }

        data() {
            return {
                ...super.data(),
                hasData: this.hasData,
                loading: this.loading,
                panelId: this.panelId,
                limit: this.limit,
                startDate: this.startDate,
                endDate: this.endDate,
                firstDate: this.firstDate,
                lastDate: this.lastDate,
                daysDiff: this.daysDiff,
                measurements: this.measurements,
                measurementCharts: this.measurementCharts
            };
        }

        remove() {
            this.$el.find('[data-action="applyFilters"]').off('click');
            Object.values(this._charts).forEach(c => c.destroy());
            super.remove();
        }
    }
});
