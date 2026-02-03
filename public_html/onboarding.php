<?php
require __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/csrf.php';
$csrfToken = csrf_token();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$stmt = $pdo->query('SELECT * FROM settings WHERE id = 1');
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$hasToken = !empty($settings['plex_token']);
$savedServerId = $settings['plex_server_id'] ?? '';
$savedServerName = $settings['plex_server_name'] ?? '';
$savedServerUrl = $settings['plex_server_url'] ?? '';

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($step < 1 || $step > 3) { $step = 1; }
$stepError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {
    header('Location: onboarding.php?step=2');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $check = $pdo->query("SELECT plex_token FROM settings WHERE id = 1");
    $current = $check->fetch(PDO::FETCH_ASSOC) ?: [];
    if (empty($current['plex_token'])) {
        $stepError = 'Please link Plex before continuing.';
    } else {
        header('Location: onboarding.php?step=3');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    $check = $pdo->query("SELECT plex_token, plex_server_id FROM settings WHERE id = 1");
    $current = $check->fetch(PDO::FETCH_ASSOC) ?: [];
    if (empty($current['plex_token']) || empty($current['plex_server_id'])) {
        $stepError = 'Please link Plex and select a server before finishing setup.';
    } else {
        $stmt = $pdo->prepare("UPDATE settings SET onboarded = 1, updated_at = datetime('now') WHERE id = 1");
        $stmt->execute();
        header('Location: create_roulette.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script>
    window.CSRF_TOKEN = "<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>";
  </script>
  <title>Welcome to Watchulater</title>
  <style>
    body{font-family: Arial, sans-serif; background:#f5f5f5; margin:0;}
    .wrap{max-width:700px; width:100%; margin:0 auto; background:#fff; padding:36px; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.08); box-sizing:border-box}
    .wrap-center{min-height:calc(100vh - 72px); display:flex; align-items:center; justify-content:center}
    h1{margin:0 0 12px 0}
    h3{margin:0 0 6px 0}
    p{color:#444}
    .step{font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#888; margin-bottom:20px}
    .actions{margin-top:50px; display:flex; gap:12px; justify-content:flex-end; flex-wrap:wrap}
    .actions.split{justify-content:space-between}
    .actions.left{justify-content:flex-start}
    .btn{padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:600}
    .primary{background:#2d7ef7; color:#fff}
    .secondary{background:#e5e7eb}
    .plex-card{border:1px solid #e5e7eb; border-radius:12px; padding:16px; margin-top:18px; background:#fafafa; width:100%; box-sizing:border-box}
    .row{display:flex; gap:12px; flex-wrap:wrap; align-items:center}
    .field{display:grid; grid-template-columns:minmax(260px, 1fr) auto; gap:12px; align-items:center; margin-top:12px}
    .input{padding:10px 12px; border-radius:8px; border:1px solid #ddd; width:100%; box-sizing:border-box; -webkit-appearance:none; appearance:none; background:#fff}
    select.input{padding-right:36px; background-image:linear-gradient(45deg, transparent 50%, #666 50%), linear-gradient(135deg, #666 50%, transparent 50%); background-position:calc(100% - 18px) 16px, calc(100% - 12px) 16px; background-size:6px 6px, 6px 6px; background-repeat:no-repeat}
    .muted{color:#777; font-size:13px}
    .divider{display:flex; align-items:center; gap:12px; margin:18px 0}
    .divider::before, .divider::after{content:""; height:1px; background:#e5e7eb; flex:1}
    .divider span{font-size:12px; color:#888; text-transform:uppercase; letter-spacing:1px}
    .toggle{background:#f3f4f6; border:1px solid #e5e7eb; padding:6px 10px; cursor:pointer; text-transform:uppercase; letter-spacing:1px; font-size:12px; color:#666; border-radius:999px}
    .server-wrap{border:1px solid #e5e7eb; border-radius:14px; padding:16px; background:#fafafa; margin-top:18px; width:100%; box-sizing:border-box}
    .manual-block{border:1px dashed #e5e7eb; border-radius:12px; padding:12px; background:#fff; width:100%; box-sizing:border-box}
    .status{margin-top:10px; font-size:13px; color:#333}
    .status.success{color:#1b7f3c}
    .status.error{color:#b42318}
    .btn[disabled]{opacity:.6; cursor:not-allowed}
  </style>
</head>
<body>
  <div class="wrap-center">
    <div class="wrap">
    <?php if ($step === 1): ?>
      <div class="step">Step 1 of 3</div>
      <h1>Welcome to Watchulater</h1>
      <p>We will get you connected to your Plex server and ready to browse.</p>
      <form method="POST" class="actions split">
        <button class="btn secondary" disabled>Back</button>
        <button class="btn primary" type="submit">Continue</button>
      </form>
    <?php elseif ($step === 2): ?>
      <div class="step">Step 2 of 3</div>
      <h1>Connect to Plex</h1>
      <p>Use the recommended method, or choose the manual fallback below.</p>

      <div class="plex-card">
        <h3>Login with Plex</h3>
        <p class="muted">We will open Plex in a new window and link your account.</p>
        <div class="actions left" style="margin-top:12px">
          <button class="btn primary" type="button" id="plex-login" style="display:inline-flex;align-items:center">
            Login with Plex
            <img src="icons/plexlogo.png" alt="" aria-hidden="true" style="width:18px;height:18px;object-fit:contain;margin-left:8px;display:inline-block">
          </button>
        </div>
        <div id="plex-status" class="status"></div>
      </div>

      <div class="divider">
        <button class="toggle" type="button" id="toggle-manual">Alternative &#9662;</button>
      </div>

      <div class="manual-block plex-card" id="manual-block" style="display:none">
        <p class="muted">Paste your Plex token if login does not work.</p>
        <div id="manual-status" class="status"></div>
        <div class="field">
          <input class="input" type="text" id="manual-token" placeholder="X-Plex-Token">
          <button class="btn secondary" type="button" id="save-token">Save token</button>
        </div>
      </div>

      <?php if ($stepError): ?>
        <div class="status error"><?php echo htmlspecialchars($stepError, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form method="POST" class="actions split">
        <button class="btn secondary" type="button" onclick="window.location.href='onboarding.php?step=1'">Back</button>
        <button class="btn primary" type="submit">Continue</button>
      </form>

      <script>
        const loginBtn = document.getElementById('plex-login');
        const plexStatusEl = document.getElementById('plex-status');
        const manualStatusEl = document.getElementById('manual-status');
        const toggleManualBtn = document.getElementById('toggle-manual');
        const manualBlock = document.getElementById('manual-block');
        const tokenInput = document.getElementById('manual-token');
        const saveTokenBtn = document.getElementById('save-token');
        let pollTimer = null;
        let plexAuthWindow = null;

        function setPlexStatus(text, cls){
          if (!plexStatusEl) return;
          plexStatusEl.textContent = text || '';
          plexStatusEl.className = 'status' + (cls ? ' ' + cls : '');
        }

        function setManualStatus(text, cls){
          if (!manualStatusEl) return;
          manualStatusEl.textContent = text || '';
          manualStatusEl.className = 'status' + (cls ? ' ' + cls : '');
        }

        async function startLogin(){
          if (!loginBtn) return;
          loginBtn.disabled = true;
          setPlexStatus('Creating link...');

          try {
            const res = await fetch('plex_pin_create.php', { credentials: 'same-origin' });
            if (!res.ok) throw new Error('PIN create failed');
            let text = await res.text();
            if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
            const data = JSON.parse(text);
            if (!data.auth_url || !data.pin_id) throw new Error('Invalid PIN response');

            plexAuthWindow = window.open(data.auth_url, '_blank');
            setPlexStatus('Waiting for Plex approval...');

            pollTimer = setInterval(async () => {
              try {
                const poll = await fetch('plex_pin_poll.php?pin_id=' + encodeURIComponent(data.pin_id));
                let pollText = await poll.text();
                if (pollText.charCodeAt(0) === 0xFEFF) pollText = pollText.slice(1);
                const pollData = JSON.parse(pollText);
                if (pollData.status === 'linked') {
                  clearInterval(pollTimer);
                  try {
                    if (plexAuthWindow && !plexAuthWindow.closed) {
                      plexAuthWindow.close();
                    }
                  } catch (_) {}
                  setPlexStatus('Plex account linked.', 'success');
                  loginBtn.disabled = false;
                }
              } catch (_) {
                // ignore transient errors
              }
            }, 2000);
          } catch (e) {
            setPlexStatus('Could not start Plex login. Try manual token.', 'error');
            loginBtn.disabled = false;
          }
        }

        saveTokenBtn?.addEventListener('click', async () => {
          const token = (tokenInput?.value || '').trim();
          if (!token) { setManualStatus('Please enter a token.', 'error'); return; }
          saveTokenBtn.disabled = true;
          setManualStatus('Validating token...');
          try {
            const res = await fetch('save_plex_token.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
              body: JSON.stringify({ token })
            });
            let text = await res.text();
            if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
            const data = JSON.parse(text);
            if (!res.ok || data.error) throw new Error(data.error || 'invalid');
            setManualStatus('Token saved.', 'success');
          } catch (_) {
            setManualStatus('Token invalid. Please try again.', 'error');
          } finally {
            saveTokenBtn.disabled = false;
          }
        });

        loginBtn?.addEventListener('click', startLogin);

        toggleManualBtn?.addEventListener('click', () => {
          const open = manualBlock.style.display === 'none';
          manualBlock.style.display = open ? 'block' : 'none';
          toggleManualBtn.textContent = open ? 'Alternative ▲' : 'Alternative ▼';
        });
      </script>
    <?php else: ?>
      <div class="step">Step 3 of 3</div>
      <h1>Select your server</h1>
      <p>Choose the server you want Watchulater to use.</p>

      <div id="server-block" class="server-wrap">
        <div class="field">
          <select class="input" id="server-select"></select>
        </div>
        <div id="server-status" class="status"></div>
      </div>

      <?php if ($stepError): ?>
        <div class="status error"><?php echo htmlspecialchars($stepError, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form method="POST" class="actions split" id="finish-form">
        <button class="btn secondary" type="button" onclick="window.location.href='onboarding.php?step=2'">Back</button>
        <button class="btn primary" type="submit" id="finish-btn" disabled>Finish setup</button>
      </form>

      <script>
        const initialHasToken = <?php echo $hasToken ? 'true' : 'false'; ?>;
        const initialServerId = <?php echo json_encode($savedServerId); ?>;
        const initialServerName = <?php echo json_encode($savedServerName); ?>;
        const initialServerUrl = <?php echo json_encode($savedServerUrl); ?>;
        const initialServerReady = Boolean(initialServerId && initialServerUrl);

        const loginBtn = document.getElementById('plex-login');
        const plexStatusEl = document.getElementById('plex-status');
        const manualStatusEl = document.getElementById('manual-status');
        const toggleManualBtn = document.getElementById('toggle-manual');
        const manualBlock = document.getElementById('manual-block');
        const finishBtn = document.getElementById('finish-btn');
        const tokenInput = document.getElementById('manual-token');
        const saveTokenBtn = document.getElementById('save-token');

        const serverBlock = document.getElementById('server-block');
        const serverSelect = document.getElementById('server-select');
        const saveServerBtn = null;
        const serverStatusEl = document.getElementById('server-status');

        let pollTimer = null;
        let plexAuthWindow = null;

        function setPlexStatus(text, cls){
          plexStatusEl.textContent = text || '';
          plexStatusEl.className = 'status' + (cls ? ' ' + cls : '');
        }

        function setManualStatus(text, cls){
          manualStatusEl.textContent = text || '';
          manualStatusEl.className = 'status' + (cls ? ' ' + cls : '');
        }

        function setServerStatus(text, cls){
          if (!serverStatusEl) return;
          serverStatusEl.textContent = text || '';
          serverStatusEl.className = 'status' + (cls ? ' ' + cls : '');
        }

        async function startLogin(){
          loginBtn.disabled = true;
          setPlexStatus('Creating link...');

          try {
            const res = await fetch('plex_pin_create.php', { credentials: 'same-origin' });
            if (!res.ok) throw new Error('PIN create failed');
            let text = await res.text();
            if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
            const data = JSON.parse(text);
            if (!data.auth_url || !data.pin_id) throw new Error('Invalid PIN response');

            plexAuthWindow = window.open(data.auth_url, '_blank');
            setPlexStatus('Waiting for Plex approval...');

            pollTimer = setInterval(async () => {
              try {
                const poll = await fetch('plex_pin_poll.php?pin_id=' + encodeURIComponent(data.pin_id));
                let pollText = await poll.text();
                if (pollText.charCodeAt(0) === 0xFEFF) pollText = pollText.slice(1);
                const pollData = JSON.parse(pollText);
                if (pollData.status === 'linked') {
                  clearInterval(pollTimer);
                  try {
                    if (plexAuthWindow && !plexAuthWindow.closed) {
                      plexAuthWindow.close();
                    }
                  } catch (_) {}
                  setPlexStatus('Plex account linked.', 'success');
                  if (serverSelect) {
                    await loadServers();
                  }
                  loginBtn.disabled = false;
                }
              } catch (_) {
                // ignore transient errors
              }
            }, 2000);
          } catch (e) {
            setPlexStatus('Could not start Plex login. Try manual token.', 'error');
            loginBtn.disabled = false;
          }
        }

        async function loadServers(){
          try {
            const res = await fetch('plex_servers.php');
            let text = await res.text();
            if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
            const data = JSON.parse(text);
            if (!data.servers || data.servers.length === 0) {
              setServerStatus('No servers found.', 'error');
            if (serverBlock) serverBlock.style.display = 'block';
            return;
          }
          setServerStatus('');
            if (serverSelect) {
              serverSelect.innerHTML = '';
              const placeholder = document.createElement('option');
              placeholder.value = '';
              placeholder.textContent = 'Select a server...';
              placeholder.disabled = true;
              placeholder.selected = true;
              serverSelect.appendChild(placeholder);
              data.servers.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.dataset.uri = s.uri || '';
                opt.dataset.name = s.name || '';
                opt.textContent = s.name + (s.owned ? ' (owned)' : '');
                serverSelect.appendChild(opt);
              });
              if (initialServerId) {
                const match = Array.from(serverSelect.options).find(o => o.value === initialServerId);
                if (match) {
                  match.selected = true;
                  setServerStatus('Saved server: ' + (initialServerName || match.dataset.name || match.textContent), 'success');
                } else if (initialServerReady) {
                  setServerStatus('Saved server: ' + (initialServerName || 'Server'), 'success');
                }
              }
              if (initialServerReady && finishBtn) {
                finishBtn.disabled = false;
              }
              if (serverBlock) serverBlock.style.display = 'block';
            }
        } catch (_) {
          setServerStatus('Could not load servers.', 'error');
          if (serverBlock) serverBlock.style.display = 'block';
        }
        }

        serverSelect?.addEventListener('change', () => {
          if (!finishBtn) return;
          const opt = serverSelect.options[serverSelect.selectedIndex];
          finishBtn.disabled = !(opt && opt.value);
          if (opt && opt.value) {
            setServerStatus('');
          }
        });

        const finishForm = document.getElementById('finish-form');
        finishForm?.addEventListener('submit', async (e) => {
          e.preventDefault();
          const opt = serverSelect?.options[serverSelect.selectedIndex];
          if (!opt || !opt.value) {
            setServerStatus('Please select a server.', 'error');
            return;
          }
          setServerStatus('Saving server...');
          try {
            const res = await fetch('select_plex_server.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
              body: JSON.stringify({ id: opt.value, uri: opt.dataset.uri, name: opt.dataset.name })
            });
            let text = await res.text();
            if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
            const data = JSON.parse(text);
            if (!res.ok || data.error) throw new Error('save failed');
            window.location.href = 'create_roulette.php';
          } catch (_) {
            setServerStatus('Could not save server.', 'error');
          }
        });

        saveTokenBtn?.addEventListener('click', async () => {
          const token = (tokenInput.value || '').trim();
          if (!token) { setManualStatus('Please enter a token.', 'error'); return; }
          saveTokenBtn.disabled = true;
          setManualStatus('Validating token...');
          try {
            const res = await fetch('save_plex_token.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
              body: JSON.stringify({ token })
            });
            let text = await res.text();
            if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
            const data = JSON.parse(text);
            if (!res.ok || data.error) throw new Error(data.error || 'invalid');
            setManualStatus('Token saved.', 'success');
            await loadServers();
          } catch (_) {
            setManualStatus('Token invalid. Please try again.', 'error');
          } finally {
            saveTokenBtn.disabled = false;
          }
        });

        loginBtn?.addEventListener('click', startLogin);

        toggleManualBtn?.addEventListener('click', () => {
          const open = manualBlock.style.display === 'none';
          manualBlock.style.display = open ? 'block' : 'none';
          toggleManualBtn.textContent = open ? 'Alternative ▲' : 'Alternative ▼';
        });

        if (initialHasToken && serverSelect) {
          loadServers();
        }

        if (initialServerReady && serverBlock && finishBtn) {
          finishBtn.disabled = false;
          serverBlock.style.display = 'block';
          setServerStatus('Saved server: ' + (initialServerName || 'Server'), 'success');
        }
      </script>
    <?php endif; ?>
    </div>
  </div>
</body>
</html>
