/**
 * Frontend logic for Inventory Views (DOM manipulation)
 */

document.addEventListener('DOMContentLoaded', () => {
    window.InventoryOps = {
        selectedBranchId: null,

        async init() {
            await this.loadBranches();
            this.loadInventory();
            this.loadHistory();
        },

        /**
         * Load branches into the dropdown filter
         */
        async loadBranches() {
            const select = document.getElementById('inventoryBranchFilter');
            if (!select) return;

            try {
                const res = await API.get('/api/branches');
                const branches = res.branches || [];

                // Default to user's branch
                const user = API.getCurrentUser();
                const userBranchId = localStorage.getItem('userBranchId') || '';

                select.innerHTML = branches.map(b =>
                    `<option value="${b.id}" ${String(b.id) === String(userBranchId) ? 'selected' : ''}>${b.name}</option>`
                ).join('');

                // If user has a branch, pre-select it; otherwise use the first branch
                this.selectedBranchId = userBranchId || (branches.length > 0 ? branches[0].id : null);

                // For managers, add an "All Branches" option at the top
                const role = (localStorage.getItem('userRole') || '').toLowerCase();
                if (role === 'manager') {
                    select.insertAdjacentHTML('afterbegin', '<option value="all">All Branches</option>');
                }

            } catch (err) {
                console.error('Failed to load branches for inventory filter:', err);
                select.innerHTML = '<option value="">Failed to load</option>';
            }
        },

        /**
         * Handle branch dropdown change
         */
        onBranchChange() {
            const select = document.getElementById('inventoryBranchFilter');
            this.selectedBranchId = select.value;
            this.loadInventory();
            this.loadHistory();
        },

        /**
         * Get the branch_id query param (empty string if "all")
         */
        getBranchParam() {
            if (!this.selectedBranchId || this.selectedBranchId === 'all') return '';
            return this.selectedBranchId;
        },

        async loadInventory() {
            try {
                const tbody = document.querySelector('#inventoryTable tbody');
                if (!tbody) return;
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center">Loading...</td></tr>';

                const branchId = this.getBranchParam();
                const res = await API.inventory.getLevels(branchId);
                const items = res.inventory || [];

                // Update summary cards
                const totalValueEl = document.getElementById('invTotalValueDisplay');
                const totalItemsEl = document.getElementById('invTotalItemsDisplay');
                const lowStockEl = document.getElementById('invLowStockCount');

                if (totalValueEl) totalValueEl.textContent = `$${parseFloat(res.total_value || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
                if (totalItemsEl) totalItemsEl.textContent = items.length;

                const lowCount = items.filter(i => parseInt(i.quantity) <= parseInt(i.reorder_level)).length;
                if (lowStockEl) lowStockEl.textContent = lowCount;

                if (items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center">No inventory found for this branch</td></tr>';
                    return;
                }

                tbody.innerHTML = items.map(item => `
                    <tr>
                        <td>${item.sku}</td>
                        <td>${item.item_name}</td>
                        <td>${item.category_name || '-'}</td>
                        <td>$${item.selling_price}</td>
                        <td style="font-weight:bold; color: ${item.quantity <= item.reorder_level ? 'var(--danger)' : 'inherit'}">${item.quantity}</td>
                        <td>
                            ${item.quantity <= item.reorder_level
                        ? '<span class="badge" style="background:var(--danger);color:white;padding:2px 6px;border-radius:4px;font-size:0.75rem">Low Stock</span>'
                        : '<span class="badge" style="background:var(--success);color:white;padding:2px 6px;border-radius:4px;font-size:0.75rem">In Stock</span>'
                    }
                        </td>
                        <td>
                            <button class="btn btn-outline" style="padding:0.25rem 0.5rem;font-size:0.75rem" onclick="InventoryOps.openAdjustModal(${item.item_id}, '${item.item_name.replace(/'/g, "\\'")}', ${item.quantity})">Adjust</button>
                        </td>
                    </tr>
                `).join('');

            } catch (err) {
                console.error("Failed to load inventory:", err);
                const tbody = document.querySelector('#inventoryTable tbody');
                if (tbody) tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--danger)">Failed to load data</td></tr>`;
            }
        },

        async loadAlerts() {
            const container = document.getElementById('stockAlertsContainer');
            if (!container) return;

            try {
                container.innerHTML = '<p>Loading alerts...</p>';
                const branchId = this.getBranchParam();
                const res = await API.inventory.getLowStock(branchId);
                const alerts = res.low_stock || [];

                if (alerts.length === 0) {
                    container.innerHTML = '<div style="padding:1rem;background:var(--bg-secondary);border-radius:6px;text-align:center">No low stock alerts!</div>';
                    return;
                }

                container.innerHTML = `
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="border-bottom:1px solid var(--border);text-align:left">
                                <th style="padding:0.5rem">SKU</th>
                                <th style="padding:0.5rem">Item</th>
                                <th style="padding:0.5rem">Current Level</th>
                                <th style="padding:0.5rem">Reorder Level</th>
                                <th style="padding:0.5rem">Shortage</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${alerts.map(a => `
                                <tr style="border-bottom:1px solid var(--border)">
                                    <td style="padding:0.5rem">${a.sku}</td>
                                    <td style="padding:0.5rem">${a.item_name}</td>
                                    <td style="padding:0.5rem;color:var(--danger);font-weight:bold">${a.quantity}</td>
                                    <td style="padding:0.5rem">${a.reorder_level}</td>
                                    <td style="padding:0.5rem">${a.shortage}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } catch (err) {
                container.innerHTML = `<p style="color:var(--danger)">Failed to load alerts: ${err.message}</p>`;
            }
        },

        async loadHistory() {
            const tbody = document.getElementById('invHistoryTableBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center">Loading...</td></tr>';
            try {
                const branchId = this.getBranchParam();
                const res = await API.inventory.getHistory(branchId);
                const history = res.history || [];
                if (history.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center">No history found</td></tr>';
                    return;
                }
                tbody.innerHTML = history.map(h => `
                    <tr>
                        <td>${new Date(h.created_at).toLocaleString()}</td>
                        <td>${h.item_name}</td>
                        <td>${h.type.toUpperCase()}</td>
                        <td style="color:${h.quantity_change > 0 ? 'var(--success)' : (h.quantity_change < 0 ? 'var(--danger)' : 'inherit')}">
                            ${h.quantity_change > 0 ? '+' : ''}${h.quantity_change}
                        </td>
                        <td>${h.user_name}</td>
                    </tr>
                `).join('');
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--danger)">Failed to load history</td></tr>`;
            }
        },

        openAdjustModal(itemId, itemName, currentQty) {
            document.getElementById('adjItemId').value = itemId;
            document.getElementById('adjItemName').textContent = itemName;
            document.getElementById('adjCurrentQty').textContent = currentQty;
            document.getElementById('inventoryAdjustModal').style.display = 'flex';
        },

        closeAdjustModal() {
            document.getElementById('inventoryAdjustModal').style.display = 'none';
            document.getElementById('adjForm').reset();
        },

        async submitAdjust(e) {
            e.preventDefault();
            const branchId = this.getBranchParam();
            const payload = {
                item_id: document.getElementById('adjItemId').value,
                branch_id: branchId ? branchId : localStorage.getItem('userBranchId'),
                type: document.getElementById('adjType').value,
                quantity: document.getElementById('adjQty').value,
                notes: document.getElementById('adjNotes').value,
            };

            if (payload.type === 'adjustment') {
                payload.adjust_direction = document.getElementById('adjDirection').value;
            }

            try {
                await API.inventory.adjustStock(payload);
                if (window.Toast) Toast.success('Stock adjusted successfully');
                this.closeAdjustModal();
                this.loadInventory();
                this.loadHistory();
            } catch (err) {
                if (window.Toast) Toast.error(err.message || 'Failed to adjust stock');
                else alert(err.message || 'Failed to adjust stock');
            }
        },

        toggleAdjustDirection(type) {
            const dirGroup = document.getElementById('adjDirectionGroup');
            if (type === 'adjustment') {
                dirGroup.style.display = 'block';
            } else {
                dirGroup.style.display = 'none';
            }
        }
    };
});
