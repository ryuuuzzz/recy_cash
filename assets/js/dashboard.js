(function () {
    'use strict';

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function cssToken(name, fallback) {
        var value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return value || fallback;
    }

    function formatRupiah(value) {
        var number = Number(value) || 0;
        return 'Rp ' + new Intl.NumberFormat('id-ID', {
            maximumFractionDigits: 0
        }).format(number);
    }

    function formatDecimal(value) {
        var number = Number(value) || 0;
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(number);
    }

    function initMonthlyChart() {
        var canvas = document.getElementById('chartBulanan');
        var config = window.RecyCashMonthlyChart;

        if (!canvas || !config || typeof window.Chart === 'undefined') {
            return;
        }

        var totalBerat = (config.berat || []).reduce(function (total, value) {
            return total + (Number(value) || 0);
        }, 0);

        if (totalBerat <= 0) {
            canvas.style.display = 'none';
            var empty = document.getElementById('chartEmpty');
            if (empty) {
                empty.classList.remove('d-none');
            }
            return;
        }

        var ctx = canvas.getContext('2d');
        var fontFamily = getComputedStyle(document.body).fontFamily;

        window.Chart.defaults.font.family = fontFamily;
        window.Chart.defaults.font.size = 11;

        function barGradient(context) {
            var chart = context.chart;
            var area = chart.chartArea;
            var canvasContext = chart.ctx;

            if (!area) {
                return 'rgba(16, 185, 129, 0.8)';
            }

            var gradient = canvasContext.createLinearGradient(0, area.bottom, 0, area.top);
            gradient.addColorStop(0, 'rgba(5, 150, 105, 0.38)');
            gradient.addColorStop(0.55, 'rgba(16, 185, 129, 0.82)');
            gradient.addColorStop(1, 'rgba(163, 230, 53, 0.96)');
            return gradient;
        }

        var textColor = cssToken('--text-muted', '#64748b');
        var gridColor = cssToken('--border', '#e2e8f0');

        var chart = new window.Chart(ctx, {
            type: 'bar',
            data: {
                labels: config.labels || [],
                datasets: [{
                    label: 'Berat Sampah',
                    data: config.berat || [],
                    nominal: config.nominal || [],
                    transaksi: config.transaksi || [],
                    backgroundColor: barGradient,
                    hoverBackgroundColor: barGradient,
                    borderRadius: 9,
                    borderSkipped: false,
                    maxBarThickness: 46
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: reducedMotion ? 0 : 900,
                    easing: 'easeOutQuart'
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(5, 46, 34, 0.94)',
                        titleColor: '#ffffff',
                        bodyColor: '#d1fae5',
                        footerColor: '#bef264',
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: false,
                        callbacks: {
                            label: function (context) {
                                return 'Berat: ' + formatDecimal(context.raw) + ' kg';
                            },
                            afterLabel: function (context) {
                                var dataset = context.dataset;
                                var nominal = dataset.nominal[context.dataIndex] || 0;
                                var trx = dataset.transaksi[context.dataIndex] || 0;
                                return [
                                    'Nilai: ' + formatRupiah(nominal),
                                    'Setoran: ' + trx + ' transaksi'
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            color: textColor,
                            autoSkip: true,
                            maxTicksLimit: window.innerWidth < 576 ? 6 : 12,
                            maxRotation: 0,
                            minRotation: 0,
                            font: {
                                family: fontFamily,
                                size: 10,
                                weight: '600'
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        border: {
                            display: false
                        },
                        grid: {
                            color: gridColor,
                            drawBorder: false
                        },
                        ticks: {
                            color: textColor,
                            padding: 8,
                            callback: function (value) {
                                return formatDecimal(value) + ' kg';
                            },
                            font: {
                                family: fontFamily,
                                size: 10,
                                weight: '600'
                            }
                        }
                    }
                }
            }
        });

        document.addEventListener('recycash:themechange', function () {
            var nextTextColor = cssToken('--text-muted', '#64748b');
            var nextGridColor = cssToken('--border', '#e2e8f0');

            chart.options.scales.x.ticks.color = nextTextColor;
            chart.options.scales.y.ticks.color = nextTextColor;
            chart.options.scales.y.grid.color = nextGridColor;
            chart.update('none');
        });

        window.addEventListener('resize', function () {
            chart.options.scales.x.ticks.maxTicksLimit = window.innerWidth < 576 ? 6 : 12;
            chart.update('none');
        });
    }

    function initWeeklyChart() {
        var canvas = document.getElementById('chartMingguan');
        var config = window.RecyCashWeeklyChart;

        if (!canvas || !config || typeof window.Chart === 'undefined') {
            return;
        }

        var totalBerat = (config.berat || []).reduce(function (total, value) {
            return total + (Number(value) || 0);
        }, 0);

        if (totalBerat <= 0) {
            canvas.style.display = 'none';
            var empty = document.getElementById('weeklyChartEmpty');
            if (empty) {
                empty.classList.remove('d-none');
            }
            return;
        }

        var ctx = canvas.getContext('2d');
        var fontFamily = getComputedStyle(document.body).fontFamily;
        var textColor = cssToken('--text-muted', '#64748b');
        var gridColor = cssToken('--border', '#e2e8f0');

        window.Chart.defaults.font.family = fontFamily;
        window.Chart.defaults.font.size = 11;

        function areaGradient(context) {
            var chart = context.chart;
            var area = chart.chartArea;

            if (!area) {
                return 'rgba(16, 185, 129, 0.15)';
            }

            var gradient = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
            gradient.addColorStop(1, 'rgba(163, 230, 53, 0.02)');
            return gradient;
        }

        var chart = new window.Chart(ctx, {
            type: 'line',
            data: {
                labels: config.labels || [],
                datasets: [{
                    label: 'Berat Sampah',
                    data: config.berat || [],
                    nominal: config.nominal || [],
                    transaksi: config.transaksi || [],
                    borderColor: '#10b981',
                    borderWidth: 2.5,
                    backgroundColor: areaGradient,
                    fill: true,
                    tension: 0.38,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#059669',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: reducedMotion ? 0 : 900,
                    easing: 'easeOutQuart'
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(5, 46, 34, 0.94)',
                        titleColor: '#ffffff',
                        bodyColor: '#d1fae5',
                        footerColor: '#bef264',
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: false,
                        callbacks: {
                            label: function (context) {
                                return 'Berat: ' + formatDecimal(context.raw) + ' kg';
                            },
                            afterLabel: function (context) {
                                var dataset = context.dataset;
                                var nominal = dataset.nominal[context.dataIndex] || 0;
                                var trx = dataset.transaksi[context.dataIndex] || 0;
                                return [
                                    'Nilai: ' + formatRupiah(nominal),
                                    'Setoran: ' + trx + ' transaksi'
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            color: textColor,
                            maxRotation: 0,
                            minRotation: 0,
                            font: {
                                family: fontFamily,
                                size: 10,
                                weight: '600'
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        border: {
                            display: false
                        },
                        grid: {
                            color: gridColor,
                            drawBorder: false
                        },
                        ticks: {
                            color: textColor,
                            padding: 8,
                            callback: function (value) {
                                return formatDecimal(value) + ' kg';
                            },
                            font: {
                                family: fontFamily,
                                size: 10,
                                weight: '600'
                            }
                        }
                    }
                }
            }
        });

        document.addEventListener('recycash:themechange', function () {
            var nextTextColor = cssToken('--text-muted', '#64748b');
            var nextGridColor = cssToken('--border', '#e2e8f0');

            chart.options.scales.x.ticks.color = nextTextColor;
            chart.options.scales.y.ticks.color = nextTextColor;
            chart.options.scales.y.grid.color = nextGridColor;
            chart.update('none');
        });
    }

    function initStockChart() {
        var canvas = document.getElementById('chartStok');
        var config = window.RecyCashStockChart;

        if (!canvas || !config || typeof window.Chart === 'undefined') {
            return;
        }

        var totalStok = (config.values || []).reduce(function (total, value) {
            return total + (Number(value) || 0);
        }, 0);

        if (totalStok <= 0) {
            canvas.style.display = 'none';
            var empty = document.getElementById('stockChartEmpty');
            var totalLabel = document.querySelector('.stock-total');

            if (empty) {
                empty.classList.remove('d-none');
            }
            if (totalLabel) {
                totalLabel.style.display = 'none';
            }
            return;
        }

        var ctx = canvas.getContext('2d');
        var fontFamily = getComputedStyle(document.body).fontFamily;
        var textColor = cssToken('--text-muted', '#64748b');

        window.Chart.defaults.font.family = fontFamily;
        window.Chart.defaults.font.size = 10;

        var chart = new window.Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: config.labels || [],
                datasets: [{
                    data: config.values || [],
                    backgroundColor: config.colors || ['#059669', '#34d399', '#a3e635'],
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                animation: {
                    duration: reducedMotion ? 0 : 900,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColor,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            boxWidth: 7,
                            boxHeight: 7,
                            padding: 12,
                            font: {
                                family: fontFamily,
                                size: 10,
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(5, 46, 34, 0.94)',
                        titleColor: '#ffffff',
                        bodyColor: '#d1fae5',
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: false,
                        callbacks: {
                            label: function (context) {
                                var value = Number(context.raw) || 0;
                                var porsi = totalStok > 0 ? (value / totalStok) * 100 : 0;
                                return formatDecimal(value) + ' kg (' + formatDecimal(porsi) + '%)';
                            }
                        }
                    }
                }
            }
        });

        document.addEventListener('recycash:themechange', function () {
            chart.options.plugins.legend.labels.color = cssToken('--text-muted', '#64748b');
            chart.update('none');
        });
    }

    function initHistoryModal() {
        var modal = document.getElementById('modalDetailSetoran');
        if (!modal) {
            return;
        }

        var kodeEl = document.getElementById('detailKode');
        var tanggalEl = document.getElementById('detailTanggal');
        var beratEl = document.getElementById('detailBerat');
        var nominalEl = document.getElementById('detailNominal');
        var itemsEl = document.getElementById('detailItems');

        modal.addEventListener('show.bs.modal', function (event) {
            var row = event.relatedTarget;
            if (!row) {
                return;
            }

            var detail = [];
            try {
                detail = JSON.parse(row.getAttribute('data-detail') || '[]');
            } catch (error) {
                detail = [];
            }

            if (kodeEl) {
                kodeEl.textContent = row.getAttribute('data-kode') || '-';
            }
            if (tanggalEl) {
                tanggalEl.textContent = row.getAttribute('data-tanggal') || '-';
            }
            if (beratEl) {
                beratEl.textContent = formatDecimal(row.getAttribute('data-berat')) + ' kg';
            }
            if (nominalEl) {
                nominalEl.textContent = formatRupiah(row.getAttribute('data-nominal'));
            }

            if (!itemsEl) {
                return;
            }

            itemsEl.innerHTML = '';

            if (!Array.isArray(detail) || detail.length === 0) {
                var emptyRow = document.createElement('tr');
                var emptyCell = document.createElement('td');
                emptyCell.colSpan = 4;
                emptyCell.className = 'text-center text-muted py-4';
                emptyCell.textContent = 'Rincian item tidak tersedia.';
                emptyRow.appendChild(emptyCell);
                itemsEl.appendChild(emptyRow);
                return;
            }

            detail.forEach(function (item) {
                var rowEl = document.createElement('tr');

                var nameCell = document.createElement('td');
                nameCell.textContent = item.nama_kategori || '-';
                nameCell.className = 'fw-semibold';

                var weightCell = document.createElement('td');
                weightCell.textContent = formatDecimal(item.berat_kg) + ' kg';
                weightCell.className = 'text-end';

                var priceCell = document.createElement('td');
                priceCell.textContent = formatRupiah(item.harga_per_kg);
                priceCell.className = 'text-end text-muted';

                var subtotalCell = document.createElement('td');
                subtotalCell.textContent = formatRupiah(item.subtotal);
                subtotalCell.className = 'text-end fw-semibold';

                rowEl.appendChild(nameCell);
                rowEl.appendChild(weightCell);
                rowEl.appendChild(priceCell);
                rowEl.appendChild(subtotalCell);
                itemsEl.appendChild(rowEl);
            });
        });

        document.querySelectorAll('.history-row').forEach(function (row) {
            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    row.click();
                }
            });
        });
    }

    function init() {
        initMonthlyChart();
        initWeeklyChart();
        initStockChart();
        initHistoryModal();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();

