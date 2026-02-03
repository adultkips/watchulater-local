<?php
require_once __DIR__ . '/csrf.php';
$csrfToken = csrf_token();
$current = basename($_SERVER['PHP_SELF'] ?? '');
$activePage = ($current === 'roulette-view.php' || $current === 'create_roulette.php') ? 'roulettes.php' : $current;
$activePage = ($current === 'battle-view.php' || $current === 'create_battle.php') ? 'battles.php' : $activePage;
$activePage = ($current === 'watch_now.php') ? ($_GET['from'] ?? 'watchlist.php') : $activePage;
$items = [
  ['href' => 'profile.php', 'label' => 'Profile', 'icon' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M4 20c2-4 6-6 8-6s6 2 8 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'],
  ['href' => 'roulettes.php', 'label' => 'Roulettes', 'icon' => '<svg viewBox="0 0 24 24"><rect x="6.5" y="4" width="11" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="2"/><rect x="4.5" y="6" width="11" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="2" opacity="0.75" transform="rotate(-8 10 14)"/><rect x="8.5" y="6" width="11" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="2" opacity="0.75" transform="rotate(8 14 14)"/></svg>'],
  ['href' => 'watchlist.php', 'label' => 'Watchlist', 'icon' => '<svg viewBox="0 0 24 24"><path d="M6 4h12a2 2 0 0 1 2 2v14l-8-4-8 4V6a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="2"/></svg>'],
  ['href' => 'watched.php', 'label' => 'Watched', 'icon' => '<svg viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/></svg>'],
  ['href' => 'battles.php', 'label' => 'Battles', 'icon' => '<svg viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM7 14h10v6H7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M8 20v-2m8 2v-2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'],
  ['href' => 'dismissed.php', 'label' => 'Dismissed', 'icon' => '<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'],
];
?>
<script>
  window.CSRF_TOKEN = "<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>";
</script>
<nav class="side-nav" aria-label="Main">
  <?php foreach ($items as $item): ?>
    <?php $active = ($activePage === $item['href']); ?>
    <a class="nav-link<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo $item['href']; ?>" data-page="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>" data-tooltip="<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>">
      <span class="icon" aria-hidden="true"><?php echo $item['icon']; ?></span>
    </a>
  <?php endforeach; ?>
</nav>
