<?php
require __DIR__ . '/bootstrap.php';
require_onboarded($pdo);
$serverId = $pdo->query("SELECT plex_server_id FROM settings WHERE id = 1")->fetchColumn() ?: '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Watch Now</title>
  <link rel="stylesheet" href="nav.css">
  <link rel="stylesheet" href="watch_now.css">
  <script src="watch_now.js" defer></script>
</head>
<body class="with-side-nav">
  <?php include 'nav.php'; ?>
  <main class="watch-now-wrap" data-server-id="<?php echo htmlspecialchars($serverId, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="watch-card" id="watch-card">
      <a class="watch-poster" id="watch-link" href="#" target="_blank" rel="noopener">
        <img id="watch-poster" src="img/placeholder-poster.png" alt="">
        <span class="watch-overlay">
          Open Plex
          <img src="icons/plexlogo.png" alt="" aria-hidden="true">
        </span>
      </a>
      <div class="watch-meta">
        <h2 id="watch-title">Loading...</h2>
        <div class="watch-sub" id="watch-year"></div>
      </div>
    </div>
    <div class="watch-actions">
      <button class="btn skip" id="watch-skip">Skip</button>
    </div>
    <div class="watch-empty" id="watch-empty" hidden>
      <p>Nothing left.</p>
      <button class="btn primary" id="watch-retry">Try again</button>
    </div>
  </main>
</body>
</html>
