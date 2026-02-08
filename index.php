<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Radio a Colori - Live</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f9; padding: 15px; }
        .container {width:100%;max-width:none;margin:0 auto;background:#fff;padding:20px;border-radius:10px;box-shadow:0 4px 10px rgba(0,0,0,0.1);text-align:center;box-sizing:border-box;}
        .main-logo { height: 120px; margin-bottom: 10px; }
        .top-search { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid #ddd; text-align: center; }
        .btn-direct { display: inline-block; background: #d32f2f; color: white !important; padding: 15px 30px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 1.2em; margin: 10px 0; }
        .on-air { background: #fff3e0; padding: 15px; border-radius: 8px; border-left: 6px solid #ff9800; margin: 15px 0; text-align: left; }
        .track-title { font-size: 1.6em; font-weight: bold; color: #e65100; display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; text-align: left; }
        th { background: #444; color: #fff; padding: 10px; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        .row-now { background: #fff9c4; font-weight: bold; }
        .footer { font-size: 0.9em; color: #555; margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px; line-height: 1.6; }
    </style>
</head>
<body>

<div class="container">
    <img src="logo.jpg" class="main-logo" alt="Radio a Colori">
    <p><strong>I Colori del Navile APS presentano Radio a Colori</strong><br></p>

    <div class="top-search">
        <form id="searchForm">
            Cerca ID:
            <input type="text" id="search_id" name="myid" value="" style="width:60px; padding:5px;">
            <button type="submit">Cerca</button>
            <a href="#" id="resetBtn" style="margin-left:5px; color:#666; font-size:0.8em; display:none;">[Reset]</a>
        </form>

        <div id="searchResult" style="display:none; background:#e8f5e9; padding:10px; margin-top:10px; border-radius:5px;"></div>
    </div>

    <a href="http://radioacolori.net:8000/stream" target="_blank" class="btn-direct">▶ SUONA IN DIRETTA</a>

    <div class="on-air">
        <b style="color:blue">STATE ASCOLTANDO</b>
        <div id="nowPlaying"></div>
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
    // ---- Config ----
    const LIVE_URL  = "live_data.php?action=live&format=json";
    const TRACK_URL = "live_data.php?action=track&format=json&id=";
    const POLL_MS = 8000;

    // ---- State for robust countdown ----
    let lastPayload = null;
    let serverNowAtFetch = 0;   // seconds float
    let perfAtFetch = 0;

    function effectiveServerNowSec() {
        if (!serverNowAtFetch) return Date.now() / 1000;
        const elapsed = (performance.now() - perfAtFetch) / 1000;
        return serverNowAtFetch + elapsed;
    }

    function escapeHtml(s) {
        return String(s ?? '')
            .replaceAll('&','&amp;')
            .replaceAll('<','&lt;')
            .replaceAll('>','&gt;')
            .replaceAll('"','&quot;')
            .replaceAll("'","&#039;");
    }

    async function fetchLive() {
        const url = LIVE_URL + "&_=" + Date.now(); // anti-cache extra
        const res = await fetch(url, { cache: "no-store" });
        if (!res.ok) throw new Error("HTTP " + res.status);
        return await res.json();
    }

    function renderLive(payload) {
        lastPayload = payload;
        serverNowAtFetch = payload.server_now || 0;
        perfAtFetch = performance.now();

        // Now playing
        const nowBox = document.getElementById("nowPlaying");
        if (payload.current) {
            const c = payload.current;
            nowBox.innerHTML =
                `<span class="track-title">${escapeHtml(c.title)}</span>` +
                `di <b>${escapeHtml(c.author || "")}</b> <small>(ID: ${escapeHtml(c.id)})</small>`;
        } else {
            nowBox.innerHTML = `<span style="color:#666">Nessun brano in palinsesto in questo momento.</span>`;
        }

        // Table
        const tbody = document.getElementById("tbody");
        tbody.innerHTML = "";
        for (const it of (payload.items || [])) {
            const tr = document.createElement("tr");
            if (it.is_now) tr.classList.add("row-now");
            tr.innerHTML =
                `<td>${escapeHtml(it.time_local || "")}</td>` +
                `<td>${escapeHtml(it.id)}</td>` +
                `<td>${escapeHtml(it.title)} - <small>${escapeHtml(it.author || "")}</small></td>` +
                `<td>${Number(it.dur || 0)}s</td>`;
            tbody.appendChild(tr);
        }

        updateCountdown();
    }

    function updateCountdown() {
        const cdw = document.getElementById("cdw");

        if (!lastPayload || !lastPayload.current) {
            cdw.textContent = "--";
            return;
        }

        const nowServer = effectiveServerNowSec();
        const end = lastPayload.current.end; // int seconds
        let remaining = Math.ceil(end - nowServer);
        if (remaining < 0) remaining = 0;

        cdw.textContent = remaining;

        // Quando arriva a 0 riallinea dal server (evita stati "stale")
        if (remaining === 0) {
            refreshLive();
        }
    }

    async function refreshLive() {
        try {
            const payload = await fetchLive();
            renderLive(payload);
        } catch (e) {
            console.error(e);
        }
    }

    // ---- Search track by ID (no reload) ----
    async function searchTrack(id) {
        const url = TRACK_URL + encodeURIComponent(id) + "&_=" + Date.now();
        const res = await fetch(url, { cache: "no-store" });
        if (!res.ok) throw new Error("HTTP " + res.status);
        return await res.json();
    }

    function showSearchResult(ok, msgHtml) {
        const box = document.getElementById("searchResult");
        const reset = document.getElementById("resetBtn");

        box.style.display = "block";
        box.innerHTML = msgHtml;
        reset.style.display = "inline";
    }

    function clearSearchResult() {
        document.getElementById("search_id").value = "";
        document.getElementById("searchResult").style.display = "none";
        document.getElementById("searchResult").innerHTML = "";
        document.getElementById("resetBtn").style.display = "none";
    }

    document.getElementById("searchForm").addEventListener("submit", async (ev) => {
        ev.preventDefault();
        const id = document.getElementById("search_id").value.trim();
        if (!id) return;

        try {
            const data = await searchTrack(id);
            if (data.ok && data.track) {
                showSearchResult(true,
                    `<b>Trovato:</b> ${escapeHtml(data.track.title)} - ${escapeHtml(data.track.author || "")}`
                );
            } else {
                showSearchResult(false, `<b>Non trovato</b>`);
            }
        } catch (e) {
            console.error(e);
            showSearchResult(false, `<b>Errore ricerca</b>`);
        }
    });

    document.getElementById("resetBtn").addEventListener("click", (ev) => {
        ev.preventDefault();
        clearSearchResult();
    });

    // ---- Timers / focus handling ----
    setInterval(refreshLive, POLL_MS);     // poll lento
    setInterval(updateCountdown, 1000);   // solo UI

    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) refreshLive();  // riallinea appena torni visibile
    });
    window.addEventListener("focus", refreshLive);

    // initial load
    refreshLive();
</script>
</body>
</html>
