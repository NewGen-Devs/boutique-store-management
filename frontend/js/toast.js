/**
 * Premium Toast Notification Service
 */
class ToastService {
    constructor() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    }

    show({ title, message, type = 'info', duration = 4000 }) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        let icon = 'info';
        if (type === 'success') icon = 'check-circle';
        if (type === 'error') icon = 'alert-circle';
        if (type === 'warning') icon = 'alert-triangle';

        toast.innerHTML = `
      <div class="toast-icon">
        <i data-lucide="${icon}"></i>
      </div>
      <div class="toast-content">
        ${title ? `<div class="toast-title">${title}</div>` : ''}
        <div class="toast-message">${message}</div>
      </div>
      <div class="toast-close">
        <i data-lucide="x"></i>
      </div>
      <div class="toast-progress"></div>
    `;

        this.container.appendChild(toast);

        // Animate in
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        // Initialize icons
        if (window.lucide) {
            lucide.createIcons({
                attrs: {
                    style: 'width: 18px; height: 18px; stroke-width: 2.5px;'
                },
                nameAttr: 'data-lucide',
                icons: undefined
            });
        }

        const progress = toast.querySelector('.toast-progress');
        progress.style.animation = `progress ${duration}ms linear forwards`;

        const closeBtn = toast.querySelector('.toast-close');
        const removeToast = () => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 400);
        };

        closeBtn.onclick = removeToast;

        if (duration > 0) {
            setTimeout(removeToast, duration);
        }
    }

    success(message, title = 'Success') {
        this.show({ title, message, type: 'success' });
    }

    error(message, title = 'Error') {
        this.show({ title, message, type: 'error' });
    }

    warning(message, title = 'Warning') {
        this.show({ title, message, type: 'warning' });
    }

    info(message, title = 'Info') {
        this.show({ title, message, type: 'info' });
    }
}

// Global instance
window.Toast = new ToastService();
