export function startWebSchedule({ url, csrfToken, fetchRequest = globalThis.fetch, schedule = globalThis.setTimeout, isVisible = () => !document.hidden }) {
    let stopped = false;

    async function tick() {
        if (stopped) return;
        if (isVisible()) {
            try {
                const response = await fetchRequest(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    redirect: 'manual',
                    headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                });
                if (response.type === 'opaqueredirect' || [401, 403, 419].includes(response.status)) {
                    stopped = true;
                }
            } catch {
                // Retry on the next interval if the connection is interrupted.
            }
        }
        if (!stopped) schedule(tick, 60000);
    }

    void tick();
}
