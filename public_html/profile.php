<?php
require __DIR__ . '/bootstrap.php';
require_onboarded($pdo);

$stmt = $pdo->query("SELECT plex_server_id, plex_server_name, plex_server_url, plex_server_token FROM settings WHERE id = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$serverId = $row['plex_server_id'] ?? '';
$serverName = $row['plex_server_name'] ?? '';
$serverUrl = $row['plex_server_url'] ?? '';
$serverToken = $row['plex_server_token'] ?? '';

$rouletteCount = (int)$pdo->query("SELECT COUNT(*) FROM roulettes")->fetchColumn();
$battleCount = (int)$pdo->query("SELECT COUNT(*) FROM battle_presets")->fetchColumn();
$watchlistCount = (int)$pdo->query("SELECT COUNT(*) FROM filmvalg WHERE status = 'watchlist'")->fetchColumn();
$watchedCount = (int)$pdo->query("SELECT COUNT(*) FROM filmvalg WHERE status = 'watched'")->fetchColumn();
$dismissedCount = (int)$pdo->query("SELECT COUNT(*) FROM filmvalg WHERE status = 'dismissed'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Profile - Watchulater</title>
  <link rel="stylesheet" href="nav.css">
  <style>
    body{font-family: Arial, sans-serif; background:#f5f5f5; margin:0;}
    .wrap{max-width:980px; margin:20px auto 0; background:#fff; padding:36px; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.08)}
    h1{margin:0 0 12px 0; text-align:center}
    p{color:#444}
    .card{border:1px solid #e5e7eb; border-radius:12px; padding:20px; margin-top:18px; background:#fafafa}
    .status{margin-top:0; font-size:13px; color:#333; text-align:right}
    .status.success{color:#1b7f3c}
    .status.error{color:#b42318}
      .btn{padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:600; display:inline-flex; align-items:center}
      .btn-icon{width:18px; height:18px; object-fit:contain; margin-left:8px; display:inline-block}
    .secondary{background:#e5e7eb}
    .primary{background:#2d7ef7; color:#fff}
    .hint{font-size:12px; color:#666; margin-top:6px}
    .list{margin:8px 0 0 0; padding:0; list-style:none; display:flex; flex-wrap:wrap; gap:8px}
    .list li{background:#fff; border:1px solid #e5e7eb; border-radius:999px; padding:8px 12px; font-size:14px; color:#333}
    .type-block{margin-top:18px}
    .type-label{font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#888}
    .divider{height:1px; background:#e5e7eb; margin-top:16px}
    .field{display:grid; grid-template-columns:110px 1fr; gap:12px; align-items:center; margin-top:12px}
      .actions{display:flex; gap:12px; margin-top:16px; flex-wrap:wrap; justify-content:flex-end}
    .card-header{display:flex; align-items:center; justify-content:space-between; gap:12px}
    h3{margin:0}
      .card-footer{display:flex; align-items:center; justify-content:flex-start; gap:10px; margin-top:8px; line-height:1}
    .status-label{font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#888}
    .icon-btn{background:none; border:none; padding:0; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; width:16px; height:16px; color:#9ca3af}
    .icon-btn svg{width:16px; height:16px}
    .icon-btn:hover{color:#6b7280}
    .link-btn{background:none; border:none; padding:0; color:#111; text-decoration:underline; font-weight:600; cursor:pointer}
    .input{padding:10px 12px; border-radius:8px; border:1px solid #ddd; width:100%; box-sizing:border-box; -webkit-appearance:none; appearance:none; background:#fff}
    select.input{padding-right:36px; background-image:linear-gradient(45deg, transparent 50%, #666 50%), linear-gradient(135deg, #666 50%, transparent 50%); background-position:calc(100% - 18px) 16px, calc(100% - 12px) 16px; background-size:6px 6px, 6px 6px; background-repeat:no-repeat}
    .label{font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#888}
    .dashboard{margin:10px 0 22px}
    .dashboard-grid{display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:12px}
    .dash-card{border:1px solid #e5e7eb; border-radius:12px; background:#fafafa; padding:14px; min-height:86px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; text-align:center; width:100%; box-sizing:border-box; aspect-ratio:1 / 1}
    .dash-link{display:flex; text-decoration:none; color:inherit}
    .dash-link:hover .dash-card{box-shadow:0 6px 14px rgba(0,0,0,.08); transform: translateY(-1px)}
    .dash-label{font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#888}
    .dash-value{font-size:35px; font-weight:700; color:#111}
    .dash-sub{font-size:12px; color:#666}
    .progress{height:20px; width:100%; background:#e5e7eb; border-radius:999px; overflow:hidden}
    .progress > span{display:block; height:100%; width:0; background:#2d7ef7}
  </style>
</head>
<body class="with-side-nav">
  <?php include 'nav.php'; ?>
  <div class="wrap">
    <h1>Profile</h1>

    <section class="dashboard">
      <div class="dashboard-grid">
        <div class="dash-card">
          <div class="dash-label">Progress</div>
          <div class="progress"><span id="dash-progress"></span></div>
          <div class="dash-sub" id="dash-progress-text">0 / 0 reacted</div>
        </div>
        <div class="dash-card">
          <div class="dash-label">Movies</div>
          <div class="dash-value" id="dash-movies">0</div>
        </div>
        <div class="dash-card">
          <div class="dash-label">Shows</div>
          <div class="dash-value" id="dash-shows">0</div>
        </div>
        <a class="dash-link" href="roulettes.php">
          <div class="dash-card">
            <div class="dash-label">Roulettes</div>
            <div class="dash-value"><?php echo $rouletteCount; ?></div>
          </div>
        </a>
        <a class="dash-link" href="watchlist.php">
          <div class="dash-card">
            <div class="dash-label">Watchlist</div>
            <div class="dash-value"><?php echo $watchlistCount; ?></div>
          </div>
        </a>
        <a class="dash-link" href="watched.php">
          <div class="dash-card">
            <div class="dash-label">Watched</div>
            <div class="dash-value"><?php echo $watchedCount; ?></div>
          </div>
        </a>
        <a class="dash-link" href="battles.php">
          <div class="dash-card">
            <div class="dash-label">Battles</div>
            <div class="dash-value"><?php echo $battleCount; ?></div>
          </div>
        </a>
        <a class="dash-link" href="dismissed.php">
          <div class="dash-card">
            <div class="dash-label">Dismissed</div>
            <div class="dash-value"><?php echo $dismissedCount; ?></div>
          </div>
        </a>
      </div>
    </section>

    <div class="card">
      <h3>Server</h3>

      <div class="field">
        <div class="label">IP & Port</div>
        <input class="input" type="text" id="server-url-input" placeholder="server:32400" value="<?php
          $displayUrl = $serverUrl;
          if ($displayUrl) {
            $parts = parse_url($displayUrl);
            $host = $parts['host'] ?? '';
            $port = $parts['port'] ?? '';
            if ($host) {
              $displayUrl = $host . ($port ? ':' . $port : '');
            }
          }
          echo htmlspecialchars($displayUrl, ENT_QUOTES, 'UTF-8');
        ?>">
      </div>

      <div class="field">
        <div class="label">Token</div>
        <input class="input" type="text" id="server-token-input" placeholder="Server token" value="<?php echo htmlspecialchars($serverToken, ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="field">
        <div class="label">Name</div>
        <select class="input" id="server-select"></select>
      </div>

      <div class="actions">
        <button class="btn secondary" type="button" id="reset-all">Reset</button>
        <button class="btn secondary" type="button" id="reconnect-plex">
          Reconnect with Plex
          <img src="icons/plexlogo.png" alt="" aria-hidden="true" class="btn-icon">
        </button>
        <button class="btn primary" type="button" id="save-all">Save</button>
      </div>

      <div id="server-status" class="status"></div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3>Connection</h3>
      </div>
      <div id="sections"></div>
      <div class="divider"></div>
      <div class="card-footer">
        <span class="status-label">Status:</span>
        <div id="plex-status" class="status">Checking connection...</div>
        <button class="icon-btn" type="button" id="refresh" aria-label="Refresh status">
          <svg viewBox="0 0 24 24"><path d="M4 4v6h6M20 20v-6h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M20 10A8 8 0 0 0 6 6M4 14a8 8 0 0 0 14 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
      </div>
    </div>
  </div>

  <script>
    const initialServerId = <?php echo json_encode($serverId); ?>;
    const serverStatusEl = document.getElementById('server-status');
    const serverSelect = document.getElementById('server-select');
    const saveAllBtn = document.getElementById('save-all');
    const reconnectBtn = document.getElementById('reconnect-plex');
    const resetBtn = document.getElementById('reset-all');
    const serverUrlInput = document.getElementById('server-url-input');
    const serverTokenInput = document.getElementById('server-token-input');

    const statusEl = document.getElementById('plex-status');
    const sectionsEl = document.getElementById('sections');
    const refreshBtn = document.getElementById('refresh');
    const dashMovies = document.getElementById('dash-movies');
    const dashShows = document.getElementById('dash-shows');
    const dashProgress = document.getElementById('dash-progress');
    const dashProgressText = document.getElementById('dash-progress-text');
    let pollTimer = null;
    let plexAuthWindow = null;
    let reconnectPending = false;

    function setStatus(text, cls){
      statusEl.textContent = text || '';
      statusEl.className = 'status' + (cls ? ' ' + cls : '');
    }

    function setServerStatus(text, cls){
      serverStatusEl.textContent = text || '';
      serverStatusEl.className = 'status' + (cls ? ' ' + cls : '');
    }
    function markNeedsSave(on){
      // Keep Save button styling consistent (always primary)
    }

    function setSections(items){
      sectionsEl.innerHTML = '';
      if (!items || items.length === 0) return;

      const groups = {};
      items.forEach(s => {
        const type = (s.type || 'other').toLowerCase();
        if (!groups[type]) groups[type] = [];
        groups[type].push(s);
      });

      const order = ['movie', 'show', 'other'];
      const types = Object.keys(groups).sort((a, b) => {
        const ia = order.indexOf(a);
        const ib = order.indexOf(b);
        if (ia === -1 && ib === -1) return a.localeCompare(b);
        if (ia === -1) return 1;
        if (ib === -1) return -1;
        return ia - ib;
      });

      types.forEach(type => {
        const block = document.createElement('div');
        block.className = 'type-block';

        const label = document.createElement('div');
        label.className = 'type-label';
        label.textContent = type === 'show' ? 'Shows' : (type === 'movie' ? 'Movies' : 'Other');
        block.appendChild(label);

        const ul = document.createElement('ul');
        ul.className = 'list';
        groups[type].forEach(s => {
          const li = document.createElement('li');
          li.textContent = (s.title || 'Untitled');
          ul.appendChild(li);
        });
        block.appendChild(ul);
        sectionsEl.appendChild(block);
      });
    }

    async function checkConnection(){
      setStatus('Checking connection...');
      setSections([]);
      try {
        const res = await fetch('plex_sections.php', { cache: 'no-store' });
        let text = await res.text();
        if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
        const data = JSON.parse(text);
        if (!res.ok || data.error) {
          if (data.error === 'missing_config') {
            throw new Error('Select a Plex server and click Save before refreshing.');
          }
          const attempted = data.attempted ? ' (' + data.attempted + ')' : '';
          throw new Error((data.detail || data.error || 'error') + attempted);
        }
        setStatus('Connected', 'success');
        const sections = data.sections || [];
        setSections(sections);
        try {
          const snapshot = {
            url: serverUrlInput.value || '',
            token: serverTokenInput.value || '',
            status: 'connected',
            sections,
            ts: Date.now()
          };
          localStorage.setItem('plex_status_snapshot', JSON.stringify(snapshot));
          localStorage.removeItem('plex_force_reconnect');
        } catch (_) {
          // ignore storage errors
        }
      } catch (e) {
        const msg = (e && e.message) ? e.message : 'Plex server unreachable.';
        setStatus('Not connected ' + msg, 'error');
      }
    }

    async function loadServers(){
      try {
        const res = await fetch('plex_servers.php', { cache: 'no-store' });
        let text = await res.text();
        if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
        const data = JSON.parse(text);
        if (!data.servers || data.servers.length === 0) return;
        serverSelect.innerHTML = '';
        if (reconnectPending) {
          const placeholder = document.createElement('option');
          placeholder.value = '';
          placeholder.textContent = 'Select a server...';
          placeholder.selected = true;
          placeholder.disabled = true;
          serverSelect.appendChild(placeholder);
        }
        data.servers.forEach(s => {
          const opt = document.createElement('option');
          opt.value = s.id;
          opt.dataset.name = s.name || '';
          opt.dataset.uri = s.uri || '';
          opt.textContent = s.name + (s.owned ? ' (owned)' : '');
          serverSelect.appendChild(opt);
        });
        if (!reconnectPending && initialServerId) {
          const match = Array.from(serverSelect.options).find(o => o.value === initialServerId);
          if (match) match.selected = true;
        }
      } catch (_) {
        // ignore
      }
    }

    async function loadServerInfo(){
      try {
        const res = await fetch('plex_server_info.php?ts=' + Date.now(), { cache: 'no-store' });
        let text = await res.text();
        if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
        const data = JSON.parse(text);
        if (data.server_hostport) {
          serverUrlInput.value = data.server_hostport;
        }
        if (data.server_token) {
          serverTokenInput.value = data.server_token;
        }
        return true;
      } catch (_) {
        // ignore
        return false;
      }
    }

    saveAllBtn.addEventListener('click', async () => {
      const opt = serverSelect.options[serverSelect.selectedIndex];
      if (reconnectPending && (!opt || !opt.value)) {
        setServerStatus('Please select a server before saving.', 'error');
        alert('Please select a server before saving.');
        return;
      }
      let url = (serverUrlInput.value || '').trim();
      const token = (serverTokenInput.value || '').trim();
      if (url && !/^https?:\/\//i.test(url)) {
        url = 'http://' + url;
      }
      setServerStatus('Saving...');
      try {
        if (opt && opt.value) {
          const res = await fetch('select_plex_server.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
            body: JSON.stringify({ id: opt.value, name: opt.dataset.name || opt.textContent, uri: opt.dataset.uri || '' })
          });
          let text = await res.text();
          if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
          const data = JSON.parse(text);
          if (!res.ok || data.error) throw new Error('save failed');
        }
        if (url || token) {
          const res2 = await fetch('save_plex_connection.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
            body: JSON.stringify({ server_url: url || undefined, server_token: token || undefined })
          });
          let text2 = await res2.text();
          if (text2.charCodeAt(0) === 0xFEFF) text2 = text2.slice(1);
          const data2 = JSON.parse(text2);
          if (!res2.ok || data2.error) throw new Error('save failed');
        }
        setServerStatus('Saved.', 'success');
        reconnectPending = false;
        markNeedsSave(false);
        try { localStorage.setItem('plex_force_reconnect', '1'); } catch (_) {}
        checkConnection();
      } catch (_) {
        setServerStatus('Could not save.', 'error');
      }
    });

    reconnectBtn.addEventListener('click', async () => {
      reconnectBtn.disabled = true;
      setServerStatus('Starting Plex login...', 'success');
      try {
        const resetRes = await fetch('reconnect_plex.php', {
          method: 'POST',
          headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' }
        });
        if (!resetRes.ok) throw new Error('reset failed');

        const res = await fetch('plex_pin_create.php', { credentials: 'same-origin' });
        if (!res.ok) {
          let errText = '';
          try { errText = await res.text(); } catch (_) {}
          throw new Error(errText || 'PIN create failed');
        }
        let text = await res.text();
        if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
        const data = JSON.parse(text);
        if (!data.auth_url || !data.pin_id) throw new Error('Invalid PIN response');

        plexAuthWindow = window.open(data.auth_url, '_blank');
        setServerStatus('Waiting for Plex approval...', 'success');

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
              setServerStatus('Plex account linked. Select server and click Save.', 'success');
              reconnectPending = true;
              markNeedsSave(true);
              await loadServers();
              await loadServerInfo();
              saveAllBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
              saveAllBtn.focus();
              reconnectBtn.disabled = false;
            }
          } catch (_) {
            // ignore transient errors
          }
        }, 2000);
      } catch (e) {
        const msg = (e && e.message) ? e.message : 'Reconnect failed.';
        if (msg.includes('rate limit') || msg.includes('429')) {
          setServerStatus('Plex rate limit hit. Please wait ~60 seconds and try again.', 'error');
          setTimeout(() => { reconnectBtn.disabled = false; }, 60000);
        } else {
          setServerStatus('Reconnect failed.', 'error');
          reconnectBtn.disabled = false;
        }
      }
    });

    resetBtn.addEventListener('click', async () => {
      if (!confirm('Reset all local data? This cannot be undone.')) return;
      setServerStatus('Resetting...');
      try {
        const res = await fetch('reset_settings.php', {
          method: 'POST',
          cache: 'no-store',
          headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' }
        });
        if (!res.ok) throw new Error('reset failed');
        window.location.href = 'onboarding.php';
      } catch (_) {
        setServerStatus('Reset failed.', 'error');
      }
    });

    refreshBtn.addEventListener('click', () => {
      try { localStorage.setItem('plex_force_reconnect', '1'); } catch (_) {}
      checkConnection();
    });
    loadServers();

    async function loadDashboard(){
      try {
        const res = await fetch('plex_library_counts.php', { cache: 'no-store' });
        let text = await res.text();
        if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
        const data = JSON.parse(text);
        if (!res.ok || data.error) throw new Error(data.error || 'error');
        const total = Number(data.total || 0);
        const movies = Number(data.movies || 0);
        const shows = Number(data.shows || 0);
        const reacted = <?php echo (int)($watchlistCount + $watchedCount + $dismissedCount); ?>;
        dashMovies.textContent = movies.toString();
        dashShows.textContent = shows.toString();
        dashProgressText.textContent = `${reacted} / ${total} reacted`;
        const pct = total > 0 ? Math.min(100, Math.round((reacted / total) * 100)) : 0;
        dashProgress.style.width = pct + '%';
      } catch (_) {
        // keep defaults
      }
    }

    (async () => {
      let usedCache = false;
      try {
        const force = localStorage.getItem('plex_force_reconnect');
        const cached = localStorage.getItem('plex_status_snapshot');
        if (!force && cached) {
          const snap = JSON.parse(cached);
          const ageMs = snap && snap.ts ? (Date.now() - snap.ts) : Infinity;
          if (snap && snap.status === 'connected' && ageMs < 5 * 60 * 1000) {
            setStatus('Connected', 'success');
            setSections(snap.sections || []);
            usedCache = true;
          }
        }
      } catch (_) {
        // ignore
      }

      await loadServerInfo();

      if (usedCache) {
        try {
          const cached = localStorage.getItem('plex_status_snapshot');
          if (cached) {
            const snap = JSON.parse(cached);
            const urlMatch = snap.url === (serverUrlInput.value || '');
            const tokenMatch = snap.token === (serverTokenInput.value || '');
            if (!urlMatch || !tokenMatch) {
              try { localStorage.setItem('plex_force_reconnect', '1'); } catch (_) {}
              checkConnection();
            }
          }
        } catch (_) {
          // ignore
        }
      } else {
        checkConnection();
      }
    })();

    loadDashboard();

    serverSelect.addEventListener('change', () => {
      if (reconnectPending && serverSelect.value) {
        saveAllBtn.disabled = false;
      }
    });
  </script>
</body>
</html>
