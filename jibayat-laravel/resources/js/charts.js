/**
 * Graphiques du tableau de bord (Chart.js servi en local, chargé seulement s'il y a un graphique).
 */
const fmt = (v) => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);

async function draw() {
    const canvases = document.querySelectorAll('canvas[data-chart]');
    if (!canvases.length) return;

    const { Chart, registerables } = await import('chart.js');
    Chart.register(...registerables);
    Chart.defaults.font.family = 'Inter, "Noto Kufi Arabic", sans-serif';
    Chart.defaults.color = '#6B7280';

    canvases.forEach((canvas) => {
        if (canvas.dataset.drawn) return;
        canvas.dataset.drawn = '1';
        const values = JSON.parse(canvas.dataset.values);

        if (canvas.dataset.chart === 'monthly') {
            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: values.labels,
                    datasets: [
                        { label: values.series?.[0] ?? 'Émis', data: values.emitted, backgroundColor: '#5B7590', borderRadius: 6, maxBarThickness: 22 },
                        { label: values.series?.[1] ?? 'Encaissé', data: values.paid, backgroundColor: '#C9A13B', borderRadius: 6, maxBarThickness: 22 },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: { callbacks: { label: (c) => `${c.dataset.label} : ${fmt(c.parsed.y)} DH` } },
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, grid: { color: '#E6E8EC' }, ticks: { callback: (v) => fmt(v) } },
                    },
                },
            });
        }

        if (canvas.dataset.chart === 'modules') {
            new Chart(canvas, {
                type: 'doughnut',
                data: { labels: values.labels, datasets: [{ data: values.data, backgroundColor: values.colors, borderWidth: 2, borderColor: '#fff' }] },
                options: {
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: { callbacks: { label: (c) => `${c.label} : ${fmt(c.parsed)} DH` } },
                    },
                },
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', draw);
document.addEventListener('livewire:navigated', draw);
