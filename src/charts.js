import {
  Chart,
  BarController,
  BarElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Legend,
} from 'chart.js';

Chart.register(
  BarController,
  BarElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Legend
);

export function initLaunchesPerYearChart(canvasId, yearData) {
  const canvas = document.getElementById(canvasId);
  if (!canvas || !yearData || !yearData.length) return null;

  const labels = yearData.map(item => item.year);
  const successData = yearData.map(item => item.success);
  const failedData = yearData.map(item => Math.max(0, item.total - item.success));

  return new Chart(canvas, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Successful',
          data: successData,
          backgroundColor: '#0284c7', // Sky blue
          borderRadius: 4,
          stack: 'launches',
        },
        {
          label: 'Failed / Partial',
          data: failedData,
          backgroundColor: '#f43f5e', // Rose
          borderRadius: 4,
          stack: 'launches',
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
          position: 'top',
          labels: {
            boxWidth: 12,
            boxHeight: 12,
            font: { size: 12 },
            color: '#64748b',
          },
        },
        tooltip: {
          backgroundColor: 'rgba(15, 23, 42, 0.9)',
          titleFont: { size: 13, weight: '600' },
          bodyFont: { size: 12 },
          padding: 10,
          cornerRadius: 6,
          callbacks: {
            afterTitle: function (context) {
              const idx = context[0].dataIndex;
              const item = yearData[idx];
              return `Total: ${item.total} launches (${item.rate} success)`;
            },
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: {
            color: '#94a3b8',
            font: { size: 11 },
            maxRotation: 45,
          },
        },
        y: {
          beginAtZero: true,
          grid: {
            color: 'rgba(148, 163, 184, 0.15)',
          },
          ticks: {
            color: '#94a3b8',
            font: { size: 11 },
          },
        },
      },
    },
  });
}

export function initLaunchSitesChart(canvasId, siteData) {
  const canvas = document.getElementById(canvasId);
  if (!canvas || !siteData || !siteData.length) return null;

  const sortedData = [...siteData].sort((a, b) => b.total - a.total);
  const labels = sortedData.map(item => item.name);
  const successData = sortedData.map(item => item.success);
  const failedData = sortedData.map(item => Math.max(0, item.total - item.success));

  return new Chart(canvas, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Successful',
          data: successData,
          backgroundColor: '#3b82f6', // Blue
          borderRadius: 4,
          stack: 'sites',
        },
        {
          label: 'Failed',
          data: failedData,
          backgroundColor: '#f43f5e', // Rose
          borderRadius: 4,
          stack: 'sites',
        },
      ],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      plugins: {
        legend: {
          position: 'top',
          labels: {
            boxWidth: 12,
            boxHeight: 12,
            font: { size: 12 },
            color: '#64748b',
          },
        },
        tooltip: {
          backgroundColor: 'rgba(15, 23, 42, 0.9)',
          titleFont: { size: 13, weight: '600' },
          bodyFont: { size: 12 },
          padding: 10,
          cornerRadius: 6,
          callbacks: {
            afterTitle: function (context) {
              const idx = context[0].dataIndex;
              const item = sortedData[idx];
              return `Total: ${item.total} launches (${item.rate})`;
            },
          },
        },
      },
      scales: {
        x: {
          beginAtZero: true,
          grid: {
            color: 'rgba(148, 163, 184, 0.15)',
          },
          ticks: {
            color: '#94a3b8',
            font: { size: 11 },
          },
        },
        y: {
          grid: { display: false },
          ticks: {
            color: '#94a3b8',
            font: { size: 11 },
          },
        },
      },
    },
  });
}
