/**
 * Service API pour gérer les appels AJAX
 */

class ApiService {
    constructor(baseUrl = '/api') {
        this.baseUrl = baseUrl;
    }

    /**
     * Effectue une requête HTTP générique
     */
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;

        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        };

        const config = { ...defaultOptions, ...options };

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || `HTTP error! status: ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }

    /**
     * GET request
     */
    async get(endpoint) {
        return this.request(endpoint, {
            method: 'GET',
        });
    }

    /**
     * POST request
     */
    async post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    /**
     * PUT request
     */
    async put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    /**
     * DELETE request
     */
    async delete(endpoint) {
        return this.request(endpoint, {
            method: 'DELETE',
        });
    }

    // === Endpoints spécifiques ===

    /**
     * Récupérer les matchs du jour
     */
    async getTodayMatches() {
        return this.get('/matches/today');
    }

    /**
     * Récupérer les détails d'un match
     */
    async getMatchDetails(matchId) {
        return this.get(`/matches/${matchId}`);
    }

    /**
     * Synchroniser les matchs
     */
    async syncMatches(date = null) {
        return this.post('/matches/sync', { date });
    }

    /**
     * Récupérer les statistiques du dashboard
     */
    async getDashboardStats() {
        return this.get('/stats/dashboard');
    }

    /**
     * Récupérer les matchs en direct
     */
    async getLiveMatches() {
        return this.get('/matches/live');
    }

    /**
     * Récupérer le profil utilisateur
     */
    async getUserProfile() {
        return this.get('/profile');
    }

    /**
     * Mettre à jour le profil utilisateur
     */
    async updateUserProfile(data) {
        return this.put('/profile', data);
    }
}

// Créer une instance globale
const api = new ApiService();

// Export pour utilisation dans d'autres modules
export default api;

/**
 * Utilitaires pour afficher les messages
 */
export function showToast(message, type = 'info') {
    // Créer un élément toast Bootstrap
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();

    const toastId = `toast-${Date.now()}`;
    const bgClass = {
        'success': 'bg-success',
        'error': 'bg-danger',
        'warning': 'bg-warning',
        'info': 'bg-info',
    }[type] || 'bg-info';

    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);

    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();

    // Supprimer l'élément après qu'il soit caché
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

/**
 * Formater une date
 */
export function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Formater un nombre avec 2 décimales
 */
export function formatNumber(number, decimals = 2) {
    return Number(number).toFixed(decimals);
}