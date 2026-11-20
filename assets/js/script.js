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
      if (event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) {
        return;
      }

      const activeTag = document.activeElement?.tagName?.toLowerCase();
      if (activeTag === 'input' || activeTag === 'textarea') {
        return;
      }

      if (['1', '2', '3', '4'].includes(event.key)) {
        const option = annotationForm.querySelectorAll('input[name="selected_sense"]')[Number(event.key) - 1];
        if (option) {
          event.preventDefault();
          option.checked = true;
          option.dispatchEvent(new Event('change', { bubbles: true }));
        }
        return;
      }

      if (event.key === 'Enter') {
        event.preventDefault();
        annotationForm.requestSubmit();
      }
    });
  }
});
