<?php
require __DIR__ . '/bootstrap.php';
require_onboarded($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Watched</title>
  <link rel="stylesheet" href="nav.css">
  <link rel="stylesheet" href="lister.css">
  <script src="film_list.js" defer></script>
</head>
<body data-status="watched" class="with-side-nav">
  <?php include 'nav.php'; ?>
  <main class="main-content" data-server-id="<?php echo htmlspecialchars($pdo->query("SELECT plex_server_id FROM settings WHERE id = 1")->fetchColumn() ?: '', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="top-bar">
      <h1 class="site-header">Watched</h1>
      <div class="center-bar">
        <button id="search-toggle" type="button" aria-label="Search" data-tooltip="Search">
          <span class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m20 20-3.5-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          </span>
        </button>
        <input id="search-input" class="search-input" type="text" placeholder="Search title" />
        <select id="sort-select">
          <option value="rating" selected>Stars</option>
          <option value="added">Added</option>
          <option value="updated">Updated</option>
          <option value="title">Title</option>
          <option value="year">Year</option>
        </select>
        <button id="sort-dir" type="button" aria-label="Toggle sort direction" data-tooltip="Sort direction" data-dir="desc">↓</button>
        <button id="filter-toggle" type="button" aria-label="Filter" data-tooltip="Filter">
          <span class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M10 18h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          </span>
        </button>
        <button id="reset-filters" type="button" aria-label="Reset list" data-tooltip="Reset list">
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
        <div class="filter-block filter-type filter-right">
          <div class="filter-title">Section</div>
          <div class="filter-section" id="section-filter"></div>
        </div>
        <div class="filter-block filter-type filter-right">
          <div class="filter-title">Watched</div>
          <div class="filter-section">
            <label class="filter-chip"><input type="checkbox" id="rec-only"><span>Recommended</span></label>
          </div>
          <div class="filter-section" id="rating-filter">
            <label class="filter-chip"><input type="checkbox" value="0.5"><span>0.5★</span></label>
            <label class="filter-chip"><input type="checkbox" value="1"><span>1★</span></label>
            <label class="filter-chip"><input type="checkbox" value="1.5"><span>1.5★</span></label>
            <label class="filter-chip"><input type="checkbox" value="2"><span>2★</span></label>
            <label class="filter-chip"><input type="checkbox" value="2.5"><span>2.5★</span></label>
            <label class="filter-chip"><input type="checkbox" value="3"><span>3★</span></label>
            <label class="filter-chip"><input type="checkbox" value="3.5"><span>3.5★</span></label>
            <label class="filter-chip"><input type="checkbox" value="4"><span>4★</span></label>
            <label class="filter-chip"><input type="checkbox" value="4.5"><span>4.5★</span></label>
            <label class="filter-chip"><input type="checkbox" value="5"><span>5★</span></label>
          </div>
        </div>
      </div>
    </div>

    <div id="film-grid" class="film-grid"></div>
    <div id="empty-msg" class="tom-liste-besked" style="display:none">
      No items
      <div><a href="roulettes.php">Go to roulettes</a></div>
    </div>
  </main>
  <button class="watch-now-fab" id="watch-now" type="button">
    <span class="icon" aria-hidden="true">
      <svg viewBox="0 0 24 24"><rect x="6.5" y="4" width="11" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="2"/><rect x="4.5" y="6" width="11" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="2" opacity="0.75" transform="rotate(-8 10 14)"/><rect x="8.5" y="6" width="11" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="2" opacity="0.75" transform="rotate(8 14 14)"/></svg>
    </span>
    Watch Now
  </button>
</body>
</html>
