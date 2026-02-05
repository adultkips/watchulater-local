(() => {
  const csrfToken = window.CSRF_TOKEN || '';
  const csrfHeaders = csrfToken ? { 'X-CSRF-Token': csrfToken } : {};
  const params = new URLSearchParams(location.search);
  const rouletteId = params.get('id');

  const titleEl = document.getElementById('title');
  const yearEl = document.getElementById('year');
  const posterEl = document.getElementById('poster');
  const cardEl = document.getElementById('roulette-card');
  const statusEl = document.getElementById('roulette-status');
  const searchToggle = document.getElementById('search-toggle');
  const searchInput = document.getElementById('search-input');
  const searchResults = document.getElementById('search-results');
  const searchWrap = document.getElementById('roulette-search');
  const wrapEl = document.querySelector('.roulette-wrap');
  const peekToggle = document.getElementById('peek-toggle');
  const peekBtn = document.getElementById('peek-btn');
  const peekText = document.getElementById('peek-text');
  const infoPanel = document.getElementById('info-panel');
  const directorEl = document.getElementById('director');
  const writerEl = document.getElementById('writer');
  const genresEl = document.getElementById('genres');
  const actorsEl = document.getElementById('actors');
  const directorRow = directorEl?.parentElement || null;
  const writerRow = writerEl?.parentElement || null;
  const posterLink = document.getElementById('poster-link');
  const actionsEl = document.querySelector('.actions');

  const btnDismiss = document.getElementById('btn-dismiss');
  const btnSkip = document.getElementById('btn-skip');
  const btnWatchlist = document.getElementById('btn-watchlist');
  const btnWatched = document.getElementById('btn-watched');

  const modal = document.getElementById('watched-modal');
  const starsWrap = document.getElementById('rating-stars');
  const recommendedBtn = document.getElementById('recommended-toggle');
  const cancelWatched = document.getElementById('cancel-watched');
  const saveWatched = document.getElementById('save-watched');

  let stack = [];
  let allItems = [];
  let current = null;
  let lastSkippedId = null;
  let rating = 0;
  let hoverRating = 0;
  let recommended = false;
  let peekExpanded = false;
  let searchReady = false;

  function setStatus(text){
    statusEl.textContent = text || '';
  }

  function shuffle(arr){
    const copy = arr.slice();
    for (let i = copy.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [copy[i], copy[j]] = [copy[j], copy[i]];
    }
    return copy;
  }

  function showItem(item){
    current = item;
    cardEl.classList.remove('fade-in');
    cardEl.classList.remove('swipe-right','swipe-left','swipe-up','swipe-bounce');
    cardEl.classList.remove('is-loaded');
    cardEl.style.transition = 'none';
    cardEl.style.transform = 'none';
    cardEl.style.opacity = '1';
    posterLink.classList.add('is-loading');
    posterLink.style.pointerEvents = 'none';
    posterLink.removeAttribute('href');
    cardEl.classList.add('is-loading');
    if (actionsEl) actionsEl.classList.add('is-loading');
    if (searchWrap && !searchReady) searchWrap.classList.add('is-loading');
    if (peekBtn) peekBtn.classList.add('peek-btn-hidden');
    titleEl.textContent = item.title || 'Untitled';
    yearEl.textContent = item.year || '';
    const placeholder = 'img/placeholder-poster.png';
    posterEl.src = placeholder;
    posterLink.classList.remove('is-icon');
    posterEl.classList.remove('is-icon');
    if (item.thumb) {
      const img = new Image();
      img.onload = () => {
        posterEl.src = item.thumb;
        const isIcon = String(item.thumb).includes('icons/genres/');
        if (isIcon) {
          posterLink.classList.add('is-icon');
          posterEl.classList.add('is-icon');
        }
        posterLink.classList.remove('is-loading');
        cardEl.classList.remove('is-loading');
        if (wrapEl) wrapEl.classList.remove('is-loading');
        cardEl.classList.add('is-loaded');
        if (actionsEl) actionsEl.classList.remove('is-loading');
        if (searchWrap) {
          searchWrap.classList.remove('is-loading');
          if (!searchReady) {
            searchWrap.classList.remove('is-ready');
            void searchWrap.offsetWidth;
            searchWrap.classList.add('is-ready');
            searchWrap.addEventListener('animationend', () => {
              searchWrap.classList.remove('is-ready');
            }, { once: true });
            searchReady = true;
          }
        }
        if (item.plex_url) {
          posterLink.href = item.plex_url;
          posterLink.style.pointerEvents = 'auto';
        }
        if (peekBtn) peekBtn.classList.remove('peek-btn-hidden');
      };
      img.src = item.thumb;
    } else {
      posterLink.classList.remove('is-loading');
      cardEl.classList.remove('is-loading');
      if (wrapEl) wrapEl.classList.remove('is-loading');
      cardEl.classList.add('is-loaded');
      if (actionsEl) actionsEl.classList.remove('is-loading');
      if (searchWrap) {
        searchWrap.classList.remove('is-loading');
        if (!searchReady) {
          searchWrap.classList.remove('is-ready');
          void searchWrap.offsetWidth;
          searchWrap.classList.add('is-ready');
          searchWrap.addEventListener('animationend', () => {
            searchWrap.classList.remove('is-ready');
          }, { once: true });
          searchReady = true;
        }
      }
      if (item.plex_url) {
        posterLink.href = item.plex_url;
        posterLink.style.pointerEvents = 'auto';
      }
      if (peekBtn) peekBtn.classList.remove('peek-btn-hidden');
    }
    posterEl.alt = item.title || '';
    peekText.textContent = item.summary || 'No summary available.';
    peekToggle.classList.toggle('expanded', peekExpanded);
    infoPanel.hidden = !peekExpanded;
    requestAnimationFrame(() => {
      cardEl.style.transition = '';
      cardEl.classList.add('fade-in');
    });
    const isShow = String(item.type || '').toLowerCase() === 'show';
    if (directorRow) directorRow.style.display = isShow ? 'none' : '';
    if (writerRow) writerRow.style.display = isShow ? 'none' : '';
    directorEl.textContent = item.director || '-';
    writerEl.textContent = item.writer || '-';
    genresEl.textContent = item.genres || '-';
    actorsEl.textContent = item.actors || '-';
  }

  posterLink.addEventListener('click', (e) => {
    if (!posterLink.getAttribute('href')) {
      e.preventDefault();
    }
  });

  function next(){
    if (stack.length === 0) {
      setStatus('No more items.');
      return;
    }
    let item = stack.shift();
    if (lastSkippedId && item && item.id === lastSkippedId && stack.length > 0) {
      const swap = stack.shift();
      stack.push(item);
      item = swap;
    }
    showItem(item);
  }

  async function load(){
    if (!rouletteId) {
      setStatus('Missing roulette ID.');
      return;
    }
    if (wrapEl) wrapEl.classList.add('is-loading');
    setStatus('');
    const res = await fetch('roulette_items.php?id=' + encodeURIComponent(rouletteId), { cache: 'no-store' });
    const data = await res.json();
    allItems = data.items || [];
    stack = shuffle(allItems.slice());
    if (stack.length === 0) {
      setStatus('No items found for this roulette.');
      return;
    }
    setStatus('');
    next();
  }

  async function act(action, extra = {}) {
    if (!current) return;
    await fetch('roulette_action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...csrfHeaders },
      body: JSON.stringify({
        action,
        film_id: current.id,
        title: current.title,
        year: current.year,
        type: current.type,
        genre: current.genres || '',
        thumb: current.thumb,
        imdb_id: current.imdb_id,
        section_title: current.section_title || '',
        rating: extra.rating ?? null,
        recommended: extra.recommended ?? false
      })
    });
    if (action !== 'skip') {
      const id = current.id;
      allItems = allItems.filter(i => i.id !== id);
      stack = stack.filter(i => i.id !== id);
      handleSearch();
    }
  }

  function swipe(dir){
    if (!cardEl) return;
    cardEl.classList.remove('swipe-right','swipe-left','swipe-up','swipe-bounce');
    if (actionsEl) actionsEl.classList.add('is-disabled');
    if (dir === 'right') cardEl.classList.add('swipe-right');
    if (dir === 'left') cardEl.classList.add('swipe-left');
    if (dir === 'up') cardEl.classList.add('swipe-up');
    if (dir === 'bounce') cardEl.classList.add('swipe-bounce');
  }

  function afterSwipe(cb){
    setTimeout(() => {
      if (actionsEl) actionsEl.classList.remove('is-disabled');
      cb();
    }, 260);
  }

  btnDismiss.addEventListener('click', async () => {
    swipe('bounce');
    await act('dismissed');
    afterSwipe(next);
  });
  btnWatchlist.addEventListener('click', async () => {
    swipe('bounce');
    await act('watchlist');
    afterSwipe(next);
  });
  btnSkip.addEventListener('click', async () => {
    swipe('bounce');
    if (current) {
      stack.push(current);
      lastSkippedId = current.id;
    }
    afterSwipe(next);
  });
  btnWatched.addEventListener('click', () => {
    rating = 0;
    recommended = false;
    if (recommendedBtn) {
      recommendedBtn.classList.remove('active');
      recommendedBtn.textContent = 'Recommend?';
    }
    renderStars();
    modal.classList.remove('hidden');
  });

  cancelWatched.addEventListener('click', () => {
    modal.classList.add('hidden');
  });
  saveWatched.addEventListener('click', async () => {
    await act('watched', { rating, recommended });
    modal.classList.add('hidden');
    swipe('bounce');
    afterSwipe(next);
  });

  function toggleInfo(){
    const expanded = !peekToggle.classList.contains('expanded');
    peekToggle.classList.toggle('expanded', expanded);
    infoPanel.hidden = !expanded;
    peekExpanded = expanded;
  }
  // Only the arrow should toggle
  peekBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleInfo();
  });

  function renderStars(){
    starsWrap.innerHTML = '';
    for (let i = 1; i <= 5; i++) {
      const s = document.createElement('button');
      s.type = 'button';
      s.className = 'star';
      s.dataset.value = String(i);
      s.textContent = '★';
      s.addEventListener('mousemove', (e) => {
        const rect = s.getBoundingClientRect();
        const half = (e.clientX - rect.left) / rect.width >= 0.5 ? 1 : 0.5;
        hoverRating = i - 1 + half;
        paintStars(hoverRating);
      });
      s.addEventListener('mouseleave', () => {
        hoverRating = 0;
        paintStars(rating);
      });
      s.addEventListener('click', () => {
        if (hoverRating > 0) rating = hoverRating;
        paintStars(rating);
      });
      starsWrap.appendChild(s);
    }
    paintStars(rating);
  }

  recommendedBtn?.addEventListener('click', () => {
    recommended = !recommended;
    recommendedBtn.classList.toggle('active', recommended);
    recommendedBtn.textContent = recommended ? 'Recommended' : 'Recommend?';
  });

  function paintStars(n){
    const stars = Array.from(starsWrap.querySelectorAll('.star'));
    stars.forEach((st, idx) => {
      const value = idx + 1;
      let fill = 0;
      if (n >= value) fill = 100;
      else if (n >= value - 0.5) fill = 50;
      st.style.setProperty('--fill', fill + '%');
    });
  }

  function setSearchResults(list){
    if (!searchResults) return;
    searchResults.innerHTML = '';
    if (!list.length) {
      searchResults.hidden = true;
      return;
    }
    const frag = document.createDocumentFragment();
    list.forEach(item => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'search-item';
      btn.innerHTML = `
        <img class="search-thumb" src="${item.thumb || 'img/placeholder-poster.png'}" alt="">
        <div class="search-meta">
          <div class="search-title">${item.title || 'Untitled'}</div>
          <div class="search-year">${item.year || ''}</div>
        </div>
      `;
      btn.addEventListener('click', () => {
        stack = stack.filter(i => i.id !== item.id);
        showItem(item);
        if (searchInput) searchInput.value = '';
        setSearchResults([]);
      });
      frag.appendChild(btn);
    });
    searchResults.appendChild(frag);
    searchResults.hidden = false;
  }

  function handleSearch(){
    const query = (searchInput?.value || '').trim().toLowerCase();
    if (!query) {
      setSearchResults([]);
      return;
    }
    const matches = allItems.filter(i => (i.title || '').toLowerCase().includes(query)).slice(0, 12);
    setSearchResults(matches);
  }

  searchToggle?.addEventListener('click', () => {
    searchInput?.classList.toggle('is-open');
    searchInput?.closest('.search-row')?.classList.toggle('is-open');
    if (searchInput?.classList.contains('is-open')) {
      searchInput.focus();
    } else {
      searchInput.value = '';
      setSearchResults([]);
    }
  });

  searchInput?.addEventListener('input', handleSearch);

  load();
})();

