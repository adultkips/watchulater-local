(() => {
  const $ = (s, r=document) => r.querySelector(s);
  const $$ = (s, r=document) => Array.from(r.querySelectorAll(s));
  const csrfToken = window.CSRF_TOKEN || '';
  const csrfHeaders = csrfToken ? { 'X-CSRF-Token': csrfToken } : {};

  const form = $('#cb-form');
  const nameInput = $('#cb-name');
  const coverInput = $('#cb-cover');
  const coverUpload = $('#cover-upload');
  const coverFile = $('#cover-file');
  const coverOptions = $$('.cover-option');
  const moviesWrap = $('#movies-sections');
  const showsWrap = $('#shows-sections');
  const genreBlock = $('#genre-block');
  const genreWrap = $('#genre-chips');
  const genreStatus = $('#genre-status');
  const genreHint = $('#genre-hint');
  const genresSelectAll = $('#genres-select-all');
  const genresReset = $('#genres-reset');
  const msg = $('#cb-message');
  const btnMoviesAll = $('#movies-select-all');
  const btnShowsAll = $('#shows-select-all');
  const btnMoviesReset = $('#movies-reset');
  const btnShowsReset = $('#shows-reset');
  const stepper = $('#cb-stepper');
  const stepBack = $('#step-back');
  const stepNext = $('#step-next');
  const stepSave = $('#step-save');
  const panels = $$('.step-panel');
  const ratingChips = $('#rating-chips');
  const ratingsSelectAll = $('#ratings-select-all');
  const ratingsReset = $('#ratings-reset');
  const recommendedOnly = $('#recommended-only');
  let step = 1;
  let ratingValues = [];

  function toast(text, type='info'){
    if(!msg) return;
    msg.textContent = text || '';
    msg.dataset.type = type;
  }

  function showStep(n){
    step = n;
    panels.forEach(p => p.classList.toggle('is-active', Number(p.dataset.step) === step));
    if (stepper) stepper.textContent = `Step ${step} of 2`;
    if (stepBack) stepBack.style.display = step === 1 ? 'none' : '';
    if (stepNext) stepNext.style.display = step === 2 ? 'none' : '';
    if (stepSave) stepSave.style.display = step === 2 ? '' : 'none';
    if (step === 2) {
      if (genreHint) genreHint.textContent = 'Loading genres...';
      setGenreStatus('');
      loadGenres();
    }
    updateNextState();
  }

  function canProceed(){
    if (!nameInput.value.trim()) return false;
    const { keys: movieKeys } = getSelected(moviesWrap);
    const { keys: showKeys } = getSelected(showsWrap);
    if (movieKeys.length === 0 && showKeys.length === 0) return false;
    if (movieKeys.length > 0 && showKeys.length > 0) return false;
    return true;
  }

  function updateNextState(){
    if (!stepNext) return;
    if (step !== 1) return;
    stepNext.disabled = !canProceed();
  }

  function chip({key, title}){
    const el = document.createElement('button');
    el.type = 'button';
    el.className = 'chip';
    el.dataset.key = String(key);
    el.dataset.title = String(title ?? key);
    el.addEventListener('click', () => toggleChip(el));
    el.textContent = title ?? String(key);
    return el;
  }

  function toggleChip(el){
    const group = el.closest('#movies-sections, #shows-sections');
    if (group && group.classList.contains('is-locked')) return;
    el.classList.toggle('is-selected');
    updateTypeLock();
    updateSectionControls();
    updateNextState();
  }

  function getSelected(wrap){
    const chips = $$('.chip.is-selected', wrap);
    const keys = [];
    const titles = [];
    chips.forEach(ch => {
      const k = ch.dataset.key;
      const t = ch.dataset.title ?? '';
      if(k){ keys.push(k); titles.push(t); }
    });
    return { keys, titles, count: chips.length };
  }

  function lockGroup(wrap, lock){
    if (!wrap) return;
    wrap.classList.toggle('is-locked', !!lock);
    $$('.chip', wrap).forEach(ch => {
      ch.classList.toggle('is-disabled', !!lock);
    });
  }

  function updateTypeLock(){
    const movies = getSelected(moviesWrap).count;
    const shows = getSelected(showsWrap).count;
    if (movies > 0 && shows === 0) {
      lockGroup(showsWrap, true);
      lockGroup(moviesWrap, false);
    } else if (shows > 0 && movies === 0) {
      lockGroup(moviesWrap, true);
      lockGroup(showsWrap, false);
    } else {
      lockGroup(moviesWrap, false);
      lockGroup(showsWrap, false);
    }
  }

  function updateSectionControls(){
    if (btnMoviesAll) {
      const total = $$('#movies-sections .chip').length;
      const selected = $$('#movies-sections .chip.is-selected').length;
      btnMoviesAll.classList.toggle('is-active', total > 0 && selected === total);
    }
    if (btnShowsAll) {
      const total = $$('#shows-sections .chip').length;
      const selected = $$('#shows-sections .chip.is-selected').length;
      btnShowsAll.classList.toggle('is-active', total > 0 && selected === total);
    }
  }

  function selectAll(wrap, on){
    if (!wrap) return;
    if (wrap.classList.contains('is-locked')) return;
    $$('.chip', wrap).forEach(ch => ch.classList.toggle('is-selected', on));
    updateTypeLock();
    updateSectionControls();
    updateNextState();
  }

  function setGenreStatus(text, type=''){
    if(!genreStatus) return;
    genreStatus.textContent = text || '';
    genreStatus.className = 'status' + (type ? ' ' + type : '');
  }

  function renderGenres(genres, selected = null){
    if (!genreWrap) return;
    genreWrap.innerHTML = '';
    const selectAll = selected === '__all__';
    const picked = Array.isArray(selected) ? selected : [];
    genres.forEach(g => {
      const label = document.createElement('label');
      label.className = 'genre-chip';
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = g;
      cb.checked = selectAll ? true : (picked.length ? picked.includes(g) : false);
      const span = document.createElement('span');
      span.textContent = g;
      label.appendChild(cb);
      label.appendChild(span);
      genreWrap.appendChild(label);
    });
    genreWrap.querySelectorAll('input').forEach(cb => {
      cb.addEventListener('change', () => {
        updateSaveLabel();
        updateGenreControls();
      });
    });
    updateGenreControls();
  }

  function renderRatings(selected = []){
    if (!ratingChips) return;
    ratingChips.innerHTML = '';
    const values = [0.5,1,1.5,2,2.5,3,3.5,4,4.5,5];
    const selectAll = selected === '__all__';
    const picked = Array.isArray(selected) ? selected : [];
    values.forEach(v => {
      const label = document.createElement('label');
      label.className = 'genre-chip';
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = String(v);
      cb.checked = selectAll ? true : (picked.length ? picked.includes(v) : false);
      const span = document.createElement('span');
      span.textContent = `${v}★`;
      label.appendChild(cb);
      label.appendChild(span);
      ratingChips.appendChild(label);
    });
    ratingChips.querySelectorAll('span').forEach(s => {    });
    ratingChips.querySelectorAll('input').forEach(cb => {
      cb.addEventListener('change', updateRatingControls);
    });
    updateRatingControls();
  }

  function getSelectedRatings(){
    if (!ratingChips) return [];
    return $$('#rating-chips input:checked').map(i => Number(i.value));
  }

  function getSelectedGenres(){
    if (!genreWrap) return [];
    return $$('#genre-chips input:checked').map(i => i.value);
  }

  function getGenreCounts(){
    const total = $$('#genre-chips input').length;
    const selected = getSelectedGenres().length;
    return { total, selected };
  }

  function getRatingCounts(){
    const total = $$('#rating-chips input').length;
    const selected = getSelectedRatings().length;
    return { total, selected };
  }

  function updateGenreControls(){
    if (!genresSelectAll) return;
    const { total, selected } = getGenreCounts();
    genresSelectAll.classList.toggle('is-active', total > 0 && selected === total);
  }

  function updateRatingControls(){
    if (!ratingsSelectAll) return;
    const { total, selected } = getRatingCounts();
    ratingsSelectAll.classList.toggle('is-active', total > 0 && selected === total);
  }

  function updateSaveLabel(){
    if (!stepSave) return;
    stepSave.textContent = 'Save';
    stepSave.disabled = false;
  }

  function syncCoverSelection(){
    if (!coverOptions.length) return;
    const value = (coverInput?.value || '').trim();
    coverOptions.forEach(btn => {
      btn.classList.toggle('is-selected', btn.dataset.cover === value);
    });
  }

  function selectAllGenres(){
    if (!genreWrap) return;
    $$('#genre-chips input').forEach(cb => {
      cb.checked = true;
    });
    updateSaveLabel();
    updateGenreControls();
  }

  function resetGenres(){
    if (!genreWrap) return;
    $$('#genre-chips input').forEach(cb => {
      cb.checked = false;
    });
    updateSaveLabel();
    updateGenreControls();
  }

  let lastGenreKey = '';
  async function loadGenres(selectedPrefill = null){
    if (step !== 2 && selectedPrefill === null) return;
    if (!genreBlock || !genreWrap) return;
    const movies = getSelected(moviesWrap).keys;
    const shows = getSelected(showsWrap).keys;
    const type = movies.length ? 'movie' : (shows.length ? 'show' : '');
    const keys = movies.length ? movies : shows;
    if (!type || keys.length === 0) {
      genreBlock.style.display = 'none';
      genreWrap.innerHTML = '';
      setGenreStatus('');
      return;
    }
    const key = type + ':' + keys.slice().sort().join(',');
    if (key === lastGenreKey && selectedPrefill === null) {
      if (genreHint) genreHint.textContent = 'Optional: Unselect genres';
      setGenreStatus('');
      genreWrap.classList.remove('is-loading');
      updateSaveLabel();
      return;
    }
    lastGenreKey = key;
    genreBlock.style.display = 'block';
    if (genreHint) genreHint.textContent = 'Loading genres...';
    setGenreStatus('');
    genreWrap.classList.add('is-loading');
    try {
      const res = await fetch('plex_genres.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders },
        body: JSON.stringify({ type, keys })
      });
      let text = await res.text();
      if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
      const data = JSON.parse(text);
      if (data && Array.isArray(data.genres) && data.genres.length) {
        renderGenres(data.genres, selectedPrefill === null ? '__all__' : selectedPrefill);
      if (genreHint) genreHint.textContent = 'Optional: Unselect genres';
        setGenreStatus('');
        updateSaveLabel();
      } else if (!res.ok || data.error) {
        throw new Error(data.error || (data.errors && data.errors[0]) || 'error');
      } else {
        renderGenres([], selectedPrefill);
        if (genreHint) genreHint.textContent = 'No genres found.';
        setGenreStatus('');
        updateSaveLabel();
      }
    } catch (_) {
      setGenreStatus('Could not load genres.', 'error');
    } finally {
      genreWrap.classList.remove('is-loading');
    }
  }

  async function loadSections(){
    toast('Loading sections...');
    try {
      const [moviesRes, showsRes] = await Promise.all([
        fetch('getsections.php?type=movie', { cache: 'no-store' }),
        fetch('getsections.php?type=show', { cache: 'no-store' })
      ]);
      const movies = await moviesRes.json();
      const shows = await showsRes.json();

      moviesWrap.innerHTML = '';
      showsWrap.innerHTML = '';

      (movies || []).forEach(s => moviesWrap.appendChild(chip({ key: s.key, title: s.title })));
      (shows || []).forEach(s => showsWrap.appendChild(chip({ key: s.key, title: s.title })));

      if (!$('.chip', moviesWrap)) moviesWrap.textContent = 'No movie sections found.';
      if (!$('.chip', showsWrap)) showsWrap.textContent = 'No show sections found.';
      toast('');
    } catch (e) {
      toast('Could not load sections.', 'error');
    } finally {
      updateTypeLock();
      updateSectionControls();
      updateSectionControls();
      updateNextState();
    }
  }

  async function prefill(){
    const params = new URLSearchParams(location.search);
    const editId = params.get('id');
    if (!editId) return;

    const data = await fetch('get_battle_edit.php?id=' + encodeURIComponent(editId), { cache: 'no-store' }).then(r => r.json());
    if (data && !data.error) {
      document.getElementById('page-title').textContent = 'Edit battle';
      nameInput.value = data.name || '';
      coverInput.value = data.cover_image || '';
      const mark = (wrap, csv) => {
        if (!wrap || !csv) return;
        const keys = String(csv).split(',').map(s => s.trim()).filter(Boolean);
        keys.forEach(k => {
          const ch = wrap.querySelector(`.chip[data-key="${CSS.escape(k)}"]`);
          if (ch) ch.classList.add('is-selected');
        });
      };
      mark(moviesWrap, data.type === 'movie' ? data.source_key : '');
      mark(showsWrap, data.type === 'show' ? data.source_key : '');
      updateTypeLock();
      syncCoverSelection();
      const savedGenres = (data.genre_filter || '').split(',').map(s => s.trim()).filter(Boolean);
      const genreAll = Number(data.genre_all || 0) === 1;
      loadGenres(genreAll ? '__all__' : savedGenres);
      const savedRatings = (data.rating_filter || '').split(',').map(s => Number(s.trim())).filter(v => !Number.isNaN(v));
      if (savedRatings.length) {
        ratingValues = savedRatings;
        renderRatingsOverride(savedRatings);
      } else if (data.rating_min) {
        ratingValues = [Number(data.rating_min)];
        renderRatingsOverride(ratingValues);
      } else {
        renderRatingsOverride('__all__');
      }
      if (recommendedOnly) recommendedOnly.checked = Number(data.recommended_only || 0) === 1;
      updateNextState();
    }
  }

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = nameInput.value.trim();
    const { keys: movieKeys, titles: movieTitles } = getSelected(moviesWrap);
    const { keys: showKeys, titles: showTitles } = getSelected(showsWrap);

    if (!name) { toast('Name is required.', 'error'); return; }
    if (movieKeys.length === 0 && showKeys.length === 0) { return; }
    if (movieKeys.length > 0 && showKeys.length > 0) { toast('Do not mix movie and show sections.', 'error'); return; }

    const type = movieKeys.length ? 'movie' : 'show';
    const payload = {
      name,
      cover_image: coverInput.value.trim(),
      type,
      source_key: (type === 'movie' ? movieKeys : showKeys).join(','),
      source_title: (type === 'movie' ? movieTitles : showTitles).join(','),
      genre_filter: getSelectedGenres().join(','),
      rating_filter: getSelectedRatings().join(','),
      recommended_only: recommendedOnly?.checked ? 1 : 0
    };
    const { total, selected } = getGenreCounts();
    if (total > 0 && selected === total) payload.genre_all = 1;

    const params = new URLSearchParams(location.search);
    const editId = params.get('id');
    if (editId) payload.id = editId;

    const res = await fetch('save_battle.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...csrfHeaders },
      body: JSON.stringify(payload)
    });
    const data = await res.json().catch(()=>({}));
    if (res.ok && data.success) {
      window.location.href = 'battles.php';
    } else {
      toast('Save failed.', 'error');
    }
  });

  btnMoviesAll?.addEventListener('click', () => {
    const anySelected = $$('.chip.is-selected', moviesWrap).length > 0;
    selectAll(moviesWrap, !anySelected);
  });
  btnShowsAll?.addEventListener('click', () => {
    const anySelected = $$('.chip.is-selected', showsWrap).length > 0;
    selectAll(showsWrap, !anySelected);
  });
  btnMoviesReset?.addEventListener('click', () => {
    selectAll(moviesWrap, false);
  });
  btnShowsReset?.addEventListener('click', () => {
    selectAll(showsWrap, false);
  });

  stepBack?.addEventListener('click', () => {
    if (step > 1) showStep(step - 1);
  });
  stepNext?.addEventListener('click', () => {
    if (step === 1) {
      if (!nameInput.value.trim()) { toast('Name is required.', 'error'); return; }
      const { keys: movieKeys } = getSelected(moviesWrap);
      const { keys: showKeys } = getSelected(showsWrap);
      if (movieKeys.length === 0 && showKeys.length === 0) { return; }
      if (movieKeys.length > 0 && showKeys.length > 0) { toast('Do not mix movie and show sections.', 'error'); return; }
      showStep(2);
    }
  });

  stepSave?.addEventListener('click', () => {
    if (step !== 2) return;
  });


  nameInput?.addEventListener('input', () => {
    updateNextState();
  });

  coverUpload?.addEventListener('click', () => {
    coverFile?.click();
  });

  coverFile?.addEventListener('change', () => {
    const file = coverFile.files && coverFile.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      toast('Please select an image file.', 'error');
      coverFile.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      coverInput.value = String(reader.result || '');
      toast('');
      syncCoverSelection();
    };
    reader.onerror = () => {
      toast('Could not read image file.', 'error');
    };
    reader.readAsDataURL(file);
  });

  coverOptions.forEach(btn => {
    btn.addEventListener('click', () => {
      const val = btn.dataset.cover || '';
      if (val) {
        coverInput.value = val;
        syncCoverSelection();
      }
    });
  });

  coverInput?.addEventListener('input', () => {
    syncCoverSelection();
  });

  genresSelectAll?.addEventListener('click', () => {
    selectAllGenres();
  });
  genresReset?.addEventListener('click', () => {
    resetGenres();
  });
  ratingsSelectAll?.addEventListener('click', () => {
    selectAllRatings();
  });
  ratingsReset?.addEventListener('click', () => {
    resetRatings();
  });

  function renderRatingsOverride(selected = '__all__'){
    if (!ratingChips) return;
    ratingChips.innerHTML = '';
    const values = [0.5,1,1.5,2,2.5,3,3.5,4,4.5,5];
    const selectAll = selected === '__all__';
    const picked = Array.isArray(selected) ? selected : [];
    values.forEach(v => {
      const label = document.createElement('label');
      label.className = 'genre-chip';
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = String(v);
      cb.checked = selectAll ? true : (picked.length ? picked.includes(v) : false);
      const span = document.createElement('span');
      span.textContent = `${v}★`;
      label.appendChild(cb);
      label.appendChild(span);
      ratingChips.appendChild(label);
    });
    ratingChips.querySelectorAll('input').forEach(cb => {
      cb.addEventListener('change', updateRatingControls);
    });
    updateRatingControls();
  }

  function selectAllRatings(){
    if (!ratingChips) return;
    $$('#rating-chips input').forEach(cb => {
      cb.checked = true;
    });
    updateRatingControls();
  }

  function resetRatings(){
    if (!ratingChips) return;
    $$('#rating-chips input').forEach(cb => {
      cb.checked = false;
    });
    updateRatingControls();
  }

  loadSections().then(() => {
    renderRatingsOverride('__all__');
    selectAllRatings();
  }).then(prefill).then(() => {
    syncCoverSelection();
    showStep(1);
  });
})();

