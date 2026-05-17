const ReportsAPI = {
    /**
     * Helper for making generic fetch requests matching our global architecture
     */
    async request(endpoint) {
        try {
            const response = await fetch(endpoint, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            return await response.json();
        } catch (error) {
            console.error(`Error fetching ${endpoint}:`, error);
            return { success: false, message: 'Network error occurred' };
        }
    },

    async getSalesSummary(startDate = '', endDate = '') {
        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        return this.request(`/api/reports/sales/summary?${params}`);
    },

    async getSalesByBranch(startDate = '', endDate = '') {
        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        return this.request(`/api/reports/sales/branch?${params}`);
    },

    async getSalesBySeller(startDate = '', endDate = '') {
        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        return this.request(`/api/reports/sales/seller?${params}`);
    },

    async getInventoryValuation() {
        return this.request(`/api/reports/inventory/valuation`);
    },

    async getLowStock() {
        return this.request(`/api/reports/inventory/low-stock`);
    }
};
