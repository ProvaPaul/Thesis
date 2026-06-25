document.addEventListener('DOMContentLoaded', () => {
  const chartEl = document.getElementById('senseChart');
  if (chartEl && typeof Chart !== 'undefined') {
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
      },
    });
  }

  const annotationForm = document.querySelector('form[action="submit_annotation.php"]');
  if (annotationForm) {
    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) {
        return;
      }

      const activeTag = document.activeElement?.tagName?.toLowerCase();
      if (activeTag === 'textarea') {
        return;
      }

      event.preventDefault();
      annotationForm.requestSubmit();
    });
  }
});
