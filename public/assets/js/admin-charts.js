/**
 * admin-charts.js — reports.php's client side. Fetches one JSON payload from
 * admin/api/reports-data.php and renders all 6 charts + their paired
 * accessible data tables. Only runs on reports.php (checks for its
 * canvases before doing anything).
 *
 * Chart.js + chartjs-plugin-datalabels are loaded as pinned <script> tags
 * by reports.php itself (real SRI, verified against cdnjs — see commit
 * message). This file assumes both globals (`Chart`, `ChartDataLabels`)
 * exist; if either failed to load (e.g. a future SRI mismatch), every
 * render function no-ops rather than throwing.
 */
(function () {
  'use strict';

  var ENDPOINT = 'api/reports-data.php';
  var PALETTE = ['#8B1D33', '#3a5a7a', '#6a4a8a', '#4a7a5a', '#a8632a', '#7a4a4a', '#4a7a7a', '#8a7a3a'];
  var COLOR_THIS_WEEK = '#8B1A2E';
  var COLOR_LAST_WEEK = '#D4B5BC';
  var COLOR_GREY = '#6b6258';

  var charts = {}; // id -> Chart instance, so print-mode can restyle + update them all
  var printMode = false;

  function onReportsPage() {
    return !!document.getElementById('chartWeek') || !!document.getElementById('chartInventory');
  }

  if (!onReportsPage()) {
    return; // not on reports.php
  }

  if (typeof Chart === 'undefined') {
    showGlobalError('Charts library failed to load (Chart.js). Reports data will still be fetched, but nothing will be drawn.');
  } else if (typeof ChartDataLabels !== 'undefined') {
    Chart.register(ChartDataLabels);
    // Direct property assignment (documented since Chart.js v3, e.g.
    // "Chart.defaults.plugins.legend.display = false") rather than
    // Chart.defaults.set(scope, values) — that method exists on the
    // internal Defaults class but isn't reliably confirmable from a
    // minified bundle, so this sticks to the form guaranteed to work.
    Chart.defaults.plugins = Chart.defaults.plugins || {};
    Chart.defaults.plugins.datalabels = Object.assign({}, Chart.defaults.plugins.datalabels, { display: false }); // opt in per-chart below
  }

  if (typeof Chart !== 'undefined') {
    // Legend/tick label readability bump (applies in both print and screen
    // palettes below — only the *color*, not the size, differs between them).
    Chart.defaults.font.size = 14;
  }

  function money(v) {
    var n = Number(v) || 0;
    var sign = n < 0 ? '-' : '';
    return sign + '$' + Math.abs(n).toFixed(2);
  }

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    attrs = attrs || {};
    Object.keys(attrs).forEach(function (k) {
      if (k === 'text') {
        node.textContent = attrs[k];
      } else if (k === 'html') {
        node.innerHTML = attrs[k];
      } else {
        node.setAttribute(k, attrs[k]);
      }
    });
    (children || []).forEach(function (c) { node.appendChild(c); });
    return node;
  }

  function showGlobalError(msg) {
    var box = document.getElementById('reportsError');
    if (!box) return;
    box.textContent = msg;
    box.style.display = 'block';
  }

  function hideGlobalError() {
    var box = document.getElementById('reportsError');
    if (!box) return;
    box.style.display = 'none';
  }

  /**
   * Build a plain HTML <table> for the accessible "View data table" details
   * block. headers: string[]; rows: string[][] (already formatted).
   */
  function buildDataTable(containerId, headers, rows) {
    var container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = '';
    if (!rows.length) {
      container.appendChild(el('p', { text: 'No data for this period.', style: 'color:var(--text-muted); margin:0.5rem 0;' }));
      return;
    }
    var table = el('table', { class: 'admin-table' });
    var thead = el('thead');
    var headRow = el('tr');
    headers.forEach(function (h) { headRow.appendChild(el('th', { text: h })); });
    thead.appendChild(headRow);
    table.appendChild(thead);
    var tbody = el('tbody');
    rows.forEach(function (r) {
      var tr = el('tr');
      r.forEach(function (cell) { tr.appendChild(el('td', { text: cell })); });
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    container.appendChild(table);
  }

  function destroyChart(id) {
    if (charts[id]) {
      charts[id].destroy();
      delete charts[id];
    }
  }

  // ── Chart 1: this week vs last week, grouped bars ──────────────────────────
  function renderChartWeek(revenueWeek) {
    var canvas = document.getElementById('chartWeek');
    var summaryEl = document.getElementById('chartWeekSummary');
    if (summaryEl) {
      var pct = revenueWeek.changePct;
      var arrow = pct > 0 ? '▲' : (pct < 0 ? '▼' : '—');
      summaryEl.textContent =
        'This week: ' + money(revenueWeek.thisWeekTotal) +
        ' | Last week: ' + money(revenueWeek.lastWeekTotal) +
        ' | Change: ' + arrow + ' ' + Math.abs(pct) + '%';
    }
    buildDataTable('chartWeekTable', ['Day', 'This Week', 'Last Week'], revenueWeek.labels.map(function (d, i) {
      return [d, money(revenueWeek.thisWeek[i]), money(revenueWeek.lastWeek[i])];
    }));
    if (!canvas || typeof Chart === 'undefined') return;
    destroyChart('chartWeek');
    charts.chartWeek = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: revenueWeek.labels,
        datasets: [
          {
            label: 'This Week', data: revenueWeek.thisWeek,
            backgroundColor: COLOR_THIS_WEEK, borderRadius: 4,
            datalabels: { display: true, anchor: 'end', align: 'top', formatter: money, font: { size: 13 } }
          },
          {
            label: 'Last Week', data: revenueWeek.lastWeek,
            backgroundColor: COLOR_LAST_WEEK, borderColor: '#8B1A2E', borderWidth: 2, borderDash: [5, 3], borderRadius: 4,
            datalabels: { display: true, anchor: 'end', align: 'top', formatter: money, font: { size: 13 }, color: '#3A2418' }
          }
        ]
      },
      options: {
        plugins: {
          legend: { position: 'bottom' },
          tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + money(c.parsed.y); } } }
        },
        scales: { y: { beginAtZero: true, ticks: { callback: money } } }
      }
    });
  }

  // ── Chart 2: this month, line + area, with average reference line ─────────
  function renderChartMonth(revenueMonth) {
    var canvas = document.getElementById('chartMonth');
    var avg = revenueMonth.average;
    buildDataTable('chartMonthTable', ['Day', 'Revenue'], revenueMonth.labels.map(function (d, i) {
      return [d, money(revenueMonth.data[i])];
    }));
    if (!canvas || typeof Chart === 'undefined') return;
    destroyChart('chartMonth');
    var avgLine = revenueMonth.labels.map(function () { return avg; });
    var lastIdx = avgLine.length - 1;
    charts.chartMonth = new Chart(canvas, {
      data: {
        labels: revenueMonth.labels,
        datasets: [
          {
            type: 'line', label: 'Revenue', data: revenueMonth.data,
            borderColor: '#8B1D33', backgroundColor: 'rgba(139,29,51,0.18)', fill: true, tension: 0.25, pointRadius: 3,
            order: 2, datalabels: { display: false }
          },
          {
            type: 'line', label: 'Average', data: avgLine,
            borderColor: '#C8B8A8', borderDash: [6, 4], borderWidth: 2, pointRadius: 0, fill: false, order: 1,
            datalabels: {
              display: function (ctx) { return ctx.dataIndex === lastIdx; },
              align: 'top', formatter: function () { return 'Avg: ' + money(avg); },
              color: '#C8B8A8', font: { size: 13, weight: 'bold' }
            }
          }
        ]
      },
      options: {
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + money(c.parsed.y); } } }
        },
        scales: { y: { beginAtZero: true, ticks: { callback: money } } }
      }
    });
  }

  // ── Chart 3: revenue by category, doughnut with center total ──────────────
  var centerTextPlugin = {
    id: 'centerText',
    beforeDraw: function (chart) {
      if (chart.config.type !== 'doughnut' || !chart._centerTotalText) return;
      var ctx = chart.ctx;
      var w = chart.width, h = chart.height;
      ctx.save();
      ctx.font = 'bold 16px Lato, sans-serif';
      ctx.fillStyle = chart._centerTotalColor || '#F2E8DC';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(chart._centerTotalText, w / 2, h / 2 - 8);
      ctx.font = '11px Lato, sans-serif';
      ctx.fillText('Total', w / 2, h / 2 + 12);
      ctx.restore();
    }
  };
  if (typeof Chart !== 'undefined') {
    Chart.register(centerTextPlugin);
  }

  function renderChartCategory(byCategory) {
    var canvas = document.getElementById('chartCategory');
    var total = byCategory.data.reduce(function (a, b) { return a + b; }, 0);
    buildDataTable('chartCategoryTable', ['Category', 'Revenue', '% of Total'], byCategory.labels.map(function (l, i) {
      var pct = total > 0 ? ((byCategory.data[i] / total) * 100).toFixed(1) : '0.0';
      return [l, byCategory.noSales[i] ? 'No sales' : money(byCategory.data[i]), pct + '%'];
    }));
    if (!canvas || typeof Chart === 'undefined') return;
    destroyChart('chartCategory');
    // A true-zero slice renders no arc at all, so it would be silently
    // omitted rather than shown greyed-out — substitute a tiny epsilon for
    // rendering only; labels/tooltips below use the real (zero) value.
    var renderData = byCategory.data.map(function (v) { return v > 0 ? v : 0.3; });
    var colors = byCategory.data.map(function (v, i) { return v > 0 ? PALETTE[i % PALETTE.length] : COLOR_GREY; });
    charts.chartCategory = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: byCategory.labels,
        datasets: [{ data: renderData, backgroundColor: colors, borderColor: '#1C1410', borderWidth: 2 }]
      },
      options: {
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: function (c) {
                var i = c.dataIndex;
                if (byCategory.noSales[i]) return c.label + ': No sales';
                var pct = total > 0 ? (byCategory.data[i] / total * 100).toFixed(1) : '0.0';
                return c.label + ': ' + money(byCategory.data[i]) + ' (' + pct + '%)';
              }
            }
          },
          datalabels: {
            display: true,
            color: '#fff',
            font: { size: 13, weight: 'bold' },
            formatter: function (value, ctx) {
              var i = ctx.dataIndex;
              if (byCategory.noSales[i]) return ctx.chart.data.labels[i] + '\nNo sales';
              var pct = total > 0 ? (byCategory.data[i] / total * 100).toFixed(0) : '0';
              return ctx.chart.data.labels[i] + '\n' + money(byCategory.data[i]) + ' (' + pct + '%)';
            }
          }
        }
      },
      plugins: [{
        id: 'centerTextData-chartCategory',
        beforeUpdate: function (chart) {
          chart._centerTotalText = money(total);
        }
      }]
    });
    charts.chartCategory._centerTotalText = money(total);
  }

  // ── Chart 4 (movies): stacked adult/child bars + poster thumbnails ────────
  // Poster rows live in a separate column (#chartMoviesPosters) beside the
  // canvas rather than drawn on it, so they need to be sized/positioned to
  // land exactly where Chart.js puts each category's bar. The canvas's
  // *total* height isn't the same as the actual bar-plotting area — Chart.js
  // reserves extra space below it for the x-axis ticks and the bottom
  // legend — so computing row height from raw canvas height (the old
  // approach) drifted out of alignment with the real bars, especially as
  // the category count changed. Reading the chart's own chartArea (the
  // rendered plot rectangle) after each layout — via the afterLayout plugin
  // hook, which fires on the initial render *and* every later resize —
  // keeps the poster rows pinned to the bars instead of a canvas-height
  // guess.
  function layoutMoviePosterRows(topMovies, rowHeights, topOffsetPx) {
    var posterCol = document.getElementById('chartMoviesPosters');
    if (!posterCol) return;
    posterCol.innerHTML = '';
    if (topOffsetPx > 0) {
      posterCol.appendChild(el('div', { style: 'height:' + topOffsetPx + 'px; flex-shrink:0;' }));
    }
    topMovies.forEach(function (m, i) {
      var wrap = el('div', { class: 'report-poster-row', style: 'height:' + rowHeights[i] + 'px;' });
      if (m.poster_path) {
        // poster_path arrives pre-resolved to an absolute URL by
        // includes/helpers.php's posterUrl() (server-side), so it's used
        // as-is here — no relative-path guessing on the client.
        wrap.appendChild(el('img', {
          src: m.poster_path,
          alt: m.item_name + ' poster',
          style: 'max-height:100%; max-width:100%; object-fit:cover; border-radius:3px;',
          onerror: "this.style.display='none';"
        }));
      }
      posterCol.appendChild(wrap);
    });
  }

  function renderChartMovies(topMovies) {
    var canvas = document.getElementById('chartMovies');
    buildDataTable('chartMoviesTable', ['Movie', 'Adult', 'Child', 'Total Tickets', 'Revenue'], topMovies.map(function (m) {
      return [m.item_name, String(m.adult_count), String(m.child_count), String(m.total_qty), money(m.total_revenue)];
    }));

    var n = topMovies.length;
    destroyChart('chartMovies');

    if (!canvas || typeof Chart === 'undefined' || !n) {
      // No chart to align posters against (library missing, or nothing to
      // plot this period) — fall back to even spacing so the column isn't
      // left in a stale state from the previous render.
      var fallbackRowH = n ? Math.max(44, Math.floor(320 / n)) : 44;
      layoutMoviePosterRows(topMovies, topMovies.map(function () { return fallbackRowH; }), 0);
      return;
    }

    // Seed height before layout runs; refined against the chart's real
    // plot area (chartArea) in the afterLayout hook below.
    var seedRowH = Math.max(44, Math.floor(320 / n));
    canvas.height = Math.max(260, seedRowH * n);

    charts.chartMovies = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: topMovies.map(function (m) { return m.item_name; }),
        datasets: [
          {
            label: 'Adult', data: topMovies.map(function (m) { return m.adult_count; }),
            backgroundColor: '#8B1D33', stack: 'tickets',
            datalabels: { display: function (ctx) { return ctx.dataset.data[ctx.dataIndex] > 0; }, color: '#fff', font: { size: 13 } }
          },
          {
            label: 'Child', data: topMovies.map(function (m) { return m.child_count; }),
            backgroundColor: '#a8632a', stack: 'tickets',
            datalabels: { display: function (ctx) { return ctx.dataset.data[ctx.dataIndex] > 0; }, color: '#fff', font: { size: 13 } }
          }
        ]
      },
      options: {
        indexAxis: 'y',
        plugins: {
          legend: { position: 'bottom' },
          datalabels: { display: true, formatter: function (v) { return v > 0 ? v : ''; } }
        },
        scales: {
          x: { beginAtZero: true, stacked: true, ticks: { precision: 0 } },
          y: {
            stacked: true,
            ticks: {
              // Chart.js doesn't truncate long category labels on its own —
              // a long movie title would otherwise just run past/under the
              // axis with no visual indicator that it was cut off.
              callback: function (value) {
                var lbl = this.getLabelForValue(value);
                return lbl.length > 24 ? lbl.slice(0, 23) + '…' : lbl;
              }
            }
          }
        }
      },
      plugins: [{
        id: 'moviePosterAlign',
        afterLayout: function (chart) {
          var area = chart.chartArea;
          if (!area || area.bottom <= area.top) return;
          var rowH = (area.bottom - area.top) / n;
          layoutMoviePosterRows(topMovies, topMovies.map(function () { return rowH; }), area.top);
        }
      }]
    });
  }

  // ── Chart 5 (concessions): units sold + per-unit margin labels ────────────
  function renderChartConcessions(topConcessions) {
    var canvas = document.getElementById('chartConcessions');
    buildDataTable('chartConcessionsTable', ['Item', 'Qty Sold', 'Revenue', 'Margin / Unit', 'Total Margin'], topConcessions.map(function (c) {
      return [
        c.item_name, String(c.total_qty), money(c.total_revenue),
        c.margin_per_unit !== null ? money(c.margin_per_unit) : '—',
        c.margin_total !== null ? money(c.margin_total) : '—'
      ];
    }));
    if (!canvas || typeof Chart === 'undefined') return;
    destroyChart('chartConcessions');
    if (!topConcessions.length) return;
    charts.chartConcessions = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: topConcessions.map(function (c) { return c.item_name; }),
        datasets: [{
          label: 'Units', data: topConcessions.map(function (c) { return c.total_qty; }),
          backgroundColor: PALETTE, borderRadius: 4,
          datalabels: {
            display: true, anchor: 'end', align: 'end', font: { size: 13 },
            formatter: function (value, ctx) {
              var c = topConcessions[ctx.dataIndex];
              var margin = c.margin_per_unit !== null ? (' • +' + money(c.margin_per_unit) + '/ea') : '';
              return value + margin;
            }
          }
        }]
      },
      options: {
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  // ── Chart 6 (inventory): stock vs. reorder point ───────────────────────────
  function renderChartInventory(inventory) {
    var canvas = document.getElementById('chartInventory');
    var active = inventory.filter(function (i) { return i.is_available; });
    buildDataTable('chartInventoryTable', ['Item', 'Category', 'Stock', 'Reorder Point'], active.map(function (i) {
      return [i.name, i.category, String(i.stock_quantity), i.reorder_point !== null ? String(i.reorder_point) : '—'];
    }));
    if (!canvas || typeof Chart === 'undefined') return;
    destroyChart('chartInventory');
    // Vertical layout — height is fixed (matching the other, taller chart
    // sections) and width instead scales with item count so category labels
    // on the x-axis don't get crushed; chartInventoryWrap's parent has
    // overflow-x:auto in reports.php so it scrolls horizontally past that.
    var wrap = document.getElementById('chartInventoryWrap');
    canvas.height = 400;
    if (wrap) wrap.style.minWidth = Math.max(480, active.length * 70) + 'px';

    var barColors = active.map(function (i) {
      if (i.stock_quantity <= 0) return '#E07A8A';
      if (i.reorder_point !== null && i.stock_quantity <= i.reorder_point) return '#f0c674';
      if (i.reorder_point === null) return COLOR_GREY;
      return '#9bd9b4';
    });

    charts.chartInventory = new Chart(canvas, {
      data: {
        labels: active.map(function (i) { return i.name; }),
        datasets: [
          {
            type: 'bar', label: 'Stock', data: active.map(function (i) { return i.stock_quantity; }),
            backgroundColor: barColors, borderRadius: 4, order: 2,
            datalabels: { display: true, anchor: 'end', align: 'end', font: { size: 13 }, formatter: function (v) { return v; } }
          },
          {
            // Same technique the pre-existing chartInventory used to draw the
            // reorder-point reference — a second `line`-type dataset sharing
            // the category axis — rather than adding chartjs-plugin-annotation
            // as a third pinned CDN dependency.
            type: 'line', label: 'Reorder Point', data: active.map(function (i) { return i.reorder_point; }),
            borderColor: '#C8B8A8', borderDash: [6, 4], borderWidth: 2, pointRadius: 3, pointBackgroundColor: '#C8B8A8',
            fill: false, spanGaps: false, order: 1, datalabels: { display: false }
          }
        ]
      },
      options: {
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  // ── KPI strip ────────────────────────────────────────────────────────────
  // Reuses the same .stat-card/.stat-number/.stat-label classes admin/
  // index.php and admin/transactions.php already use for their summary
  // cards, rather than a one-off .policy-box + inline-color combo — the
  // previous inline styles used --crimson (low contrast on --bg-card) for
  // the number and --text-muted (very low contrast, meant for tiny
  // de-emphasized micro-labels elsewhere in the admin panel) for the label
  // and transaction count, which read as hard to read against the dark card.
  function renderKpiStrip(summary) {
    var strip = document.getElementById('kpiStrip');
    if (!strip) return;
    strip.innerHTML = '';
    var periods = [
      ['today', 'Today'], ['week', 'This Week'], ['month', 'This Month'], ['allTime', 'All Time']
    ];
    periods.forEach(function (p) {
      var key = p[0], label = p[1];
      var d = summary[key] || { total: 0, count: 0 };
      var box = el('div', { class: 'stat-card', style: 'text-align:center;' }, [
        el('p', { class: 'stat-number', text: money(d.total), style: 'margin:0 0 0.35rem;' }),
        el('p', { class: 'stat-label', text: label, style: 'margin:0 0 0.35rem;' }),
        el('p', { text: d.count + ' transaction' + (d.count !== 1 ? 's' : ''), style: 'font-size:0.78rem; color:var(--cream-dim); margin:0;' })
      ]);
      strip.appendChild(box);
    });
  }

  // ── Fetch + orchestration ──────────────────────────────────────────────────
  function currentRangeParams() {
    var rangeSelect = document.getElementById('rangeSelect');
    var range = rangeSelect ? rangeSelect.value : 'week';
    var params = { range: range };
    if (range === 'custom') {
      var start = document.getElementById('rangeStart');
      var end = document.getElementById('rangeEnd');
      if (start && start.value) params.start = start.value;
      if (end && end.value) params.end = end.value;
    }
    return params;
  }

  function buildUrl(params) {
    var qs = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    return ENDPOINT + (qs ? '?' + qs : '');
  }

  function loadAndRender() {
    var loading = document.getElementById('rangeLoading');
    if (loading) loading.style.display = 'inline';
    hideGlobalError();

    fetch(buildUrl(currentRangeParams()), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (res) {
        var contentType = res.headers.get('content-type') || '';
        if (contentType.indexOf('application/json') < 0) {
          // Not JSON — almost certainly requireAuth()'s 302 to login.php,
          // followed transparently by fetch() into the login page's HTML.
          throw new Error('SESSION_EXPIRED');
        }
        return res.json();
      })
      .then(function (data) {
        if (!data || data.ok !== true) {
          throw new Error((data && data.error) || 'Unknown error loading report data.');
        }
        renderKpiStrip(data.summary);
        renderChartWeek(data.revenueWeek);
        renderChartMonth(data.revenueMonth);
        renderChartCategory(data.byCategory);
        renderChartMovies(data.topMovies);
        renderChartConcessions(data.topConcessions);
        renderChartInventory(data.inventory);
      })
      .catch(function (err) {
        if (err && err.message === 'SESSION_EXPIRED') {
          showGlobalError('Your admin session has expired. Please reload this page to sign in again.');
        } else {
          showGlobalError('Could not load report data. ' + (err && err.message ? err.message : ''));
        }
      })
      .finally(function () {
        if (loading) loading.style.display = 'none';
      });
  }

  function wireRangeSelector() {
    var form = document.getElementById('rangeForm');
    var select = document.getElementById('rangeSelect');
    var customFields = document.getElementById('customRangeFields');
    if (!form || !select) return;

    function syncCustomVisibility() {
      customFields.style.display = select.value === 'custom' ? 'flex' : 'none';
    }
    syncCustomVisibility();

    select.addEventListener('change', function () {
      syncCustomVisibility();
      if (select.value !== 'custom') {
        loadAndRender();
      }
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      loadAndRender();
    });
  }

  // ── Print: canvas colors are baked-in pixels, not CSS — swap to a
  // light-on-white palette before printing and back after, or dark theme
  // text would be invisible on paper even with admin-print.css in place. ──
  function setPrintPalette(isPrint) {
    if (typeof Chart === 'undefined') return;
    printMode = isPrint;
    // On-screen: pure white text/grid for readability against this theme's
    // dark card background (.policy-box / .report-chart-section render on
    // --bg-card: #1C1410 — white is the highest-contrast choice there).
    // Print: unchanged, still light-on-white per admin-print.css.
    Chart.defaults.color = isPrint ? '#111111' : '#ffffff';
    Chart.defaults.borderColor = isPrint ? '#CCCCCC' : 'rgba(255,255,255,0.1)';
    Object.keys(charts).forEach(function (id) {
      var chart = charts[id];
      if (id === 'chartCategory') {
        chart._centerTotalColor = isPrint ? '#111111' : '#ffffff';
      }
      // 'none' mode skips Chart.js's animation and draws synchronously.
      // With the default animated update(), the redraw is scheduled on a
      // requestAnimationFrame and hasn't actually painted new pixels yet
      // by the time this function returns — which matters a lot for print,
      // since the beforeprint canvas→image snapshot below runs immediately
      // after this and would otherwise capture the *previous* (on-screen)
      // palette baked into the printed PNG instead of the print palette.
      chart.update('none');
    });
  }
  window.addEventListener('beforeprint', function () { setPrintPalette(true); });
  window.addEventListener('afterprint', function () { setPrintPalette(false); });

  // ── Print step 2: canvas → static <img> snapshot ───────────────────────
  // <canvas> is a known cross-browser print liability on top of the color
  // problem above: some engines rasterize it at screen resolution (blurry
  // paper output), others snapshot the page for printing before the canvas
  // has (re)painted at all (blank paper output). Swapping each chart's
  // canvas for a plain <img> built from its own pixels sidesteps both.
  // This only produces the correct result because it's registered as a
  // *second* 'beforeprint' listener — same-event listeners run in
  // registration order, so setPrintPalette(true) above (which now redraws
  // synchronously via update('none')) has already finished recoloring
  // every chart by the time toDataURL() below reads its pixels.
  var canvasPrintSwaps = [];

  function swapCanvasesForPrint() {
    canvasPrintSwaps = [];
    Object.keys(charts).forEach(function (id) {
      var chart = charts[id];
      var canvas = chart && chart.canvas;
      if (!canvas || !canvas.parentNode) return;
      var dataUrl;
      try {
        dataUrl = canvas.toDataURL('image/png', 1.0);
      } catch (e) {
        return; // e.g. a tainted canvas — leave the live canvas in place for print
      }
      var img = el('img', {
        src: dataUrl,
        alt: canvas.getAttribute('aria-label') || '',
        style: 'max-width:100%; height:auto; display:block;'
      });
      canvas.parentNode.insertBefore(img, canvas);
      canvas.style.display = 'none';
      canvasPrintSwaps.push({ canvas: canvas, img: img });
    });
  }

  function restoreCanvasesAfterPrint() {
    canvasPrintSwaps.forEach(function (pair) {
      if (pair.img.parentNode) pair.img.parentNode.removeChild(pair.img);
      pair.canvas.style.display = '';
    });
    canvasPrintSwaps = [];
  }

  window.addEventListener('beforeprint', swapCanvasesForPrint);
  window.addEventListener('afterprint', restoreCanvasesAfterPrint);

  // ── Init ────────────────────────────────────────────────────────────────
  // Apply the on-screen palette up front — without this, Chart.defaults.color
  // stays at Chart.js's own built-in default until the first print cycle
  // fires afterprint, so newly-created charts on a fresh page load would
  // render in the library default instead of this theme's screen palette.
  setPrintPalette(false);
  wireRangeSelector();
  loadAndRender();
})();
