let salesTrendChartInstance = null;
let branchRevenueChartInstance = null;
let categoryChartInstance = null;

async function renderDashboardCharts() {
    // Only render if Chart is globally available
    if (typeof Chart === 'undefined') {
        console.error('Chart.js library is not loaded');
        return;
    }

    try {
        const [trendRes, branchRes, categoryRes] = await Promise.all([
            ChartsAPI.getSalesTrend(30),
            ChartsAPI.getBranchRevenue(),
            ChartsAPI.getCategoryPerformance()
        ]);

        // 1. Sales Trend
        if (trendRes.success && trendRes.data) {
            const trendCtx = document.getElementById('salesTrendChart');
            if (trendCtx) {
                if (salesTrendChartInstance) salesTrendChartInstance.destroy();
                salesTrendChartInstance = new Chart(trendCtx.getContext('2d'), {
                    type: 'line',
                    data: trendRes.data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
        }

        // 2. Branch Revenue
        if (branchRes.success && branchRes.data) {
            const branchCtx = document.getElementById('branchRevenueChart');
            if (branchCtx) {
                if (branchRevenueChartInstance) branchRevenueChartInstance.destroy();
                branchRevenueChartInstance = new Chart(branchCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: branchRes.data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '65%',
                        plugins: {
                            legend: { position: 'right' }
                        }
                    }
                });
            }
        }

        // 3. Category Performance
        if (categoryRes.success && categoryRes.data) {
            const categoryCtx = document.getElementById('categoryPerformanceChart');
            if (categoryCtx) {
                if (categoryChartInstance) categoryChartInstance.destroy();
                categoryChartInstance = new Chart(categoryCtx.getContext('2d'), {
                    type: 'bar',
                    data: categoryRes.data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
        }
    } catch (error) {
        console.error('Error rendering dashboard charts:', error);
    }
}
