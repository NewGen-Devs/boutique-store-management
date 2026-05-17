const DashboardAPI = {
    async request(endpoint) {
        try {
            const response = await fetch(endpoint, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            return await response.json();
        } catch (error) {
            console.error(`Error fetching ${endpoint}:`, error);
            return { success: false, message: 'Network error occurred' };
        }
    },

    async getManagerDashboard() {
        return this.request('/api/dashboard/manager');
    },

    async getStoreKeeperDashboard() {
        return this.request('/api/dashboard/storekeeper');
    },

    async getSellerDashboard() {
        return this.request('/api/dashboard/seller');
    }
};
