/* Alex Theatre Admin — admin.js
   Self-contained UI helpers. No dependencies. */
(function () {
    'use strict';

    var deleteModal     = document.getElementById('deleteModal');
    var deleteModalName = document.getElementById('deleteModalName');
    var deleteModalForm = document.getElementById('deleteModalForm');
    var deleteModalId   = document.getElementById('deleteModalId');

    function openModal(node) {
        if (!node) return;
        node.removeAttribute('hidden');
    }

    function closeModal() {
        if (deleteModal) {
            deleteModal.setAttribute('hidden', '');
        }
    }

    function confirmDelete(id, name, formAction) {
        if (!deleteModal) return;
        if (deleteModalName) {
            deleteModalName.textContent = name ? String(name) : 'this item';
        }
        if (deleteModalId) {
            deleteModalId.value = id != null ? String(id) : '';
        }
        if (deleteModalForm && formAction) {
            deleteModalForm.setAttribute('action', formAction);
        }
        // Reset submit button state in case it was disabled previously.
        if (deleteModalForm) {
            var btn = deleteModalForm.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = false;
                if (btn.dataset.originalText) {
                    btn.textContent = btn.dataset.originalText;
                }
            }
        }
        openModal(deleteModal);
    }

    // Bind close handlers (backdrop click + [data-modal-close] buttons + ESC).
    if (deleteModal) {
        deleteModal.addEventListener('click', function (e) {
            if (e.target === deleteModal) {
                closeModal();
            }
        });
        var closers = deleteModal.querySelectorAll('[data-modal-close]');
        for (var i = 0; i < closers.length; i++) {
            closers[i].addEventListener('click', closeModal);
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            closeModal();
        }
    });

    // Auto-dismiss alerts after 5 seconds.
    var alerts = document.querySelectorAll('.alert');
    for (var a = 0; a < alerts.length; a++) {
        (function (alertEl) {
            setTimeout(function () {
                alertEl.classList.add('is-fading');
                setTimeout(function () {
                    if (alertEl && alertEl.parentNode) {
                        alertEl.parentNode.removeChild(alertEl);
                    }
                }, 450);
            }, 5000);
        })(alerts[a]);
    }

    // Sidebar toggle (mobile).
    var toggles = document.querySelectorAll('[data-sidebar-toggle]');
    var sidebar = document.getElementById('adminSidebar');
    for (var t = 0; t < toggles.length; t++) {
        toggles[t].addEventListener('click', function () {
            if (sidebar) sidebar.classList.toggle('is-open');
        });
    }

    // Prevent double form submits.
    var guardedForms = document.querySelectorAll('form[data-prevent-double="1"]');
    for (var f = 0; f < guardedForms.length; f++) {
        guardedForms[f].addEventListener('submit', function (e) {
            var form = e.currentTarget;
            var btn  = form.querySelector('button[type="submit"]');
            if (btn) {
                if (!btn.dataset.originalText) {
                    btn.dataset.originalText = btn.textContent;
                }
                btn.disabled    = true;
                btn.textContent = 'Saving…';
            }
        });
    }

    // Expose the two helpers used inline by templates.
    window.confirmDelete = confirmDelete;
    window.closeModal    = closeModal;
})();
