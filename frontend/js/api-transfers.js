/**
 * API overrides/extensions for Transfer module
 * Uses the global API object from api.js
 */

window.API = window.API || {};
window.API.transfers = {
    /**
     * Get all transfers
     */
    getAll() {
        return window.API.get('/api/transfers');
    },

    /**
     * Create a new transfer request
     */
    create(data) {
        return window.API.post('/api/transfers', data);
    },

    /**
     * Update transfer status (approve, receive, cancel)
     */
    updateStatus(id, status) {
        return window.API.put(`/api/transfers/${id}/status`, { status });
    }
};
