/**
 * Toast Notification Manager
 * Modern toast notifications for user feedback
 */

const ToastManager = {
    container: null,

    /**
     * Initialize toast container
     */
    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container-custom';
            document.body.appendChild(this.container);
        }
    },

    /**
     * Show toast notification
     * @param {string} message Message to display
     * @param {string} type Type: success, error, warning, info
     * @param {number} duration Duration in ms (0 for persistent)
     */
    show(message, type = 'info', duration = 5000) {
        this.init();

        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        const toast = document.createElement('div');
        toast.className = `toast-custom ${type}`;
        toast.innerHTML = `
            <span class="toast-icon">${icons[type]}</span>
            <span class="toast-message">${message}</span>
            <button class="toast-close" aria-label="Close">&times;</button>
        `;

        this.container.appendChild(toast);

        // Close button handler
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => this.remove(toast));

        // Auto-remove after duration
        if (duration > 0) {
            setTimeout(() => this.remove(toast), duration);
        }

        return toast;
    },

    /**
     * Remove toast with animation
     */
    remove(toast) {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 300);
    },

    /**
     * Convenience methods
     */
    success(message, duration = 5000) {
        return this.show(message, 'success', duration);
    },

    error(message, duration = 7000) {
        return this.show(message, 'error', duration);
    },

    warning(message, duration = 6000) {
        return this.show(message, 'warning', duration);
    },

    info(message, duration = 5000) {
        return this.show(message, 'info', duration);
    },

    /**
     * Clear all toasts
     */
    clearAll() {
        if (this.container) {
            const toasts = this.container.querySelectorAll('.toast-custom');
            toasts.forEach(toast => this.remove(toast));
        }
    }
};

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ToastManager.init());
} else {
    ToastManager.init();
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ToastManager;
}
