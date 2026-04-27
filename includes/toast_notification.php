<!-- Toast Notification System -->
<style>
    .toast-container {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 99999 !important;
        pointer-events: none;
        padding: 20px;
        box-sizing: border-box;
    }

    .toast-container.active {
        pointer-events: auto;
    }

    .toast-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9998;
        animation: fadeIn 0.3s ease forwards;
    }

    .toast-overlay.closing {
        animation: fadeOut 0.3s ease forwards;
    }

    .toast-notification {
        position: relative;
        z-index: 9999;
        background: white;
        border-radius: 20px;
        padding: 40px 50px 40px 40px; /* espaço extra para o botão fechar */
        box-shadow: 0 25px 75px rgba(0, 0, 0, 0.35);
        display: flex;
        align-items: center;
        gap: 25px;
        min-width: 300px;
        max-width: 520px;
        pointer-events: auto;
        border: 1px solid rgba(255, 255, 255, 0.5);
        animation: toastSlideIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }

    .toast-notification.closing {
        animation: toastSlideOut 0.4s cubic-bezier(0.4, 0, 0.6, 1) forwards;
    }

    .toast-close {
        position: absolute;
        top: 15px;
        right: 20px;
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #999;
        cursor: pointer;
        padding: 5px;
        line-height: 1;
        transition: color 0.2s;
        z-index: 1;
    }

    .toast-close:hover {
        color: #333;
    }

    .toast-icon {
        font-size: 2.5rem;
        min-width: 60px;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .toast-content {
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex: 1;
    }

    .toast-title {
        font-weight: 700;
        font-size: 1.2rem;
        line-height: 1.3;
        margin: 0;
    }

    .toast-message {
        font-size: 0.95rem;
        opacity: 0.8;
        line-height: 1.4;
        margin: 0;
    }

    .toast-progress {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 5px;
        border-radius: 0 0 20px 20px;
        width: 100%;
    }

    /* Tipos */
    .toast-success { border-left: 6px solid #10b981; }
    .toast-success .toast-icon { color: #10b981; }
    .toast-success .toast-title { color: #059669; }
    .toast-success .toast-progress { background: linear-gradient(90deg, #10b981, transparent); }

    .toast-error { border-left: 6px solid #ef4444; }
    .toast-error .toast-icon { color: #ef4444; }
    .toast-error .toast-title { color: #dc2626; }
    .toast-error .toast-progress { background: linear-gradient(90deg, #ef4444, transparent); }

    .toast-warning { border-left: 6px solid #f59e0b; }
    .toast-warning .toast-icon { color: #f59e0b; }
    .toast-warning .toast-title { color: #d97706; }
    .toast-warning .toast-progress { background: linear-gradient(90deg, #f59e0b, transparent); }

    .toast-info { border-left: 6px solid #3b82f6; }
    .toast-info .toast-icon { color: #3b82f6; }
    .toast-info .toast-title { color: #1d4ed8; }
    .toast-info .toast-progress { background: linear-gradient(90deg, #3b82f6, transparent); }

    .toast-message { color: #666; }

    /* Animações */
    @keyframes toastSlideIn {
        from { opacity: 0; transform: scale(0.75) translateY(-50px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    @keyframes toastSlideOut {
        from { opacity: 1; transform: scale(1) translateY(0); }
        to { opacity: 0; transform: scale(0.75) translateY(50px); }
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }

    /* Barra de progresso (animação via JS) */
    .toast-progress.animating {
        animation: toastProgress linear forwards;
    }
    @keyframes toastProgress {
        from { width: 100%; }
        to { width: 0%; }
    }

    /* Responsivo */
    @media (max-width: 768px) {
        .toast-notification {
            padding: 30px 40px 30px 30px;
            gap: 15px;
        }
        .toast-icon { font-size: 2rem; min-width: 50px; }
        .toast-title { font-size: 1.1rem; }
        .toast-message { font-size: 0.9rem; }
        .toast-close { top: 12px; right: 16px; }
    }

    @media (max-width: 480px) {
        .toast-container { padding: 15px; }
        .toast-notification {
            padding: 25px 35px 25px 25px;
            min-width: 280px;
            max-width: 100%;
        }
        .toast-icon { font-size: 1.8rem; min-width: 45px; }
        .toast-title { font-size: 1rem; }
        .toast-message { font-size: 0.85rem; }
        .toast-close { top: 10px; right: 14px; }
    }
</style>

<div class="toast-container" id="toastContainer"></div>

<script>
    class ToastNotification {
        constructor(containerId = 'toastContainer') {
            this.container = document.getElementById(containerId);
            this.queue = [];              // fila de notificações pendentes
            this.active = null;          // { element, overlay, timer, resolve, config }
            this.previousActiveElement = null; // elemento com foco antes de abrir
            this.iconsMap = {
                success: 'fa-check-circle',
                error: 'fa-times-circle',
                warning: 'fa-exclamation-circle',
                info: 'fa-info-circle'
            };
        }

        /**
         * Exibe uma notificação.
         * Se já existir uma ativa, entra na fila e só aparece depois.
         * Retorna uma Promise que resolve quando a notificação fecha.
         */
        show(title, message = '', type = 'info', duration = 3000) {
            return new Promise(resolve => {
                const config = { title, message, type, duration, resolve };
                if (this.active) {
                    // colocar na fila
                    this.queue.push(config);
                } else {
                    this._display(config);
                }
            });
        }

        // Métodos de conveniência
        success(title, message = '') { return this.show(title, message, 'success', 3000); }
        error(title, message = '')   { return this.show(title, message, 'error', 4000); }
        warning(title, message = '') { return this.show(title, message, 'warning', 3500); }
        info(title, message = '')    { return this.show(title, message, 'info', 3000); }

        /** Exibe o toast no ecrã */
        _display(config) {
            // Guarda o elemento que tem foco agora
            this.previousActiveElement = document.activeElement;

            this.container.classList.add('active');

            // Overlay
            const overlay = document.createElement('div');
            overlay.className = 'toast-overlay';
            this.container.appendChild(overlay);

            // Toast
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${config.type}`;
            toast.setAttribute('role', 'alertdialog');
            toast.setAttribute('aria-labelledby', 'toast-title');
            toast.setAttribute('aria-describedby', config.message ? 'toast-message' : undefined);

            const iconClass = this.iconsMap[config.type] || 'fa-info-circle';

            toast.innerHTML = `
                <button class="toast-close" aria-label="Fechar notificação" id="toast-close-btn">&times;</button>
                <div class="toast-icon">
                    <i class="fas ${iconClass}"></i>
                </div>
                <div class="toast-content">
                    <h2 class="toast-title" id="toast-title">${this._escapeHtml(config.title)}</h2>
                    ${config.message ? `<p class="toast-message" id="toast-message">${this._escapeHtml(config.message)}</p>` : ''}
                </div>
                <div class="toast-progress"></div>
            `;

            this.container.appendChild(toast);

            // Barra de progresso animada
            const progressBar = toast.querySelector('.toast-progress');
            if (config.duration > 0) {
                progressBar.style.animationDuration = config.duration + 'ms';
                progressBar.classList.add('animating');
            }

            // Guarda referências ativas
            this.active = {
                element: toast,
                overlay: overlay,
                timer: null,
                config: config,
                progressBar: progressBar,
                closeHandler: null
            };

            // Auto-fecho (só se duration > 0)
            if (config.duration > 0) {
                this.active.timer = setTimeout(() => {
                    this._close(toast, true);
                }, config.duration);
            }

            // Fecho ao clicar no overlay
            const overlayClick = () => this._close(toast, true);
            overlay.addEventListener('click', overlayClick);
            this.active.overlayClick = overlayClick;

            // Fecho pelo botão X
            const closeBtn = toast.querySelector('.toast-close');
            const closeHandler = () => this._close(toast, true);
            closeBtn.addEventListener('click', closeHandler);
            this.active.closeHandler = closeHandler;

            // Fecho pela tecla Escape
            this._escKeyHandler = (e) => {
                if (e.key === 'Escape' && this.active && this.active.element === toast) {
                    this._close(toast, true);
                }
            };
            document.addEventListener('keydown', this._escKeyHandler);

            // Foco no botão fechar (acessibilidade)
            closeBtn.focus();
        }

        /** Fecha o toast atual */
        _close(toastElement, processNext = false) {
            if (!this.active || this.active.element !== toastElement) return;

            // Limpa timeout
            if (this.active.timer) clearTimeout(this.active.timer);
            // Remove event listeners
            if (this.active.overlayClick) this.active.overlay.removeEventListener('click', this.active.overlayClick);
            if (this.active.closeHandler) toastElement.querySelector('.toast-close').removeEventListener('click', this.active.closeHandler);
            if (this._escKeyHandler) document.removeEventListener('keydown', this._escKeyHandler);

            // Animações de saída
            toastElement.classList.add('closing');
            this.active.overlay.classList.add('closing');

            const resolve = this.active.config.resolve;
            const activeRef = this.active;

            // Remove elementos após animação
            setTimeout(() => {
                if (toastElement.parentNode) toastElement.parentNode.removeChild(toastElement);
                if (activeRef.overlay.parentNode) activeRef.overlay.parentNode.removeChild(activeRef.overlay);
                this.container.classList.remove('active');
                this.active = null;

                // Devolve foco ao elemento anterior (se ainda existir)
                if (this.previousActiveElement && typeof this.previousActiveElement.focus === 'function') {
                    try { this.previousActiveElement.focus(); } catch(e) { /* ignore */ }
                }
                this.previousActiveElement = null;

                // Resolve a promise
                if (resolve) resolve();

                // Processa próximo da fila
                if (processNext && this.queue.length > 0) {
                    const next = this.queue.shift();
                    this._display(next);
                }
            }, 400); // duração da animação de saída
        }

        /** Fecha manualmente (usa a instância global) */
        dismiss() {
            if (this.active) {
                this._close(this.active.element, true);
            }
        }

        /** Limpa a fila (notificações pendentes não serão exibidas) */
        clearQueue() {
            this.queue.forEach(item => {
                if (item.resolve) item.resolve();
            });
            this.queue = [];
        }

        _escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Instância global
    window.toast = new ToastNotification('toastContainer');

    // Exibir toast da sessão PHP (se existir)
    document.addEventListener('DOMContentLoaded', function() {
        const sessionToast = document.querySelector('[data-session-toast]');
        if (sessionToast) {
            const type = sessionToast.dataset.sessionToastType || 'info';
            const title = sessionToast.dataset.sessionToastTitle || 'Notificação';
            const message = sessionToast.dataset.sessionToastMessage || '';
            setTimeout(() => {
                window.toast[type](title, message);
            }, 500);
        }
    });
</script>