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
  var COLOR_GREY = '#5a6478';

  var charts = {}; // id -> Chart instance, so print can snapshot them all

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
    var n;
    try {
      // Number(v) on some objects (e.g. a Chart.js internal context/proxy
      // passed in by mistake) can itself throw "Cannot convert object to
      // primitive value" rather than returning NaN — guard the coercion
      // itself, not just the result, so this formatter never crashes.
      n = (v !== null && typeof v === 'object')
        ? (typeof v.y === 'number' ? v.y : NaN)
        : Number(v);
    } catch (e) {
      n = NaN;
    }
    if (typeof n !== 'number' || isNaN(n)) n = 0;
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
            // Labels hidden by default — adjacent bars near the same height
            // (e.g. both near the axis max) collided into unreadable
            // overlapping text. Value is still available via the tooltip.
            datalabels: { display: false }
          },
          {
            label: 'Last Week', data: revenueWeek.lastWeek,
            backgroundColor: COLOR_LAST_WEEK, borderColor: '#8B1A2E', borderWidth: 2, borderDash: [5, 3], borderRadius: 4,
            datalabels: { display: false }
          }
        ]
      },
      options: {
        // Top padding gives the tallest bar's datalabel room to render above
        // the chart area instead of clipping against the canvas edge.
        layout: { padding: { top: 24 } },
        plugins: {
          legend: { position: 'bottom' },
          tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + money(c.parsed.y); } } }
        },
        scales: { y: { beginAtZero: true, ticks: { callback: money } } }
      }
    });
  }

  // ── Chart 2: this month vs last month, both by day-of-month ────────────────
  function renderChartMonth(revenueMonth, lastMonthData, currentLabel, lastLabel) {
    var canvas = document.getElementById('chartMonth');
    buildDataTable('chartMonthTable', ['Day', currentLabel || 'This Month', lastLabel || 'Last Month'], revenueMonth.labels.map(function (d, i) {
      return [d, money(revenueMonth.data[i]), money((lastMonthData || [])[i] || 0)];
    }));
    var titleEl = document.getElementById('chartMonthTitle');
    if (titleEl) {
      titleEl.textContent = 'Revenue by Day — ' + (currentLabel || 'This Month') + ' vs. ' + (lastLabel || 'Last Month');
    }
    if (!canvas || typeof Chart === 'undefined') return;
    destroyChart('chartMonth');

    // lastMonthData is always a complete month (28-31 days); revenueMonth.data
    // only covers days elapsed so far this month, which is always <= that —
    // except on the last day of a longer month than last month. Use the
    // longer of the two for day-of-month labels so neither series is cut off.
    var dayCount = Math.max(revenueMonth.data.length, (lastMonthData || []).length);
    var labels = [];
    for (var i = 1; i <= dayCount; i++) labels.push(i);

    var datasets = [
      {
        type: 'line', label: currentLabel || 'This Month', data: revenueMonth.data,
        borderColor: '#8B1D33', backgroundColor: 'rgba(139,29,51,0.18)', fill: true, tension: 0.25, pointRadius: 3,
        order: 2, datalabels: { display: false }
      }
    ];
    if (lastMonthData && lastMonthData.some(function (v) { return v > 0; })) {
      datasets.push({
        type: 'line', label: lastLabel || 'Last Month', data: lastMonthData,
        borderColor: '#C8B8A8', backgroundColor: 'transparent', borderDash: [5, 3], borderWidth: 2, pointRadius: 2, fill: false,
        order: 1, datalabels: { display: false }
      });
    }

    charts.chartMonth = new Chart(canvas, {
      data: { labels: labels, datasets: datasets },
      options: {
        plugins: {
          legend: { position: 'bottom', labels: { color: '#ffffff' } },
          tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + money(c.parsed.y); } } }
        },
        scales: { y: { beginAtZero: true, ticks: { callback: money } } }
      }
    });
  }

  // ── Chart: daily transaction count by channel (stacked bars) ──────────────
  function renderChartTransactions(d) {
    var canvas = document.getElementById('chartTransactions');
    if (!d || !d.length) return;
    buildDataTable('chartTransactionsTable', ['Day', 'Online', 'Walk-Up', 'Kiosk', 'Total Orders', 'Revenue'], d.map(function (r) {
      return [r.day, String(r.online), String(r.walkin), String(r.kiosk), String(r.txn_count), money(r.revenue)];
    }));
    if (!canvas || typeof Chart === 'undefined') return;
    destroyChart('chartTransactions');

    charts.chartTransactions = new Chart(canvas, {
      data: {
        labels: d.map(function (r) { return r.day; }),
        datasets: [
          { type: 'bar', label: 'Online', data: d.map(function (r) { return r.online; }), backgroundColor: '#3a5a7a', stack: 'txn', datalabels: { display: false } },
          { type: 'bar', label: 'Walk-Up', data: d.map(function (r) { return r.walkin; }), backgroundColor: '#4a7a5a', stack: 'txn', datalabels: { display: false } },
          { type: 'bar', label: 'Kiosk', data: d.map(function (r) { return r.kiosk; }), backgroundColor: '#6a4a8a', stack: 'txn', datalabels: { display: false } }
        ]
      },
      options: {
        plugins: {
          legend: { position: 'bottom', labels: { color: '#ffffff', font: { size: 12 } } },
          datalabels: { display: false },
          tooltip: {
            callbacks: {
              label: function (c) { return c.dataset.label + ': ' + c.parsed.y + ' order' + (c.parsed.y !== 1 ? 's' : ''); }
            }
          }
        },
        scales: {
          x: { stacked: true, ticks: { color: '#ffffff', font: { size: 11 }, maxRotation: 45 }, grid: { color: 'rgba(255,255,255,0.05)' } },
          y: { stacked: true, beginAtZero: true, ticks: { color: '#ffffff', stepSize: 1, precision: 0 }, grid: { color: 'rgba(255,255,255,0.1)' } }
        }
      }
    });
  }

  // ── Chart 6: tickets sold vs. scanned (attended) per now-showing movie ────
  function renderChartScanRate(d) {
    var canvas = document.getElementById('chartScanRate');
    buildDataTable('chartScanRateTable', ['Movie', 'Tickets Sold', 'Tickets Scanned'], (d || []).map(function (r) {
      return [r.title, String(r.tickets_sold), String(r.tickets_scanned)];
    }));
    if (!d || !d.length || !canvas || typeof Chart === 'undefined') return;
    destroyChart('chartScanRate');

    charts.chartScanRate = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: d.map(function (r) { return r.title.length > 20 ? r.title.slice(0, 19) + '…' : r.title; }),
        datasets: [
          {
            label: 'Tickets Sold', data: d.map(function (r) { return r.tickets_sold; }),
            backgroundColor: '#3a5a7a',
            datalabels: { display: true, anchor: 'end', align: 'right', color: '#ffffff', font: { size: 11 } }
          },
          {
            label: 'Scanned / Attended', data: d.map(function (r) { return r.tickets_scanned; }),
            backgroundColor: COLOR_THIS_WEEK,
            datalabels: { display: true, anchor: 'end', align: 'right', color: '#ffffff', font: { size: 11 } }
          }
        ]
      },
      options: {
        indexAxis: 'y',
        plugins: {
          legend: { position: 'bottom', labels: { color: '#ffffff' } },
          tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + c.parsed.x; } } }
        },
        scales: {
          x: { beginAtZero: true, ticks: { color: '#ffffff', precision: 0 }, grid: { color: 'rgba(255,255,255,0.1)' } },
          y: { ticks: { color: '#ffffff', font: { size: 12 } }, grid: { color: 'rgba(255,255,255,0.05)' } }
        }
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
              // No-sales categories render as tiny adjacent epsilon slivers
              // (see renderData above) — their labels have no room to avoid
              // colliding with each other when 2+ categories are empty.
              // That info is already in the tooltip and data table, so the
              // on-chart label is suppressed here rather than overlapping.
              if (byCategory.noSales[i]) return '';
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

  // ── Chart 4 (movies): stacked adult/child bars ─────────────────────────────
  // Poster thumbnails + the afterLayout alignment mechanism that pinned them
  // to each bar were removed — they misaligned in print/PDF output (a
  // separate DOM column can't be captured by the canvas-to-image print
  // snapshot) and pushed movie titles out of legible space. Plain horizontal
  // bar chart with the title as the y-axis label instead.
  function renderChartMovies(topMovies) {
    var canvas = document.getElementById('chartMovies');
    buildDataTable('chartMoviesTable', ['Movie', 'Adult', 'Child', 'Total Tickets', 'Revenue'], topMovies.map(function (m) {
      return [m.item_name, String(m.adult_count), String(m.child_count), String(m.total_qty), money(m.total_revenue)];
    }));

    var n = topMovies.length;
    destroyChart('chartMovies');
    if (!canvas || typeof Chart === 'undefined' || !n) return;

    canvas.height = Math.max(260, n * 50);

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
          legend: { position: 'bottom', labels: { color: '#ffffff' } },
          datalabels: { display: true, formatter: function (v) { return v > 0 ? v : ''; } }
        },
        scales: {
          x: { beginAtZero: true, stacked: true, ticks: { precision: 0, color: '#ffffff' }, grid: { color: 'rgba(255,255,255,0.1)' } },
          y: {
            stacked: true,
            ticks: {
              color: '#ffffff',
              autoSkip: false,
              // Chart.js doesn't truncate long category labels on its own —
              // a long movie title would otherwise just run past/under the
              // axis with no visual indicator that it was cut off. Full
              // title is still in the tooltip and the data table below.
              callback: function (value) {
                var lbl = this.getLabelForValue(value);
                return lbl.length > 24 ? lbl.slice(0, 23) + '…' : lbl;
              }
            },
            grid: { color: 'rgba(255,255,255,0.1)' }
          }
        }
      }
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
    // Vertical layout — height matches the other chart sections (280) and
    // width instead scales with item count so category labels on the x-axis
    // don't get crushed; chartInventoryWrap's parent has overflow-x:auto in
    // reports.php so it scrolls horizontally past that.
    var wrap = document.getElementById('chartInventoryWrap');
    canvas.height = 280;
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
            datalabels: { display: true, anchor: 'end', align: 'top', clip: false, color: '#ffffff', font: { size: 12, weight: 'bold' }, formatter: function (v) { return v; } }
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
        // Top padding gives the tallest bar's datalabel room to render above
        // the chart area instead of clipping against the canvas edge — same
        // fix already applied to the week revenue chart.
        layout: { padding: { top: 24 } },
        plugins: { legend: { position: 'bottom' } },
        // Not beginAtZero here — every product currently sits in a tight
        // 42-50 range with no reorder points set, so a 0-50 axis makes every
        // bar look nearly identical and adds no information (unlike the
        // week/month revenue charts, which stay zero-based because they
        // legitimately span down toward zero). Min is computed below from
        // the real data once it's known, not hardcoded.
        scales: { y: { ticks: { precision: 0, stepSize: 2 } } }
      }
    });

    var stockValues = active.map(function (i) { return i.stock_quantity; });
    if (stockValues.length) {
      var minStock = Math.min.apply(null, stockValues);
      charts.chartInventory.options.scales.y.min = Math.max(0, Math.floor(minStock / 5) * 5 - 5);
      charts.chartInventory.update('none');
    }
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

  // The week-vs-last-week chart is intrinsically fixed-period (always "this
  // week vs last week", regardless of the range selector) — but showing it
  // unconditionally next to a "This Month" selection reads as a bug to an
  // owner who just changed the range and still sees a week chart. Hide it
  // outside 'week' range and explain why via a note in its place.
  function updateRangeVisibility(range) {
    var weekSection  = document.getElementById('chart-week-section');
    var monthSection = document.getElementById('chart-month-section');
    // 'today' groups with 'week', not 'month' — a month-over-month trend
    // line has nothing to do with a single day, whereas the week chart's
    // most recent daily bar IS today, giving it visible context.
    var showWeek = (range === 'week' || range === 'today');
    if (weekSection)  weekSection.style.display  = showWeek ? '' : 'none';
    if (monthSection) monthSection.style.display = showWeek ? 'none' : '';
  }

  function rangeLabel(key) {
    switch (key) {
      case 'today':  return 'Today';
      case 'week':   return 'This Week';
      case 'month':  return 'This Month';
      case 'custom': return 'Custom Range';
      default:       return 'This Week';
    }
  }

  // Reflects the selected range in the titles of the three charts whose data
  // actually changes with it (category/movies/concessions — see
  // reports-data.php's top-of-file note on which charts are range-scoped).
  function updateRangeScopedTitles(rangeKey) {
    var label = rangeLabel(rangeKey);
    var categoryTitle    = document.getElementById('chartCategoryTitle');
    var moviesTitle       = document.getElementById('chartMoviesTitle');
    var concessionsTitle = document.getElementById('chartConcessionsTitle');
    if (categoryTitle)    categoryTitle.textContent    = 'Revenue by Category — ' + label;
    if (moviesTitle)       moviesTitle.textContent       = 'Top 5 Movies (Tickets Sold) — ' + label;
    if (concessionsTitle) concessionsTitle.textContent = 'Top 5 Concessions (Units Sold) — ' + label;
  }

  function loadAndRender() {
    var loading = document.getElementById('rangeLoading');
    if (loading) loading.style.display = 'inline';
    hideGlobalError();

    var params = currentRangeParams();
    updateRangeVisibility(params.range);

    fetch(buildUrl(params), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
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
        renderChartMonth(data.revenueMonth, data.revenueLastMonth, data.currentMonthLabel, data.lastMonthLabel);
        renderChartTransactions(data.transactionSummary);
        renderChartScanRate(data.ticketScanRate);
        renderChartCategory(data.byCategory);
        renderChartMovies(data.topMovies);
        renderChartConcessions(data.topConcessions);
        renderChartInventory(data.inventory);
        updateRangeScopedTitles(data.range && data.range.key);
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

  // ── Print: canvas → static <img> snapshot, no chart recoloring ─────────────
  // Earlier builds recolored each live chart for print by mutating
  // chart.options.scales in place and calling chart.update('none') — that
  // was the actual cause of two separate confirmed crashes (a stack overflow
  // inside Chart.js's own option-resolution internals, and a "cannot convert
  // object to primitive" thrown from a tick callback mid-resolution), not a
  // simple re-entrancy bug — a recursion guard around setPrintPalette did not
  // fix it because the second call itself was corrupting state, not calling
  // itself. Snapshotting each canvas exactly as currently rendered (no
  // mutation, no update()) sidesteps the whole class of problem. Legibility
  // of the white-on-dark on-screen palette is handled in admin-print.css by
  // keeping a dark backdrop behind the snapshot image instead of forcing a
  // light background the source pixels were never drawn for.
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
        'class': 'print-canvas-img',
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

  window.printAdminReport = function () {
    swapCanvasesForPrint();
    // One tick so the browser has actually painted the newly-inserted <img>
    // elements before the print snapshot is taken.
    setTimeout(function () {
      window.print();
      setTimeout(restoreCanvasesAfterPrint, 1000);
    }, 150);
  };

  var printBtn = document.getElementById('btn-print-report');
  if (printBtn) {
    printBtn.addEventListener('click', function () {
      if (typeof window.printAdminReport === 'function') {
        window.printAdminReport();
      } else {
        window.print();
      }
    });
  }

  wireRangeSelector();
  loadAndRender();
})();
