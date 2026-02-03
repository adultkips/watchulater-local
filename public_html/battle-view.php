<?php
require __DIR__ . '/bootstrap.php';
require_onboarded($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Battle</title>
  <link rel="stylesheet" href="nav.css">
  <link rel="stylesheet" href="battle_view.css">
  <script src="battle_view.js" defer></script>
</head>
<body class="with-side-nav">
  <?php include 'nav.php'; ?>
  <main class="main-content">
    <h1 id="battle-title" class="battle-title">Battle</h1>
    <div class="battle-container" id="battle"></div>
    <hr class="battle-divider" />
    <div class="ranklist" id="ranklist"></div>
  </main>
</body>
</html>
