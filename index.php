<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Radio a Colori - Live</title>
  <style>
    body { font-family: sans-serif; background: #f4f4f9; padding: 15px; }
    .container { max-width: 850px; margin: auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; }
    .main-logo { height: 120px; margin-bottom: 10px; }
    .btn-direct { display: inline-block; background: #d32f2f; color: white !important; padding: 15px 30px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 1.2em; margin: 10px 0; }
    .on-air { background: #fff3e0; padding: 15px; border-radius: 8px; border-left: 6px solid #ff9800; margin: 15px 0; text-align: left; }
    .track-title { font-size: 1.6em; font-weight: bold; color: #e65100; display: block; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; text-align: left; }
    th { background: #444; color: #fff; padding: 10px; }
    td { padding: 10px; border-bottom: 1px solid #eee; }
    .row-now { background: #fff9c4; font-weight: bold; }
    .footer { font-size: 0.9em; color: #555; margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px; line-height: 1.6; }
    .muted { color:#666; font-size: 0.95em; }
  </style>
</head>
<body>
<div class="container">
  <img src="logo.jpg" class="main-logo" alt="Radio a Colori">
  <p><strong>I Colori del Navile APS presentano Radio a Colori</strong><br></p>

  <a href="http://radioacolori.net:8000/stream" target="_blank" class="btn-direct">▶ SUONA IN DIRETTA</a>

  <div class="on-air">
    <b style="color:blue">STATE ASCOLTANDO</b>
    <div id="now_box" class="muted">Caricamento...</div>
  </div>

  <p>Cambio brano tra: <b id="cdw">--</b> s</p>

  <table>
    <thead><tr><th>Ora</th><th>ID</th><th>Brano</th><th>Durata</th></tr></thead>
    <tbody id="tbody"></tbody>
  </table>

  <div class="footer">
    <strong>Powered by I Colori del Navile APS</strong><br>
    Musica libera con licenza <strong>CC-BY</strong><br>
    Email info at radioacolori.net | CF 91357680379 - ROC 33355
  </div>
</div>

<script>
  // === Config ===
  const ENDPOINT = 'live_data.php?format=json';
  const POLL_MS = 8000;

  // === State ===
  let lastPayload = null;

  // Per countdown robusto: uso il tempo server + il tempo trascorso localmente
  let serverNowAtFetch = 0;        // secondi (float) dal server
  let perfAtFetch = 0;             // performance.now() al momento del fetch

  function effectiveServerNowSec() {
    if (!serverNowAtFetch) return Date.now() / 1000;
    const elapsed = (performance.now() - perfAtFetch) / 1000;
    return serverNowAtFetch + elapsed;
  }

  function render(payload) {
    lastPayload = payload;
    serverNowAtFetch = payload.server_now || 0;
    perfAtFetch = performance.now();

    const nowBox = document.getElementById('now_box');
    const cdw = document.getElementById('cdw');
    const tbody = document.getElementById('tbody');

    // NOW PLAYING
    if (payload.current) {
      const c = payload.current;
      nowBox.innerHTML =
        `<span class="track-title">${escapeHtml(c.title)}</span>` +
        `di <b>${escapeHtml(c.author || '')}</b> <small>(ID: ${escapeHtml(c.id)})</small>`;
    } else {
      nowBox.textContent = 'Nessun brano in palinsesto in questo momento.';
    }

    // TABLE
    tbody.innerHTML = '';
    for (const it of (payload.items || [])) {
      const tr = document.createElement('tr');
      if (it.is_now) tr.className = 'row-now';

      tr.innerHTML =
        `<td>${escapeHtml(it.time_local || '')}</td>` +
        `<td>${escapeHtml(it.id)}</td>` +
        `<td>${escapeHtml(it.title)} - <small>${escapeHtml(it.author || '')}</small></td>` +
        `<td>${Number(it.dur || 0)}s</td>`;

      tbody.appendChild(tr);
    }

    // Set initial countdown immediately
    updateCountdown();
  }

  function updateCountdown() {
    const cdw = document.getElementById('cdw');

    if (!lastPayload || !lastPayload.current) {
      cdw.textContent = '--';
      return;
    }

    const nowServer = effectiveServerNowSec();
    const end = lastPayload.current.end; // int seconds
    let remaining = Math.ceil(end - nowServer);
    if (remaining < 0) remaining = 0;

    cdw.textContent = remaining;

    // Se è finito, riallinea subito col server (non fidarti del client)
    if (remaining === 0) {
      fetchAndRender();
    }
  }

  async function fetchAndRender() {
    try {
      // anti-cache extra lato client
      const url = ENDPOINT + '&_=' + Date.now();
      const res = await fetch(url, { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const payload = await res.json();
      render(payload);
    } catch (e) {
      // Non spaccare la pagina: lascia l'ultimo stato e riprova al prossimo giro
      const nowBox = document.getElementById('now_box');
      if (nowBox && (!lastPayload)) nowBox.textContent = 'Errore caricamento dati.';
      console.error(e);
    }
  }

  function escapeHtml(s) {
    return String(s ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  // Poll lento per riallineare
  setInterval(fetchAndRender, POLL_MS);

  // Countdown tick ogni secondo (solo UI, non decide lo stato)
  setInterval(updateCountdown, 1000);

  // IMPORTANTISSIMO: quando torni in tab, riallinea subito
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) fetchAndRender();
  });
  window.addEventListener('focus', fetchAndRender);

  // Initial load
  fetchAndRender();
</script>
</body>
</html>
