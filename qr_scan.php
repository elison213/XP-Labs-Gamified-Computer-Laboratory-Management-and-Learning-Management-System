<?php
$page_title = 'QR Scan';
$page_id = 'auth-qr-scan';
$ui_theme = 'default';
?>
<?php include 'components/head.php'; ?>

<nav class="navbar navbar-dark nav-xp shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-semibold" href="login.php">← Back</a>
    <span class="navbar-text text-white-50 small">Check-in</span>
  </div>
</nav>

<div class="container py-4">
  <div class="text-center mb-4 reveal">
    <h2 class="h4 fw-bold">Scan QR Code</h2>
    <p class="text-secondary small mb-0">Point the camera at a lab QR code. Data is shown only — no server yet.</p>
  </div>

  <div class="d-flex flex-column align-items-center">
    <div id="reader" class="qr-frame overflow-hidden mb-3"></div>
    <p id="qr-status" class="small text-secondary mb-1">Waiting for scan…</p>
    <p id="qr-result" class="small text-muted font-monospace mb-0" style="max-width: 360px; word-break: break-all;"></p>
  </div>
</div>

<script src="https://unpkg.com/html5-qrcode"></script>
<script>
(function () {
  var statusEl = document.getElementById('qr-status');
  var resultEl = document.getElementById('qr-result');

  function setWaiting() {
    statusEl.className = 'small text-secondary mb-1';
    statusEl.textContent = 'Waiting for scan…';
  }

  function onScanSuccess(decodedText) {
    statusEl.className = 'small text-success mb-1';
    statusEl.textContent = 'Scanned successfully';
    resultEl.textContent = decodedText;
  }

  function onScanFailure() {}

  setWaiting();

  if (typeof Html5QrcodeScanner === 'undefined') {
    statusEl.className = 'small text-danger mb-1';
    statusEl.textContent = 'Scanner library failed to load. Check your connection.';
    return;
  }

  var scanner = new Html5QrcodeScanner('reader', { fps: 10 }, false);
  scanner.render(onScanSuccess, onScanFailure);
})();
</script>

<?php include 'components/footer.php'; ?>
