// ===================================
// DASHBOARD.JS — All Dashboard Logic
// Handles auth check, role UI, CRUD, POS
// ===================================

// Database persistence
window.saveDB = function () {
  localStorage.setItem('BoutiqueDB', JSON.stringify(window.MOCK_DATA));
};

document.addEventListener('DOMContentLoaded', () => {
  lucide.createIcons();

  // Database State
  const saved = localStorage.getItem('BoutiqueDB');
  if (saved) {
    window.MOCK_DATA = JSON.parse(saved);
  } else {
    window.saveDB();
  }

  // Render UI immediately from localStorage (prevents flash/flicker)
  const cachedRole = localStorage.getItem('userRole');
  if (cachedRole) {
    const roleInt = parseInt(cachedRole);
    setupRoleUI(isNaN(roleInt) ? cachedRole : roleInt);
    setupMobileMenu();
  }

  // Verify auth with backend silently (no redirect loop on failure)
  fetch('/api/user', {
    method: 'GET',
    headers: {
      'X-CSRF-Token': localStorage.getItem('csrfToken') || ''
    }
  })
    .then(response => {
      if (response.status === 401) {
        // Only redirect on explicit 401 Unauthorized
        localStorage.removeItem('userId');
        localStorage.removeItem('userRole');
        localStorage.removeItem('userName');
        localStorage.removeItem('csrfToken');
        window.location.href = '/login';
        return null;
      }
      if (!response.ok) return null;
      return response.json();
    })
    .then(data => {
      if (!data || !data.user) return;

      // Update localStorage with fresh data from backend
      const freshRole = data.user.role_id;
      const freshName = (data.user.first_name || '') + ' ' + (data.user.last_name || '');
      localStorage.setItem('userRole', freshRole);
      localStorage.setItem('userName', freshName.trim());

      // If role changed from what we rendered, re-render
      const currentRole = parseInt(cachedRole);
      if (freshRole !== currentRole && !cachedRole) {
        // Only re-setup if we didn't render initially
        setupRoleUI(freshRole);
        setupMobileMenu();
      }
    })
    .catch(error => {
      // Network error — don't redirect, just log it
      console.warn('Backend verification failed (network issue):', error.message);
    });
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
      { id: 'reports', icon: 'bar-chart-2', label: 'Analytics' }
    ];
    defaultView = 'overview';
    renderRecentSales();
    renderBranches();
    renderUsers();
  } else if (role === 2 || role === 'store_keeper') {
    userNameEl.textContent = localName || 'Alice Smith';
    userRoleEl.textContent = 'Store Keeper';
    navItems = [
      { id: 'inventory', icon: 'package', label: 'Stock' },
      { id: 'stock-ops', icon: 'bell', label: 'Alerts' },
      { id: 'my-sales', icon: 'activity', label: 'Performance' }
    ];
    defaultView = 'inventory';
    document.getElementById('addItemBtn').style.display = 'inline-flex';
    document.getElementById('addItemBtn').onclick = createItem;
    renderStockAlerts();
    renderMySales(activeUserNameForFilter);
  } else if (role === 3 || role === 'seller') {
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
  const tbody = document.querySelector('#inventoryTable tbody');
  if (!tbody) return;
  tbody.innerHTML = '';

  const inventory = window.MOCK_DATA.inventory || [];
  const userRole = parseInt(localStorage.getItem('userRole')) || localStorage.getItem('userRole');

  inventory.forEach((item, index) => {
    let statusClass = 'badge-success';
    if (item.stock < 10) statusClass = 'badge-warning';
    if (item.stock === 0) statusClass = 'badge-danger';

    let actionBtns = '';
    if (userRole === 2 || userRole === 'store_keeper') {
      actionBtns = `<button class="btn btn-outline" style="padding:0.25rem 0.75rem;font-size:0.75rem" onclick="updateItemQty(${index})">Update</button>`;
    } else if (userRole === 1 || userRole === 'manager') {
      actionBtns = `<button class="btn btn-outline" style="padding:0.25rem 0.75rem;font-size:0.75rem" onclick="transferItem(${index})">Transfer</button>`;
    }

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td style="color:var(--text-secondary)">${item.id}</td>
      <td style="font-weight:600">${item.name}</td>
      <td>${item.category}</td>
      <td>$${item.price.toFixed(2)}</td>
      <td>${item.stock}</td>
      <td><span class="badge ${statusClass}">${item.stock === 0 ? 'Out' : (item.stock < 10 ? 'Low' : 'In Stock')}</span></td>
      <td>${item.branch}</td>
      ${actionBtns ? `<td>${actionBtns}</td>` : ''}
    `;
    tbody.appendChild(tr);
  });
}

function updateItemQty(index) {
  const item = window.MOCK_DATA.inventory[index];
  const newQty = prompt(`Update stock for ${item.name} (Current: ${item.stock}):`, item.stock);
  if (newQty !== null && !isNaN(newQty)) {
    window.MOCK_DATA.inventory[index].stock = parseInt(newQty);
    window.saveDB();
    renderInventoryTable();
    checkAlerts();
  }
}

function transferItem(index) {
  const item = window.MOCK_DATA.inventory[index];
  const newBranch = prompt(`Transfer ${item.name} to branch (Current: ${item.branch}):`, '');
  if (newBranch) {
    window.MOCK_DATA.inventory[index].branch = newBranch;
    window.saveDB();
    renderInventoryTable();
  }
}

function createItem() {
  const name = prompt('Item Name:');
  const price = prompt('Price:');
  const qty = prompt('Initial Quantity:');
  if (name && price && qty) {
    window.MOCK_DATA.inventory.push({
      id: 'ITM' + Math.floor(Math.random() * 1000),
      name,
      category: 'General',
      price: parseFloat(price),
      stock: parseInt(qty),
      branch: 'Headquarters',
      status: parseInt(qty) > 0 ? 'In Stock' : 'Out of Stock'
    });
    window.saveDB();
    renderInventoryTable();
  }
}

// ——— Sales ———
async function renderRecentSales() {
  const result = await DashboardAPI.getManagerDashboard();
  if (!result?.success) {
    console.warn('Failed to load manager dashboard:', result?.message);
    return;
  }

  const d = result.data;
  const kpis = d.kpis;

  // KPI Widgets
  const fmtCur = (v) => '$' + (parseFloat(v) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  document.getElementById('ovTodayRevenue').textContent = fmtCur(kpis.today_revenue);
  document.getElementById('ovMonthRevenue').textContent = fmtCur(kpis.month_revenue);
  document.getElementById('ovTotalProducts').textContent = parseInt(kpis.total_products || 0).toLocaleString();
  document.getElementById('ovActiveBranches').textContent = kpis.active_branches || 0;
  document.getElementById('ovActiveUsers').textContent = kpis.active_users || 0;
  document.getElementById('ovTodayTxns').textContent = kpis.today_transactions || 0;

  // Top Sellers
  const sellerBody = document.querySelector('#ovTopSellersTable tbody');
  if (d.top_sellers && d.top_sellers.length > 0) {
    sellerBody.innerHTML = d.top_sellers.map(s => `
      <tr>
        <td>${s.first_name} ${s.last_name}</td>
        <td>${s.sale_count}</td>
        <td>${fmtCur(s.total_revenue)}</td>
      </tr>
    `).join('');
  } else {
    sellerBody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--text-muted)">No sales this month</td></tr>';
  }

  // Branch Revenue
  const branchBody = document.querySelector('#ovBranchRevenueTable tbody');
  if (d.branch_revenue && d.branch_revenue.length > 0) {
    branchBody.innerHTML = d.branch_revenue.map(b => `
      <tr>
        <td>${b.branch_name}</td>
        <td>${b.transactions}</td>
        <td>${fmtCur(b.revenue)}</td>
      </tr>
    `).join('');
  } else {
    branchBody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--text-muted)">No branch data</td></tr>';
  }

  // Low Stock Alerts
  const lowStockContainer = document.getElementById('ovLowStockContainer');
  if (d.low_stock_alerts && d.low_stock_alerts.length > 0) {
    lowStockContainer.innerHTML = d.low_stock_alerts.map(item => `
      <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--border)">
        <span style="font-weight:600;color:var(--warning)">${item.name} <span style="font-weight:400;color:var(--text-secondary)">(${item.sku})</span></span>
        <span style="font-size:0.8rem">Stock: <strong>${item.current_stock}</strong> / Min: ${item.reorder_level}</span>
      </div>
    `).join('');
  } else {
    lowStockContainer.innerHTML = '<p style="color:var(--success);font-size:0.85rem">✓ All items are well-stocked!</p>';
  }

  // Recent Transactions
  const tbody = document.querySelector('#recentSalesTable tbody');
  if (!tbody) return;
  if (d.recent_transactions && d.recent_transactions.length > 0) {
    tbody.innerHTML = d.recent_transactions.map(sale => {
      const date = new Date(sale.created_at).toLocaleDateString();
      return `
        <tr>
          <td style="color:var(--text-secondary)">${date}</td>
          <td>${sale.transaction_number}</td>
          <td>${sale.seller_first || ''} ${sale.seller_last || ''}</td>
          <td>${sale.branch_name || '—'}</td>
          <td style="font-weight:700">${fmtCur(sale.final_amount)}</td>
        </tr>
      `;
    }).join('');
  } else {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">No recent transactions</td></tr>';
  }
}

// ——— POS ———
let cart = [];

function renderPOSItems() {
  const grid = document.getElementById('posProductGrid');
  if (!grid) return;
  grid.innerHTML = '';

  window.MOCK_DATA.inventory.forEach(item => {
    if (item.stock === 0) return;
    const div = document.createElement('div');
    div.className = 'product-card';
    div.onclick = () => addToCart(item);
    div.innerHTML = `
      <div class="product-img d-flex justify-center align-center" style="color:var(--text-secondary)">
        <i data-lucide="shopping-bag"></i>
      </div>
      <div style="font-weight:600;font-size:0.875rem;margin-bottom:0.2rem">${item.name}</div>
      <div style="color:var(--text-secondary);font-size:0.8rem">$${item.price.toFixed(2)}</div>
    `;
    grid.appendChild(div);
  });
  lucide.createIcons();
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
  document.getElementById('cartSubtotal').textContent = `$${subtotal.toFixed(2)}`;
  document.getElementById('cartTotal').textContent = `$${subtotal.toFixed(2)}`;
}

function removeFromCart(index) {
  cart.splice(index, 1);
  updateCartUI();
}

function processCheckout() {
  if (cart.length === 0) return alert('Cart is empty.');
  alert(`Payment processed! Total: ${document.getElementById('cartTotal').textContent}`);
  cart = [];
  updateCartUI();
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

function formatCurrency(val) {
  const num = parseFloat(val) || 0;
  return '$' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function setReportRange(preset) {
  const startEl = document.getElementById('reportStartDate');
  const endEl = document.getElementById('reportEndDate');
  const today = new Date();
  endEl.value = today.toISOString().split('T')[0];

  if (preset === 'today') {
    startEl.value = endEl.value;
  } else if (preset === 'week') {
    const weekAgo = new Date(today);
    weekAgo.setDate(today.getDate() - 7);
    startEl.value = weekAgo.toISOString().split('T')[0];
  } else if (preset === 'month') {
    startEl.value = today.toISOString().slice(0, 8) + '01';
  }

  loadReports();
}

async function loadReports() {
  const startDate = document.getElementById('reportStartDate')?.value || '';
  const endDate = document.getElementById('reportEndDate')?.value || '';

  // Run all report API calls in parallel
  const [summaryRes, branchRes, sellerRes, valuationRes, lowStockRes] = await Promise.all([
    ReportsAPI.getSalesSummary(startDate, endDate),
    ReportsAPI.getSalesByBranch(startDate, endDate),
    ReportsAPI.getSalesBySeller(startDate, endDate),
    ReportsAPI.getInventoryValuation(),
    ReportsAPI.getLowStock()
  ]);

  // KPI Cards
  if (summaryRes?.success) {
    const d = summaryRes.data;
    document.getElementById('kpiRevenue').textContent = formatCurrency(d.revenue);
    document.getElementById('kpiTransactions').textContent = parseInt(d.total_transactions || 0).toLocaleString();
    document.getElementById('kpiItemsSold').textContent = parseInt(d.total_items_sold || 0).toLocaleString();
    document.getElementById('kpiProfit').textContent = formatCurrency(d.profit);
  }

  // Branch Table
  const branchBody = document.querySelector('#reportBranchTable tbody');
  if (branchRes?.success && branchRes.data.length > 0) {
    branchBody.innerHTML = branchRes.data.map(b => `
      <tr>
        <td>${b.branch_name || '—'}</td>
        <td>${parseInt(b.transactions || 0).toLocaleString()}</td>
        <td>${formatCurrency(b.revenue)}</td>
      </tr>
    `).join('');
  } else {
    branchBody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--text-muted)">No branch data available for this period</td></tr>';
  }

  // Seller Table
  const sellerBody = document.querySelector('#reportSellerTable tbody');
  if (sellerRes?.success && sellerRes.data.length > 0) {
    sellerBody.innerHTML = sellerRes.data.map(s => `
      <tr>
        <td>${s.first_name} ${s.last_name}</td>
        <td>${parseInt(s.total_sales || 0).toLocaleString()}</td>
        <td>${formatCurrency(s.revenue)}</td>
      </tr>
    `).join('');
  } else {
    sellerBody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--text-muted)">No seller data available for this period</td></tr>';
  }

  // Inventory Valuation Cards
  if (valuationRes?.success) {
    const v = valuationRes.data;
    document.getElementById('invTotalItems').textContent = parseInt(v.total_products || 0).toLocaleString();
    document.getElementById('invCostValue').textContent = formatCurrency(v.total_cost_value);
    document.getElementById('invRetailValue').textContent = formatCurrency(v.total_retail_value);
    document.getElementById('invPotentialProfit').textContent = formatCurrency(v.potential_profit);
  }

  // Low Stock Table
  const lowStockBody = document.querySelector('#reportLowStockTable tbody');
  if (lowStockRes?.success && lowStockRes.data.length > 0) {
    lowStockBody.innerHTML = lowStockRes.data.map(item => `
      <tr style="color:var(--warning)">
        <td>${item.sku || '—'}</td>
        <td>${item.name}</td>
        <td>${item.category_name || '—'}</td>
        <td><strong>${item.quantity}</strong></td>
        <td>${item.reorder_level || '—'}</td>
      </tr>
    `).join('');
  } else {
    lowStockBody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--success)">All items are well-stocked!</td></tr>';
  }

  // Render Charts concurrently
  if (typeof renderDashboardCharts === 'function') {
    renderDashboardCharts();
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
function renderMySales(userName) {
  const tbody = document.querySelector('#mySalesTable tbody');
  if (!tbody) return;
  tbody.innerHTML = '';

  const mySales = (window.MOCK_DATA.recentSales || []).filter(s => s.seller === userName);
  if (mySales.length === 0) {
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">No sales recorded yet.</td></tr>';
    return;
  }

  mySales.forEach(s => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td style="color:var(--text-secondary)">${s.date}</td>
      <td>${s.id}</td>
      <td>${s.branch}</td>
      <td style="font-weight:700">$${s.total.toFixed(2)}</td>
    `;
    tbody.appendChild(tr);
  });
}

// ——— Discounts ———
function applyDiscount() {
  if (cart.length === 0) return alert('Add items first.');
  const code = prompt('Discount Code (e.g., VIP10):');
  if (code) {
    alert('Discount applied! 10% off total.');
    const el = document.getElementById('cartTotal');
    const cur = parseFloat(el.textContent.replace('$', ''));
    el.textContent = '$' + (cur * 0.9).toFixed(2);
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