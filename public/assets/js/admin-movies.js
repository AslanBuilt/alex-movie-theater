/* Alex Theatre Admin — admin-movies.js
   Self-contained UI helpers for the Movies admin pages:
     1) Drag-to-reorder on movies.php (native HTML5 drag-and-drop, no libraries)
     2) Quick-add showtimes preview + end-time calc on movie-edit.php's create page
   Both blocks no-op quietly (via null checks) on pages that don't have the
   relevant markup, so this single file can be loaded by both pages. */
(function () {
    'use strict';

    /* ============================================================
       1) Movie list drag-to-reorder (movies.php)
       ============================================================ */
    (function initReorder() {
        var wrap          = document.getElementById('movies-table-wrap');
        var arrangeToggle = document.getElementById('arrange-toggle');
        var saveBtn       = document.getElementById('save-order');
        var cancelBtn     = document.getElementById('cancel-order');
        var statusEl      = document.getElementById('reorder-status');

        if (!wrap || !arrangeToggle || !saveBtn || !cancelBtn) {
            return; // not on movies.php, or arrange mode disabled server-side
        }

        var tbody = wrap.querySelector('tbody');
        if (!tbody) return;

        var csrfToken    = wrap.getAttribute('data-csrf') || '';
        var rows         = Array.prototype.slice.call(tbody.querySelectorAll('.movie-row'));
        var originalOrder = rows.slice();
        var dragSrc      = null;

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
            var order = Array.prototype.slice.call(tbody.querySelectorAll('.movie-row')).map(function (row, i) {
                return { id: row.getAttribute('data-movie-id'), sort_order: i + 1 };
            });

            var originalLabel = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.setAttribute('aria-busy', 'true');
            saveBtn.textContent = 'Saving…';

            fetch('api/movies-reorder.php', {
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
                        originalOrder = Array.prototype.slice.call(tbody.querySelectorAll('.movie-row'));
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

    /* ============================================================
       2) Quick-add showtimes preview + end-time calc
          (movie-edit.php, create path only)
       ============================================================ */
    (function initQuickAddShowtimes() {
        var blocksContainer = document.getElementById('showtime-blocks');
        var blockTemplate    = document.getElementById('showtime-block-template');
        var addBlockBtn       = document.getElementById('add-showtime-block');
        var durationHoursEl   = document.getElementById('duration_hours');
        var durationMinsEl    = document.getElementById('duration_minutes_part');

        if (!blocksContainer || !blockTemplate || !addBlockBtn) {
            return; // not on the movie create page
        }

        // If the server re-rendered blocks from a failed submission (see
        // movie_edit_render_showtime_block() in movie-edit.php), start the
        // index after them so newly-added blocks don't collide with the
        // pre-existing ones.
        var blockIndex = parseInt(blocksContainer.getAttribute('data-initial-count') || '0', 10) || 0;
        var DOW_ABBR   = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        var MONTH_ABBR = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        function currentDurationMinutes() {
            var h = parseInt((durationHoursEl && durationHoursEl.value) || '0', 10) || 0;
            var m = parseInt((durationMinsEl && durationMinsEl.value) || '0', 10) || 0;
            var total = h * 60 + m;
            return total > 0 ? total : 0;
        }

        function formatClock(totalMinutes) {
            totalMinutes = ((totalMinutes % 1440) + 1440) % 1440;
            var h = Math.floor(totalMinutes / 60);
            var m = totalMinutes % 60;
            var suffix = h >= 12 ? 'PM' : 'AM';
            var h12 = h % 12;
            if (h12 === 0) h12 = 12;
            return h12 + ':' + (m < 10 ? '0' : '') + m + ' ' + suffix;
        }

        function parseISODate(s) {
            var parts = (s || '').split('-');
            if (parts.length !== 3) return null;
            var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            return isNaN(d.getTime()) ? null : d;
        }

        function generateDates(fromStr, toStr, days) {
            var from = parseISODate(fromStr);
            var to   = parseISODate(toStr);
            if (!from || !to || from > to || !days.length) return [];
            var out = [];
            var cur = new Date(from.getTime());
            var guard = 0; // sanity backstop mirroring the server's 366-day cap
            while (cur <= to && guard < 400) {
                if (days.indexOf(cur.getDay()) !== -1) {
                    out.push(new Date(cur.getTime()));
                }
                cur.setDate(cur.getDate() + 1);
                guard++;
            }
            return out;
        }

        function updatePreview(block) {
            var days = Array.prototype.slice.call(block.querySelectorAll('input[type="checkbox"]:checked'))
                .map(function (cb) { return parseInt(cb.value, 10); });
            var startTimeEl = block.querySelector('.st-start-time');
            var dateFromEl  = block.querySelector('.st-date-from');
            var dateToEl    = block.querySelector('.st-date-to');
            var preview     = block.querySelector('.st-preview');
            if (!startTimeEl || !dateFromEl || !dateToEl || !preview) return;

            var startTime = startTimeEl.value;
            var dateFrom  = dateFromEl.value;
            var dateTo    = dateToEl.value;

            if (!days.length || !startTime || !dateFrom || !dateTo) {
                preview.textContent = 'Pick days, a start time, and a date range to preview showtimes.';
                return;
            }

            var dates = generateDates(dateFrom, dateTo, days);
            if (!dates.length) {
                preview.textContent = 'That range and day selection produces no showtimes.';
                return;
            }

            var parts = startTime.split(':');
            var startMinutes = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
            var duration = currentDurationMinutes();

            var sampleDates = dates.slice(0, 4).map(function (d) {
                return DOW_ABBR[d.getDay()] + ' ' + MONTH_ABBR[d.getMonth()] + ' ' + d.getDate();
            });
            var sample = sampleDates.join(', ');
            if (dates.length > 4) {
                sample += ', +' + (dates.length - 4) + ' more';
            }

            var text = 'Will create ' + dates.length + ' showtime' + (dates.length === 1 ? '' : 's') + ': ' + sample + '.';
            if (duration > 0) {
                text += ' Starts ' + formatClock(startMinutes) + ', ends around ' + formatClock(startMinutes + duration) + ' each night.';
            } else {
                text += ' Set a duration on the movie to calculate an end time.';
            }
            preview.textContent = text;
        }

        function updateAllPreviews() {
            Array.prototype.slice.call(blocksContainer.querySelectorAll('.showtime-block')).forEach(updatePreview);
        }

        function bindBlock(block) {
            Array.prototype.slice.call(block.querySelectorAll('input')).forEach(function (input) {
                input.addEventListener('input', function () { updatePreview(block); });
                input.addEventListener('change', function () { updatePreview(block); });
            });
            var removeBtn = block.querySelector('.st-remove-block');
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    if (block.parentNode) block.parentNode.removeChild(block);
                });
            }
            updatePreview(block);
        }

        function addBlock() {
            var html = blockTemplate.innerHTML.split('__INDEX__').join(String(blockIndex));
            var wrapperEl = document.createElement('div');
            wrapperEl.innerHTML = html.trim();
            var block = wrapperEl.firstElementChild;
            if (!block) return;
            blocksContainer.appendChild(block);
            bindBlock(block);
            blockIndex++;
        }

        // Bind any blocks the server already rendered (a validation-error
        // round trip repopulating what the admin typed) before deciding
        // whether a fresh convenience block is needed.
        Array.prototype.slice.call(blocksContainer.querySelectorAll('.showtime-block')).forEach(bindBlock);

        addBlockBtn.addEventListener('click', addBlock);
        if (durationHoursEl) durationHoursEl.addEventListener('input', updateAllPreviews);
        if (durationMinsEl) durationMinsEl.addEventListener('input', updateAllPreviews);

        if (blocksContainer.children.length === 0) {
            addBlock(); // fresh page load — start with one empty block for convenience
        }
    })();
})();
