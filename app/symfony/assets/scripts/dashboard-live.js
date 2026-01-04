/**
 * Mise à jour automatique du dashboard avec AJAX
 */

import api, { showToast, formatDate } from './api.js';

class DashboardLive {
    constructor() {
        this.refreshInterval = 30000; // 30 secondes
        this.intervalId = null;
        this.isUpdating = false;
    }

    /**
     * Initialiser les mises à jour automatiques
     */
    init() {
        console.log('Dashboard Live initialized');

        // Mise à jour initiale
        this.updateStats();
        this.updateLiveMatches();

        // Démarrer les mises à jour automatiques
        this.startAutoRefresh();

        // Ajouter le bouton de rafraîchissement manuel
        this.addRefreshButton();

        // Écouter les événements Turbo
        document.addEventListener('turbo:before-cache', () => this.stopAutoRefresh());
        document.addEventListener('turbo:load', () => this.init());
    }

    /**
     * Démarrer les mises à jour automatiques
     */
    startAutoRefresh() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }

        this.intervalId = setInterval(() => {
            this.updateStats();
            this.updateLiveMatches();
        }, this.refreshInterval);
    }

    /**
     * Arrêter les mises à jour automatiques
     */
    stopAutoRefresh() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }

    /**
     * Mettre à jour les statistiques du dashboard
     */
    async updateStats() {
        if (this.isUpdating) return;

        try {
            this.isUpdating = true;

            const response = await api.getDashboardStats();

            if (response.success) {
                this.renderStats(response.stats);
            }
        } catch (error) {
            console.error('Error updating stats:', error);
        } finally {
            this.isUpdating = false;
        }
    }

    /**
     * Afficher les statistiques
     */
    renderStats(stats) {
        // Mettre à jour les cartes de statistiques
        const statCards = [
            { selector: '.stat-value.text-primary', value: stats.total_matches_today },
            { selector: '.stat-value.text-success', value: stats.safe_bets },
            { selector: '.stat-value.text-warning', value: stats.value_bets },
            { selector: '.stat-value.text-info', value: stats.high_confidence },
        ];

        statCards.forEach(({ selector, value }) => {
            const element = document.querySelector(selector);
            if (element) {
                this.animateValue(element, value);
            }
        });
    }

    /**
     * Animer le changement de valeur
     */
    animateValue(element, newValue) {
        const currentValue = parseInt(element.textContent) || 0;

        if (currentValue === newValue) return;

        // Animation simple
        element.style.transition = 'transform 0.3s';
        element.style.transform = 'scale(1.2)';

        setTimeout(() => {
            element.textContent = newValue;
            element.style.transform = 'scale(1)';
        }, 150);
    }

    /**
     * Mettre à jour les matchs en direct
     */
    async updateLiveMatches() {
        try {
            const response = await api.getLiveMatches();

            if (response.success && response.count > 0) {
                this.renderLiveMatches(response.matches);
            }
        } catch (error) {
            console.error('Error updating live matches:', error);
        }
    }

    /**
     * Afficher les matchs en direct
     */
    renderLiveMatches(matches) {
        const container = document.getElementById('liveMatchesContainer');

        if (!container) {
            // Créer le conteneur s'il n'existe pas
            this.createLiveMatchesContainer(matches);
            return;
        }

        // Mettre à jour le contenu
        const html = matches.map(match => this.createMatchCard(match)).join('');
        container.innerHTML = html;
    }

    /**
     * Créer le conteneur des matchs en direct
     */
    createLiveMatchesContainer(matches) {
        const dashboardContainer = document.querySelector('.container.my-4');

        if (!dashboardContainer) return;

        const html = `
            <div class="row mb-4">
                <div class="col">
                    <div class="alert alert-danger d-flex align-items-center">
                        <span class="badge bg-danger me-2">🔴 LIVE</span>
                        <strong class="me-auto">${matches.length} match(s) en cours</strong>
                        <button class="btn btn-sm btn-outline-danger" onclick="dashboardLive.updateLiveMatches()">
                            🔄 Actualiser
                        </button>
                    </div>
                </div>
            </div>
            <div class="row mb-4" id="liveMatchesContainer">
                ${matches.map(match => this.createMatchCard(match)).join('')}
            </div>
        `;

        dashboardContainer.insertAdjacentHTML('afterbegin', html);
    }

    /**
     * Créer une carte de match
     */
    createMatchCard(match) {
        return `
            <div class="col-md-4 mb-3">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <small>${match.league}</small>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-5">
                                <strong>${match.home_team}</strong>
                                <div class="h3">${match.home_score || 0}</div>
                            </div>
                            <div class="col-2">
                                <div class="badge bg-danger">LIVE</div>
                                <div class="small">${match.minute || 0}'</div>
                            </div>
                            <div class="col-5">
                                <strong>${match.away_team}</strong>
                                <div class="h3">${match.away_score || 0}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Ajouter le bouton de rafraîchissement manuel
     */
    addRefreshButton() {
        const navbar = document.querySelector('.navbar .navbar-nav');

        if (!navbar || document.getElementById('refreshButton')) return;

        const buttonHTML = `
            <li class="nav-item">
                <button id="refreshButton" class="nav-link btn btn-link" title="Rafraîchir les données">
                    🔄
                </button>
            </li>
        `;

        navbar.insertAdjacentHTML('afterbegin', buttonHTML);

        document.getElementById('refreshButton').addEventListener('click', async () => {
            const button = document.getElementById('refreshButton');
            button.disabled = true;
            button.innerHTML = '⏳';

            await this.updateStats();
            await this.updateLiveMatches();

            showToast('Données mises à jour', 'success');

            button.disabled = false;
            button.innerHTML = '🔄';
        });
    }

    /**
     * Synchroniser les matchs (appel API)
     */
    async syncMatches() {
        try {
            showToast('Synchronisation en cours...', 'info');

            const response = await api.syncMatches();

            if (response.success) {
                showToast(`${response.stats.synced} matchs synchronisés`, 'success');
                await this.updateStats();
            }
        } catch (error) {
            showToast('Erreur lors de la synchronisation', 'error');
            console.error('Sync error:', error);
        }
    }
}

// Créer une instance globale
const dashboardLive = new DashboardLive();

// Export pour utilisation globale
window.dashboardLive = dashboardLive;

export default dashboardLive;