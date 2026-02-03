<?php
require __DIR__ . '/bootstrap.php';
require_onboarded($pdo);
$rouletteCount = (int)$pdo->query("SELECT COUNT(*) FROM roulettes")->fetchColumn();
$isFirstRoulette = $rouletteCount === 0 && empty($_GET['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Create roulette</title>
  <link rel="stylesheet" href="nav.css">
  <link rel="stylesheet" href="roulettes.css">
  <script src="create_roulette.js" defer></script>
</head>
<body class="with-side-nav create-roulette">
  <?php include 'nav.php'; ?>
  <main class="container">
    <form id="cr-form" autocomplete="off">
      <div class="stepper" id="cr-stepper">Step 1 of 2</div>
      <h1 id="page-title"><?php echo $isFirstRoulette ? 'Create your first roulette' : 'Create roulette'; ?></h1>

      <section class="step-panel" data-step="1">
        <div class="field field-cover">
          <label for="cr-name">Name</label>
          <input id="cr-name" name="name" type="text" maxlength="100" required placeholder="My roulette" />
        </div>

        <div class="cover-gallery">
          <div class="cover-title">Cover icons</div>
          <div class="cover-grid">
            <button type="button" class="cover-option" data-cover="icons/genres/action.png" aria-label="Action"><img src="icons/genres/action.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/adventure.png" aria-label="Adventure"><img src="icons/genres/adventure.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/animation.png" aria-label="Animation"><img src="icons/genres/animation.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/biography.png" aria-label="Biography"><img src="icons/genres/biography.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/comedy.png" aria-label="Comedy"><img src="icons/genres/comedy.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/crime.png" aria-label="Crime"><img src="icons/genres/crime.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/drama.png" aria-label="Drama"><img src="icons/genres/drama.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/family.png" aria-label="Family"><img src="icons/genres/family.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/fantasy.png" aria-label="Fantasy"><img src="icons/genres/fantasy.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/film.png" aria-label="Film"><img src="icons/genres/film.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/history.png" aria-label="History"><img src="icons/genres/history.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/horror.png" aria-label="Horror"><img src="icons/genres/horror.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/kids.png" aria-label="Kids"><img src="icons/genres/kids.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/music.png" aria-label="Music"><img src="icons/genres/music.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/mystery.png" aria-label="Mystery"><img src="icons/genres/mystery.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/romance.png" aria-label="Romance"><img src="icons/genres/romance.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/scifi.png" aria-label="Science Fiction"><img src="icons/genres/scifi.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/series.png" aria-label="Series"><img src="icons/genres/series.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/sport.png" aria-label="Sport"><img src="icons/genres/sport.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/superheroes.png" aria-label="Superheroes"><img src="icons/genres/superheroes.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/thriller.png" aria-label="Thriller"><img src="icons/genres/thriller.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/war.png" aria-label="War"><img src="icons/genres/war.png" alt=""></button>
            <button type="button" class="cover-option" data-cover="icons/genres/western.png" aria-label="Western"><img src="icons/genres/western.png" alt=""></button>
          </div>
        </div>

        <div class="field">
          <label for="cr-cover">Or</label>
          <div class="field-inline">
            <input id="cr-cover" name="cover" type="text" placeholder="https://..." />
            <button type="button" class="btn small" id="cover-upload">Upload</button>
          </div>
          <input id="cover-file" type="file" accept="image/*" hidden />
        </div>

        <div class="section-divider"><span>SECTIONS</span></div>

        <div class="source-grid">
        <section class="source-block">
          <div class="source-header">
            <h3>Movies</h3>
            <div class="source-actions">
              <button type="button" class="btn small text-reset" id="movies-reset">Reset</button>
              <button type="button" class="btn small" id="movies-select-all">Select all</button>
            </div>
          </div>
          <div id="movies-sections" class="chips" aria-live="polite"></div>
        </section>

        <div class="source-divider">or</div>

        <section class="source-block">
          <div class="source-header">
            <h3>Shows</h3>
            <div class="source-actions">
              <button type="button" class="btn small text-reset" id="shows-reset">Reset</button>
              <button type="button" class="btn small" id="shows-select-all">Select all</button>
            </div>
          </div>
          <div id="shows-sections" class="chips" aria-live="polite"></div>
        </section>
        </div>
      </section>

      <section class="step-panel" data-step="2">
        <section class="source-block" id="genre-block" style="display:none">
          <div class="source-header">
            <h3>Genres</h3>
            <div class="source-actions">
              <button type="button" class="btn small text-reset" id="genres-reset">Reset</button>
              <button type="button" class="btn small" id="genres-select-all">Select all</button>
            </div>
          </div>
          <span class="muted" id="genre-hint">Optional: Unselect genres</span>
          <div id="genre-status" class="status"></div>
          <div id="genre-chips" class="chips" aria-live="polite"></div>
        </section>
      </section>

      <div class="actions">
        <button type="button" class="btn secondary left" id="step-back">Back</button>
        <a class="btn-cancel" href="roulettes.php">Cancel</a>
        <button type="button" class="btn primary" id="step-next">Next</button>
        <button type="submit" class="btn primary" id="step-save">Save</button>
      </div>
      <div id="cr-message" class="status"></div>
    </form>
  </main>
</body>
</html>
