(function () {
    const data = window.DatabruhDashboardData || {};

    const eventTypeLabels = data.eventTypeLabels ?? [];
    const eventTypeValues = data.eventTypeValues ?? [];
    const severityLabels = data.severityLabels ?? [];
    const severityValues = data.severityValues ?? [];
    const depotLabels = data.depotLabels ?? [];
    const depotValues = data.depotValues ?? [];
    const driverScores = data.driverScores ?? {};
    const scoreLabels = data.scoreLabels ?? [];
    const costLabels = data.costLabels ?? [];
    const costValues = data.costValues ?? [];
    const downtimeLabels = data.downtimeLabels ?? [];
    const downtimeValues = data.downtimeValues ?? [];
    const statusLabels = data.statusLabels ?? [];
    const statusValues = data.statusValues ?? [];
    const alertLabels = data.alertLabels ?? [];
    const alertValues = data.alertValues ?? [];

    Chart.defaults.color = '#58636b';
    Chart.defaults.font.family = "'Geist', 'Avenir Next', sans-serif";
    Chart.defaults.borderColor = 'rgba(17, 29, 38, 0.1)';

    const sharedScale = {
        grid: { color: 'rgba(17, 29, 38, 0.08)' },
        ticks: { color: '#58636b' }
    };

    const chartMotion = {
        reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        entries: new Map(),
        register(canvasId, config) {
            const canvas = document.getElementById(canvasId);

            if (!canvas) {
                return null;
            }

            const targetData = (config.data?.datasets ?? []).map(
                (dataset) => Array.isArray(dataset.data) ? [...dataset.data] : []
            );

            if (!this.reducedMotion) {
                config.data.datasets.forEach((dataset, index) => {
                    dataset.data = targetData[index].map((value) =>
                        Number.isFinite(Number(value)) ? 0 : value
                    );
                });
            }

            config.options = {
                ...(config.options ?? {}),
                animation: { duration: 0 }
            };

            const chart = new Chart(canvas, config);
            const card = canvas.closest('[data-chart-card]');

            this.entries.set(canvasId, {
                canvas,
                card,
                chart,
                targetData,
                type: config.type,
                played: this.reducedMotion
            });

            canvas.dataset.chartAnimationState = this.reducedMotion ? 'complete' : 'ready';

            if (this.reducedMotion) {
                card?.classList.add('is-chart-active', 'is-chart-drawn');
            }

            return chart;
        },
        play(canvasId) {
            const entry = this.entries.get(canvasId);

            if (!entry || entry.played) {
                return;
            }

            entry.played = true;
            entry.card?.classList.add('is-chart-active');

            if (this.reducedMotion) {
                entry.canvas.dataset.chartAnimationState = 'complete';
                entry.card?.classList.add('is-chart-drawn');
                return;
            }

            entry.chart.stop();
            entry.targetData.forEach((values, index) => {
                entry.chart.data.datasets[index].data = [...values];
            });

            entry.chart.options.animation.duration = entry.type === 'bar' ? 1650 : 1350;
            entry.chart.options.animation.easing = 'easeOutQuart';
            entry.chart.options.animation.delay = (context) => {
                if (context.type !== 'data') {
                    return 0;
                }

                const step = entry.type === 'bar' ? 90 : 55;
                return context.dataIndex * step + context.datasetIndex * 60;
            };
            entry.chart.options.animation.onComplete = () => {
                entry.canvas.dataset.chartAnimationState = 'complete';
                entry.card?.classList.add('is-chart-drawn');
            };
            entry.canvas.dataset.chartAnimationState = 'playing';
            entry.chart.update();
        }
    };

    window.DatabruhCharts = chartMotion;

    chartMotion.register('eventTypeChart', {
        type: 'bar',
        data: {
            labels: eventTypeLabels,
            datasets: [{
                label: 'Number of Incidents',
                data: eventTypeValues,
                backgroundColor: '#285f77',
                hoverBackgroundColor: '#c74732',
                borderColor: '#111d26',
                hoverBorderColor: '#111d26',
                borderWidth: 1,
                hoverBorderWidth: 3,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            transitions: {
                active: {
                    animation: { duration: 240, easing: 'easeOutQuart' }
                }
            },
            plugins: { legend: { display: false } },
            scales: {
                x: sharedScale,
                y: {
                    ...sharedScale,
                    beginAtZero: true,
                    ticks: { ...sharedScale.ticks, stepSize: 1 }
                }
            }
        }
    });

    chartMotion.register('severityChart', {
        type: 'doughnut',
        data: {
            labels: severityLabels,
            datasets: [{
                data: severityValues,
                backgroundColor: ['#42695e', '#a97221', '#b83d29', '#742a23'],
                borderColor: '#f2ddd7',
                borderWidth: 4,
                hoverOffset: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, boxHeight: 10, padding: 16 }
                }
            }
        }
    });

    chartMotion.register('depotChart', {
        type: 'bar',
        data: {
            labels: depotLabels,
            datasets: [{
                label: 'Number of Incidents',
                data: depotValues,
                backgroundColor: '#285f77',
                hoverBackgroundColor: '#c74732',
                borderColor: '#111d26',
                hoverBorderColor: '#111d26',
                borderWidth: 1,
                hoverBorderWidth: 3,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            transitions: {
                active: {
                    animation: { duration: 240, easing: 'easeOutQuart' }
                }
            },
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    ...sharedScale,
                    beginAtZero: true,
                    ticks: { ...sharedScale.ticks, stepSize: 1 }
                },
                y: sharedScale
            }
        }
    });

    const scoreColors = ['#111d26', '#b83d29', '#285f77', '#42695e', '#a97221'];

    const scoreDatasets = Object.keys(driverScores).map((name, i) => ({
        label: name,
        data: driverScores[name],
        borderColor: scoreColors[i % scoreColors.length],
        backgroundColor: 'transparent',
        spanGaps: true,
        tension: 0.3,
        pointRadius: 3,
        pointHoverRadius: 5
    }));

    chartMotion.register('scoreChart', {
        type: 'line',
        data: {
            labels: scoreLabels,
            datasets: scoreDatasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, boxHeight: 10, padding: 14 }
                }
            },
            scales: {
                x: sharedScale,
                y: { ...sharedScale, min: 0, max: 160 }
            }
        }
    });

    chartMotion.register('costChart', {
        type: 'bar',
        data: {
            labels: costLabels,
            datasets: [{
                label: 'Total Cost (VND)',
                data: costValues,
                backgroundColor: '#285f77',
                hoverBackgroundColor: '#c74732',
                borderColor: '#111d26',
                hoverBorderColor: '#111d26',
                borderWidth: 1,
                hoverBorderWidth: 3,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            transitions: {
                active: {
                    animation: { duration: 240, easing: 'easeOutQuart' }
                }
            },
            plugins: { legend: { display: false } },
            scales: {
                x: sharedScale,
                y: { ...sharedScale, beginAtZero: true }
            }
        }
    });

    chartMotion.register('downtimeChart', {
        type: 'bar',
        data: {
            labels: downtimeLabels,
            datasets: [{
                label: 'Downtime (Hours)',
                data: downtimeValues,
                backgroundColor: '#a97221',
                hoverBackgroundColor: '#c74732',
                borderColor: '#111d26',
                hoverBorderColor: '#111d26',
                borderWidth: 1,
                hoverBorderWidth: 3,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            transitions: {
                active: {
                    animation: { duration: 240, easing: 'easeOutQuart' }
                }
            },
            plugins: { legend: { display: false } },
            scales: {
                x: { ...sharedScale, beginAtZero: true },
                y: sharedScale
            }
        }
    });

    chartMotion.register('statusChart', {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusValues,
                backgroundColor: ['#42695e', '#285f77', '#a97221', '#b83d29', '#742a23', '#111d26'],
                borderColor: '#f2ddd7',
                borderWidth: 4,
                hoverOffset: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, boxHeight: 10, padding: 16 }
                }
            }
        }
    });

    chartMotion.register('alertsChart', {
        type: 'pie',
        data: {
            labels: alertLabels,
            datasets: [{
                data: alertValues,
                backgroundColor: ['#b83d29', '#a97221'],
                borderColor: '#f2ddd7',
                borderWidth: 4,
                hoverOffset: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, boxHeight: 10, padding: 16 }
                }
            }
        }
    });
})();
