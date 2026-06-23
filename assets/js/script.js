document.addEventListener('DOMContentLoaded', () => {
  const chartEl = document.getElementById('senseChart');
  if (!chartEl || typeof Chart === 'undefined') {
    return;
  }

  const labels = JSON.parse(chartEl.dataset.labels || '[]');
  const values = JSON.parse(chartEl.dataset.values || '[]');

  new Chart(chartEl, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Annotations',
        data: values,
        backgroundColor: '#1f6feb'
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false }
      },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } }
      }
    }
  });
});
