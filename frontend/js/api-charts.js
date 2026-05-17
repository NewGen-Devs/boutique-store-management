/**
 * Charts API Module
 * Fetches configured plotting data from the backend
 */
const ChartsAPI = {
    async getSalesTrend(days = 30) {
        return API.get(`/api/charts/sales-trend?days=${days}`);
    },

    async getBranchRevenue() {
        return API.get(`/api/charts/revenue-by-branch`);
    },

    async getCategoryPerformance() {
        return API.get(`/api/charts/category-performance`);
    }
};

