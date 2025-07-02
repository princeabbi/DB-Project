// MODIFIED: app.js

function sanitize(str) { return str.replace(/</g, "<").replace(/>/g, ">"); }
document.addEventListener('DOMContentLoaded', () => {
    const path = window.location.pathname.split("/").pop();
    if (path === 'index.html' || path === '') {
        // This auth logic does not need to change.
        const loginForm = document.getElementById('login-form');
        const registerForm = document.getElementById('register-form');
        const loginContainer = document.getElementById('login-form-container');
        const registerContainer = document.getElementById('register-form-container');
        const showRegisterLink = document.getElementById('show-register');
        const showLoginLink = document.getElementById('show-login');
        const authMessage = document.getElementById('auth-message');
        showRegisterLink.addEventListener('click', (e) => { e.preventDefault(); loginContainer.classList.add('hidden'); registerContainer.classList.remove('hidden'); });
        showLoginLink.addEventListener('click', (e) => { e.preventDefault(); registerContainer.classList.add('hidden'); loginContainer.classList.remove('hidden'); });
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'login');
            formData.append('username', document.getElementById('login-username').value);
            formData.append('password', document.getElementById('login-password').value);
            const response = await fetch('api/auth.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) { window.location.href = 'dashboard.html'; } else { authMessage.textContent = result.message; authMessage.className = 'message error'; }
        });
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'register');
            formData.append('username', document.getElementById('register-username').value);
            formData.append('password', document.getElementById('register-password').value);
            const response = await fetch('api/auth.php', { method: 'POST', body: formData });
            const result = await response.json();
            authMessage.textContent = result.message;
            authMessage.className = result.success ? 'message success' : 'message error';
            if (result.success) { registerForm.reset(); setTimeout(() => showLoginLink.click(), 2000); }
        });
    }

    if (path === 'dashboard.html') {
        const addProductBtn = document.getElementById('add-product-btn');
        const productTableBody = document.getElementById('product-table-body');
        // WHAT CHANGED: We declare a variable to hold the user's role.
        let currentUserRole = '';
        
        async function checkAuth() {
            const response = await fetch('api/auth.php?action=check_auth');
            const result = await response.json();
            if (!result.authenticated) {
                window.location.href = 'index.html';
            } else {
                document.getElementById('username-display').textContent = sanitize(result.username);
                // WHAT CHANGED: We store the role and update the UI accordingly.
                currentUserRole = result.role;
                updateUIVisibility();
                fetchProducts();
            }
        }

        // WHAT CHANGED: This new function shows/hides elements based on role.
        function updateUIVisibility() {
            if (currentUserRole === 'admin') {
                addProductBtn.style.display = 'block';
            } else {
                addProductBtn.style.display = 'none';
            }
        }

        async function fetchProducts() {
            const response = await fetch('api/inventory.php?action=read');
            if(response.status === 401) { window.location.href = 'index.html'; return; }
            const products = await response.json();
            productTableBody.innerHTML = '';
            if(products.length === 0) { productTableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No products found.</td></tr>'; } else {
                products.forEach(product => {
                    // WHAT CHANGED: We conditionally render the Delete button only for admins.
                    const deleteButton = currentUserRole === 'admin' ? `<button class="btn delete" data-id="${product.id}">Delete</button>` : '';
                    const row = `<tr><td>${sanitize(product.name)}</td><td>${sanitize(product.sku)}</td><td>${product.qty}</td><td>$${parseFloat(product.price).toFixed(2)}</td>
                        <td><button class="btn edit" data-id="${product.id}" data-name="${sanitize(product.name)}" data-sku="${sanitize(product.sku)}" data-qty="${product.qty}" data-price="${product.price}">Edit</button>
                            ${deleteButton}</td></tr>`;
                    productTableBody.innerHTML += row;
                });
            }
        }
        
        // The rest of the file remains the same. The logic is already protected by the back-end.
        const productModal = document.getElementById('product-modal');
        const modalCancelBtn = document.getElementById('modal-cancel-btn');
        const productForm = document.getElementById('product-form');
        const modalTitle = document.getElementById('modal-title');
        const dashboardMessage = document.getElementById('dashboard-message');
        const logoutBtn = document.getElementById('logout-btn');
        function showModal(isEdit = false, data = {}) {
            productForm.reset(); document.getElementById('product-id').value = isEdit ? data.id : '';
            modalTitle.textContent = isEdit ? 'Edit Product' : 'Add Product';
            if (isEdit) { document.getElementById('product-name').value = data.name; document.getElementById('product-sku').value = data.sku; document.getElementById('product-qty').value = data.qty; document.getElementById('product-price').value = data.price; }
            productModal.classList.remove('hidden');
        }
        function hideModal() { productModal.classList.add('hidden'); }
        addProductBtn.addEventListener('click', () => showModal());
        modalCancelBtn.addEventListener('click', () => hideModal());
        productForm.addEventListener('submit', async e => {
            e.preventDefault();
            const id = document.getElementById('product-id').value; const action = id ? 'update' : 'create';
            const formData = new FormData();
            formData.append('action', action); formData.append('id', id);
            formData.append('name', document.getElementById('product-name').value);
            formData.append('sku', document.getElementById('product-sku').value);
            formData.append('qty', document.getElementById('product-qty').value);
            formData.append('price', document.getElementById('product-price').value);
            const response = await fetch('api/inventory.php', { method: 'POST', body: formData });
            const result = await response.json();
            dashboardMessage.textContent = result.message; dashboardMessage.className = result.success ? 'message success' : 'message error';
            if(result.success) { hideModal(); fetchProducts(); }
            setTimeout(() => { dashboardMessage.textContent = ''; dashboardMessage.className='message'; }, 3000);
        });
        productTableBody.addEventListener('click', async (e) => {
            const target = e.target; const id = target.dataset.id;
            if (target.classList.contains('edit')) { const { name, sku, qty, price } = target.dataset; showModal(true, { id, name, sku, qty, price }); }
            if (target.classList.contains('delete')) {
                if (confirm('Are you sure you want to delete this product?')) {
                    const formData = new FormData(); formData.append('action', 'delete'); formData.append('id', id);
                    const response = await fetch('api/inventory.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    dashboardMessage.textContent = result.message; dashboardMessage.className = result.success ? 'message success' : 'message error';
                    fetchProducts();
                    setTimeout(() => { dashboardMessage.textContent = ''; dashboardMessage.className='message'; }, 3000);
                }
            }
        });
        logoutBtn.addEventListener('click', async () => { await fetch('api/auth.php?action=logout'); window.location.href = 'index.html'; });
        checkAuth();
    }
});