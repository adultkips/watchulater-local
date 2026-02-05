(() => {
  const csrfToken = window.CSRF_TOKEN || '';
  const csrfHeaders = csrfToken ? { 'X-CSRF-Token': csrfToken } : {};
  const grid = document.getElementById('roulettes-grid');
  const empty = document.getElementById('roulettes-empty');
  const addTile = document.getElementById('add-roulette');
  const sortSelect = document.getElementById('sort-select');
  const sortDirBtn = document.getElementById('sort-dir');
  const searchToggle = document.getElementById('search-toggle');
  const searchInput = document.getElementById('search-input');
  const filterToggle = document.getElementById('filter-toggle');
  const filterPanel = document.getElementById('filter-panel');
  const genreFilter = document.getElementById('genre-filter');
  const sectionFilter = document.getElementById('section-filter');
  const resetBtn = document.getElementById('reset-roulettes');
  let allItems = [];
  let visibleIds = [];

  const sortKey = 'wl_sort_roulettes';
  const dirKey = 'wl_sortdir_roulettes';

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

  function goCreate() {
    window.location.href = 'create_roulette.php';
  }

  function makeCard(item) {
    const el = document.createElement('article');
    el.className = 'card roulette-card';
    el.tabIndex = 0;
    el.dataset.id = item.id;

    const link = document.createElement('a');
    link.className = 'card-link';
    link.href = 'roulette-view.php?id=' + encodeURIComponent(item.id);
    link.setAttribute('aria-label', 'Open roulette');

    const img = document.createElement('img');
    img.className = 'card-cover';
    img.src = item.cover_image || 'img/placeholder-poster.png';
    img.alt = '';
    img.decoding = 'async';
    img.loading = 'lazy';
    if (String(item.cover_image || '').includes('icons/genres/')) {
      img.classList.add('is-icon');
      link.classList.add('is-icon');
    }
    link.appendChild(img);
    el.appendChild(link);

    const title = document.createElement('h2');
    title.className = 'card-title';
    title.textContent = item.name || '—';
    el.appendChild(title);

    const meta = document.createElement('div');
    meta.className = 'meta';
    const movies = (item.movie_title || item.movie_source || '').trim();
    const shows = (item.show_title || item.show_source || '').trim();
    const list = (movies || shows || '').split(',').map(s => s.trim()).filter(Boolean);
    const genres = (item.genre_filter || '').split(',').map(s => s.trim()).filter(Boolean);
    const genreAll = Number(item.genre_all || 0) === 1;
    if (list.length === 0 && genres.length === 0) {
      meta.textContent = 'No sources';
    } else {
      list.forEach(t => {
        const pill = document.createElement('span');
        pill.className = 'pill';
        pill.textContent = t;
        pill.classList.add('pill-filter');
        pill.dataset.filterType = 'section';
        if (sectionFilter) {
          const cb = sectionFilter.querySelector(`input[value="${CSS.escape(t)}"]`);
          if (cb?.checked) pill.classList.add('is-active');
        }
        meta.appendChild(pill);
      });
      if (genreAll) {
        const pill = document.createElement('span');
        pill.className = 'pill';
        pill.textContent = 'All genres';
        pill.classList.add('pill-filter');
        pill.dataset.filterType = 'genre';
        if (genreFilter) {
          const cb = genreFilter.querySelector(`input[value="All genres"]`);
          if (cb?.checked) pill.classList.add('is-active');
        }
        meta.appendChild(pill);
      } else {
        const maxGenres = 3;
        const genreList = genres.slice(0, maxGenres);
        const remaining = genres.length - genreList.length;
        genreList.forEach(g => {
          const pill = document.createElement('span');
          pill.className = 'pill';
          pill.textContent = g;
          pill.classList.add('pill-filter');
          pill.dataset.filterType = 'genre';
          if (genreFilter) {
            const cb = genreFilter.querySelector(`input[value="${CSS.escape(g)}"]`);
            if (cb?.checked) pill.classList.add('is-active');
          }
          meta.appendChild(pill);
        });
        if (remaining > 0) {
          const pill = document.createElement('span');
          pill.className = 'pill';
          pill.textContent = `+${remaining}`;
          meta.appendChild(pill);
        }
      }
    }
    el.appendChild(meta);

    const actions = document.createElement('div');
    actions.className = 'card-actions';

    const edit = document.createElement('button');
    edit.className = 'btn small ghost';
    edit.type = 'button';
    edit.setAttribute('aria-label', 'Edit');
    edit.setAttribute('data-tooltip', 'Edit');
    edit.innerHTML = `
      <span class="icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M4 20h4l10-10-4-4L4 16v4Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M14 6l4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </span>
    `;
    edit.addEventListener('click', (e) => {
      e.stopPropagation();
      window.location.href = 'create_roulette.php?id=' + encodeURIComponent(item.id);
    });

    const del = document.createElement('button');
    del.className = 'btn small danger';
    del.type = 'button';
    del.setAttribute('aria-label', 'Delete');
    del.setAttribute('data-tooltip', 'Delete');
    del.innerHTML = `
      <span class="icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2m-9 3 1 11h8l1-11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </span>
    `;
    del.addEventListener('click', async (e) => {
      e.stopPropagation();
      if (!confirm('Delete this roulette?')) return;
      await fetch('delete_roulette.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders },
        body: JSON.stringify({ id: item.id })
      });
      el.remove();
      const remaining = grid.querySelectorAll('.card.roulette-card').length;
      if (remaining === 0) empty.hidden = false;
    });

    actions.appendChild(edit);
    actions.appendChild(del);
    el.appendChild(actions);

    meta.addEventListener('click', (e) => {
      const target = e.target.closest('.pill-filter');
      if (!target) return;
      const type = target.dataset.filterType || '';
      const value = target.textContent || '';
      if (type === 'section' && sectionFilter) {
        const cb = sectionFilter.querySelector(`input[value="${CSS.escape(value)}"]`);
        if (cb) {
          cb.checked = !cb.checked;
          target.classList.toggle('is-active', cb.checked);
        }
      }
      if (type === 'genre' && genreFilter) {
        const cb = genreFilter.querySelector(`input[value="${CSS.escape(value)}"]`);
        if (cb) {
          cb.checked = !cb.checked;
          target.classList.toggle('is-active', cb.checked);
        }
      }
      render(sortList(applyFilters(allItems)));
    });

    return el;
  }

  function sortList(list) {
    const v = sortSelect?.value || 'added';
    const dir = sortDirBtn?.dataset.dir === 'desc' ? 'desc' : 'asc';
    const copy = [...list];
    copy.sort((a, b) => {
      let res = 0;
      if (v === 'title') {
        res = String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' });
      } else {
        const aKey = String(a.created_at || '');
        const bKey = String(b.created_at || '');
        res = aKey.localeCompare(bKey);
      }
      return dir === 'asc' ? res : -res;
    });
    return copy;
  }

  function renderGenres(list, selected = []) {
    if (!genreFilter) return;
    const genres = new Set();
    let hasAll = false;
    list.forEach(i => {
      const all = Number(i.genre_all || 0) === 1;
      if (all) {
        hasAll = true;
        return;
      }
      const g = (i.genre_filter || '').split(',').map(s => s.trim()).filter(Boolean);
      g.forEach(x => genres.add(x));
    });
    genreFilter.innerHTML = '';
    const items = [];
    if (hasAll) items.push('All genres');
    items.push(...Array.from(genres).sort());
    if (items.length === 0) return;
    items.forEach(g => {
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
      const movies = (i.movie_title || i.movie_source || '').trim();
      const shows = (i.show_title || i.show_source || '').trim();
      const src = (movies || shows || '').split(',').map(s => s.trim()).filter(Boolean);
      src.forEach(s => sections.add(s));
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
    const selectedGenres = genreFilter
      ? Array.from(genreFilter.querySelectorAll('input:checked')).map(i => i.value)
      : [];
    const selectedSections = sectionFilter
      ? Array.from(sectionFilter.querySelectorAll('input:checked')).map(i => i.value)
      : [];
    const query = (searchInput?.value || '').trim().toLowerCase();
    return { selectedGenres, selectedSections, query };
  }

  function applyFilters(list) {
    const { selectedGenres, selectedSections, query } = currentFilters();
    return list.filter(i => {
      if (query) {
        const t = (i.name || '').toLowerCase();
        if (!t.includes(query)) return false;
      }
      if (selectedSections.length) {
        const movies = (i.movie_title || i.movie_source || '').trim();
        const shows = (i.show_title || i.show_source || '').trim();
        const src = (movies || shows || '').split(',').map(s => s.trim()).filter(Boolean);
        const hasAllSections = selectedSections.every(s => src.includes(s));
        if (!hasAllSections) return false;
      }
      if (selectedGenres.length) {
        const all = Number(i.genre_all || 0) === 1;
        if (selectedGenres.includes('All genres')) {
          if (!all) return false;
        }
        if (all) return !selectedGenres.filter(g => g !== 'All genres').length;
        const genres = (i.genre_filter || '').split(',').map(s => s.trim()).filter(Boolean);
        const hasAllGenres = selectedGenres.every(g => genres.includes(g));
        if (!hasAllGenres) return false;
      }
      return true;
    });
  }

  function render(list) {
    [...grid.querySelectorAll('.card.roulette-card')].forEach(n => n.remove());
    visibleIds = Array.isArray(list) ? list.map(i => i.id) : [];
    if (Array.isArray(list) && list.length) {
      const frag = document.createDocumentFragment();
      list.forEach(item => frag.appendChild(makeCard(item)));
      grid.appendChild(frag);
      if (empty) empty.hidden = true;
    } else {
      if (empty) empty.hidden = false;
    }
  }

  async function load() {
    const res = await fetch('getroulettes.php', { cache: 'no-store' });
    const list = await res.json();
    allItems = Array.isArray(list) ? list : [];
    if (allItems.length === 0) {
      window.location.href = 'create_roulette.php';
      return;
    }
    const selectedGenres = genreFilter
      ? Array.from(genreFilter.querySelectorAll('input:checked')).map(i => i.value)
      : [];
    const selectedSections = sectionFilter
      ? Array.from(sectionFilter.querySelectorAll('input:checked')).map(i => i.value)
      : [];
    renderGenres(allItems, selectedGenres);
    renderSections(allItems, selectedSections);
    const filtered = applyFilters(allItems);
    render(sortList(filtered));
  }

  addTile?.addEventListener('click', goCreate);
  addTile?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); goCreate(); }
  });
  const emptyCreate = document.getElementById('empty-create');
  emptyCreate?.addEventListener('click', goCreate);

  sortSelect?.addEventListener('change', () => {
    saveSort();
    render(sortList(applyFilters(allItems)));
  });
  sortDirBtn?.addEventListener('click', () => {
    const next = sortDirBtn.dataset.dir === 'asc' ? 'desc' : 'asc';
    sortDirBtn.dataset.dir = next;
    sortDirBtn.textContent = next === 'asc' ? '↑' : '↓';
    saveSort();
    render(sortList(applyFilters(allItems)));
  });
  searchToggle?.addEventListener('click', () => {
    searchInput?.classList.toggle('is-open');
    if (searchInput?.classList.contains('is-open')) {
      searchInput.focus();
    } else {
      searchInput.value = '';
      render(sortList(applyFilters(allItems)));
    }
  });
  searchInput?.addEventListener('input', () => {
    render(sortList(applyFilters(allItems)));
  });
  filterToggle?.addEventListener('click', () => {
    filterPanel?.classList.toggle('visible');
    if (filterToggle && filterPanel) {
      filterToggle.classList.toggle('is-active', filterPanel.classList.contains('visible'));
    }
  });
  filterPanel?.addEventListener('change', () => {
    render(sortList(applyFilters(allItems)));
  });
  resetBtn?.addEventListener('click', async () => {
    if (!visibleIds.length) return;
    if (!confirm(`Delete ${visibleIds.length} roulette(s)? This cannot be undone.`)) return;
    await fetch('delete_all_roulettes.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...csrfHeaders },
      body: JSON.stringify({ ids: visibleIds })
    });
    load();
  });

  document.addEventListener('DOMContentLoaded', () => {
    applySavedSort();
    load();
  });
})();
