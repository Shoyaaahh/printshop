(function () {
  if (!window.Chart || !window.CHART) return;
  var C = window.CHART, charts = [];
  var palette = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#64748b', '#f97316', '#14b8a6', '#6366f1'];

  function css(v) { return getComputedStyle(document.documentElement).getPropertyValue(v).trim(); }
  function peso(v) { return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }); }
  function make(id, cfg) {
    var el = document.getElementById(id); if (!el) return null;
    var ch = new Chart(el, cfg); charts.push(ch); return ch;
  }
  function base() {
    Chart.defaults.color = css('--muted') || '#64748b';
    Chart.defaults.borderColor = css('--border-subtle') || 'rgba(226, 232, 240, 0.6)';
    Chart.defaults.font.family = css('--font-display') || 'sans-serif';
    Chart.defaults.font.size = 12;
  }

  function build() {
    charts.forEach(function (c) { c.destroy(); }); charts = [];
    base();

    var isDark = document.documentElement.dataset.theme === 'dark';
    var primaryBlue = isDark ? '#3b82f6' : '#2563eb';
    var blueGlow = isDark ? 'rgba(59, 130, 246, 0.22)' : 'rgba(37, 99, 235, 0.12)';

    var scale = {
      y: {
        beginAtZero: true,
        grid: { color: isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.04)' },
        ticks: { callback: peso, font: { weight: 600 } }
      },
      x: {
        grid: { display: false },
        ticks: { font: { weight: 600 } }
      }
    };

    var sales = make('salesChart', {
      type: 'bar',
      data: {
        labels: C.daily.labels,
        datasets: [{
          label: 'Sales Revenue',
          data: C.daily.data,
          backgroundColor: primaryBlue,
          borderRadius: 6,
          borderSkipped: false,
          hoverBackgroundColor: isDark ? '#60a5fa' : '#1d4ed8'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: isDark ? '#0e1726' : '#0c162c',
            titleFont: { weight: 700 },
            bodyFont: { weight: 600 },
            padding: 10,
            cornerRadius: 8,
            callbacks: { label: function (c) { return ' Verified: ' + peso(c.parsed.y); } }
          }
        },
        scales: scale
      }
    });

    var sel = document.getElementById('period');
    if (sel && sales) {
      sel.onchange = function () {
        sales.data.labels = C[sel.value].labels;
        sales.data.datasets[0].data = C[sel.value].data;
        sales.update();
      };
    }

    make('trendChart', {
      type: 'line',
      data: {
        labels: C.monthly.labels,
        datasets: [{
          label: 'Revenue Trend',
          data: C.monthly.data,
          borderColor: primaryBlue,
          borderWidth: 2.5,
          backgroundColor: blueGlow,
          fill: true,
          tension: 0.35,
          pointBackgroundColor: primaryBlue,
          pointBorderColor: '#ffffff',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: isDark ? '#0e1726' : '#0c162c',
            padding: 10,
            cornerRadius: 8,
            callbacks: { label: function (c) { return ' Monthly: ' + peso(c.parsed.y); } }
          }
        },
        scales: scale
      }
    });

    make('popChart', {
      type: 'bar',
      data: {
        labels: C.popular.labels,
        datasets: [{
          label: 'Orders',
          data: C.popular.data,
          backgroundColor: isDark ? '#1e293b' : '#334155',
          borderRadius: 6,
          hoverBackgroundColor: primaryBlue
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: {
            beginAtZero: true,
            ticks: { precision: 0 },
            grid: { color: isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.04)' }
          },
          y: { grid: { display: false } }
        }
      }
    });

    make('statusChart', {
      type: 'doughnut',
      data: {
        labels: C.status.labels,
        datasets: [{
          data: C.status.data,
          backgroundColor: palette,
          borderWidth: isDark ? 2 : 2,
          borderColor: isDark ? '#0e1726' : '#ffffff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          legend: {
            position: 'right',
            labels: { boxWidth: 12, padding: 12, font: { weight: 600 } }
          }
        }
      }
    });
  }

  build();
  document.addEventListener('themechange', build);
})();