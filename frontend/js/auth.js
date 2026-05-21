// ===================================
// AUTH.JS — Login Page Logic
// ===================================

document.addEventListener('DOMContentLoaded', () => {
  lucide.createIcons();

  document.getElementById('loginForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;
    const btn = document.getElementById('loginBtn');
    const errorMsg = document.getElementById('errorMsg');
    const emailInput = document.getElementById('loginEmail');
    const passwordInput = document.getElementById('loginPassword');

    // Reset previous state
    btn.innerHTML = 'Verifying...';
    btn.style.opacity = '0.6';
    btn.disabled = true;
    errorMsg.style.display = 'none';
    emailInput.style.borderColor = '';
    passwordInput.style.borderColor = '';

    // Send login request to API
    fetch('/api/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        email: email,
        password: password
      })
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Store auth data in localStorage
          localStorage.setItem('userRole', data.user.role_id);
          localStorage.setItem('userName', data.user.first_name + ' ' + data.user.last_name);
          localStorage.setItem('userId', data.user.id);
          localStorage.setItem('userBranchId', data.user.branch_id || '');
          localStorage.setItem('csrfToken', data.csrf_token);

          // Show success state
          btn.innerHTML = '✓ Success';
          btn.style.background = 'var(--success)';
          btn.style.opacity = '1';

          // Redirect to dashboard
          setTimeout(() => {
            window.location.href = '/dashboard';
          }, 400);
        } else {
          // Handle error
          throw new Error(data.message || 'Login failed');
        }
      })
      .catch(error => {
        // Reset button
        btn.innerHTML = 'Log in';
        btn.style.opacity = '1';
        btn.disabled = false;

        // Show error message
        errorMsg.textContent = error.message || 'Invalid email or password.';
        errorMsg.style.display = 'block';

        // Highlight error fields
        emailInput.style.borderColor = 'var(--danger)';
        passwordInput.style.borderColor = 'var(--danger)';

        console.error('Login error:', error);
      });
  });
});
