import Chart from 'chart.js/auto';

/**
 * Configuration globale pour tous les charts
 */
Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
Chart.defaults.color = '#64748b';
Chart.defaults.borderColor = 'rgba(148, 163, 184, 0.1)';

/**
 * Couleurs du thème
 */
const colors = {
    primary: '#667eea',
    primaryLight: 'rgba(102, 126, 234, 0.1)',
    success: '#10b981',
    successLight: 'rgba(16, 185, 129, 0.1)',
    danger: '#ef4444',
    dangerLight: 'rgba(239, 68, 68, 0.1)',
    warning: '#f59e0b',
    warningLight: 'rgba(245, 158, 11, 0.1)',
    info: '#3b82f6',
    infoLight: 'rgba(59, 130, 246, 0.1)',
    purple: '#764ba2',
    purpleLight: 'rgba(118, 75, 162, 0.1)',
};

/**
 * Animation de fade-in pour les charts
 */
const fadeInAnimation = {
    onComplete: (animation) => {
        animation.chart.canvas.style.opacity = 1;
    },
    delay: (context) => {
        let delay = 0;
        if (context.type === 'data' && context.mode === 'default') {
            delay = context.dataIndex * 50 + context.datasetIndex * 100;
        }
        return delay;
    },
};

/**
 * Options communes pour les charts
 */
const commonOptions = {
    responsive: true,
    maintainAspectRatio: true,
    animation: {
        duration: 800,
        easing: 'easeInOutQuart',
        ...fadeInAnimation,
    },
    interaction: {
        mode: 'index',
        intersect: false,
    },
    plugins: {
        legend: {
            display: true,
            position: 'top',
            labels: {
                padding: 15,
                usePointStyle: true,
                font: {
                    size: 12,
                    weight: '500',
                },
            },
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            cornerRadius: 8,
            titleFont: {
                size: 14,
                weight: '600',
            },
            bodyFont: {
                size: 13,
            },
            borderColor: 'rgba(255, 255, 255, 0.1)',
            borderWidth: 1,
        },
    },
};

/**
 * Créer un chart de statistiques de prédictions
 */
export function createPredictionStatsChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    ctx.style.opacity = 0;

    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Réussies', 'Échouées', 'En attente'],
            datasets: [{
                data: [data.success, data.failed, data.pending],
                backgroundColor: [
                    colors.success,
                    colors.danger,
                    colors.warning,
                ],
                borderWidth: 0,
                hoverOffset: 10,
            }],
        },
        options: {
            ...commonOptions,
            cutout: '70%',
            plugins: {
                ...commonOptions.plugins,
                legend: {
                    ...commonOptions.plugins.legend,
                    position: 'bottom',
                },
            },
        },
    });
}

/**
 * Créer un chart de performance par sport
 */
export function createSportPerformanceChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    ctx.style.opacity = 0;

    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.sports,
            datasets: [{
                label: 'Taux de réussite (%)',
                data: data.successRates,
                backgroundColor: colors.primaryLight,
                borderColor: colors.primary,
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
            }],
        },
        options: {
            ...commonOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        drawBorder: false,
                    },
                    ticks: {
                        callback: (value) => value + '%',
                    },
                },
                x: {
                    grid: {
                        display: false,
                    },
                },
            },
        },
    });
}

/**
 * Créer un chart d'évolution du ROI
 */
export function createROIChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    ctx.style.opacity = 0;

    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.dates,
            datasets: [{
                label: 'ROI (%)',
                data: data.roi,
                borderColor: colors.success,
                backgroundColor: colors.successLight,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: colors.success,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
            }],
        },
        options: {
            ...commonOptions,
            scales: {
                y: {
                    beginAtZero: false,
                    grid: {
                        drawBorder: false,
                    },
                    ticks: {
                        callback: (value) => value + '%',
                    },
                },
                x: {
                    grid: {
                        display: false,
                    },
                },
            },
        },
    });
}

/**
 * Créer un chart de répartition des paris
 */
export function createBetDistributionChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    ctx.style.opacity = 0;

    return new Chart(ctx, {
        type: 'polarArea',
        data: {
            labels: data.betTypes,
            datasets: [{
                data: data.amounts,
                backgroundColor: [
                    colors.primaryLight,
                    colors.successLight,
                    colors.infoLight,
                    colors.warningLight,
                    colors.purpleLight,
                ],
                borderColor: [
                    colors.primary,
                    colors.success,
                    colors.info,
                    colors.warning,
                    colors.purple,
                ],
                borderWidth: 2,
            }],
        },
        options: {
            ...commonOptions,
            scales: {
                r: {
                    grid: {
                        circular: true,
                    },
                    beginAtZero: true,
                },
            },
        },
    });
}

/**
 * Créer un chart de comparaison de cotes
 */
export function createOddsComparisonChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    ctx.style.opacity = 0;

    const datasets = data.bookmakers.map((bookmaker, index) => ({
        label: bookmaker,
        data: data.odds[index],
        backgroundColor: [colors.primaryLight, colors.successLight, colors.dangerLight],
        borderColor: [colors.primary, colors.success, colors.danger],
        borderWidth: 2,
        borderRadius: 6,
    }));

    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['1', 'X', '2'],
            datasets: datasets,
        },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                title: {
                    display: true,
                    text: 'Comparaison des cotes',
                    font: {
                        size: 16,
                        weight: '600',
                    },
                    padding: 20,
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false,
                    },
                },
                x: {
                    grid: {
                        display: false,
                    },
                },
            },
        },
    });
}

/**
 * Initialiser tous les charts de la page
 */
export function initializeCharts() {
    // Les charts seront initialisés via les fonctions d'export ci-dessus
    console.log('Chart.js module loaded and ready');
}