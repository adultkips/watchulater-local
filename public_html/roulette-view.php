<?php
require __DIR__ . '/bootstrap.php';
require_onboarded($pdo);

$id = trim($_GET['id'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Roulette</title>
  <link rel="stylesheet" href="nav.css">
  <link rel="stylesheet" href="roulettes.css">
  <link rel="stylesheet" href="roulette_view.css">
  <script src="roulette_view.js" defer></script>
</head>
<body class="with-side-nav">
  <?php include 'nav.php'; ?>
  <main class="roulette-wrap is-loading">
    <div class="roulette-loading" id="roulette-loading">
      <div class="loading-spinner" aria-hidden="true"></div>
      <div class="loading-text">Loading Roulette...</div>
    </div>
    <section class="card-stack">
      <div class="roulette-card is-loading" id="roulette-card">
        <a class="poster is-loading" id="poster-link" target="_blank" rel="noopener">
          <img id="poster" src="img/placeholder-poster.png" alt="" />
          <div class="poster-dim" aria-hidden="true"></div>
          <div class="poster-spinner" aria-hidden="true"></div>
          <span class="poster-overlay">
            Open Plex
            <img src="icons/plexlogo.png" alt="" aria-hidden="true">
          </span>
        </a>
        <div class="card-meta">
          <h2 id="title">Loading roulette...</h2>
          <div class="sub" id="year"></div>
          <div class="peek" id="peek-toggle">
            <div id="peek-text"></div>
            <div class="fade"></div>
          </div>
          <section class="info-panel" id="info-panel" hidden>
            <div class="info-grid">
              <div><span class="info-label">Director</span><span id="director"></span></div>
              <div><span class="info-label">Writer</span><span id="writer"></span></div>
              <div><span class="info-label">Genres</span><span id="genres"></span></div>
              <div><span class="info-label">Cast</span><span id="actors"></span></div>
            </div>
          </section>
          <button type="button" class="peek-toggle-btn peek-btn-hidden" id="peek-btn" aria-label="Toggle details">
            <span class="peek-hit">Toggle details</span>
          </button>
        </div>
      </div>
    </section>

    <section class="actions is-loading">
      <button class="btn secondary" id="btn-watchlist">
        <span class="icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M6 4h12a2 2 0 0 1 2 2v14l-8-4-8 4V6a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        </span>
        <span>Watchlist</span>
      </button>
      <button class="btn secondary" id="btn-watched">👁 Watched</button>
      <button class="btn secondary" id="btn-skip">↻ Skip</button>
      <button class="btn secondary" id="btn-dismiss">✕ Dismiss</button>
    </section>


    <div class="status" id="roulette-status"></div>
  </main>

  <div class="roulette-search is-loading" id="roulette-search">
    <div class="search-results" id="search-results" hidden></div>
    <div class="search-row">
      <button id="search-toggle" type="button" aria-label="Search">
        <span class="icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m20 20-3.5-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </span>
      </button>
      <input id="search-input" class="search-input" type="text" placeholder="Search title" autocomplete="off" spellcheck="false" autocapitalize="off" />
    </div>
  </div>

  <div class="modal hidden" id="watched-modal">
    <div class="modal-content">
      <h3>Rate what you watched</h3>
      <div class="stars" id="rating-stars"></div>
      <button type="button" class="reco" id="recommended-toggle">Recommend?</button>
      <div class="modal-actions">
        <button class="btn secondary" id="cancel-watched">Cancel</button>
        <button class="btn primary" id="save-watched">Save</button>
      </div>
    </div>
  </div>
</body>
</html>
