(() => {
  const card = document.getElementById('watch-card');
  const poster = document.getElementById('watch-poster');
  const watchLink = document.getElementById('watch-link');
  const titleEl = document.getElementById('watch-title');
  const yearEl = document.getElementById('watch-year');
  const skipBtn = document.getElementById('watch-skip');
  const emptyEl = document.getElementById('watch-empty');
  const retryBtn = document.getElementById('watch-retry');

  let serverId = '';
  let stack = [];
  let original = [];
  let current = null;

  function shuffle(arr){
    const copy = arr.slice();
    for (let i = copy.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [copy[i], copy[j]] = [copy[j], copy[i]];
    }
    return copy;
  }

  async function loadServerId(){
    const wrap = document.querySelector('.watch-now-wrap');
    const embedded = wrap?.dataset.serverId || '';
    if (embedded) {
      serverId = embedded;
      return;
    }
    try {
      const res = await fetch('plex_server_info.php?ts=' + Date.now(), { cache: 'no-store' });
      const data = await res.json();
      serverId = data.server_id || '';
    } catch (_) {
      serverId = '';
    }
  }

  function plexUrl(item){
    if (!serverId || !item || !item.film_id) return '';
    const ratingKey = String(item.film_id).replace('plex:', '');
    return 'https://app.plex.tv/desktop/#!/server/' + encodeURIComponent(serverId) +
      '/details?key=' + encodeURIComponent('/library/metadata/' + ratingKey);
  }

  function showItem(item){
    current = item;
    if (!item) return;
    const placeholder = 'img/placeholder-poster.png';
    poster.src = placeholder;
    if (item.thumb) {
      const img = new Image();
      img.onload = () => { poster.src = item.thumb; };
      img.src = item.thumb;
    }
    titleEl.textContent = item.title || 'Untitled';
    yearEl.textContent = item.year || '';
    const url = plexUrl(item);
    if (url) {
      watchLink.href = url;
      watchLink.style.pointerEvents = 'auto';
    } else {
      watchLink.href = '#';
      watchLink.style.pointerEvents = 'none';
    }
    card.style.display = '';
    emptyEl.hidden = true;
  }

  function next(){
    if (!stack.length) {
      card.style.display = 'none';
      skipBtn.style.display = 'none';
      emptyEl.hidden = false;
      return;
    }
    const item = stack.shift();
    showItem(item);
  }

  function loadFromSession(){
    const raw = sessionStorage.getItem('watch_now_items');
    if (!raw) return false;
    try {
      const data = JSON.parse(raw);
      if (!Array.isArray(data) || data.length === 0) return false;
      original = data.slice();
      stack = shuffle(data.slice());
      return true;
    } catch (_) {
      return false;
    }
  }

  skipBtn.addEventListener('click', () => {
    next();
  });

  retryBtn.addEventListener('click', () => {
    stack = shuffle(original.slice());
    skipBtn.style.display = '';
    next();
  });

  card.addEventListener('click', (e) => {
    if (e.target && e.target.closest('.watch-poster')) return;
    const url = plexUrl(current);
    if (url) window.open(url, '_blank');
  });

  window.addEventListener('beforeunload', () => {
    sessionStorage.setItem('watch_now_reset', '1');
  });

  (async () => {
    await loadServerId();
    if (!loadFromSession()) {
      titleEl.textContent = 'No items selected.';
      skipBtn.disabled = true;
      return;
    }
    const forceReset = sessionStorage.getItem('watch_now_reset') === '1';
    if (forceReset) {
      sessionStorage.removeItem('watch_now_reset');
      stack = shuffle(original.slice());
    }
    next();
  })();
})();
