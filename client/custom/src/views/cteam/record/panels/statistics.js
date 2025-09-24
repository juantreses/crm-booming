define([
    'views/record/panels/bottom',
    'lib!chart'
], (BottomPanelView, Chart) => {

    return class extends BottomPanelView {

        templateContent = `
            <div class="team-statistics-panel">
                <div class="panel-header" style="margin-bottom: 15px;">
                    <h4>Team Lead Statistieken</h4>
                </div>

                <div class="filter-form" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <div class="row">
                        <div class="col-sm-4">
                            <label class="control-label">Startdatum</label>
                            <input type="date" class="form-control" name="startDate" value="{{startDate}}">
                        </div>
                        <div class="col-sm-4">
                            <label class="control-label">Einddatum</label>
                            <input type="date" class="form-control" name="endDate" value="{{endDate}}">
                        </div>
                        <div class="col-sm-4" style="display: flex; align-items: end; gap: 5px;">
                            <button type="button" class="btn btn-primary" data-action="applyFilters">
                                <span class="fas fa-filter"></span> Filters Toepassen
                            </button>
                            <button type="button" class="btn btn-default" data-action="resetFilters">
                                <span class="fas fa-sync"></span> Reset
                            </button>
                        </div>
                    </div>
                    <div class="row" style="margin-top: 10px;">
                        <div class="col-sm-12">
                            <button type="button" class="btn btn-xs btn-link" data-action="setPreset" data-preset="currentWeek">Deze week</button>
                            <button type="button" class="btn btn-xs btn-link" data-action="setPreset" data-preset="thisMonth">Deze maand</button>
                            <button type="button" class="btn btn-xs btn-link" data-action="setPreset" data-preset="thisQuarter">Dit kwartaal</button>
                            <button type="button" class="btn btn-xs btn-link" data-action="setPreset" data-preset="thisYear">Dit jaar</button>
                        </div>
                    </div>
                </div>

                <div class="statistics-container">
                    {{#if hasData}}
                        <div class="overview-cards" style="margin-bottom: 20px;">
                            <div class="row">
                                <div class="col-sm-3">
                                    <div class="card text-center" style="padding: 15px; background: #e3f2fd; border-radius: 5px;">
                                        <h3 style="margin: 0; color: #1976d2;">{{totalLeads}}</h3>
                                        <p style="margin: 5px 0 0 0;">Totaal Leads</p>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="card text-center" style="padding: 15px; background: #f3e5f5; border-radius: 5px;">
                                        <h3 style="margin: 0; color: #7b1fa2;">{{uniqueAppointmentBooked}}</h3>
                                        <p style="margin: 5px 0 0 0;">Afspraken Geboekt</p>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="card text-center" style="padding: 15px; background: #e8f5e8; border-radius: 5px;">
                                        <h3 style="margin: 0; color: #388e3c;">{{uniqueConvertedLeads}}</h3>
                                        <p style="margin: 5px 0 0 0;">Geconverteerde Leads</p>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="card text-center" style="padding: 15px; background: #fff3e0; border-radius: 5px;">
                                        <h3 style="margin: 0; color: #f57c00;">{{conversionRate}}%</h3>
                                        <p style="margin: 5px 0 0 0;">Conversie Percentage</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="chart-section" style="margin-bottom: 30px;">
                            <h5 style="margin-bottom: 15px;">Lead Statistieken</h5>
                            <div class="chart-container" style="position: relative; height: 400px;">
                                <canvas id="{{panelId}}_funnel"></canvas>
                            </div>
                        </div>

                        <div class="kpi-cards" style="margin-bottom: 20px;">
                            <h5 style="margin-bottom: 15px;">KPI's</h5>
                            <div class="row">
                                <div class="col-sm-3">
                                    <div class="card text-center" style="padding: 15px; background: #e8f5e8; border-radius: 5px;">
                                        <h3 style="margin: 0; color: #388e3c;">{{phoneConversion}}%</h3>
                                        <p style="margin: 5px 0 0 0;">Telefoon Conversie</p>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="card text-center" style="padding: 15px; background: #e3f2fd; border-radius: 5px;">
                                        <h3 style="margin: 0; color: #1976d2;">{{showUpRate}}%</h3>
                                        <p style="margin: 5px 0 0 0;">Opkomst Percentage</p>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="card text-center" style="padding: 15px; background: #ffebee; border-radius: 5px;">
                                        <h3 style="margin: 0; color: #d32f2f;">{{noShowRate}}%</h3>
                                        <p style="margin: 5px 0 0 0;">No-show Percentage</p>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="card text-center" style="padding: 15px; background: #f3e5f5; border-radius: 5px;">
                                        <h3 style="margin: 0; color: #7b1fa2;">{{meetingConversionRate}}%</h3>
                                        <p style="margin: 5px 0 0 0;">Kickstart Conversie</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{#if hasMemberStats}}
                        <div class="chart-section">
                            <h5 style="margin-bottom: 15px;">Klant Statistieken</h5>
                            <div class="chart-container" style="position: relative; height: 250px;">
                                <canvas id="{{panelId}}_members"></canvas>
                            </div>
                        </div>
                        {{/if}}

                    {{else}}
                        <div class="alert alert-info">
                            <span class="fas fa-info-circle"></span>
                            Geen statistiek gegevens beschikbaar voor dit team
                        </div>
                    {{/if}}
                </div>

                {{#if loading}}
                    <div class="loading-indicator">
                        <span class="fas fa-spinner fa-spin"></span> Team statistieken laden...
                    </div>
                {{/if}}
            </div>
        `

        setup() {
            super.setup();
            this.panelId = 'teamStatistics' + this.model.attributes.id;

            // Initialize data
            this.hasData = false;
            this.loading = false;
            this.statisticsData = null;

            // Initialize filter values
            this.setInitialDates();

            // Load statistics
            this.loadStatistics();
        }

        setInitialDates() {
            const today = new Date();
            const currentDay = today.getDay();
            const diff = today.getDate() - currentDay + (currentDay === 0 ? -6 : 1);
            
            // Create new Date objects to avoid mutating the original
            const monday = new Date(today.getFullYear(), today.getMonth(), diff);
            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);

            this.startDate = this.formatDateForInput(monday);
            this.endDate = this.formatDateForInput(sunday);
        }

        formatDateForInput(date) {
            // Use local timezone instead of UTC to avoid date shifting
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        setDatesFromPreset(preset) {
            const today = new Date();
            let startDate, endDate;

            switch (preset) {
                case 'thisMonth':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    break;
                case 'thisQuarter':
                    const currentMonth = today.getMonth();
                    const quarterStartMonth = Math.floor(currentMonth / 3) * 3;
                    startDate = new Date(today.getFullYear(), quarterStartMonth, 1);
                    endDate = new Date(today.getFullYear(), quarterStartMonth + 3, 0);
                    break;
                case 'thisYear':
                    startDate = new Date(today.getFullYear(), 0, 1);
                    endDate = new Date(today.getFullYear(), 11, 31);
                    break;
                case 'currentWeek':
                default:
                    this.setInitialDates();
                    this.loadStatistics();
                    return;
            }

            this.startDate = this.formatDateForInput(startDate);
            this.endDate = this.formatDateForInput(endDate);
            this.loadStatistics();
        }

        resetFilters() {
            this.setInitialDates();
            this.reRender();
            this.loadStatistics();
        }

        loadStatistics() {
            this.loading = true;
            this.reRender();

            const teamId = this.model.id;
            const url = `CTeam/${teamId}/statistics`;
            
            const params = {};
            if (this.startDate) {
                params.startDate = this.startDate;
            }
            if (this.endDate) {
                params.endDate = this.endDate;
            }

            const queryString = Object.keys(params).length > 0 ? '?' + new URLSearchParams(params).toString() : '';

            Espo.Ajax.getRequest(url + queryString)
                .then(response => {
                    if (response.success && response.data) {
                        this.processStatisticsData(response.data);
                    } else {
                        console.error('Invalid response format:', response);
                        this.hasData = false;
                    }
                })
                .catch(error => {
                    console.error('Error loading team statistics:', error);
                    this.hasData = false;
                })
                .finally(() => {
                    this.loading = false;
                    this.reRender();
                });
        }

        processStatisticsData(data) {
            this.statisticsData = data;
            this.hasData = true;

            // Extract key metrics
            const leadStats = data.leadStatistics || {};
            const eventStats = data.leadEventStatistics || {};

            this.totalLeads = leadStats.totalLeads || 0;
            this.uniqueLeadsCalled = eventStats.uniqueLeadsCalled || 0;
            this.uniqueInvited = eventStats.uniqueInvited || 0;
            this.uniqueAppointmentBooked = eventStats.uniqueAppointmentBooked || 0;
            this.uniqueAttended = eventStats.uniqueAttended || 0;
            this.uniqueNoShow = eventStats.uniqueNoShow || 0;
            this.uniqueConvertedLeads = eventStats.uniqueConvertedLeads || 0;

            // Calculate conversion rate
            this.conversionRate = this.totalLeads > 0 
                ? ((this.uniqueConvertedLeads / this.totalLeads) * 100).toFixed(1)
                : '0.0';

            // Calculate KPIs
            this.phoneConversion = this.uniqueLeadsCalled > 0 
                ? ((this.uniqueInvited / this.uniqueLeadsCalled) * 100).toFixed(1)
                : '0.0';

            this.showUpRate = this.uniqueInvited > 0 
                ? ((this.uniqueAttended / this.uniqueInvited) * 100).toFixed(1)
                : '0.0';

            this.noShowRate = this.uniqueInvited > 0 
                ? ((this.uniqueNoShow / this.uniqueInvited) * 100).toFixed(1)
                : '0.0';

            this.meetingConversionRate = this.uniqueAttended > 0 
                ? ((this.uniqueConvertedLeads / this.uniqueAttended) * 100).toFixed(1)
                : '0.0';

            // Check if we have member statistics
            this.hasMemberStats = data.memberStatistics && Object.keys(data.memberStatistics).length > 0;
        }

        afterRender() {
            super.afterRender();
            
            // This is needed to update the input fields with the new date values
            this.$el.find('input[name="startDate"]').val(this.startDate);
            this.$el.find('input[name="endDate"]').val(this.endDate);

            if (this.hasData && !this.loading) {
                this.initializeCharts();
            }

            this.bindFilterEvents();
        }

        bindFilterEvents() {
            this.$el.find('[data-action="applyFilters"]').on('click', () => {
                this.applyFilters();
            });
            this.$el.find('[data-action="resetFilters"]').on('click', () => {
                this.resetFilters();
            });
            this.$el.find('[data-action="setPreset"]').on('click', (event) => {
                const preset = $(event.currentTarget).data('preset');
                this.setDatesFromPreset(preset);
            });
        }

        applyFilters() {
            this.startDate = this.$el.find('input[name="startDate"]').val() || '';
            this.endDate = this.$el.find('input[name="endDate"]').val() || '';
            this.loadStatistics();
        }

        initializeCharts() {
            // Destroy existing charts
            if (this.funnelChart) this.funnelChart.destroy();
            if (this.membersChart) this.membersChart.destroy();

            const funnelCanvas = this.$el.find(`#${this.panelId}_funnel`)[0];
            const membersCanvas = this.$el.find(`#${this.panelId}_members`)[0];

            if (!funnelCanvas) {
                console.error('Canvas elements not found');
                return;
            }

            this.createFunnelChart(funnelCanvas);
            
            if (membersCanvas && this.hasMemberStats) {
                this.createMembersChart(membersCanvas);
            }
        }

        createFunnelChart(canvas) {
            // Updated to show uniqueAttended, based on user feedback
            const funnelData = [
                { label: 'Totaal Leads', value: this.totalLeads, color: '#2196f3' },
                { label: 'Gebeld', value: this.uniqueLeadsCalled, color: '#ff9800' },
                { label: 'Afspraken Aanwezig', value: this.uniqueAttended, color: '#9c27b0' },
                { label: 'Geconverteerd', value: this.uniqueConvertedLeads, color: '#4caf50' }
            ];

            this.funnelChart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: funnelData.map(item => item.label),
                    datasets: [{
                        label: 'Aantal',
                        data: funnelData.map(item => item.value),
                        backgroundColor: funnelData.map(item => item.color + '80'),
                        borderColor: funnelData.map(item => item.color),
                        borderWidth: 2
                    }]
                },
                options: {
                    indexAxis: 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            },
                            title: {
                                display: true,
                                text: 'Aantal'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Leads'
                            }
                        }
                    }
                }
            });
        }

        createMembersChart(canvas) {
            const memberData = this.statisticsData.memberStatistics || {};
            const labels = Object.keys(memberData);
            const data = Object.values(memberData);
            const colors = this.generateColors(labels.length);

            this.membersChart = new Chart(canvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors.background,
                        borderColor: colors.border,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.parsed}`;
                                }
                            }
                        }
                    }
                }
            });
        }

        generateColors(count) {
            const colorPalette = [
                '#2196f3', '#4caf50', '#ff9800', '#9c27b0', 
                '#f44336', '#00bcd4', '#8bc34a', '#ffc107',
                '#e91e63', '#607d8b', '#795548', '#3f51b5'
            ];

            const background = [];
            const border = [];

            for (let i = 0; i < count; i++) {
                const color = colorPalette[i % colorPalette.length];
                background.push(color + '80'); // Add transparency
                border.push(color);
            }

            return { background, border };
        }

        data() {
            return {
                ...super.data(),
                hasData: this.hasData,
                loading: this.loading,
                panelId: this.panelId,
                startDate: this.startDate,
                endDate: this.endDate,
                totalLeads: this.totalLeads,
                uniqueAppointmentBooked: this.uniqueAppointmentBooked,
                uniqueConvertedLeads: this.uniqueConvertedLeads,
                conversionRate: this.conversionRate,
                phoneConversion: this.phoneConversion,
                showUpRate: this.showUpRate,
                noShowRate: this.noShowRate,
                meetingConversionRate: this.meetingConversionRate,
                hasMemberStats: this.hasMemberStats
            };
        }

        remove() {
            // Clean up event listeners
            this.$el.find('[data-action="applyFilters"]').off('click');
            this.$el.find('[data-action="resetFilters"]').off('click');
            this.$el.find('[data-action="setPreset"]').off('click');

            // Destroy charts
            if (this.funnelChart) this.funnelChart.destroy();
            if (this.membersChart) this.membersChart.destroy();

            super.remove();
        }
    }
});