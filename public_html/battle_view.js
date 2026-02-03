(() => {
  const csrfToken = window.CSRF_TOKEN || '';
  const csrfHeaders = csrfToken ? { 'X-CSRF-Token': csrfToken } : {};
  const params = new URLSearchParams(location.search);
  const battleId = params.get('id');

  const battleTitle = document.getElementById('battle-title');
  const battleContainer = document.getElementById('battle');
  const rankList = document.getElementById('ranklist');

  let currentBattle = [];

  async function load() {
    if (!battleId) {
      battleContainer.innerHTML = '<p>Missing battle id.</p>';
      return;
    }
    const res = await fetch('battle_data.php?id=' + encodeURIComponent(battleId), { cache: 'no-store' });
    const data = await res.json();
    if (data.name && battleTitle) battleTitle.textContent = data.name;
    if (data.exhausted) {
      const existing = battleContainer.querySelectorAll('.battle-poster');
      if (existing.length) {
        existing.forEach(p => {
          p.classList.remove('is-winner', 'is-loser-left', 'is-loser-right');
        });
        void battleContainer.offsetWidth;
        existing.forEach(p => p.classList.add('is-exit-down'));
        setTimeout(() => {
          renderBattle(data.battle || []);
        }, 260);
      } else {
        renderBattle(data.battle || []);
      }
    } else {
      renderBattle(data.battle || []);
    }
    renderRank(data.ranklist || []);
  }

  function renderBattle(battle) {
    battleContainer.classList.remove('is-resolving');
    if (!battle || battle.length !== 2) {
      const existing = battleContainer.querySelectorAll('.battle-poster');
      if (existing.length) {
        existing.forEach(p => {
          p.classList.remove('is-winner', 'is-loser-left', 'is-loser-right');
        });
        // trigger reflow so exit animation applies cleanly
        void battleContainer.offsetWidth;
        existing.forEach(p => p.classList.add('is-exit-down'));
        setTimeout(() => {
          battleContainer.innerHTML = '<p>No more matches.</p>';
        }, 260);
      } else {
        battleContainer.innerHTML = '<p>No more matches.</p>';
      }
      currentBattle = [];
      return;
    }
    battleContainer.innerHTML = '';
    currentBattle = battle;
    const posterA = createPoster(battle[0], true);
    const posterB = createPoster(battle[1], false);
    const vs = document.createElement('div');
    vs.className = 'vs-divider';
    vs.textContent = 'VS';
    battleContainer.append(posterA, vs, posterB);
  }

  function createPoster(item, isLeft) {
    const img = document.createElement('img');
    img.className = 'battle-poster';
    img.src = item.thumb || 'img/placeholder-poster.png';
    img.alt = item.title || '';
    img.addEventListener('click', () => {
      const winnerId = item.film_id;
      const loser = currentBattle.find(f => f.film_id !== winnerId);
      const loserId = loser?.film_id;
      const winnerSide = isLeft ? 'left' : 'right';
      if (!loserId) return;
      animateWin(img, isLeft);
      setTimeout(() => {
        saveBattle(winnerId, loserId, winnerSide);
      }, 380);
    });
    return img;
  }

  async function saveBattle(winnerId, loserId, winnerSide) {
    await fetch('battle_action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...csrfHeaders },
      body: JSON.stringify({
        battle_id: battleId,
        winner_id: winnerId,
        loser_id: loserId,
        winner_side: winnerSide
      })
    });
    setTimeout(load, 200);
  }

  function animateWin(winnerEl, winnerIsLeft){
    if (!battleContainer) return;
    battleContainer.classList.add('is-resolving');
    const posters = Array.from(battleContainer.querySelectorAll('.battle-poster'));
    posters.forEach(p => {
      if (p === winnerEl) {
        p.classList.add('is-winner');
      } else {
        p.classList.add(winnerIsLeft ? 'is-loser-right' : 'is-loser-left');
      }
    });
  }

  function renderRank(rank) {
    rankList.innerHTML = '';
    const header = document.createElement('div');
    header.className = 'ranklist-header';
    const title = document.createElement('h2');
    title.textContent = 'Ranklist';
    header.appendChild(title);

    if (rank && rank.length > 0) {
      const resetBtn = document.createElement('button');
      resetBtn.id = 'reset-ranklist-btn';
      resetBtn.className = 'btn danger icon-only';
      resetBtn.setAttribute('data-tooltip', 'Reset ranklist');
      resetBtn.setAttribute('aria-label', 'Reset ranklist');
      resetBtn.innerHTML = `
        <span class="icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2m-9 3 1 11h8l1-11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
      `;
      resetBtn.addEventListener('click', async () => {
        if (!confirm('Reset this ranklist?')) return;
        await fetch('reset_battle_ranklist.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', ...csrfHeaders },
          body: JSON.stringify({ battle_id: battleId })
        });
        load();
      });
      header.appendChild(resetBtn);
    }

    rankList.appendChild(header);

    if (!rank || rank.length === 0) {
      const empty = document.createElement('p');
      empty.textContent = 'No ranklist yet.';
      rankList.appendChild(empty);
      return;
    }

    rank.forEach((entry, idx) => {
      const item = document.createElement('div');
      item.className = 'ranklist-item';

      const poster = document.createElement('img');
      poster.className = 'ranklist-poster';
      poster.src = entry.thumb || 'img/placeholder-poster.png';
      poster.alt = entry.title || 'Poster';

      const info = document.createElement('div');
      info.className = 'ranklist-info';

      const titleYear = document.createElement('div');
      titleYear.innerHTML = `<p class="rank-title">${entry.title || 'Untitled'} <span>${entry.year || ''}</span></p>`;

      const scoreInfo = document.createElement('div');
      scoreInfo.className = 'ranklist-score';
      scoreInfo.innerHTML = `<span>Score: ${entry.points}</span><span>|</span><span>Battles: ${entry.battles_played}</span>`;

      const actions = document.createElement('div');
      actions.className = 'ranklist-actions';

      if (entry.plex_url) {
        const plexBtn = document.createElement('a');
        plexBtn.href = entry.plex_url;
        plexBtn.target = '_blank';
        plexBtn.className = 'btn';
        plexBtn.innerHTML = `Open Plex <img src="icons/plexlogo.png" alt="Plex">`;
        actions.appendChild(plexBtn);
      }

      const reset = document.createElement('button');
      reset.className = 'btn danger icon-only';
      reset.setAttribute('data-tooltip', 'Reset entry');
      reset.setAttribute('aria-label', 'Reset entry');
      reset.innerHTML = `
        <span class="icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2m-9 3 1 11h8l1-11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
      `;
      reset.addEventListener('click', async () => {
        if (!confirm('Reset this entry?')) return;
        await fetch('reset_battle_film.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', ...csrfHeaders },
          body: JSON.stringify({ battle_id: battleId, film_id: entry.film_id })
        });
        load();
      });
      actions.appendChild(reset);

      info.append(titleYear, scoreInfo, actions);

      const rankEl = document.createElement('div');
      rankEl.className = 'ranklist-rank';
      rankEl.textContent = `#${idx + 1}`;

      item.append(poster, info, rankEl);
      rankList.appendChild(item);
    });
  }

  load();
})();
