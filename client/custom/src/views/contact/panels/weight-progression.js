define([
    'views/record/panels/bottom',
    'lib!chart'
], (BottomPanelView, Chart) => {

    return class extends BottomPanelView {
        
        templateContent = `
            <div class="weight-evolution-panel">
                <div class="panel-header" style="margin-bottom: 15px;">
                    <h4>Gewicht evolutie</h4>
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
                
                <div class="weight-data-container">
                    {{#if hasData}}
                        <div class="overview-table" style="margin-bottom: 20px;">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr style="background: #f8f9fa;">
                                        <th>Meting</th>
                                        <th>Eerste weging<br><small style="font-weight:normal;">{{firstDate}}</small></th>
                                        <th>Laatste weging<br><small style="font-weight:normal;">{{lastDate}}</small></th>
                                        <th>Evolutie</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{#each overviewRows}}
                                    <tr>
                                        <td><strong>{{label}}</strong></td>
                                        <td>{{first}}</td>
                                        <td>{{last}}</td>
                                        <td style="color: {{color}}; font-weight: bold;">{{evolution}}</td>
                                    </tr>
                                    {{/each}}
                                    <tr>
                                        <td colspan="3" style="font-size: 0.85em; color: #666;">
                                            Periode: {{daysDiff}} dagen
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="#" data-action="toggleAllResults">
                                                <span class="fas fa-list"></span> {{#if showAllResults}}Toon minder{{else}}Toon alle resultaten{{/if}}
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="chart-container" style="position: relative; height: 300px; margin-bottom: 20px;">
                            <canvas id="{{panelId}}"></canvas>
                        </div>
                        <div class="chart-container" style="position: relative; height: 300px; margin-bottom: 20px;">
                            <canvas id="{{panelId}}_percentage"></canvas>
                        </div>
                        <div class="chart-container" style="position: relative; height: 300px; margin-bottom: 20px;">
                            <canvas id="{{panelId}}_kilogram"></canvas>
                        </div>
                        <div class="chart-container" style="position: relative; height: 300px; margin-bottom: 20px;">
                            <canvas id="{{panelId}}_metabool"></canvas>
                        </div>
                        <div class="chart-container" style="position: relative; height: 300px; margin-bottom: 20px;">
                            <canvas id="{{panelId}}_vocht_bmr"></canvas>
                        </div>
                    {{else}}
                        <div class="alert alert-info">
                            <span class="fas fa-info-circle"></span>
                            No weight data available for this record
                        </div>
                    {{/if}}
                </div>
                {{#if loading}}
                    <div class="loading-indicator">
                        <span class="fas fa-spinner fa-spin"></span> Loading weight data...
                    </div>
                {{/if}}
            </div>
        `
        
        setup() {
            super.setup();
            this.panelId = 'weightEvolutionChart' + this.model.attributes.id;
            
            // Initialize data
            this.hasData = false;
            this.loading = false;
            this.weightData = [];
            this.showAllResults = false;
            
            // Initialize filter values
            this.limit = 60;
            this.startDate = '';
            this.endDate = '';
            
            // Load weight data
            this.loadWeightData();
        }
        
        loadWeightData() {
            this.loading = true;
            this.reRender();
            
            const recordId = this.model.id;
            
            const params = {
                where: [
                    {
                        type: 'equals',
                        attribute: 'contactId',
                        value: recordId
                    }
                ],
                orderBy: 'datum',
                // Fetch newest first so the limit keeps the most recent records;
                // they are re-sorted chronologically below for the chart.
                order: 'desc',
                maxSize: this.limit
            };
            
            // Add date filters if provided
            if (this.startDate) {
                params.where.push({
                    type: 'greaterThanOrEquals',
                    attribute: 'datum',
                    value: this.startDate
                });
            }
            
            if (this.endDate) {
                params.where.push({
                    type: 'lessThanOrEquals',
                    attribute: 'datum',
                    value: this.endDate
                });
            }
            
            Espo.Ajax.getRequest('CBodyscan', params)
                .then(response => {
                    // Sort the data by date to ensure proper order
                    const sortedData = (response.list || []).sort((a, b) => {
                        return new Date(a.datum) - new Date(b.datum);
                    });
                    this.processWeightData(sortedData);
                })
                .catch(error => {
                    console.error('Error loading weight data:', error);
                })
                .finally(() => {
                    this.loading = false;
                    this.reRender();
                });
        }
        
        processWeightData(rawData) {
            if (!rawData || rawData.length === 0) {
                this.hasData = false;
                return;
            }
            
            this.weightData = rawData;
            this.hasData = true;
            
            // Calculate overview statistics
            const first = rawData[0];
            const last = rawData[rawData.length - 1];
            
            this.firstDate = this.formatDate(first.datum);
            this.lastDate = this.formatDate(last.datum);
            // Overview rows. betterWhen: 'lower' / 'higher' decides the colour
            // of the evolution; null keeps it neutral. Rows marked extended are
            // only shown after clicking "Toon alle resultaten".
            const rowDefs = [
                {label: 'Gewicht', field: 'gewicht', unit: ' kg', decimals: 1, betterWhen: 'lower'},
                {label: 'Spieren (%)', field: 'spierpercentage', unit: '%', decimals: 1, betterWhen: 'higher'},
                {label: 'Vet (%)', field: 'vetPercentage', unit: '%', decimals: 1, betterWhen: 'lower'},
                {label: 'Vocht (%)', field: 'vochtpercentage', unit: '%', decimals: 1, betterWhen: 'higher'},
                {label: 'Visceraal vet', field: 'visceraalvet', unit: '', decimals: 1, betterWhen: 'lower'},
                {label: 'Spieren (kg)', field: 'spiermassa', unit: ' kg', decimals: 1, betterWhen: 'higher', extended: true},
                {label: 'Vet (kg)', field: 'vetmassa', unit: ' kg', decimals: 1, betterWhen: 'lower', extended: true},
                {label: 'Botmassa', field: 'botmassa', unit: ' kg', decimals: 1, betterWhen: 'higher', extended: true},
                {label: 'BMR', field: 'bmr', unit: '', decimals: 0, betterWhen: 'higher', extended: true},
                {label: 'Metabolische Lft.', field: 'metabolischeleeftijd', unit: '', decimals: 0, betterWhen: 'lower', extended: true},
                {label: 'Lichaamstype', field: 'lichaamstype', unit: '', decimals: 0, betterWhen: null, extended: true},
            ];

            this.allOverviewRows = rowDefs.map(def => {
                const firstValue = parseFloat(first[def.field] || 0);
                const lastValue = parseFloat(last[def.field] || 0);
                const change = lastValue - firstValue;

                let color = 'inherit';
                if (def.betterWhen && change !== 0) {
                    const improved = def.betterWhen === 'higher' ? change > 0 : change < 0;
                    color = improved ? 'green' : 'red';
                }

                return {
                    label: def.label,
                    extended: !!def.extended,
                    first: firstValue.toFixed(def.decimals) + def.unit,
                    last: lastValue.toFixed(def.decimals) + def.unit,
                    evolution: (change >= 0 ? '+' : '-') + ' ' + Math.abs(change).toFixed(def.decimals) + def.unit,
                    color: color,
                };
            });

            // Calculate days difference
            const firstDate = new Date(first.datum);
            const lastDate = new Date(last.datum);
            this.daysDiff = Math.abs(Math.ceil((lastDate - firstDate) / (1000 * 60 * 60 * 24)));
        }
        
        afterRender() {
            super.afterRender();
            
            if (this.hasData && !this.loading) {
                this.initializeChart();
            }
            
            // Bind filter events
            this.bindFilterEvents();
            this.bindApplyShowAll();
        }
        
        bindFilterEvents() {
            this.$el.find('[data-action="applyFilters"]').on('click', () => {
                this.applyFilters();
            });
        }

        bindApplyShowAll() {
            this.$el.find('[data-action="toggleAllResults"]').on('click', (e) => {
                e.preventDefault(); // Stop the link from navigating/scrolling
                this.actionToggleAllResults();
            });
        }
        
        applyFilters() {
            // Get filter values from form
            this.limit = parseInt(this.$el.find('input[name="limit"]').val()) || 60;
            this.startDate = this.$el.find('input[name="startDate"]').val() || '';
            this.endDate = this.$el.find('input[name="endDate"]').val() || '';
            
            // Reload data with new filters
            this.loadWeightData();
        }
        
        initializeChart() {
            if (this.gewichtChart) {
                this.gewichtChart.destroy();
            }
            if (this.percentageChart) {
                this.percentageChart.destroy();
            }
            if (this.kilogramChart) {
                this.kilogramChart.destroy();
            }
            if (this.metaboolChart) {
                this.metaboolChart.destroy();
            }
            if (this.vochtBmrChart) {
                this.vochtBmrChart.destroy();
            }
            
            const canvas = this.$el.find(`#${this.panelId}`)[0];
            const canvasPercentage = this.$el.find(`#${this.panelId}_percentage`)[0];
            const canvasKilogram = this.$el.find(`#${this.panelId}_kilogram`)[0];
            const canvasMetabool = this.$el.find(`#${this.panelId}_metabool`)[0];
            const canvasVochtBmr = this.$el.find(`#${this.panelId}_vocht_bmr`)[0];
            
            if (!canvas || !canvasPercentage || !canvasKilogram || !canvasMetabool || !canvasVochtBmr) {
                console.error('Canvas elements not found');
                return;
            }

            require(['lib!client/custom/lib/chartjs-adapter-date-fns.js'], (dateFnsAdapter) => {
                // For time scale, we need to pass the actual date objects, not formatted strings
                const labels = this.weightData.map(item => new Date(item.datum));
                const weights = this.weightData.map(item => parseFloat(item.gewicht));
                const vetPercentage = this.weightData.map(item => parseFloat(item.vetPercentage || 0));
                const spierPercentage = this.weightData.map(item => parseFloat(item.spierpercentage || 0));
                const vetMassa = this.weightData.map(item => parseFloat(item.vetmassa || 0));
                const spierMassa = this.weightData.map(item => parseFloat(item.spiermassa || 0));
                const visceraalVet = this.weightData.map(item => parseFloat(item.visceraalvet || 0));
                const metabolischeLeeftijd = this.weightData.map(item => parseFloat(item.metabolischeleeftijd || 0));
                const vochtPercentage = this.weightData.map(item => parseFloat(item.vochtpercentage || 0));
                const bmr = this.weightData.map(item => parseFloat(item.bmr || 0));
                
                this.gewichtChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Gewicht (kg)',
                            data: weights,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.1,
                            fill: true,
                            pointRadius: 5,
                            pointHoverRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 30,
                                right: 30,
                                top: 0,
                                bottom: 0
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
                                    stepSize: 2,
                                    displayFormats: {
                                        month: 'MMM yyyy',
                                        day: 'dd/MM/yyyy'
                                    }
                                },
                                ticks: {
                                    autoSkip: false
                                }
                            },
                            y: {
                                beginAtZero: false,
                                min: Math.floor(Math.min(...weights)) - 2,
                                max: Math.ceil(Math.max(...weights)) + 2
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function(context) {
                                        return `Gewicht: ${context.parsed.y.toFixed(1)} kg`;
                                    }
                                }
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });

                const minFatPercentageY = Math.floor(Math.min(...vetPercentage)) - 2;
                const maxFatPercentageY = Math.ceil(Math.max(...vetPercentage)) + 2;
                const minMusclePercentageY = Math.floor(Math.min(...spierPercentage)) - 2;
                const maxMusclePercentageY = Math.ceil(Math.max(...spierPercentage)) + 2;
                
                this.percentageChart = new Chart(canvasPercentage.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Vet (%)',
                                data: vetPercentage,
                                borderColor: 'rgba(255, 205, 86, 1)',
                                backgroundColor: 'rgba(255, 205, 86, 0.2)',
                                yAxisID: 'y2',
                                tension: 0.1,
                                pointRadius: 5,
                                pointHoverRadius: 8
                            },
                            {
                                label: 'Spier (%)',
                                data: spierPercentage,
                                borderColor: 'rgba(255, 99, 132, 1)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                yAxisID: 'y',
                                tension: 0.1,
                                pointRadius: 5,
                                pointHoverRadius: 8
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 30,
                                right: 30,
                                top: 0,
                                bottom: 0
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
                                    stepSize: 2,
                                    displayFormats: {
                                        month: 'MMM yyyy',
                                        day: 'dd/MM/yyyy'
                                    }
                                },
                                ticks: {
                                    autoSkip: false
                                }
                            },
                            y: {
                                type: 'linear',
                                position: 'left',
                                title: { display: true, text: 'Spieren (%)' },
                                min: minMusclePercentageY,
                                max: maxMusclePercentageY
                            },
                            y2: {
                                type: 'linear',
                                position: 'right',
                                title: { display: true, text: 'Vet (%)' },
                                grid: { drawOnChartArea: false },
                                min: minFatPercentageY,
                                max: maxFatPercentageY
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });

                const minFatKgY = Math.floor(Math.min(...vetMassa)) - 2;
                const maxFatKgY = Math.ceil(Math.max(...vetMassa)) + 2;
                const minMuscleKgY = Math.floor(Math.min(...spierMassa)) - 2;
                const maxMuscleKgY = Math.ceil(Math.max(...spierMassa)) + 2;
                
                this.kilogramChart = new Chart(canvasKilogram.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Vet (kg)',
                                data: vetMassa,
                                borderColor: 'rgba(255, 205, 86, 1)',
                                backgroundColor: 'rgba(255, 205, 86, 0.2)',
                                yAxisID: 'y2',
                                tension: 0.1,
                                pointRadius: 5,
                                pointHoverRadius: 8
                            },
                            {
                                label: 'Spier (kg)',
                                data: spierMassa,
                                borderColor: 'rgba(255, 99, 132, 1)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                yAxisID: 'y',
                                tension: 0.1,
                                pointRadius: 5,
                                pointHoverRadius: 8
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 30,
                                right: 30,
                                top: 0,
                                bottom: 0
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
                                    stepSize: 2,
                                    displayFormats: {
                                        month: 'MMM yyyy',
                                        day: 'dd/MM/yyyy'
                                    }
                                },
                                ticks: {
                                    autoSkip: false
                                }
                            },
                            y: {
                                type: 'linear',
                                position: 'left',
                                title: { display: true, text: 'Spieren (kg)' },
                                min: minMuscleKgY,
                                max: maxMuscleKgY
                            },
                            y2: {
                                type: 'linear',
                                position: 'right',
                                title: { display: true, text: 'Vet (kg)' },
                                grid: { drawOnChartArea: false },
                                min: minFatKgY,
                                max: maxFatKgY
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });

                const minMetabolischeLftY = Math.floor(Math.min(...metabolischeLeeftijd)) - 2;
                const maxMetabolischeLftY = Math.ceil(Math.max(...metabolischeLeeftijd)) + 2;

                const minVisceraalVetY = Math.max(Math.floor(Math.min(...visceraalVet)) - 2, 0);
                const maxVisceraalVetY = Math.ceil(Math.max(...visceraalVet)) + 2;
                
                this.metaboolChart = new Chart(canvasMetabool.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Visceraal vet',
                                data: visceraalVet,
                                borderColor: 'rgba(255, 205, 86, 1)',
                                backgroundColor: 'rgba(255, 205, 86, 0.2)',
                                yAxisID: 'y2',
                                tension: 0.1,
                                pointRadius: 5,
                                pointHoverRadius: 8
                            },
                            {
                                label: 'Metabolische leeftijd',
                                data: metabolischeLeeftijd,
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                yAxisID: 'y',
                                tension: 0.1,
                                pointRadius: 5,
                                pointHoverRadius: 8
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 30,
                                right: 30,
                                top: 0,
                                bottom: 0
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
                                    stepSize: 2,
                                    displayFormats: {
                                        month: 'MMM yyyy',
                                        day: 'dd/MM/yyyy'
                                    }
                                },
                                ticks: {
                                    autoSkip: false
                                }
                            },
                            y: {
                                type: 'linear',
                                position: 'left',
                                title: { display: true, text: 'Metabolische leeftijd' },
                                min: minMetabolischeLftY,
                                max: maxMetabolischeLftY
                            },
                            y2: {
                                type: 'linear',
                                position: 'right',
                                title: { display: true, text: 'Visceraal vet' },
                                grid: { drawOnChartArea: false },
                                min: minVisceraalVetY,
                                max: maxVisceraalVetY
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });

                const minVochtPercentageY = Math.floor(Math.min(...vochtPercentage)) - 2;
                const maxVochtPercentageY = Math.ceil(Math.max(...vochtPercentage)) + 2;
                const minBmrY = Math.floor(Math.min(...bmr)) - 50;
                const maxBmrY = Math.ceil(Math.max(...bmr)) + 50;
                
                this.vochtBmrChart = new Chart(canvasVochtBmr.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Vocht (%)',
                                data: vochtPercentage,
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                yAxisID: 'y',
                                tension: 0.1,
                                pointRadius: 5,
                                pointHoverRadius: 8
                            },
                            {
                                label: 'BMR',
                                data: bmr,
                                borderColor: 'rgba(255, 99, 132, 1)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                yAxisID: 'y2',
                                tension: 0.1,
                                pointRadius: 5,
                                pointHoverRadius: 8
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 30,
                                right: 30,
                                top: 0,
                                bottom: 0
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
                                    stepSize: 2,
                                    displayFormats: {
                                        month: 'MMM yyyy',
                                        day: 'dd/MM/yyyy'
                                    }
                                },
                                ticks: {
                                    autoSkip: false
                                }
                            },
                            y: {
                                type: 'linear',
                                position: 'left',
                                title: { display: true, text: 'Vocht (%)' },
                                min: minVochtPercentageY,
                                max: maxVochtPercentageY
                            },
                            y2: {
                                type: 'linear',
                                position: 'right',
                                title: { display: true, text: 'BMR' },
                                grid: { drawOnChartArea: false },
                                min: minBmrY,
                                max: maxBmrY
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });
            });
        }

        
        
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-GB'); // DD/MM/YYYY format
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
                showAllResults: this.showAllResults,
                overviewRows: (this.allOverviewRows || [])
                    .filter(row => this.showAllResults || !row.extended),
            };
        }

        actionToggleAllResults() {
            this.showAllResults = !this.showAllResults;
            this.reRender();
        }
        
        // Clean up chart when panel is removed
        remove() {
            // Unbind filter events
            this.$el.find('[data-action="applyFilters"]').off('click');
            
            if (this.gewichtChart) {
                this.gewichtChart.destroy();
            }
            if (this.percentageChart) {
                this.percentageChart.destroy();
            }
            if (this.kilogramChart) {
                this.kilogramChart.destroy();
            }
            if (this.metaboolChart) {
                this.metaboolChart.destroy();
            }
            if (this.vochtBmrChart) {
                this.vochtBmrChart.destroy();
            }
            super.remove();
        }
    }
});
