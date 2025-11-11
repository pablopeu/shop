<?php
/**
 * Reusable Modal Component
 *
 * Este componente proporciona un modal de confirmación reutilizable
 * para reemplazar los msgbox/alerts nativos del navegador.
 *
 * Uso:
 * 1. Incluir este archivo: <?php include __DIR__ . '/includes/modal.php'; ?>
 * 2. Llamar showModal() desde JavaScript con título, mensaje y callback
 */
?>

<style>
    /* Modal Overlay */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 9998;
        animation: fadeIn 0.2s ease-out;
    }

    .modal-overlay.show {
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* Modal Container */
    .modal-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow: auto;
        animation: slideDown 0.3s ease-out;
        z-index: 9999;
    }

    /* Modal Header */
    .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .modal-header .modal-icon {
        font-size: 28px;
        line-height: 1;
    }

    .modal-header .modal-title {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
        flex: 1;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        color: #999;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .modal-close:hover {
        background: #f0f0f0;
        color: #333;
    }

    /* Modal Body */
    .modal-body {
        padding: 24px;
        color: #555;
        font-size: 15px;
        line-height: 1.6;
    }

    .modal-body .modal-message {
        margin-bottom: 0;
    }

    .modal-body .modal-details {
        margin-top: 12px;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 6px;
        font-size: 14px;
        color: #666;
    }

    /* Modal Footer */
    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid #e0e0e0;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    .modal-footer .btn {
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: all 0.3s;
    }

    .modal-footer .btn-cancel {
        background: #e0e0e0;
        color: #555;
    }

    .modal-footer .btn-cancel:hover {
        background: #d0d0d0;
    }

    .modal-footer .btn-confirm {
        background: #4CAF50;
        color: white;
    }

    .modal-footer .btn-confirm:hover {
        background: #45a049;
    }

    .modal-footer .btn-confirm.danger {
        background: #dc3545;
    }

    .modal-footer .btn-confirm.danger:hover {
        background: #c82333;
    }

    .modal-footer .btn-confirm.warning {
        background: #ffc107;
        color: #333;
    }

    .modal-footer .btn-confirm.warning:hover {
        background: #e0a800;
    }

    /* Animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes slideDown {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .modal-container {
            width: 95%;
            margin: 20px;
        }

        .modal-header {
            padding: 16px 20px;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 12px 20px;
            flex-direction: column-reverse;
        }

        .modal-footer .btn {
            width: 100%;
        }
    }
</style>

<!-- Modal HTML -->
<div class="modal-overlay" id="confirmModal" onclick="handleModalOverlayClick(event)">
    <div class="modal-container">
        <div class="modal-header">
            <span class="modal-icon" id="modalIcon">⚠️</span>
            <h3 class="modal-title" id="modalTitle">Confirmar Acción</h3>
            <button class="modal-close" onclick="closeModal()" type="button">✕</button>
        </div>
        <div class="modal-body">
            <p class="modal-message" id="modalMessage">¿Estás seguro de que deseas realizar esta acción?</p>
            <div class="modal-details" id="modalDetails" style="display: none;"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-cancel" onclick="closeModal()" type="button" id="modalCancelBtn">
                Cancelar
            </button>
            <button class="btn btn-confirm" id="modalConfirmBtn" type="button">
                Confirmar
            </button>
        </div>
    </div>
</div>

<script>
    /**
     * Configuración global del modal
     */
    let modalCallback = null;
    let modalCancelCallback = null;

    /**
     * Muestra el modal de confirmación
     *
     * @param {Object} options - Opciones del modal
     * @param {string} options.title - Título del modal
     * @param {string} options.message - Mensaje principal
     * @param {string} [options.details] - Detalles adicionales (opcional)
     * @param {string} [options.icon] - Icono del modal (default: ⚠️)
     * @param {string} [options.confirmText] - Texto del botón confirmar (default: "Confirmar")
     * @param {string} [options.cancelText] - Texto del botón cancelar (default: "Cancelar")
     * @param {string} [options.confirmType] - Tipo de acción: 'danger', 'warning', 'primary' (default: 'primary')
     * @param {Function} options.onConfirm - Callback al confirmar
     * @param {Function} [options.onCancel] - Callback al cancelar (opcional)
     */
    function showModal(options) {
        const modal = document.getElementById('confirmModal');
        const icon = document.getElementById('modalIcon');
        const title = document.getElementById('modalTitle');
        const message = document.getElementById('modalMessage');
        const details = document.getElementById('modalDetails');
        const confirmBtn = document.getElementById('modalConfirmBtn');
        const cancelBtn = document.getElementById('modalCancelBtn');

        // Set content
        icon.textContent = options.icon || '⚠️';
        title.textContent = options.title || 'Confirmar Acción';
        message.textContent = options.message || '¿Estás seguro?';

        // Set details (optional)
        if (options.details) {
            details.textContent = options.details;
            details.style.display = 'block';
        } else {
            details.style.display = 'none';
        }

        // Set button texts
        confirmBtn.textContent = options.confirmText || 'Confirmar';
        cancelBtn.textContent = options.cancelText || 'Cancelar';

        // Set confirm button type
        confirmBtn.className = 'btn btn-confirm';
        if (options.confirmType === 'danger') {
            confirmBtn.classList.add('danger');
        } else if (options.confirmType === 'warning') {
            confirmBtn.classList.add('warning');
        }

        // Store callbacks
        modalCallback = options.onConfirm;
        modalCancelCallback = options.onCancel || null;

        // Show modal
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        // Focus on confirm button
        setTimeout(() => confirmBtn.focus(), 100);
    }

    /**
     * Cierra el modal
     */
    function closeModal() {
        const modal = document.getElementById('confirmModal');
        modal.classList.remove('show');
        document.body.style.overflow = '';

        // Execute cancel callback if exists
        if (modalCancelCallback) {
            modalCancelCallback();
        }

        // Clear callbacks
        modalCallback = null;
        modalCancelCallback = null;
    }

    /**
     * Maneja el click en el overlay (cerrar al hacer click fuera)
     */
    function handleModalOverlayClick(event) {
        if (event.target.id === 'confirmModal') {
            closeModal();
        }
    }

    /**
     * Ejecuta la acción confirmada
     */
    document.getElementById('modalConfirmBtn')?.addEventListener('click', function() {
        if (modalCallback) {
            modalCallback();
        }
        closeModal();
    });

    /**
     * Cerrar modal con tecla ESC
     */
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('confirmModal');
            if (modal.classList.contains('show')) {
                closeModal();
            }
        }
    });

    /**
     * Helper: Confirmar y redirigir a URL
     * Útil para reemplazar confirm() en enlaces
     */
    function confirmAndRedirect(url, options) {
        showModal({
            ...options,
            onConfirm: function() {
                window.location.href = url;
            }
        });
    }

    /**
     * Helper: Confirmar y enviar formulario
     * Útil para reemplazar confirm() en forms
     */
    function confirmAndSubmit(formId, options) {
        showModal({
            ...options,
            onConfirm: function() {
                document.getElementById(formId).submit();
            }
        });
    }
</script>
