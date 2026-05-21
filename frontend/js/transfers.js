/**
 * Frontend logic for Transfer Views (DOM manipulation)
 */

document.addEventListener('DOMContentLoaded', () => {
    window.TransferOps = {
        async init() {
            this.bindEvents();
            await this.loadTransfers();
            await this.populateTransferDropdowns();
        },

        bindEvents() {
            // Setup modal listeners if needed
        },

        async loadTransfers() {
            const tbody = document.getElementById('transfersTableBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center">Loading transfers...</td></tr>';

            try {
                const res = await API.transfers.getAll();
                const transfers = res.transfers || [];

                if (transfers.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center">No transfers found</td></tr>';
                    return;
                }

                const userRole = parseInt(localStorage.getItem('userRole')) || 0;
                const userBranch = parseInt(localStorage.getItem('userBranchId')) || 0;

                tbody.innerHTML = transfers.map(t => {
                    let badgeClass = 'badge-secondary';
                    if (t.status === 'completed') badgeClass = 'badge-success';
                    else if (t.status === 'in_transit') badgeClass = 'badge-primary';
                    else if (t.status === 'cancelled') badgeClass = 'badge-danger';
                    else if (t.status === 'pending') badgeClass = 'badge-warning';

                    // Actions
                    let actions = '';
                    if (t.status === 'pending' && userRole === 1) {
                        actions += `<button class="btn btn-outline" style="padding:0.2rem 0.5rem;font-size:0.75rem;margin-right:5px;border-color:var(--primary);color:var(--primary)" onclick="TransferOps.updateStatus(${t.id}, 'in_transit')">Approve (Send)</button>`;
                    }
                    if (t.status === 'in_transit' && (userRole === 1 || String(userBranch) === String(t.to_branch_id))) {
                        actions += `<button class="btn btn-primary" style="padding:0.2rem 0.5rem;font-size:0.75rem;margin-right:5px" onclick="TransferOps.updateStatus(${t.id}, 'completed')">Receive</button>`;
                    }
                    if ((t.status === 'pending' || t.status === 'in_transit') && userRole === 1) {
                        actions += `<button class="btn btn-danger" style="padding:0.2rem 0.5rem;font-size:0.75rem" onclick="TransferOps.updateStatus(${t.id}, 'cancelled')">Cancel</button>`;
                    }

                    return `
                    <tr>
                        <td style="color:var(--text-secondary)">#TRF-${t.id}</td>
                        <td style="font-weight:600">${t.item_name}</td>
                        <td>${t.from_branch_name} <i data-lucide="arrow-right" style="width:12px;display:inline-block;vertical-align:middle;margin:0 4px"></i> ${t.to_branch_name}</td>
                        <td>${t.quantity}</td>
                        <td><span class="badge ${badgeClass}">${t.status.toUpperCase().replace('_', ' ')}</span></td>
                        <td style="font-size:0.8rem">${t.initiated_by_name}</td>
                        <td style="font-size:0.8rem">${new Date(t.created_at).toLocaleDateString()}</td>
                        <td>${actions}</td>
                    </tr>
                    `;
                }).join('');

                lucide.createIcons();
            } catch (err) {
                console.error("Failed to load transfers:", err);
                tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:var(--danger)">Failed to load data</td></tr>`;
            }
        },

        async populateTransferDropdowns() {
            // Load branches into To/From selectors safely
            const fromSelect = document.getElementById('transferFromBranch');
            const toSelect = document.getElementById('transferToBranch');
            if (!fromSelect || !toSelect) return;

            try {
                const res = await API.get('/api/branches');
                const branches = res.branches || [];

                const userRole = parseInt(localStorage.getItem('userRole'));
                const userBranch = localStorage.getItem('userBranchId');

                const opts = branches.map(b => `<option value="${b.id}">${b.name}</option>`).join('');

                if (userRole === 1) {
                    fromSelect.innerHTML = '<option value="">Select Source Branch...</option>' + opts;
                    fromSelect.disabled = false;
                } else {
                    fromSelect.innerHTML = branches.filter(b => String(b.id) === String(userBranch))
                        .map(b => `<option value="${b.id}">${b.name}</option>`).join('');
                    fromSelect.disabled = true; // Store keepers send from their own branch
                }

                toSelect.innerHTML = '<option value="">Select Destination Branch...</option>' + opts;

            } catch (err) {
                console.error('Failed to load branches for transfers');
            }
        },

        async searchInventoryForTransfer() {
            const searchStr = document.getElementById('transferItemSearch').value.toLowerCase();
            const resultsDiv = document.getElementById('transferItemResults');
            const branchId = document.getElementById('transferFromBranch').value;

            if (!branchId) {
                resultsDiv.innerHTML = '<div style="padding:10px;color:var(--warning)">Select a Source Branch first!</div>';
                return;
            }

            if (searchStr.length < 2) {
                resultsDiv.innerHTML = '';
                return;
            }

            try {
                // To properly isolate by branch, we just use the existing inventory API, mapped to branchId
                const res = await API.inventory.getLevels(branchId);
                const items = res.inventory || [];

                const filtered = items.filter(i =>
                    i.item_name.toLowerCase().includes(searchStr) ||
                    i.sku.toLowerCase().includes(searchStr)
                ).slice(0, 5); // top 5

                if (filtered.length === 0) {
                    resultsDiv.innerHTML = '<div style="padding:10px;color:var(--text-muted)">No items found in stock</div>';
                    return;
                }

                resultsDiv.innerHTML = filtered.map(i => `
                    <div style="padding:8px 12px;border-bottom:1px solid var(--border);cursor:pointer;display:flex;justify-content:space-between;align-items:center"
                         onclick="TransferOps.selectTransferItem(${i.item_id}, '${i.item_name.replace(/'/g, "\\'")}', ${i.quantity})"
                         onmouseover="this.style.background='var(--bg-secondary)'"
                         onmouseout="this.style.background='transparent'">
                         <div>
                            <div style="font-weight:600">${i.item_name}</div>
                            <div style="font-size:0.75rem;color:var(--text-secondary)">SKU: ${i.sku}</div>
                         </div>
                         <div style="font-size:0.8rem;color:var(--primary);font-weight:600">Stock: ${i.quantity}</div>
                    </div>
                `).join('');
            } catch (err) {
                console.error(err);
            }
        },

        selectTransferItem(id, name, availableStock) {
            document.getElementById('transferItemId').value = id;
            document.getElementById('transferItemSearch').value = name;
            document.getElementById('transferItemResults').innerHTML = '';
            document.getElementById('transferQty').max = availableStock;

            const hint = document.getElementById('transferStockHint');
            if (hint) {
                hint.textContent = `Avail: ${availableStock}`;
                hint.style.display = 'inline';
            }
        },

        openTransferModal() {
            document.getElementById('transferModal').style.display = 'flex';
            document.getElementById('transferForm').reset();
            const hint = document.getElementById('transferStockHint');
            if (hint) hint.style.display = 'none';
        },

        closeTransferModal() {
            document.getElementById('transferModal').style.display = 'none';
        },

        async submitTransfer(e) {
            e.preventDefault();

            const payload = {
                item_id: document.getElementById('transferItemId').value,
                from_branch_id: document.getElementById('transferFromBranch').value,
                to_branch_id: document.getElementById('transferToBranch').value,
                quantity: document.getElementById('transferQty').value
            };

            if (!payload.item_id) {
                Toast.error("Please search and select an item.");
                return;
            }
            if (payload.from_branch_id === payload.to_branch_id) {
                Toast.error("Destination branch cannot be the same as the source.");
                return;
            }

            try {
                const btn = e.target.querySelector('button[type="submit"]');
                const oldText = btn.innerHTML;
                btn.innerHTML = 'Submitting...';
                btn.disabled = true;

                await API.transfers.create(payload);
                if (window.Toast) Toast.success('Transfer requested successfully');
                this.closeTransferModal();
                this.loadTransfers();

                btn.innerHTML = oldText;
                btn.disabled = false;
            } catch (err) {
                if (window.Toast) Toast.error(err.message || 'Failed to request transfer');
                else alert(err.message || 'Failed to request transfer');
                e.target.querySelector('button[type="submit"]').disabled = false;
                e.target.querySelector('button[type="submit"]').innerHTML = 'Request Transfer';
            }
        },

        updateStatus(id, status) {
            const formattedStatus = status.replace('_', ' ');
            document.getElementById('transferConfirmText').textContent = `Are you sure you want to change this transfer status to "${formattedStatus}"?`;

            const btn = document.getElementById('transferConfirmBtn');
            btn.innerHTML = 'Confirm';
            btn.onclick = async () => {
                btn.innerHTML = 'Processing...';
                try {
                    await API.transfers.updateStatus(id, status);
                    Toast.success(`Transfer marked as ${formattedStatus}`);
                    this.loadTransfers();
                    if (window.InventoryOps && document.getElementById('view-inventory').classList.contains('active')) {
                        InventoryOps.loadInventory();
                    }
                    this.closeConfirmModal();
                } catch (err) {
                    Toast.error(err.message || 'Failed to update status');
                    this.closeConfirmModal();
                }
            };

            document.getElementById('transferConfirmModal').style.display = 'flex';
            lucide.createIcons();
        },

        closeConfirmModal() {
            document.getElementById('transferConfirmModal').style.display = 'none';
        }
    };
});
