/**
 * Compte à rebours de déconnexion automatique (config jibayat.auto_logout_minutes).
 * Toute interaction remet le compteur local à zéro ; le serveur fait foi (middleware AutoLogout) :
 * à l'échéance locale, on interroge /session/statut puis on recharge la page (➜ connexion).
 */
const root = document.querySelector('[data-auto-logout]');
if (root) {
    const limit = parseInt(root.dataset.autoLogout, 10) * 60;
    const statusUrl = root.dataset.statusUrl;
    const label = document.querySelector('[data-session-countdown]');
    let last = Date.now();
    let lastServerTouch = Date.now();

    const reset = () => {
        last = Date.now();
        // Prolonge la session côté serveur au plus une fois par minute pendant l'activité.
        if (Date.now() - lastServerTouch > 60_000) {
            lastServerTouch = Date.now();
            fetch(root.dataset.touchUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        }
    };
    ['click', 'keydown', 'scroll', 'touchstart'].forEach((e) => window.addEventListener(e, reset, { passive: true }));

    setInterval(async () => {
        const remaining = limit - Math.floor((Date.now() - last) / 1000);
        if (label) {
            const m = Math.max(0, Math.floor(remaining / 60));
            const s = String(Math.max(0, remaining % 60)).padStart(2, '0');
            label.textContent = `${m}:${s}`;
        }
        if (remaining <= 0) {
            try {
                const r = await fetch(statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const data = r.ok ? await r.json() : { remaining: 0 };
                if (data.remaining > 5) { last = Date.now() - (limit - data.remaining) * 1000; return; }
            } catch (_) { /* hors ligne : on recharge */ }
            window.location.reload();
        }
    }, 1000);
}
