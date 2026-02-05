(() => {
  const csrfToken = window.CSRF_TOKEN || '';
  const csrfHeaders = csrfToken ? { 'X-CSRF-Token': csrfToken } : {};
  const status = document.body.dataset.status;
  const grid = document.getElementById('film-grid');
  const emptyMsg = document.getElementById('empty-msg');
  const sortSelect = document.getElementById('sort-select');
  const sortDirBtn = document.getElementById('sort-dir');
  const searchToggle = document.getElementById('search-toggle');
  const searchInput = document.getElementById('search-input');
  const filterToggle = document.getElementById('filter-toggle');
  const filterPanel = document.getElementById('filter-panel');
  const resetBtn = document.getElementById('reset-filters');
  const ratingFilter = document.getElementById('rating-filter');
  const recOnly = document.getElementById('rec-only');
  const genreFilter = document.getElementById('genre-filter');
  const sectionFilter = document.getElementById('section-filter');
  const watchNowBtn = document.getElementById('watch-now');

  let items = [];
  let visibleIds = [];
  let visibleItems = [];

  const sortKey = `wl_sort_${status}`;
  const dirKey = `wl_sortdir_${status}`;

  function applySavedSort() {
    try {
      const savedSort = localStorage.getItem(sortKey);
      const savedDir = localStorage.getItem(dirKey);
      if (savedSort && sortSelect) sortSelect.value = savedSort;
      if (savedDir && sortDirBtn) {
        sortDirBtn.dataset.dir = savedDir;
        sortDirBtn.textContent = savedDir === 'asc' ? '↑' : '↓';
      }
    } catch (_) {}
  }

  function saveSort() {
    try {
      if (sortSelect) localStorage.setItem(sortKey, sortSelect.value);
      if (sortDirBtn) localStorage.setItem(dirKey, sortDirBtn.dataset.dir || 'desc');
    } catch (_) {}
  }
  const serverId = document.querySelector('.main-content')?.dataset.serverId || '';
  let editModal = null;
  let editState = { id: null, rating: 0, recommended: false, mode: 'edit' };

  const icons = {
    watchlist: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h12a2 2 0 0 1 2 2v14l-8-4-8 4V6a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="2"/></svg>',
    watched: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/></svg>',
    dismiss: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    edit: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10-10-4-4L4 16v4Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M14 6l4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    delete: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2m-9 3 1 11h8l1-11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
  };

  function renderGenres(list, selected = []) {
    const genres = new Set();
    list.forEach(i => {
      (i.genre || '').split(',').map(g => g.trim()).filter(Boolean).forEach(g => genres.add(g));
    });
    genreFilter.innerHTML = '';
    if (genres.size === 0) return;
    Array.from(genres).sort().forEach(g => {
      const label = document.createElement('label');
      label.className = 'filter-chip';
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = g;
      if (selected.includes(g)) cb.checked = true;
      const text = document.createElement('span');
      text.textContent = g;
      label.appendChild(cb);
      label.appendChild(text);
      genreFilter.appendChild(label);
    });
  }

  function renderSections(list, selected = []) {
    if (!sectionFilter) return;
    const sections = new Set();
    list.forEach(i => {
      const s = (i.section_title || '').trim();
      if (s) sections.add(s);
    });
    sectionFilter.innerHTML = '';
    if (sections.size === 0) return;
    Array.from(sections).sort().forEach(s => {
      const label = document.createElement('label');
      label.className = 'filter-chip';
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = s;
      if (selected.includes(s)) cb.checked = true;
      const text = document.createElement('span');
      text.textContent = s;
      label.appendChild(cb);
      label.appendChild(text);
      sectionFilter.appendChild(label);
    });
  }

  function currentFilters() {
    const selectedGenres = Array.from(genreFilter.querySelectorAll('input:checked')).map(i => i.value);
    const selectedSections = sectionFilter
      ? Array.from(sectionFilter.querySelectorAll('input:checked')).map(i => i.value)
      : [];
    const selectedRatings = ratingFilter
      ? Array.from(ratingFilter.querySelectorAll('input:checked')).map(i => Number(i.value))
      : [];
    const recommendedOnly = !!recOnly?.checked;
    const query = (searchInput?.value || '').trim().toLowerCase();
    return { selectedGenres, selectedSections, selectedRatings, recommendedOnly, query };
  }

  function applyFilters(list) {
    const { selectedGenres, selectedSections, selectedRatings, recommendedOnly, query } = currentFilters();
    return list.filter(i => {
      if (query) {
        const t = (i.title || '').toLowerCase();
        if (!t.includes(query)) return false;
      }
      if (selectedSections.length) {
        const sec = (i.section_title || '').trim();
        if (!selectedSections.includes(sec)) return false;
      }
      if (selectedGenres.length) {
        const ig = (i.genre || '').split(',').map(g => g.trim());
        const hasAll = selectedGenres.every(g => ig.includes(g));
        if (!hasAll) return false;
      }
      if (status === 'watched') {
        if (recommendedOnly && Number(i.recommended || 0) !== 1) return false;
        if (selectedRatings.length) {
          const r = Number(i.rating || 0);
          if (!selectedRatings.includes(r)) return false;
        }
      }
      return true;
    });
  }

  function sortList(list) {
    const v = sortSelect.value;
    const dir = sortDirBtn?.dataset.dir === 'asc' ? 'asc' : 'desc';
    const copy = list.slice();
    copy.sort((a, b) => {
      let res = 0;
      if (v === 'title') res = (a.title || '').localeCompare(b.title || '');
      if (v === 'year') res = (a.year || '').localeCompare(b.year || '');
      if (v === 'added') res = (a.created_at || '').localeCompare(b.created_at || '');
      if (v === 'updated') res = (a.updated_at || '').localeCompare(b.updated_at || '');
      if (v === 'rating') {
        res = (Number(a.rating || 0) - Number(b.rating || 0));
        if (res === 0) {
          res = (Number(a.recommended || 0) - Number(b.recommended || 0));
        }
      }
      return dir === 'asc' ? res : -res;
    });
    return copy;
  }

  async function saveStatus(filmId, newStatus, meta = {}) {
    if (newStatus !== 'watched') {
      meta = { ...meta, rating: null, recommended: null };
    }
    await fetch('filmvalg_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...csrfHeaders },
      body: JSON.stringify({
        film_id: filmId,
        status: newStatus,
        rating: meta.rating ?? null,
        recommended: meta.recommended ?? null
      })
    });
    await load();
  }

  async function deleteItem(filmId) {
    if (!confirm('Delete this item?')) return;
    await fetch('filmvalg_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...csrfHeaders },
      body: JSON.stringify({ action: 'delete', film_id: filmId })
    });
    await load();
  }

  async function updateMeta(filmId, rating, recommended) {
    await fetch('filmvalg_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...csrfHeaders },
      body: JSON.stringify({
        action: 'update_meta',
        film_id: filmId,
        rating,
        recommended
      })
    });
    await load();
  }

  function ensureEditModal() {
    if (editModal) return editModal;
    const modal = document.createElement('div');
    modal.className = 'modal hidden';
    modal.innerHTML = `
      <div class="modal-content" role="dialog" aria-modal="true">
        <h3 id="edit-title">Edit rating</h3>
        <div class="stars" id="edit-stars"></div>
        <button type="button" class="reco" id="edit-recommend">Recommend?</button>
        <div class="modal-actions">
          <button type="button" class="btn secondary" data-close="1">Cancel</button>
          <button type="button" class="btn primary" id="edit-save">Save</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);

    const starsWrap = modal.querySelector('#edit-stars');
    for (let i = 1; i <= 5; i++) {
      const star = document.createElement('button');
      star.type = 'button';
      star.className = 'star';
      star.dataset.value = String(i);
      star.textContent = '★';
      star.addEventListener('mousemove', (e) => {
        const rect = star.getBoundingClientRect();
        const half = (e.clientX - rect.left) / rect.width >= 0.5 ? 1 : 0.5;
        paintStars(i - 1 + half);
      });
      star.addEventListener('mouseleave', () => {
        paintStars(editState.rating);
      });
      star.addEventListener('click', (e) => {
        const rect = star.getBoundingClientRect();
        const half = (e.clientX - rect.left) / rect.width >= 0.5 ? 1 : 0.5;
        editState.rating = i - 1 + half;
        paintStars(editState.rating);
      });
      starsWrap.appendChild(star);
    }

    const recommendBtn = modal.querySelector('#edit-recommend');
    recommendBtn.addEventListener('click', () => {
      editState.recommended = !editState.recommended;
      renderRecommend();
    });

    modal.querySelectorAll('[data-close="1"]').forEach(el => {
      el.addEventListener('click', () => closeEdit());
    });

    modal.querySelector('#edit-save').addEventListener('click', async () => {
      if (!editState.id) return;
      if (editState.mode === 'move') {
        await saveStatus(editState.id, 'watched', {
          rating: editState.rating,
          recommended: editState.recommended ? 1 : 0
        });
      } else {
        await updateMeta(editState.id, editState.rating, editState.recommended ? 1 : 0);
      }
      closeEdit();
    });

    function paintStars(active) {
      modal.querySelectorAll('.star').forEach(star => {
        const v = Number(star.dataset.value);
        let fill = 0;
        if (active >= v) fill = 100;
        else if (active >= v - 0.5) fill = 50;
        star.style.setProperty('--fill', fill + '%');
      });
    }

    function renderRecommend() {
      if (editState.recommended) {
        recommendBtn.classList.add('is-active');
        recommendBtn.textContent = 'Recommended';
      } else {
        recommendBtn.classList.remove('is-active');
        recommendBtn.textContent = 'Recommend?';
      }
    }

    modal._paintStars = paintStars;
    modal._renderRecommend = renderRecommend;
    editModal = modal;
    return modal;
  }

  function openEdit(item, mode = 'edit') {
    const modal = ensureEditModal();
    editState = {
      id: item.film_id,
      rating: Number(item.rating || 0),
      recommended: Number(item.recommended || 0) === 1,
      mode
    };
    const titleEl = modal.querySelector('#edit-title');
    titleEl.textContent = mode === 'move' ? 'Rate what you watched' : 'Edit rating';
    modal._paintStars(editState.rating);
    modal._renderRecommend();
    modal.classList.remove('hidden');
  }

  function closeEdit() {
    if (!editModal) return;
    editModal.classList.add('hidden');
    editState = { id: null, rating: 0, recommended: false };
  }

  function makeButton(label, icon, className, onClick) {
    const btn = document.createElement('button');
    btn.className = className;
    btn.type = 'button';
    btn.setAttribute('aria-label', label);
    btn.setAttribute('data-tooltip', label);
    btn.innerHTML = `<span class="icon">${icon}</span>`;
    btn.addEventListener('click', onClick);
    return btn;
  }

  function render(list) {
    grid.innerHTML = '';
    visibleIds = list.map(i => i.film_id);
    visibleItems = list.slice();
    const selectedGenres = Array.from(genreFilter.querySelectorAll('input:checked')).map(i => i.value);
    if (list.length === 0) {
      emptyMsg.style.display = 'block';
      if (watchNowBtn) watchNowBtn.style.display = 'none';
      return;
    }
    emptyMsg.style.display = 'none';
    if (watchNowBtn) watchNowBtn.style.display = '';
    list.forEach(i => {
      const card = document.createElement('div');
      card.className = 'film-item';

      const posterLink = document.createElement('a');
      posterLink.className = 'film-poster-link';
      posterLink.target = '_blank';
      posterLink.rel = 'noopener';

      const poster = document.createElement('div');
      poster.className = 'film-poster';
      const overlay = document.createElement('span');
      overlay.className = 'poster-overlay';
      overlay.innerHTML = 'Open Plex <img src="icons/plexlogo.png" alt="" aria-hidden="true">';
      const placeholder = 'img/placeholder-poster.png';
      poster.style.backgroundImage = `url('${placeholder}')`;
      poster.style.backgroundSize = 'cover';
      poster.style.backgroundPosition = 'center';
      if (i.thumb) {
        const img = new Image();
        img.onload = () => {
          poster.style.backgroundImage = `url('${i.thumb}')`;
        };
        img.src = i.thumb;
      }
      if (status === 'watched' && Number(i.recommended || 0) === 1) {
        const badge = document.createElement('div');
        badge.className = 'recommended-badge';
        badge.textContent = 'Recommended';
        poster.appendChild(badge);
      }
      poster.appendChild(overlay);
      if (serverId && i.film_id) {
        const ratingKey = String(i.film_id).replace('plex:', '');
        posterLink.href = 'https://app.plex.tv/desktop/#!/server/' + encodeURIComponent(serverId) +
          '/details?key=' + encodeURIComponent('/library/metadata/' + ratingKey);
      } else {
        posterLink.href = '#';
        posterLink.style.pointerEvents = 'none';
      }
      posterLink.appendChild(poster);
      card.appendChild(posterLink);

      const info = document.createElement('div');
      info.className = 'film-info';
      if (status === 'watched') {
        const ratingRow = document.createElement('div');
        ratingRow.className = 'rating-row';
        const rating = Number(i.rating || 0);
        for (let s = 1; s <= 5; s++) {
          const star = document.createElement('span');
          star.className = 'rating-star';
          star.textContent = '★';
          let fill = 0;
          if (rating >= s) fill = 100;
          else if (rating >= s - 0.5) fill = 50;
          star.style.setProperty('--fill', fill + '%');
          ratingRow.appendChild(star);
        }
        info.appendChild(ratingRow);
      }
      const title = document.createElement('h4');
      title.textContent = i.title || i.film_id;
      const year = document.createElement('div');
      year.className = 'film-year';
      const sectionLabel = (i.section_title || '').trim();
      const typeLabel = (i.type || '').trim();
      const prettyType = typeLabel ? typeLabel.charAt(0).toUpperCase() + typeLabel.slice(1) : '';
      year.textContent = [i.year, sectionLabel || prettyType].filter(Boolean).join(' - ');
      info.appendChild(title);
      info.appendChild(year);

      const genreWrap = document.createElement('div');
      genreWrap.className = 'genre-pills';
      const genreList = (i.genre || '').split(',').map(g => g.trim()).filter(Boolean);
      genreList.forEach(g => {
        const pill = document.createElement('span');
        pill.className = 'genre-pill';
        pill.textContent = g;
        if (selectedGenres.includes(g)) {
          pill.classList.add('is-active');
        }
        pill.addEventListener('click', () => {
          const cb = genreFilter.querySelector(`input[value="${CSS.escape(g)}"]`);
          if (cb) {
            cb.checked = !cb.checked;
            load();
          }
        });
        genreWrap.appendChild(pill);
      });
      info.appendChild(genreWrap);
      card.appendChild(info);

      const actions = document.createElement('div');
      actions.className = 'film-actions';
      const primaryRow = document.createElement('div');
      primaryRow.className = 'actions-row';
      const secondaryRow = document.createElement('div');
      secondaryRow.className = 'actions-row secondary';

      if (status === 'watched') {
        primaryRow.appendChild(makeButton('Edit', icons.edit, 'btn ghost', () => openEdit(i)));
      }
      if (status !== 'watchlist') {
        primaryRow.appendChild(makeButton('Watchlist', icons.watchlist, 'btn primary', () => saveStatus(i.film_id, 'watchlist')));
      }
      if (status !== 'watched') {
        primaryRow.appendChild(makeButton('Watched', icons.watched, 'btn watched', () => openEdit(i, 'move')));
      }
      if (status !== 'dismissed') {
        primaryRow.appendChild(makeButton('Dismiss', icons.dismiss, 'btn', () => saveStatus(i.film_id, 'dismissed')));
      }
      primaryRow.appendChild(makeButton('Delete', icons.delete, 'btn danger', () => deleteItem(i.film_id)));

      actions.appendChild(primaryRow);
      if (secondaryRow.childElementCount) actions.appendChild(secondaryRow);
      card.appendChild(actions);
      grid.appendChild(card);
    });
  }

  async function load() {
    const res = await fetch('filmvalg_api.php?status=' + encodeURIComponent(status), { cache: 'no-store' });
    const data = await res.json();
    items = data.items || [];
    const selectedGenres = Array.from(genreFilter.querySelectorAll('input:checked')).map(i => i.value);
    const selectedSections = sectionFilter
      ? Array.from(sectionFilter.querySelectorAll('input:checked')).map(i => i.value)
      : [];
    renderGenres(items, selectedGenres);
    renderSections(items, selectedSections);
    const filtered = applyFilters(items);
    const sorted = sortList(filtered);
    render(sorted);
  }

  sortSelect.addEventListener('change', () => {
    saveSort();
    load();
  });
  sortDirBtn?.addEventListener('click', () => {
    const next = sortDirBtn.dataset.dir === 'asc' ? 'desc' : 'asc';
    sortDirBtn.dataset.dir = next;
    sortDirBtn.textContent = next === 'asc' ? '↑' : '↓';
    saveSort();
    load();
  });
  searchToggle?.addEventListener('click', () => {
    searchInput?.classList.toggle('is-open');
    if (searchInput?.classList.contains('is-open')) {
      searchInput.focus();
    } else {
      searchInput.value = '';
      load();
    }
  });
  searchInput?.addEventListener('input', load);
  filterToggle.addEventListener('click', () => {
    filterPanel.classList.toggle('visible');
    filterToggle.classList.toggle('is-active', filterPanel.classList.contains('visible'));
  });
  filterPanel.addEventListener('change', load);
  resetBtn?.addEventListener('click', () => {
    if (!visibleIds.length) return;
    const label = status === 'watchlist' ? 'Watchlist' : status === 'watched' ? 'Watched' : 'Dismissed';
    if (!confirm(`Remove ${visibleIds.length} item(s) from ${label} and return to roulette?`)) return;
    fetch('filmvalg_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...csrfHeaders },
      body: JSON.stringify({ action: 'delete_many', film_ids: visibleIds })
    }).then(load);
  });
  window.addEventListener('scroll', () => {
    document.body.classList.toggle('is-scrolled', window.scrollY > 0);
  }, { passive: true });

  watchNowBtn?.addEventListener('click', () => {
    if (!visibleItems.length) return;
    const payload = visibleItems.map(i => ({
      film_id: i.film_id,
      title: i.title || '',
      year: i.year || '',
      type: i.type || '',
      thumb: i.thumb || ''
    }));
    payload.forEach(i => { i.source_status = status; });
    sessionStorage.setItem('watch_now_items', JSON.stringify(payload));
    window.location.href = 'watch_now.php?from=' + encodeURIComponent(status + '.php');
  });

  applySavedSort();
  load();
})();
