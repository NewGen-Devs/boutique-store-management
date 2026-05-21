/**
 * API overrides/extensions for Inventory module
 * Uses the global API object from api.js
 */

window.API = window.API || {};
window.API.inventory = {
    /**
     * Get all inventory levels for a branch
     */
    getLevels(branchId = '') {
        const query = branchId ? `?branch_id=${branchId}` : '';
        return window.API.get(`/api/inventory${query}`);
    },

    /**
     * Get low stock alerts for a branch
     */
    getLowStock(branchId = '') {
        const query = branchId ? `?branch_id=${branchId}` : '';
        return window.API.get(`/api/inventory/low-stock${query}`);
    },

    /**
     * Get stock movement history for a branch
     */
    getHistory(branchId = '') {
        const query = branchId ? `?branch_id=${branchId}` : '';
        return window.API.get(`/api/inventory/history${query}`);
    },

    /**
     * Adjust stock (in, out, damage, adjustment)
     */
    adjustStock(data) {
        return window.API.post('/api/inventory/adjust', data);
    }
};
