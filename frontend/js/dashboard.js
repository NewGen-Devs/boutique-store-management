// ===================================
// DASHBOARD.JS — All Dashboard Logic
// Handles auth check, role UI, CRUD, POS
// ===================================

// Database persistence
window.saveDB = function () {
  localStorage.setItem('BoutiqueDB', JSON.stringify(window.MOCK_DATA));
};

// Guard to prevent setupRoleUI from being called multiple times
let roleUISetup = false;

document.addEventListener('DOMContentLoaded', () => {
  lucide.createIcons();

  // Database State
  const saved = localStorage.getItem('BoutiqueDB');
  if (saved) {
    window.MOCK_DATA = JSON.parse(saved);
  } else {
    window.saveDB();
  }

  // Auth Check - Verify with backend
  const userId = localStorage.getItem('userId');
  const userRole = localStorage.getItem('userRole');
  
  if (!userId) {
    console.log('No userId in localStorage, redirecting to login');
    window.location.href = '/login?reason=no_session';
    return;
  }

  // If we have cached user data, use it to setup UI while verifying with backend
  if (userRole && !roleUISetup) {
    roleUISetup = true;
    setupRoleUI(parseInt(userRole));
    setupMobileMenu();
    
    // Load branches into filter dropdown
    if (typeof loadBranchesIntoFilter === 'function') {
      loadBranchesIntoFilter();
    }
  }

  // Verify auth with backend (non-blocking)
  const csrfToken = localStorage.getItem('csrfToken');
  if (csrfToken) {
    fetch('/api/user', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      }
    })
    .then(response => {
      if (!response.ok) {
        console.warn('API user verification failed:', response.status);
        return null;
      }
      return response.json();
    })
    .then(data => {
      if (data && data.user) {
        // Update role UI if data changed
        const newRole = data.user.role_id;
        if (newRole && newRole !== userRole) {
          console.log('User role changed, updating UI');
          setupRoleUI(newRole);
        }
      }
    })
    .catch(error => {
      console.warn('Auth verification error (non-blocking):', error.message);
      // Don't redirect on error - user data is already cached in localStorage
    });
  } else {
    // No CSRF token, clear auth and redirect
    console.log('No CSRF token, redirecting to login');
    localStorage.removeItem('userId');
    localStorage.removeItem('userRole');
    localStorage.removeItem('userName');
    window.location.href = '/login?reason=no_csrf';
  }
});

// ——— Role-Based UI Setup ———
function setupRoleUI(role) {
  const userNameEl = document.getElementById('userNameDisplay');
  const userRoleEl = document.getElementById('userRoleDisplay');
  const userAvatarEl = document.getElementById('userAvatar');
  const sidebarNav = document.getElementById('sidebarNav');
  const localName = localStorage.getItem('userName');

  let navItems = [];
  let defaultView = '';
  let activeUserNameForFilter = localName || '';

  // Convert role_id to role name for comparison
  // 1 = manager, 2 = store_keeper, 3 = seller
  if (role === 1 || role === 'manager') {
    userNameEl.textContent = localName || 'Admin';
    userRoleEl.textContent = 'Manager';
    navItems = [
      { id: 'overview', icon: 'layout-dashboard', label: 'Overview' },
      { id: 'products', icon: 'tag', label: 'Products' },
      { id: 'branches', icon: 'store', label: 'Branches' },
      { id: 'users', icon: 'users', label: 'Users' },
      { id: 'inventory', icon: 'package-search', label: 'Inventory' },
      { id: 'transfers', icon: 'truck', label: 'Transfers' },
      { id: 'reports', icon: 'bar-chart-2', label: 'Analytics' }
    ];
    defaultView = 'overview';
    renderRecentSales();
    renderBranches();
    renderUsers();
    // Initialize inventory for managers
    setTimeout(() => { loadInventory(); }, 300);

  } else if (role == 2 || role === 'store_keeper') {
    userNameEl.textContent = localName || 'Alice Smith';
    userRoleEl.textContent = 'Store Keeper';
    navItems = [
      { id: 'inventory', icon: 'package', label: 'Stock' },
      { id: 'transfers', icon: 'truck', label: 'Transfers' },
      { id: 'stock-ops', icon: 'bell', label: 'Alerts' },
      { id: 'my-sales', icon: 'activity', label: 'Performance' }
    ];
    defaultView = 'inventory';
    document.getElementById('addItemBtn').style.display = 'inline-flex';
    document.getElementById('addItemBtn').onclick = createItem;
    renderStockAlerts();
    renderMySales(activeUserNameForFilter);
    // Initialize inventory for store keepers
    setTimeout(() => { loadInventory(); loadLowStock(); }, 300);

  } else if (role == 3 || role === 'seller') {
    userNameEl.textContent = localName || 'Sarah Connor';
    userRoleEl.textContent = 'Seller';
    navItems = [
      { id: 'pos', icon: 'credit-card', label: 'POS' },
      { id: 'inventory', icon: 'search', label: 'Items' },
      { id: 'my-sales', icon: 'activity', label: 'Performance' }
    ];
    defaultView = 'pos';
    renderPOSItems();
    renderMySales(activeUserNameForFilter);
    const cartSummary = document.querySelector('.cart-summary');
    if (!document.getElementById('discountBtn')) {
      const discBtn = document.createElement('button');
      discBtn.id = 'discountBtn';
      discBtn.className = 'btn btn-outline';
      discBtn.style.width = '100%';
      discBtn.style.marginBottom = '1rem';
      discBtn.textContent = 'Apply Discount';
      discBtn.onclick = applyDiscount;
      cartSummary.insertBefore(discBtn, document.getElementById('checkoutBtn'));
    }
  } else if (role === 4 || role === 'viewer' || role === 'admin') {
    userNameEl.textContent = localName || 'Report Viewer';
    userRoleEl.textContent = 'Viewer';
    navItems = [
      { id: 'overview', icon: 'layout-dashboard', label: 'Overview' },
      { id: 'reports', icon: 'bar-chart-2', label: 'Analytics' }
    ];
    defaultView = 'overview';
    renderRecentSales();
  }

  // Avatar
  const finalName = userNameEl.textContent;
  const initials = finalName.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
  userAvatarEl.textContent = initials;

  // Clear nav before rendering new items
  sidebarNav.innerHTML = '';
  
  // Render Nav
  navItems.forEach(item => {
    const btn = document.createElement('div');
    btn.className = 'nav-item';
    btn.id = `nav-${item.id}`;
    btn.innerHTML = `<i data-lucide="${item.icon}" class="nav-icon"></i> <span>${item.label}</span>`;
    btn.onclick = () => switchView(item.id);
    sidebarNav.appendChild(btn);
  });

  lucide.createIcons();
  renderInventoryTable();
  switchView(defaultView);
}

// ——— View Switching ———
function switchView(viewId) {
  document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
  const target = document.getElementById(`view-${viewId}`);
  if (target) target.classList.add('active');
  document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
  const nav = document.getElementById(`nav-${viewId}`);
  if (nav) nav.classList.add('active');
  document.getElementById('sidebar').classList.remove('open');
  
  // Load data for specific views
  if (viewId === 'inventory' && typeof loadInventory === 'function') {
    loadInventory();
    if (typeof loadLowStock === 'function') loadLowStock();
    if (typeof loadInventoryHistory === 'function') loadInventoryHistory();
  }
  
  if (viewId === 'transfers' && typeof loadTransfers === 'function') {
    loadTransfers();
  }
}

function logout() {
  // Call API logout endpoint
  fetch('/api/logout', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': localStorage.getItem('csrfToken') || ''
    }
  })
    .then(response => response.json())
    .then(data => {
      // Clear local storage
      localStorage.removeItem('userRole');
      localStorage.removeItem('userName');
      localStorage.removeItem('userId');
      localStorage.removeItem('csrfToken');
      // Redirect to login
      window.location.href = '/login';
    })
    .catch(error => {
      console.error('Logout error:', error);
      // Force logout anyway
      localStorage.removeItem('userRole');
      localStorage.removeItem('userName');
      localStorage.removeItem('userId');
      localStorage.removeItem('csrfToken');
      window.location.href = '/login';
    });
}

// ——— Inventory ———
function renderInventoryTable() {
  // Removed mock data rendering
}

function updateItemQty(index) {}
function transferItem(index) {}
function createItem() {}

// ——— Sales ———
function renderRecentSales() {
  const tbody = document.querySelector('#recentSalesTable tbody');
  if (!tbody) return;
  tbody.innerHTML = '';

  window.MOCK_DATA.recentSales.forEach(sale => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td style="color:var(--text-secondary)">${sale.date}</td>
      <td>${sale.id}</td>
      <td>${sale.seller}</td>
      <td>${sale.branch}</td>
      <td style="font-weight:700">$${sale.total.toFixed(2)}</td>
    `;
    tbody.appendChild(tr);
  });
}

// ——— POS ———
let cart = [];

async function renderPOSItems() {
  const grid = document.getElementById('posProductGrid');
  if (!grid) return;
  grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 2rem;">Loading inventory...</div>';

  try {
    const branchId = window.activeUserBranch || 1; // Default to 1 if not set
    const res = await fetch(`/api/inventory?branch_id=${branchId}`, {
      headers: { 'X-CSRF-Token': localStorage.getItem('csrfToken') || '' }
    });
    const json = await res.json();
    const items = json.data || [];
    
    grid.innerHTML = '';
    
    if (items.length === 0) {
        grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted)">No items available in stock.</div>';
        return;
    }

    items.forEach(item => {
      if (item.quantity <= 0) return; // Don't show out of stock
      const div = document.createElement('div');
      div.className = 'product-card';
      // Normalize data to match old mock structure expectations
      const cartItem = {
          id: item.item_id,
          name: item.item_name,
          price: parseFloat(item.selling_price),
          stock: item.quantity
      };
      div.onclick = () => addToCart(cartItem);
      div.innerHTML = `
        <div class="product-img d-flex justify-center align-center" style="color:var(--text-secondary)">
          <i data-lucide="shopping-bag"></i>
        </div>
        <div style="font-weight:600;font-size:0.875rem;margin-bottom:0.2rem">${cartItem.name}</div>
        <div style="color:var(--text-secondary);font-size:0.8rem">$${cartItem.price.toFixed(2)}</div>
        <div style="color:var(--text-muted);font-size:0.7rem;margin-top:0.25rem">Stock: ${cartItem.stock}</div>
      `;
      grid.appendChild(div);
    });
    lucide.createIcons();
  } catch (e) {
      grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--danger)">Error loading inventory.</div>';
      console.error(e);
  }
}

function addToCart(item) {
  const existing = cart.find(c => c.id === item.id);
  if (existing) {
    if (existing.quantity < item.stock) existing.quantity += 1;
    else alert('Not enough stock!');
  } else {
    cart.push({ ...item, quantity: 1 });
  }
  updateCartUI();
}

function updateCartUI() {
  const container = document.getElementById('cartItemsContainer');
  if (cart.length === 0) {
    container.innerHTML = '<div style="text-align:center;color:var(--text-muted);margin-top:2rem;font-size:0.875rem">Cart is empty</div>';
    document.getElementById('cartSubtotal').textContent = '$0.00';
    document.getElementById('cartTotal').textContent = '$0.00';
    window.posDiscountRate = 0; // reset discount if cart empty
    return;
  }

  container.innerHTML = '';
  let subtotal = 0;

  cart.forEach((item, index) => {
    const total = item.price * item.quantity;
    subtotal += total;
    const div = document.createElement('div');
    div.className = 'cart-item';
    div.innerHTML = `
      <div style="flex:1">
        <div style="font-size:0.875rem;font-weight:600">${item.name}</div>
        <div style="font-size:0.75rem;color:var(--text-secondary)">$${item.price.toFixed(2)} × ${item.quantity}</div>
      </div>
      <div style="font-weight:700">$${total.toFixed(2)}</div>
      <button class="btn" style="padding:0.25rem;color:var(--danger)" onclick="removeFromCart(${index})">
        <i data-lucide="trash-2" style="width:16px;height:16px"></i>
      </button>
    `;
    container.appendChild(div);
  });

  lucide.createIcons();
  
  const discountRate = window.posDiscountRate || 0;
  const finalTotal = subtotal * (1 - discountRate);

  document.getElementById('cartSubtotal').textContent = `$${subtotal.toFixed(2)}`;
  
  if (discountRate > 0) {
      document.getElementById('cartTotal').innerHTML = `<span style="text-decoration:line-through;color:var(--text-muted);font-size:1rem;margin-right:0.5rem">$${subtotal.toFixed(2)}</span>$${finalTotal.toFixed(2)}`;
  } else {
      document.getElementById('cartTotal').textContent = `$${finalTotal.toFixed(2)}`;
  }
}

function removeFromCart(index) {
  cart.splice(index, 1);
  updateCartUI();
}

async function processCheckout() {
  if (cart.length === 0) return alert('Cart is empty.');
  
  const checkoutBtn = document.getElementById('checkoutBtn');
  checkoutBtn.disabled = true;
  checkoutBtn.textContent = 'Processing...';

  try {
      const branchId = window.activeUserBranch || 1;
      
      let subtotal = 0;
      const items = cart.map(item => {
          const itemTotal = item.price * item.quantity;
          subtotal += itemTotal;
          return {
              item_id: item.id,
              quantity: item.quantity,
              unit_price: item.price,
              subtotal: itemTotal,
              discount: 0
          };
      });

      const discountRate = window.posDiscountRate || 0;
      const discountAmount = subtotal * discountRate;
      const finalAmount = subtotal - discountAmount;

      const payload = {
          branch_id: branchId,
          items: items,
          total_amount: subtotal,
          discount_amount: discountAmount,
          final_amount: finalAmount,
          payment_method: 'cash',
          notes: ''
      };

      const res = await fetch('/api/sales', {
          method: 'POST',
          headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': localStorage.getItem('csrfToken') || ''
          },
          body: JSON.stringify(payload)
      });

      const json = await res.json();
      
      if (res.ok && json.success) {
          showReceiptModal(json.data.id, cart, subtotal, discountAmount, finalAmount);
          cart = [];
          window.posDiscountRate = 0;
          updateCartUI();
          renderPOSItems(); // Refresh stock
          renderMySales(); // Refresh own sales
      } else {
          alert('Failed to process sale: ' + (json.message || 'Unknown error'));
      }
  } catch (e) {
      console.error(e);
      alert('Network error occurred during checkout.');
  } finally {
      checkoutBtn.disabled = false;
      checkoutBtn.textContent = 'Finalize Transaction';
  }
}

function showReceiptModal(txnId, items, subtotal, discount, total) {
    const modal = document.getElementById('receiptModal');
    if (!modal) return alert(`Payment processed! TXN ID: ${txnId}\\nTotal: $${total.toFixed(2)}`);

    const date = new Date().toLocaleString();
    let itemsHtml = items.map(i => `
        <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem; font-size:0.875rem;">
            <span>${i.quantity}x ${i.name}</span>
            <span>$${(i.price * i.quantity).toFixed(2)}</span>
        </div>
    `).join('');

    document.getElementById('receiptContent').innerHTML = `
        <div style="text-align:center; margin-bottom:1rem;">
            <h2 style="margin:0; font-size:1.25rem;">Boutique Store</h2>
            <div style="color:var(--text-secondary); font-size:0.75rem;">TXN: ${txnId} | ${date}</div>
        </div>
        <div style="border-top:1px dashed var(--border); border-bottom:1px dashed var(--border); padding:1rem 0; margin-bottom:1rem;">
            ${itemsHtml}
        </div>
        <div style="display:flex; justify-content:space-between; font-size:0.875rem; margin-bottom:0.25rem;">
            <span>Subtotal:</span>
            <span>$${subtotal.toFixed(2)}</span>
        </div>
        ${discount > 0 ? `
        <div style="display:flex; justify-content:space-between; font-size:0.875rem; color:var(--success); margin-bottom:0.25rem;">
            <span>Discount:</span>
            <span>-$${discount.toFixed(2)}</span>
        </div>
        ` : ''}
        <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:1.1rem; margin-top:0.5rem;">
            <span>Total:</span>
            <span>$${total.toFixed(2)}</span>
        </div>
        <div style="text-align:center; margin-top:1.5rem; font-size:0.8rem; color:var(--text-secondary);">
            Thank you for shopping with us!
        </div>
    `;
    modal.style.display = 'flex';
}

function closeReceiptModal() {
    const modal = document.getElementById('receiptModal');
    if (modal) modal.style.display = 'none';
}


// ——— Branches ———
async function renderBranches() {
  const tbody = document.querySelector('#branchesTable tbody');
  if (!tbody) return;
  tbody.innerHTML = '';

  try {
    const response = await fetch('/api/branches', {
      headers: {
        'X-CSRF-Token': localStorage.getItem('csrfToken') || ''
      }
    });
    const result = await response.json();
    const branches = result.branches || [];

    branches.forEach((b, i) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td style="color:var(--text-secondary)">${b.id}</td>
        <td style="font-weight:600">${b.name}</td>
        <td>${b.address || '-'}</td>
        <td>${b.manager_id || '-'}</td>
        <td><button class="btn btn-danger" style="padding:0.25rem 0.75rem;font-size:0.75rem" onclick="deleteBranch(${b.id})">Delete</button></td>
      `;
      tbody.appendChild(tr);
    });
  } catch (error) {
    console.error('Error loading branches:', error);
  }
}

function openBranchForm() {
  const modal = document.getElementById('branchFormModal');
  if (modal) {
    document.getElementById('branchForm').reset();
    modal.style.display = 'flex';
  }
}

function closeBranchForm() {
  const modal = document.getElementById('branchFormModal');
  if (modal) modal.style.display = 'none';
}

async function submitBranchForm(e) {
  e.preventDefault();
  const name = document.getElementById('branchName').value.trim();
  const address = document.getElementById('branchAddress').value.trim();
  if (name) {
    try {
      const response = await fetch('/api/branches', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': localStorage.getItem('csrfToken') || ''
        },
        body: JSON.stringify({ name: name, address: address || '', manager_id: null })
      });
      const result = await response.json();
      if (result.success) {
        closeBranchForm();
        renderBranches();
        populateBranchDropdown();
      } else {
        alert(result.message || 'Failed to create branch');
      }
    } catch (error) {
      console.error(error);
      alert('Error connecting to server');
    }
  }
}

let branchToDeleteId = null;

function closeDeleteConfirmModal() {
  const modal = document.getElementById('deleteConfirmModal');
  if (modal) modal.style.display = 'none';
  branchToDeleteId = null;
}

function deleteBranch(branchId) {
  branchToDeleteId = branchId;
  const modal = document.getElementById('deleteConfirmModal');
  if (modal) {
    document.getElementById('confirmDeleteBtn').onclick = executeDeleteBranch;
    modal.style.display = 'flex';
  }
}

async function executeDeleteBranch() {
  if (!branchToDeleteId) return;
  try {
    const response = await fetch(`/api/branches/${branchToDeleteId}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-Token': localStorage.getItem('csrfToken') || ''
      }
    });
    const result = await response.json();
    if (result.success) {
      renderBranches();
      populateBranchDropdown();
      closeDeleteConfirmModal();
    } else {
      alert(result.message || 'Failed to delete branch');
      closeDeleteConfirmModal();
    }
  } catch (error) {
    console.error('Error deleting branch:', error);
    alert('Error deleting branch');
    closeDeleteConfirmModal();
  }
}

// ====================================================
// USER MANAGEMENT (Phase 2.3 — API-backed)
// ====================================================
let editingUserId = null;

async function renderUsers() {
  const tbody = document.getElementById('usersTableBody') || document.querySelector('#usersTable tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted)">Loading users...</td></tr>';

  // Populate role filter dropdown
  await populateRoleDropdowns();

  const search = document.getElementById('userSearchInput')?.value || '';
  const role = document.getElementById('userRoleFilter')?.value || '';
  const statusEl = document.getElementById('userStatusFilter');
  const status = statusEl ? statusEl.value : '';

  const result = await UsersAPI.list(1, 50, search, role, status);
  if (!result || !result.success) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--danger)">Failed to load users.</td></tr>';
    return;
  }

  const users = result.users || [];
  if (users.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted)">No users found.</td></tr>';
    return;
  }

  tbody.innerHTML = '';
  users.forEach(u => {
    const isActive = u.is_active == 1;
    const statusClass = isActive ? 'badge-success' : 'badge-danger';
    const statusText = isActive ? 'Active' : 'Inactive';
    const roleName = u.role_name || 'N/A';
    const branchName = u.branch_name || '—';
    const lastLogin = u.last_login ? new Date(u.last_login).toLocaleDateString() : 'Never';
    const fullName = (u.first_name || '') + ' ' + (u.last_name || '');

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td style="color:var(--text-secondary)">${u.id}</td>
      <td style="font-weight:600">${fullName.trim()}</td>
      <td>${u.email}</td>
      <td style="text-transform:capitalize">${roleName}</td>
      <td>${branchName}</td>
      <td><span class="badge ${statusClass}">${statusText}</span></td>
      <td style="font-size:0.8rem">${lastLogin}</td>
      <td>
        <div style="display:flex;gap:0.25rem;flex-wrap:wrap">
          <button class="btn btn-outline" style="padding:0.2rem 0.5rem;font-size:0.7rem" onclick="editUser(${u.id})">Edit</button>
          <button class="btn btn-danger" style="padding:0.2rem 0.5rem;font-size:0.7rem" onclick="deleteUser(${u.id})">Delete</button>
          ${u.is_locked ? `<button class="btn btn-primary" style="padding:0.2rem 0.5rem;font-size:0.7rem" onclick="unlockUser(${u.id})">Unlock</button>` : ''}
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

async function populateRoleDropdowns() {
  // User form role dropdown
  const userRoleSelect = document.getElementById('userRole');
  const filterRoleSelect = document.getElementById('userRoleFilter');

  try {
    const result = await RolesAPI.list();
    if (!result || !result.success) {
      console.error('Failed to fetch roles:', result?.message || 'Unknown error');
      return;
    }

    const roles = result.roles || [];

    // Populate form dropdown
    if (userRoleSelect && userRoleSelect.options.length <= 1) {
      roles.forEach(r => {
        const opt = document.createElement('option');
        opt.value = r.id;
        opt.textContent = r.name;
        userRoleSelect.appendChild(opt);
      });
    }

    // Populate filter dropdown
    if (filterRoleSelect && filterRoleSelect.options.length <= 1) {
      roles.forEach(r => {
        const opt = document.createElement('option');
        opt.value = r.id;
        opt.textContent = r.name;
        filterRoleSelect.appendChild(opt);
      });
    }
  } catch (error) {
    console.error('Error loading roles:', error);
  }
}

async function populateBranchDropdown() {
  const branchSelect = document.getElementById('userBranch');
  if (!branchSelect) return;

  // Clear existing options except the first one
  while (branchSelect.options.length > 1) {
    branchSelect.remove(1);
  }

  try {
    const response = await fetch('/api/branches', {
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': localStorage.getItem('csrfToken') || ''
      }
    });

    if (!response.ok) {
      console.error('Failed to fetch branches:', response.status, response.statusText);
      return;
    }

    const result = await response.json();
    if (!result || !result.success) {
      console.error('Failed to fetch branches:', result?.message || 'Unknown error');
      return;
    }

    const branches = result.branches || [];
    branches.forEach(b => {
      const opt = document.createElement('option');
      opt.value = b.id;
      opt.textContent = b.name;
      branchSelect.appendChild(opt);
    });
  } catch (error) {
    console.error('Failed to load branches:', error);
  }
}

function openUserForm(userId) {
  editingUserId = userId || null;
  const modal = document.getElementById('userFormModal');
  const title = document.getElementById('userFormTitle');
  const passwordInput = document.getElementById('userPassword');
  const passwordHint = document.getElementById('passwordHint');
  const form = document.getElementById('userForm');

  form.reset();

  if (editingUserId) {
    title.textContent = 'Edit User';
    passwordInput.removeAttribute('required');
    if (passwordHint) {
      passwordHint.style.display = 'block';
    }
    // Load existing user data
    loadUserForEdit(editingUserId);
  } else {
    title.textContent = 'Add User';
    passwordInput.setAttribute('required', 'required');
    if (passwordHint) {
      passwordHint.style.display = 'none';
    }
  }

  populateRoleDropdowns();
  populateBranchDropdown();
  modal.style.display = 'flex';
}

async function loadUserForEdit(userId) {
  const result = await UsersAPI.get(userId);
  if (!result || !result.success || !result.user) {
    alert('Failed to load user data.');
    return;
  }

  const u = result.user;
  document.getElementById('userEmail').value = u.email || '';
  document.getElementById('userFirstName').value = u.first_name || '';
  document.getElementById('userLastName').value = u.last_name || '';
  document.getElementById('userRole').value = u.role_id || '';
  document.getElementById('userActive').checked = u.is_active == 1;

  const branchSelect = document.getElementById('userBranch');
  if (branchSelect && u.branch_id) {
    branchSelect.value = u.branch_id;
  }
}

function closeUserForm() {
  editingUserId = null;
  const modal = document.getElementById('userFormModal');
  if (modal) modal.style.display = 'none';
}

async function submitUserForm(e) {
  e.preventDefault();

  const userData = {
    email: document.getElementById('userEmail').value.trim(),
    first_name: document.getElementById('userFirstName').value.trim(),
    last_name: document.getElementById('userLastName').value.trim(),
    role_id: parseInt(document.getElementById('userRole').value),
    branch_id: document.getElementById('userBranch').value || null,
    is_active: document.getElementById('userActive').checked ? 1 : 0
  };

  const password = document.getElementById('userPassword').value;
  if (password) {
    userData.password = password;
  }

  let result;
  if (editingUserId) {
    result = await UsersAPI.update(editingUserId, userData);
  } else {
    if (!password) {
      alert('Password is required for new users.');
      return;
    }
    result = await UsersAPI.create(userData);
  }

  if (!result) return;

  if (result.success) {
    closeUserForm();
    renderUsers();
  } else {
    const errorMsg = result.errors
      ? Object.values(result.errors).join('\n')
      : result.message || 'Failed to save user.';
    alert(errorMsg);
  }
}

async function editUser(userId) {
  openUserForm(userId);
}

async function deleteUser(userId) {
  if (!confirm('Are you sure you want to delete this user?')) return;
  const result = await UsersAPI.delete(userId);
  if (result && result.success) {
    renderUsers();
  } else {
    alert(result?.message || 'Failed to delete user.');
  }
}

async function unlockUser(userId) {
  if (!confirm('Unlock this user account?')) return;
  const result = await UsersAPI.unlock(userId);
  if (result && result.success) {
    alert('User unlocked successfully.');
    renderUsers();
  } else {
    alert(result?.message || 'Failed to unlock user.');
  }
}

let filterDebounce = null;

function filterUsersTable() {
  clearTimeout(filterDebounce);
  filterDebounce = setTimeout(() => {
    renderUsers();
  }, 300);
}

// ====================================================
// ROLE MANAGEMENT (Phase 2.3 — API-backed)
// ====================================================
let editingRoleId = null;

async function renderRoles() {
  const tbody = document.getElementById('rolesTableBody') || document.querySelector('#rolesTable tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">Loading roles...</td></tr>';

  const result = await RolesAPI.list();
  if (!result || !result.success) {
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--danger)">Failed to load roles.</td></tr>';
    return;
  }

  const roles = result.roles || [];
  if (roles.length === 0) {
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">No roles found.</td></tr>';
    return;
  }

  tbody.innerHTML = '';
  roles.forEach(r => {
    const permCount = (r.permissions || []).length;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td style="font-weight:600;text-transform:capitalize">${r.name}</td>
      <td style="color:var(--text-secondary)">${r.description || '—'}</td>
      <td style="text-align:center"><span class="badge badge-success">${permCount}</span></td>
      <td>
        <div style="display:flex;gap:0.25rem">
          <button class="btn btn-outline" style="padding:0.2rem 0.5rem;font-size:0.7rem" onclick="editRole(${r.id})">Edit</button>
          <button class="btn btn-danger" style="padding:0.2rem 0.5rem;font-size:0.7rem" onclick="deleteRole(${r.id})">Delete</button>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

async function openRoleForm(roleId) {
  editingRoleId = roleId || null;
  const modal = document.getElementById('roleFormModal');
  const title = document.getElementById('roleFormTitle');
  const form = document.getElementById('roleForm');

  form.reset();

  // Load all permissions for checkboxes
  await loadPermissionCheckboxes();

  if (editingRoleId) {
    title.textContent = 'Edit Role';
    await loadRoleForEdit(editingRoleId);
  } else {
    title.textContent = 'Add Role';
  }

  modal.style.display = 'flex';
}

async function loadPermissionCheckboxes() {
  const container = document.getElementById('rolePermissions');
  if (!container) return;

  const result = await RolesAPI.permissions();
  if (!result || !result.success) {
    container.innerHTML = '<p style="color:var(--danger)">Failed to load permissions.</p>';
    return;
  }

  const permissions = result.permissions || [];
  if (permissions.length === 0) {
    container.innerHTML = '<p style="color:var(--text-muted)">No permissions available.</p>';
    return;
  }

  // Group by resource
  const grouped = {};
  permissions.forEach(p => {
    const resource = p.resource || p.name?.split('.')[0] || 'other';
    if (!grouped[resource]) grouped[resource] = [];
    grouped[resource].push(p);
  });

  container.innerHTML = '';
  Object.entries(grouped).forEach(([resource, perms]) => {
    const section = document.createElement('div');
    section.style.marginBottom = '0.75rem';
    section.innerHTML = `<strong style="text-transform:capitalize;font-size:0.8rem;color:var(--text-secondary)">${resource}</strong>`;
    perms.forEach(p => {
      const label = document.createElement('label');
      label.style.cssText = 'display:flex;align-items:center;gap:0.5rem;margin:0.25rem 0;font-size:0.85rem;cursor:pointer';
      label.innerHTML = `<input type="checkbox" name="permissions" value="${p.id}"> ${p.name || p.action || p.permission}`;
      section.appendChild(label);
    });
    container.appendChild(section);
  });
}

async function loadRoleForEdit(roleId) {
  const result = await RolesAPI.list();
  if (!result || !result.success) return;

  const role = (result.roles || []).find(r => r.id == roleId);
  if (!role) return;

  document.getElementById('roleName').value = role.name || '';
  document.getElementById('roleDescription').value = role.description || '';

  // Check the assigned permissions
  const assignedIds = (role.permissions || []).map(p => String(p.id));
  document.querySelectorAll('#rolePermissions input[type="checkbox"]').forEach(cb => {
    cb.checked = assignedIds.includes(cb.value);
  });
}

function closeRoleForm() {
  editingRoleId = null;
  const modal = document.getElementById('roleFormModal');
  if (modal) modal.style.display = 'none';
}

async function submitRoleForm(e) {
  e.preventDefault();

  const selectedPermissions = [];
  document.querySelectorAll('#rolePermissions input[type="checkbox"]:checked').forEach(cb => {
    selectedPermissions.push(parseInt(cb.value));
  });

  const roleData = {
    name: document.getElementById('roleName').value.trim(),
    description: document.getElementById('roleDescription').value.trim(),
    permissions: selectedPermissions
  };

  if (!roleData.name) {
    alert('Role name is required.');
    return;
  }

  let result;
  if (editingRoleId) {
    result = await RolesAPI.update(editingRoleId, roleData);
  } else {
    result = await RolesAPI.create(roleData);
  }

  if (!result) return;

  if (result.success) {
    closeRoleForm();
    renderRoles()
  } else {
    const errorMsg = result.errors
      ? Object.values(result.errors).join('\n')
      : result.message || 'Failed to save role.';
    alert(errorMsg);
  }
}

async function editRole(roleId) {
  openRoleForm(roleId);
}

async function deleteRole(roleId) {
  if (!confirm('Are you sure you want to delete this role?')) return;
  const result = await RolesAPI.delete(roleId);
  if (result && result.success) {
    renderRoles();
  } else {
    alert(result?.message || 'Failed to delete role.');
  }
}

// ——— Reports ———
async function generateReport(type) {
  const log = document.getElementById('reportLog');
  const title = document.getElementById('reportTitle');
  log.textContent = 'Loading...';

  try {
      const res = await fetch('/api/sales', {
          headers: { 'X-CSRF-Token': localStorage.getItem('csrfToken') || '' }
      });
      const json = await res.json();
      const allSales = json.data || [];

      if (type === 'daily') {
        title.textContent = 'Daily Sales — ' + new Date().toLocaleDateString();
        // Just show all sales simply as a placeholder for daily, or filter by today's date if timestamps exist
        log.textContent = JSON.stringify(allSales.slice(0, 10), null, 2);
      } else if (type === 'weekly') {
        title.textContent = 'Weekly Revenue';
        const total = allSales.reduce((acc, s) => acc + parseFloat(s.final_amount), 0);
        log.textContent = `Total Revenue (All Time): $${total.toFixed(2)}\\n\\n(Full weekly breakdown requires backend filters)`;
      } else if (type === 'inventory') {
        title.textContent = 'Inventory Snapshot';
        const invRes = await fetch('/api/inventory', { headers: { 'X-CSRF-Token': localStorage.getItem('csrfToken') || '' }});
        const invJson = await invRes.json();
        const items = invJson.data || [];
        const fast = items.filter(i => i.quantity < 10);
        const slow = items.filter(i => i.quantity >= 10);
        log.textContent = 'LOW STOCK:\\n' + JSON.stringify(fast, null, 2) + '\\n\\nHEALTHY:\\n' + JSON.stringify(slow, null, 2);
      }
  } catch (e) {
      log.textContent = 'Error loading report data.';
  }
}

// ——— Stock Alerts ———
function renderStockAlerts() {
  const container = document.getElementById('stockAlertsContainer');
  if (!container) return;

  const low = window.MOCK_DATA.inventory.filter(i => i.stock < 5);
  if (low.length === 0) {
    container.innerHTML = '<p style="color:var(--success)">All stock levels are optimal.</p>';
    return;
  }

  container.innerHTML = low.map(item => `
    <div class="data-panel" style="margin-bottom:1rem;border-left:3px solid var(--warning)">
      <h4 style="color:var(--warning);font-size:0.875rem;font-weight:600;margin-bottom:0.25rem">Low Stock: ${item.name}</h4>
      <p style="font-size:0.8rem;color:var(--text-secondary)">Only ${item.stock} left in ${item.branch}. SKU: ${item.id}</p>
    </div>
  `).join('');
}

function checkAlerts() {
  if (document.getElementById('view-stock-ops')?.classList.contains('active')) renderStockAlerts();
}

// ——— My Sales ———
async function renderMySales(userName) {
  const tbody = document.querySelector('#mySalesTable tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">Loading...</td></tr>';

  try {
    const userId = window.activeUserId || '';
    const url = userId ? `/api/sales?user_id=${userId}` : '/api/sales';
    const res = await fetch(url, { headers: { 'X-CSRF-Token': localStorage.getItem('csrfToken') || '' } });
    const json = await res.json();
    const mySales = json.data || [];

    if (mySales.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">No sales recorded yet.</td></tr>';
      return;
    }
    
    tbody.innerHTML = '';
    mySales.forEach(s => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td style="color:var(--text-secondary)">${new Date(s.created_at).toLocaleString()}</td>
        <td>${s.transaction_number}</td>
        <td>${s.branch_name || 'Main Branch'}</td>
        <td style="font-weight:700;color:var(--primary)">$${parseFloat(s.final_amount).toFixed(2)}</td>
      `;
      tbody.appendChild(tr);
    });
  } catch (e) {
    console.error(e);
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--danger)">Failed to load sales history.</td></tr>';
  }
}

// ——— Discounts ———
function applyDiscount() {
  if (cart.length === 0) return alert('Add items first.');
  const code = prompt('Discount Code (e.g., VIP10):');
  if (code) {
    alert('Discount applied! 10% off total.');
    window.posDiscountRate = 0.10;
    updateCartUI();
  }
}

// ——— Mobile Menu ———
function setupMobileMenu() {
  const sidebar = document.getElementById('sidebar');
  const openBtn = document.getElementById('mobileMenuBtn');
  const closeBtn = document.getElementById('mobileMenuClose');

  const toggle = () => {
    const mobile = window.innerWidth <= 768;
    openBtn.style.display = mobile ? 'block' : 'none';
    closeBtn.style.display = mobile ? 'block' : 'none';
    if (!mobile) sidebar.classList.remove('open');
  };

  toggle();
  window.addEventListener('resize', toggle);
  openBtn.onclick = () => sidebar.classList.add('open');
  closeBtn.onclick = () => sidebar.classList.remove('open');
}