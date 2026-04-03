<?php
$page_title = 'Login';
$page_id = 'auth-login';
$ui_theme = 'default';
?>
<?php include 'components/head.php'; ?>

<div class="login-wrap d-flex align-items-center justify-content-center p-3">
  <div class="card login-card shadow border-0" style="max-width: 400px; width: 100%;">
    <div class="card-body p-4">
      <div class="text-center mb-4">
        <h1 class="h4 fw-bold text-dark mb-1">XPLabs</h1>
        <p class="text-secondary small mb-0">Computer Lab System</p>
      </div>

      <form id="form-login" action="#" method="post" onsubmit="return false;">
        <div class="mb-3">
          <label class="form-label small text-secondary">LRN / Username</label>
          <input type="text" id="login-lrn" class="form-control" placeholder="Enter LRN" autocomplete="username" required>
        </div>
        <div class="mb-3">
          <label class="form-label small text-secondary">Password</label>
          <input type="password" id="login-password" class="form-control" placeholder="Enter password" autocomplete="current-password" required>
        </div>
        <div id="login-error" class="alert alert-danger py-2 small mb-3" style="display:none;"></div>
        <button type="submit" id="btn-login" class="btn btn-primary w-100 mb-3">Login</button>
      </form>

      <div class="d-flex align-items-center my-3">
        <hr class="flex-grow-1">
        <span class="px-2 small text-secondary">OR</span>
        <hr class="flex-grow-1">
      </div>

      <a href="qr_scan.php" class="btn btn-success w-100">Scan QR to sign in</a>

      <p class="text-center small text-secondary mt-3 mb-0">
        <a href="index.php" class="text-decoration-none">Back to home</a>
      </p>
    </div>
  </div>
</div>

<script>
document.getElementById('form-login').addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const btn = document.getElementById('btn-login');
  const errorDiv = document.getElementById('login-error');
  const lrn = document.getElementById('login-lrn').value.trim();
  const password = document.getElementById('login-password').value;
  
  // Reset error
  errorDiv.style.display = 'none';
  errorDiv.textContent = '';
  
  // Validate
  if (!lrn || !password) {
    errorDiv.textContent = 'Please enter both LRN and password.';
    errorDiv.style.display = 'block';
    return;
  }
  
  // Disable button, show loading
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...';
  
  try {
    const basePath = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
    const apiUrl = basePath + 'api/auth/login.php';
    console.log('Login API URL:', apiUrl);
    
    const response = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ lrn: lrn, password: password })
    });
    
    const data = await response.json();
    console.log('Login response:', data);
    
    if (response.ok && data.success) {
      // Build absolute URL for redirect
      const redirectUrl = data.redirect;
      console.log('Redirecting to:', redirectUrl);
      window.location.href = redirectUrl;
    } else {
      errorDiv.textContent = data.error || 'Login failed. Please try again.';
      errorDiv.style.display = 'block';
    }
  } catch (err) {
    errorDiv.textContent = 'Connection error: ' + err.message;
    errorDiv.style.display = 'block';
    console.error('Login error:', err);
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'Login';
  }
});
</script>
<?php include 'components/footer.php'; ?>
