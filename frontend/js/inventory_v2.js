// ===================================
// INVENTORY.JS — Simple API Client
// ===================================

const InventoryAPI = {
  async getByBranch(branchId = null) {
    const url = branchId ? `/api/inventory?branch_id=${branchId}` : '/api/inventory';
    const res = await fetch(url, { headers: { 'X-CSRF-Token': localStorage.getItem('csrfToken') || '' } });
    return res.json();
  },

  async getLowStock(branchId = null) {
    const url = branchId ? `/api/inventory/low-stock?branch_id=${branchId}` : '/api/inventory/low-stock';
    const res = await fetch(url, { headers: { 'X-CSRF-Token': localStorage.getItem('csrfToken') || '' } });
    return res.json();
  },

  async getTotalValue(branchId = null) {
    const url = branchId ? `/api/inventory/value?branch_id=${branchId}` : '/api/inventory/value';
    const res = await fetch(url, { headers: { 'X-CSRF-Token': localStorage.getItem('csrfToken') || '' } });
    return res.json();
  },

  async getHistory(branchId = null) {
    const url = branchId ? `/api/inventory/history?branch_id=${branchId}` : '/api/inventory/history';
    const res = await fetch(url, { headers: { 'X-CSRF-Token': localStorage.getItem('csrfToken') || '' } });
    return res.json();
  },

  async adjust(itemId, branchId, change, notes = '') {
    const res = await fetch('/api/inventory/adjust', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': localStorage.getItem('csrfToken') || ''
      },
      body: JSON.stringify({ item_id: itemId, branch_id: branchId, quantity_change: change, notes })
    });
    return res.json();
  },

  async markDamaged(itemId, branchId, qty, notes = 'Damaged') {
    const res = await fetch('/api/inventory/damage', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': localStorage.getItem('csrfToken') || ''
      },
      body: JSON.stringify({ item_id: itemId, branch_id: branchId, quantity: qty, notes })
    });
    return res.json();
  }
};

// ===================================
// BRANCH FILTER LOADING & SETUP
// ===================================

async function loadBranchesIntoFilter() {
  const filter = document.getElementById('invBranchFilter');
  if (!filter) return;

  try {
    const res = await fetch('/api/branches', { headers: { 'X-CSRF-Token': localStorage.getItem('csrfToken') || '' } });
    const json = await res.json();
    
    if (json.success && json.data) {
      // Rebuild options safely
      filter.innerHTML = '<option value="">All Branches</option>';
      json.data.forEach(branch => {
        const option = document.createElement('option');
        option.value = branch.id;
        option.textContent = branch.name;
        filter.appendChild(option);
      });
    }
  } catch (e) {
    console.error('Error loading branches:', e);
  }
}

// ===================================
// UI FUNCTIONS
// ===================================

async function loadInventory() {
  const branchSelect = document.getElementById('invBranchFilter');
  const branchId = branchSelect?.value || null;
  const tbody = document.getElementById('inventoryTableBody');
  if (!tbody) return;

  tbody.innerHTML = '<tr><td colspan="9">Loading...</td></tr>';
  try {
    // Get data from API
    const res = await InventoryAPI.getByBranch(branchId ? parseInt(branchId) : null);
    let items = res.data || [];
    
    tbody.innerHTML = '';
    if (items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#999">No inventory data</td></tr>';
      return;
    }
    
    if (!branchId) {
      // Group by item ID when All Branches is selected
      const grouped = {};
      items.forEach(item => {
        if (!grouped[item.item_id]) {
          grouped[item.item_id] = { ...item, branch_name: 'All Branches', branch_id: null };
          grouped[item.item_id].quantity = 0;
          grouped[item.item_id].damaged_quantity = 0;
        }
        grouped[item.item_id].quantity += parseInt(item.quantity || 0);
        grouped[item.item_id].damaged_quantity += parseInt(item.damaged_quantity || 0);
      });
      items = Object.values(grouped);
    }

    items.forEach(item => {
      const status = item.quantity <= (item.reorder_level || 5) ? 'low-stock' : 'ok';
      
      let actionHtml = `<span style="font-size:0.75rem;color:var(--text-secondary)">Select branch to manage</span>`;
      if (branchId) {
        actionHtml = `
          <button class="btn btn-outline btn-sm" onclick="adjustStock(${item.item_id}, '${item.item_name}', ${item.branch_id})" style="padding:0.2rem 0.5rem;font-size:0.75rem;margin-right:4px">+ / - Stock</button>
          <button class="btn btn-outline btn-sm" onclick="markDamaged(${item.item_id}, '${item.item_name}', ${item.branch_id})" style="padding:0.2rem 0.5rem;font-size:0.75rem;color:var(--danger)">Damage</button>
        `;
      }
      
      tbody.innerHTML += `
        <tr>
          <td>${item.sku || 'N/A'}</td>
          <td>${item.item_name || 'N/A'}</td>
          <td>${item.category_name || 'N/A'}</td>
          <td>${item.branch_name || 'N/A'}</td>
          <td>$${parseFloat(item.selling_price || 0).toFixed(2)}</td>
          <td style="font-size:1.1rem"><strong>${item.quantity || 0}</strong></td>
          <td style="color:var(--danger)">${item.damaged_quantity || 0}</td>
          <td><span class="badge badge-${status === 'low-stock' ? 'warning' : 'success'}">${status === 'low-stock' ? 'Low' : 'OK'}</span></td>
          <td>${actionHtml}</td>
        </tr>
      `;
    });
  } catch (e) {
    console.error('loadInventory error:', e);
    tbody.innerHTML = '<tr><td colspan="9" style="color:red">Error loading inventory</td></tr>';
  }
}

async function loadLowStock() {
  const branchId = document.getElementById('invBranchFilter')?.value || null;
  const container = document.getElementById('lowStockAlerts');
  if (!container) return;

  try {
    const res = await InventoryAPI.getLowStock(branchId ? parseInt(branchId) : null);
    let items = res.data || [];
    
    container.innerHTML = items.length === 0 
      ? '<p style="color:green">All stock levels optimal</p>'
      : items.map(i => `
        <div style="padding:0.75rem;border-left:3px solid orange;margin-bottom:0.5rem;background:var(--bg-secondary)">
          <strong>${i.item_name}</strong><br>
          <small>Stock: ${i.quantity} | Reorder: ${i.reorder_level}</small>
        </div>
      `).join('');
  } catch (e) {
    container.innerHTML = '<p style="color:red">Error loading alerts</p>';
  }
}

function adjustStock(itemId, itemName, branchId) {
  document.getElementById('stockModalTitle').textContent = 'Adjust Stock';
  document.getElementById('stockModalItemName').textContent = itemName;
  document.getElementById('stockQtyLabel').textContent = 'Quantity Change';
  document.getElementById('stockQtyHelp').textContent = 'Use positive numbers to add stock, negative to remove.';
  document.getElementById('stockQtyHelp').style.display = 'block';
  
  document.getElementById('stockItemId').value = itemId;
  document.getElementById('stockBranchId').value = branchId;
  document.getElementById('stockActionType').value = 'adjust';
  document.getElementById('stockQty').value = '';
  document.getElementById('stockQty').min = '';
  
  document.getElementById('stockModal').style.display = 'flex';
}

function markDamaged(itemId, itemName, branchId) {
  document.getElementById('stockModalTitle').textContent = 'Mark Damaged';
  document.getElementById('stockModalItemName').textContent = itemName;
  document.getElementById('stockQtyLabel').textContent = 'Damaged Quantity';
  document.getElementById('stockQtyHelp').style.display = 'none';
  
  document.getElementById('stockItemId').value = itemId;
  document.getElementById('stockBranchId').value = branchId;
  document.getElementById('stockActionType').value = 'damage';
  document.getElementById('stockQty').value = '';
  document.getElementById('stockQty').min = '1';
  
  document.getElementById('stockModal').style.display = 'flex';
}

function closeStockModal() {
  document.getElementById('stockModal').style.display = 'none';
}

async function submitStockForm(e) {
  e.preventDefault();
  
  const itemId = document.getElementById('stockItemId').value;
  const branchId = document.getElementById('stockBranchId').value;
  const actionType = document.getElementById('stockActionType').value;
  const qty = document.getElementById('stockQty').value;
  
  if (!qty || isNaN(qty)) return;

  try {
    const btn = document.getElementById('stockSubmitBtn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Saving...';
    
    let res;
    if (actionType === 'adjust') {
      res = await InventoryAPI.adjust(itemId, branchId, parseInt(qty));
    } else {
      res = await InventoryAPI.markDamaged(itemId, branchId, parseInt(qty));
    }
    
    btn.disabled = false;
    btn.textContent = originalText;
    
    if (res.success) {
      closeStockModal();
      loadInventory();
      loadLowStock();
    } else {
      alert('Error: ' + res.message);
    }
  } catch (err) {
    alert('Server communication error');
    document.getElementById('stockSubmitBtn').disabled = false;
    document.getElementById('stockSubmitBtn').textContent = 'Confirm';
  }
}

async function loadInventoryHistory() {
  const branchId = document.getElementById('invBranchFilter')?.value || null;
  const tbody = document.getElementById('historyTableBody');
  if (!tbody) return;

  tbody.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';
  try {
    const res = await InventoryAPI.getHistory(branchId ? parseInt(branchId) : null);
    let records = res.data || [];
    
    tbody.innerHTML = '';
    if (records.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999">No history available</td></tr>';
      return;
    }
    
    records.forEach(r => {
      tbody.innerHTML += `
        <tr>
          <td>${new Date(r.created_at).toLocaleString()}</td>
          <td>${r.item_name || 'N/A'}</td>
          <td>${r.branch_name || 'N/A'}</td>
          <td><span class="badge badge-${r.type === 'in' ? 'success' : r.type === 'damage' ? 'danger' : 'info'}">${r.type}</span></td>
          <td>${r.quantity_change}</td>
          <td>${r.notes || '-'}</td>
        </tr>
      `;
    });
  } catch (e) {
    console.error('loadInventoryHistory error:', e);
    tbody.innerHTML = '<tr><td colspan="6" style="color:red">Error loading history</td></tr>';
  }
}

// Function called when Add Item button is clicked
async function createItem() {
  document.getElementById('addInventoryModal').style.display = 'flex';
  document.getElementById('addInvQty').value = '';
  
  // Load products
  try {
    const productSelect = document.getElementById('addInvProductId');
    productSelect.innerHTML = '<option value="">Loading products...</option>';
    // We fetch a list of products (page 1 should suffice for demo, or we can just fetch without page if the backend didn't paginate. The backend paginates at 20, so we just use that for now)
    const res = await fetch('/api/products?page=1', { headers: { 'X-CSRF-Token': localStorage.getItem('csrfToken') || '' } }).then(r=>r.json());
    
    productSelect.innerHTML = '<option value="">Select a product</option>';
    if (res.data && res.data.data) {
       res.data.data.forEach(p => {
          productSelect.innerHTML += `<option value="${p.id}">${p.name} (${p.sku})</option>`;
       });
    }

    // Load branches
    const branchSelect = document.getElementById('addInvBranchId');
    branchSelect.innerHTML = '<option value="">Loading branches...</option>';
    const branchRes = await fetch('/api/branches', { headers: { 'X-CSRF-Token': localStorage.getItem('csrfToken') || '' } }).then(r=>r.json());
    
    branchSelect.innerHTML = '<option value="">Select a branch</option>';
    if (branchRes.data) {
       branchRes.data.forEach(b => {
          branchSelect.innerHTML += `<option value="${b.id}">${b.name}</option>`;
       });
    }
  } catch (err) {
    console.error('Failed to load data for modal:', err);
  }
}

function closeAddInventoryModal() {
  document.getElementById('addInventoryModal').style.display = 'none';
}

async function submitAddInventoryForm(e) {
  e.preventDefault();
  
  const itemId = document.getElementById('addInvProductId').value;
  const branchId = document.getElementById('addInvBranchId').value;
  const qty = document.getElementById('addInvQty').value;
  
  if (!itemId || !branchId || !qty || isNaN(qty)) return;

  try {
    const btn = document.getElementById('addInvSubmitBtn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Saving...';
    
    // adjust automatically creates the inventory record if it doesn't exist
    const res = await InventoryAPI.adjust(parseInt(itemId), parseInt(branchId), parseInt(qty));
    
    btn.disabled = false;
    btn.textContent = originalText;
    
    if (res.success) {
      closeAddInventoryModal();
      loadInventory();
      loadLowStock();
    } else {
      alert('Error: ' + res.message);
    }
  } catch (err) {
    alert('Server communication error');
    document.getElementById('addInvSubmitBtn').disabled = false;
    document.getElementById('addInvSubmitBtn').textContent = 'Confirm';
  }
}
