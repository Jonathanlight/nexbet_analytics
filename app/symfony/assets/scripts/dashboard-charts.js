/**
 * Gestion des graphiques du dashboard
 * Version simplifiée sans Chart.js - utilise des graphiques CSS purs
 */

import SimpleCharts from './simple-charts.js';

class DashboardCharts {
    constructor() {
        this.initialized = false;
    }

    /**
     * Vérifier si les conteneurs existent dans le DOM
     */
    containersExist() {
        return document.getElementById('predictionStatsChart') !== null ||
               document.getElementById('roiChart') !== null;
    }

    /**
     * Initialiser les graphiques
     */
    initCharts() {
        // Vérifier que nous sommes sur la page du dashboard
        if (!this.containersExist()) {
            return;
        }

        // Données pour les statistiques de prédictions
        const predictionData = {
            values: {
                'Réussies': 65,
                'Échouées': 25,
                'En attente': 10
            },
            colors: ['#10b981', '#ef4444', '#f59e0b']
        };

        // Données pour l'évolution du ROI
        const roiData = {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil'],
            values: [5.2, 8.5, 12.3, 15.8, 18.2, 22.5, 25.8],
            suffix: '%'
        };

        // Créer le graphique de prédictions (donut)
        const predictionContainer = document.getElementById('predictionStatsChart');
        if (predictionContainer) {
            SimpleCharts.createDonutChart('predictionStatsChart', predictionData);
        }

        // Créer le graphique ROI (ligne)
        const roiContainer = document.getElementById('roiChart');
        if (roiContainer) {
            SimpleCharts.createLineChart('roiChart', roiData);
        }

        this.initialized = true;
    }

    /**
     * Mettre à jour les données des graphiques
     */
    updateCharts(data) {
        if (data.predictions) {
            SimpleCharts.createDonutChart('predictionStatsChart', {
                values: data.predictions,
                colors: ['#10b981', '#ef4444', '#f59e0b']
            });
        }

        if (data.roi) {
            SimpleCharts.createLineChart('roiChart', {
                labels: data.roi.labels,
                values: data.roi.values,
                suffix: '%'
            });
        }
    }

    /**
     * Initialiser le dashboard
     */
    init() {
        if (!this.containersExist()) {
            return;
        }
        this.initCharts();
    }

    /**
     * Nettoyer (pas nécessaire avec CSS, mais gardé pour compatibilité)
     */
    destroy() {
        this.initialized = false;
    }
}

// Créer une instance globale
const dashboardCharts = new DashboardCharts();

// Fonction d'initialisation
function initDashboard() {
    setTimeout(() => {
        dashboardCharts.init();
    }, 50);
}

// Écouter les événements Turbo et DOM
document.addEventListener('turbo:load', initDashboard);
document.addEventListener('turbo:render', initDashboard);
document.addEventListener('DOMContentLoaded', initDashboard);

export default dashboardCharts;
