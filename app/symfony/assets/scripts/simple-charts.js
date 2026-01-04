/**
 * Simple Charts - Solution légère sans dépendances pour les graphiques
 * Remplace Chart.js avec des graphiques CSS/SVG purs
 */

const colors = {
    primary: '#667eea',
    success: '#10b981',
    danger: '#ef4444',
    warning: '#f59e0b',
    info: '#3b82f6',
    purple: '#764ba2',
    gray: '#64748b',
};

/**
 * Créer un graphique circulaire (donut) en CSS
 */
export function createDonutChart(containerId, data) {
    const container = document.getElementById(containerId);
    if (!container) return null;

    const total = Object.values(data.values).reduce((a, b) => a + b, 0);
    if (total === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4">Aucune donnée</div>';
        return;
    }

    let cumulativePercent = 0;
    const segments = [];
    const colorMap = data.colors || [colors.success, colors.danger, colors.warning, colors.info, colors.purple];

    Object.entries(data.values).forEach(([label, value], index) => {
        const percent = (value / total) * 100;
        segments.push({
            label,
            value,
            percent,
            color: colorMap[index % colorMap.length],
            startPercent: cumulativePercent,
        });
        cumulativePercent += percent;
    });

    // Créer le gradient conique pour le donut
    const gradientStops = segments.map(seg =>
        `${seg.color} ${seg.startPercent}% ${seg.startPercent + seg.percent}%`
    ).join(', ');

    const html = `
        <div class="simple-donut-container">
            <div class="simple-donut" style="background: conic-gradient(${gradientStops});">
                <div class="simple-donut-center">
                    <span class="simple-donut-total">${total}</span>
                    <span class="simple-donut-label">Total</span>
                </div>
            </div>
            <div class="simple-donut-legend">
                ${segments.map(seg => `
                    <div class="simple-legend-item">
                        <span class="simple-legend-color" style="background: ${seg.color};"></span>
                        <span class="simple-legend-label">${seg.label}</span>
                        <span class="simple-legend-value">${seg.value} (${seg.percent.toFixed(0)}%)</span>
                    </div>
                `).join('')}
            </div>
        </div>
    `;

    container.innerHTML = html;
    return container;
}

/**
 * Créer un graphique en barres horizontales en CSS
 */
export function createHorizontalBarChart(containerId, data) {
    const container = document.getElementById(containerId);
    if (!container) return null;

    const maxValue = Math.max(...data.values);
    const colorMap = data.colors || [colors.primary, colors.success, colors.info, colors.warning, colors.purple];

    const html = `
        <div class="simple-bar-chart">
            ${data.labels.map((label, index) => {
                const value = data.values[index];
                const percent = maxValue > 0 ? (value / maxValue) * 100 : 0;
                const color = colorMap[index % colorMap.length];
                return `
                    <div class="simple-bar-row">
                        <div class="simple-bar-label">${label}</div>
                        <div class="simple-bar-track">
                            <div class="simple-bar-fill" style="width: ${percent}%; background: ${color};">
                                <span class="simple-bar-value">${value}${data.suffix || ''}</span>
                            </div>
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;

    container.innerHTML = html;
    return container;
}

/**
 * Créer un graphique en ligne simple avec SVG
 */
export function createLineChart(containerId, data) {
    const container = document.getElementById(containerId);
    if (!container) return null;

    const width = 400;
    const height = 200;
    const padding = 40;

    const values = data.values;
    const labels = data.labels;

    if (!values || values.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4">Aucune donnée</div>';
        return;
    }

    const minVal = Math.min(...values);
    const maxVal = Math.max(...values);
    const range = maxVal - minVal || 1;

    const xStep = (width - padding * 2) / (values.length - 1 || 1);

    // Créer les points du path
    const points = values.map((val, i) => {
        const x = padding + i * xStep;
        const y = height - padding - ((val - minVal) / range) * (height - padding * 2);
        return { x, y, val };
    });

    const pathD = points.map((p, i) =>
        (i === 0 ? 'M' : 'L') + `${p.x},${p.y}`
    ).join(' ');

    // Path pour le remplissage
    const fillD = pathD +
        ` L${points[points.length - 1].x},${height - padding}` +
        ` L${padding},${height - padding} Z`;

    // Générer les lignes de grille Y
    const gridLines = [];
    const gridLabels = [];
    for (let i = 0; i <= 4; i++) {
        const y = padding + (i / 4) * (height - padding * 2);
        const val = maxVal - (i / 4) * range;
        gridLines.push(`<line x1="${padding}" y1="${y}" x2="${width - padding}" y2="${y}" stroke="#e2e8f0" stroke-width="1"/>`);
        gridLabels.push(`<text x="${padding - 5}" y="${y + 4}" text-anchor="end" fill="#64748b" font-size="10">${val.toFixed(1)}${data.suffix || ''}</text>`);
    }

    const html = `
        <div class="simple-line-chart">
            <svg viewBox="0 0 ${width} ${height}" class="simple-line-svg">
                <!-- Grille -->
                ${gridLines.join('')}
                ${gridLabels.join('')}

                <!-- Zone remplie -->
                <path d="${fillD}" fill="rgba(16, 185, 129, 0.1)" />

                <!-- Ligne -->
                <path d="${pathD}" fill="none" stroke="${colors.success}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>

                <!-- Points -->
                ${points.map(p => `
                    <circle cx="${p.x}" cy="${p.y}" r="5" fill="${colors.success}" stroke="white" stroke-width="2"/>
                `).join('')}

                <!-- Labels X -->
                ${labels.map((label, i) => `
                    <text x="${padding + i * xStep}" y="${height - 10}" text-anchor="middle" fill="#64748b" font-size="10">${label}</text>
                `).join('')}
            </svg>
        </div>
    `;

    container.innerHTML = html;
    return container;
}

/**
 * Créer une jauge circulaire simple
 */
export function createGaugeChart(containerId, value, max = 100, label = '') {
    const container = document.getElementById(containerId);
    if (!container) return null;

    const percent = Math.min(100, Math.max(0, (value / max) * 100));
    const circumference = 2 * Math.PI * 45; // rayon = 45
    const dashOffset = circumference - (percent / 100) * circumference;

    // Couleur selon la valeur
    let color = colors.danger;
    if (percent >= 70) color = colors.success;
    else if (percent >= 50) color = colors.warning;

    const html = `
        <div class="simple-gauge">
            <svg viewBox="0 0 100 100" class="simple-gauge-svg">
                <circle cx="50" cy="50" r="45" fill="none" stroke="#e2e8f0" stroke-width="8"/>
                <circle cx="50" cy="50" r="45" fill="none" stroke="${color}" stroke-width="8"
                    stroke-dasharray="${circumference}"
                    stroke-dashoffset="${dashOffset}"
                    stroke-linecap="round"
                    transform="rotate(-90 50 50)"/>
            </svg>
            <div class="simple-gauge-center">
                <span class="simple-gauge-value">${value.toFixed(1)}%</span>
                ${label ? `<span class="simple-gauge-label">${label}</span>` : ''}
            </div>
        </div>
    `;

    container.innerHTML = html;
    return container;
}

/**
 * Créer des barres de progression empilées
 */
export function createStackedProgress(containerId, data) {
    const container = document.getElementById(containerId);
    if (!container) return null;

    const total = Object.values(data.values).reduce((a, b) => a + b, 0);
    const colorMap = data.colors || [colors.success, colors.warning, colors.danger];

    const segments = Object.entries(data.values).map(([label, value], index) => ({
        label,
        value,
        percent: total > 0 ? (value / total) * 100 : 0,
        color: colorMap[index % colorMap.length],
    }));

    const html = `
        <div class="simple-stacked-progress">
            <div class="simple-stacked-bar">
                ${segments.map(seg => `
                    <div class="simple-stacked-segment"
                         style="width: ${seg.percent}%; background: ${seg.color};"
                         title="${seg.label}: ${seg.value} (${seg.percent.toFixed(1)}%)">
                    </div>
                `).join('')}
            </div>
            <div class="simple-stacked-legend">
                ${segments.map(seg => `
                    <span class="simple-stacked-legend-item">
                        <span class="simple-legend-color" style="background: ${seg.color};"></span>
                        ${seg.label}: ${seg.value}
                    </span>
                `).join('')}
            </div>
        </div>
    `;

    container.innerHTML = html;
    return container;
}

// Export par défaut des fonctions
export default {
    createDonutChart,
    createHorizontalBarChart,
    createLineChart,
    createGaugeChart,
    createStackedProgress,
    colors,
};
