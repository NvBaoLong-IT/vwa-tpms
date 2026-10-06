const tickUrl = document.currentScript.dataset.tickUrl;

function checkScheduledTasks() {
    fetch(tickUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).catch(() => {});
}

checkScheduledTasks();
setInterval(checkScheduledTasks, 60_000);
