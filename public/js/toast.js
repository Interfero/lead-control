/**
 * Toast-уведомления для Levelion CRM
 * Этап 12: UX/UI улучшения
 */
class Toast {
    static container = null;
    
    static init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    }
    
    static show(message, type = 'info', duration = 4000) {
        this.init();
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <span>${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        this.container.appendChild(toast);
        
        // Автоматическое скрытие
        if (duration > 0) {
            setTimeout(() => {
                toast.classList.add('hiding');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
        
        return toast;
    }
    
    static success(message, duration) { return this.show(message, 'success', duration); }
    static error(message, duration) { return this.show(message, 'error', duration); }
    static warning(message, duration) { return this.show(message, 'warning', duration); }
    static info(message, duration) { return this.show(message, 'info', duration); }
}

// Глобальный доступ
window.Toast = Toast;
