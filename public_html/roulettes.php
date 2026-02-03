<?php
require __DIR__ . '/bootstrap.php';
require_onboarded($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Roulettes</title>
  <link rel="stylesheet" href="nav.css">
  <link rel="stylesheet" href="roulettes.css">
  <script src="roulettes.js" defer></script>
</head>
<body class="with-side-nav">
  <?php include 'nav.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <h1 class="site-header">Roulettes</h1>
      <div class="center-bar">
        <button id="search-toggle" type="button" aria-label="Search" data-tooltip="Search">
          <span class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m20 20-3.5-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          </span>
        </button>
        <input id="search-input" class="search-input" type="text" placeholder="Search title" />
        <select id="sort-select">
          <option value="added" selected>Added</option>
          <option value="title">Title</option>
        </select>
        <button id="sort-dir" type="button" aria-label="Toggle sort direction" data-tooltip="Sort direction" data-dir="desc">↓</button>
        <button id="filter-toggle" type="button" aria-label="Filter" data-tooltip="Filter">
          <span class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M10 18h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          </span>
        </button>
        <button id="reset-roulettes" type="button" aria-label="Delete all roulettes" data-tooltip="Delete all">
          <span class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2m-9 3 1 11h8l1-11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
        </button>
      </div>
      <div id="filter-panel" class="filter-panel">
        <div class="filter-block filter-left">
          <div class="filter-title">Genres</div>
          <div class="filter-section" id="genre-filter"></div>
        </div>
        <div class="filter-block filter-right">
          <div class="filter-title">Sections</div>
          <div class="filter-section" id="section-filter"></div>
        </div>
      </div>
    </div>

    <section id="roulettes-section" aria-labelledby="roulettes-heading">
      <div class="film-grid" id="roulettes-grid" aria-live="polite">
      </div>

      <div class="empty" id="roulettes-empty" hidden>
        <button type="button" class="empty-cta" id="empty-create">+ Create your first roulette</button>
      </div>
    </section>
  </main>
  <button class="fab" id="add-roulette" type="button" aria-label="Create new roulette">
    <span class="fab-plus">+</span>
    <span class="fab-label">New Roulette</span>
  </button>
</body>
</html>
