/* Alex Theatre Admin — admin-upload.js
   Self-contained UI helpers, mirroring the admin-movies.js pattern:
     1) Reusable drag-and-drop image upload zone (concession-edit.php,
        event-edit.php). Auto-initializes every [data-upload-zone] element
        found on the page, so wiring a new form up is markup-only.
     2) Concessions list drag-to-reorder (concessions.php), same native
        HTML5 drag-and-drop approach as the movies list.
   Both blocks no-op quietly (via null checks) on pages that don't have the
   relevant markup, so this single file can be loaded by every admin page. */
(function () {
    'use strict';

    /* ============================================================
       1) Drag-and-drop upload zone
       ============================================================ */
    function initUploadZone(zone) {
        var inputId       = zone.getAttribute('data-input-id');
        var previewId      = zone.getAttribute('data-preview-id');
        var placeholderId = zone.getAttribute('data-placeholder-id');
        var labelId        = zone.getAttribute('data-label-id');

        var input       = inputId ? document.getElementById(inputId) : null;
        var preview     = previewId ? document.getElementById(previewId) : null;
        var placeholder = placeholderId ? document.getElementById(placeholderId) : null;
        var label       = labelId ? document.getElementById(labelId) : null;

        if (!zone || !input) {
            return;
        }

        function showFile(file) {
            if (!file) return;
            if (preview) {
                preview.src = URL.createObjectURL(file);
                preview.style.display = '';
            }
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            if (label) {
                label.textContent = 'New image selected — replaces the current image on save.';
                label.style.display = '';
            }
        }

        zone.addEventListener('click', function (e) {
            if (e.target === input) return;
            input.click();
        });
        zone.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                input.click();
            }
        });
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            zone.classList.add('is-dragover');
        });
        zone.addEventListener('dragleave', function () {
            zone.classList.remove('is-dragover');
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('is-dragover');
            var files = e.dataTransfer && e.dataTransfer.files;
            if (files && files.length) {
                input.files = files;
                showFile(files[0]);
            }
        });
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            showFile(f);
        });
    }

    Array.prototype.slice.call(document.querySelectorAll('[data-upload-zone]')).forEach(initUploadZone);

    /* ============================================================
       2) Concessions list drag-to-reorder (concessions.php)
       ============================================================ */
    (function initConcessionsReorder() {
        var wrap          = document.getElementById('concessions-table-wrap');
        var arrangeToggle = document.getElementById('arrange-toggle');
        var saveBtn       = document.getElementById('save-order');
        var cancelBtn     = document.getElementById('cancel-order');
        var statusEl      = document.getElementById('reorder-status');

        if (!wrap || !arrangeToggle || !saveBtn || !cancelBtn) {
            return; // not on concessions.php
        }

        var tbody = wrap.querySelector('tbody');
        if (!tbody) return;

        var csrfToken     = wrap.getAttribute('data-csrf') || '';
        var rows          = Array.prototype.slice.call(tbody.querySelectorAll('.concession-row'));
        var originalOrder = rows.slice();
        var dragSrc       = null;

        function showStatus(type, message) {
            if (!statusEl) return;
            statusEl.innerHTML = '';
            var div = document.createElement('div');
            div.className = 'alert alert-' + type;
            div.setAttribute('role', 'alert');
            div.textContent = message;
            statusEl.appendChild(div);
            setTimeout(function () {
                div.style.transition = 'opacity 0.45s ease';
                div.style.opacity = '0';
                setTimeout(function () {
                    if (div.parentNode) div.parentNode.removeChild(div);
                }, 450);
            }, 4000);
        }

        function bindDrag(row) {
            row.addEventListener('dragstart', function (e) {
                dragSrc = this;
                this.style.opacity = '0.4';
                e.dataTransfer.effectAllowed = 'move';
            });
            row.addEventListener('dragover', function (e) {
                e.preventDefault();
                if (this !== dragSrc) {
                    this.style.background = 'var(--bg-card-hover)';
                }
            });
            row.addEventListener('dragleave', function () {
                this.style.background = '';
            });
            row.addEventListener('drop', function (e) {
                e.preventDefault();
                this.style.background = '';
                if (dragSrc && dragSrc !== this) {
                    var list   = this.parentNode;
                    var srcIdx = Array.prototype.indexOf.call(list.children, dragSrc);
                    var tgtIdx = Array.prototype.indexOf.call(list.children, this);
                    list.insertBefore(dragSrc, srcIdx < tgtIdx ? this.nextSibling : this);
                }
            });
            row.addEventListener('dragend', function () {
                this.style.opacity = '';
                rows.forEach(function (r) { r.style.background = ''; });
            });
        }

        rows.forEach(bindDrag);

        function setHandlesVisible(visible) {
            Array.prototype.slice.call(wrap.querySelectorAll('.drag-handle')).forEach(function (h) {
                h.style.display = visible ? 'inline' : 'none';
            });
        }

        function enterArrangeMode() {
            rows.forEach(function (r) {
                r.setAttribute('draggable', 'true');
                r.style.cursor = 'grab';
            });
            setHandlesVisible(true);
            arrangeToggle.hidden = true;
            saveBtn.hidden = false;
            cancelBtn.hidden = false;
        }

        function exitArrangeMode(restoreOriginal) {
            rows.forEach(function (r) {
                r.removeAttribute('draggable');
                r.style.cursor = '';
                r.style.opacity = '';
                r.style.background = '';
            });
            setHandlesVisible(false);
            arrangeToggle.hidden = false;
            saveBtn.hidden = true;
            cancelBtn.hidden = true;
            if (restoreOriginal) {
                originalOrder.forEach(function (r) { tbody.appendChild(r); });
            }
        }

        arrangeToggle.addEventListener('click', enterArrangeMode);
        cancelBtn.addEventListener('click', function () { exitArrangeMode(true); });

        saveBtn.addEventListener('click', function () {
            var order = Array.prototype.slice.call(tbody.querySelectorAll('.concession-row')).map(function (row, i) {
                return { id: row.getAttribute('data-concession-id'), sort_order: i + 1 };
            });

            var originalLabel = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.setAttribute('aria-busy', 'true');
            saveBtn.textContent = 'Saving…';

            fetch('api/concessions-reorder.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ order: order })
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    saveBtn.disabled = false;
                    saveBtn.removeAttribute('aria-busy');
                    saveBtn.textContent = originalLabel;
                    if (d && d.success) {
                        originalOrder = Array.prototype.slice.call(tbody.querySelectorAll('.concession-row'));
                        showStatus('success', 'Order saved.');
                        exitArrangeMode(false);
                    } else {
                        showStatus('error', (d && d.error) || 'Could not save the new order.');
                    }
                })
                .catch(function () {
                    saveBtn.disabled = false;
                    saveBtn.removeAttribute('aria-busy');
                    saveBtn.textContent = originalLabel;
                    showStatus('error', 'Could not save the new order. Check your connection and try again.');
                });
        });
    })();
})();
