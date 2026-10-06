import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

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
