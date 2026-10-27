import Swal from 'sweetalert2';

export async function showFlashNotifications(element, alert = Swal) {
    if (!element) return;
    let notifications;
    try {
        notifications = JSON.parse(element.dataset.notifications);
    } catch {
        return;
    }
    if (!Array.isArray(notifications)) return;
    for (const notification of notifications) {
        const toast = notification.toast === true;
        await alert.fire({
            icon: notification.icon,
            titleText: notification.title,
            text: notification.text,
            toast,
            position: toast ? 'top-end' : 'center',
            timer: toast ? 5500 : undefined,
            timerProgressBar: toast,
            showConfirmButton: !toast,
            confirmButtonText: 'Đã hiểu',
            showCloseButton: true,
            closeButtonAriaLabel: 'Đóng thông báo',
            confirmButtonColor: '#168f88',
            customClass: { popup: 'vwa-flash-popup' },
            didOpen: toast ? popup => {
                popup.addEventListener('mouseenter', () => alert.stopTimer());
                popup.addEventListener('mouseleave', () => alert.resumeTimer());
            } : undefined,
        });
    }
}
