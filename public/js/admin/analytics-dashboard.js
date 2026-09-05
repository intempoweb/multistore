(function () {
    const periodSelect = document.querySelector('[data-dashboard-period]');
    const customFields = Array.from(document.querySelectorAll('[data-custom-period-field]'));

    const syncCustomFields = () => {
        const showCustom = periodSelect && periodSelect.value === 'custom';

        customFields.forEach((field) => {
            field.classList.toggle('d-none', !showCustom);
        });
    };

    if (periodSelect) {
        periodSelect.addEventListener('change', syncCustomFields);
        syncCustomFields();
    }

    const canvas = document.getElementById('salesTrendChart');

    if (!canvas || !window.Chart) {
        return;
    }

    let rows = [];

    try {
        rows = JSON.parse(canvas.dataset.chart || '[]');
    } catch (error) {
        rows = [];
    }

    const empty = rows.length === 0 || rows.every((row) => Number(row.revenue || 0) === 0 && Number(row.orders || 0) === 0);

    if (empty) {
        const wrapper = canvas.closest('.admin-chart-box');
        if (wrapper) {
            wrapper.classList.add('admin-chart-box--empty');
            wrapper.insertAdjacentHTML('beforeend', '<div class="admin-chart-empty">Nessun dato vendite nel periodo selezionato.</div>');
        }
    }

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: rows.map((row) => row.label),
            datasets: [
                {
                    label: 'Fatturato',
                    data: rows.map((row) => Number(row.revenue || 0)),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.12)',
                    fill: true,
                    tension: 0.32,
                    yAxisID: 'money',
                },
                {
                    label: 'Ordini',
                    data: rows.map((row) => Number(row.orders || 0)),
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.12)',
                    tension: 0.32,
                    yAxisID: 'orders',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            if (context.dataset.yAxisID === 'money') {
                                return context.dataset.label + ': ' + new Intl.NumberFormat('it-IT', {
                                    style: 'currency',
                                    currency: 'EUR',
                                }).format(context.parsed.y || 0);
                            }

                            return context.dataset.label + ': ' + new Intl.NumberFormat('it-IT').format(context.parsed.y || 0);
                        },
                    },
                },
            },
            scales: {
                money: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return new Intl.NumberFormat('it-IT', {
                                style: 'currency',
                                currency: 'EUR',
                                maximumFractionDigits: 0,
                            }).format(value);
                        },
                    },
                },
                orders: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        precision: 0,
                    },
                },
            },
        },
    });
})();
