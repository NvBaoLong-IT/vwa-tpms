import './bootstrap';
import { startWebSchedule } from './web-schedule';
import { showFlashNotifications } from './flash-notifications';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const flashNotifications = document.getElementById('flash-notifications');
if (flashNotifications) {
    document.querySelector('.global-validation-summary')?.remove();
    void showFlashNotifications(flashNotifications);
}

const scheduleUrl = document.querySelector('meta[name="web-schedule-url"]')?.content;
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
if (scheduleUrl && csrfToken) startWebSchedule({ url: scheduleUrl, csrfToken });

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.querySelector('input[type="file"]')) return;
    const button = form.querySelector('button[type="submit"], button:not([type])');
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span aria-hidden="true" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent"></span> Đang xử lý...';
    }
    form.setAttribute('aria-busy', 'true');
});
