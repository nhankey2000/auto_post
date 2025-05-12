<x-filament::page>
    <div class="mb-4">
        {{ $this->form }}
    </div>

    <div>
        <canvas id="growthChart" style="max-height: 400px;"></canvas>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const ctx = document.getElementById('growthChart').getContext('2d');
                const chart = new Chart(ctx, {
                    type: 'line',
                    data: @json($this->getChartData()),
                    options: {
                        scales: {
                            y: {
                                beginAtZero: false,
                                min: {{ $this->getChartData()['options']['scales']['y']['min'] }},
                                max: {{ $this->getChartData()['options']['scales']['y']['max'] }},
                                ticks: {
                                    stepSize: 5,
                                },
                                title: {
                                    display: true,
                                    text: 'Giá trị'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Ngày'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                            },
                            tooltip: {
                                enabled: true,
                            },
                        },
                    },
                });

                window.addEventListener('update-chart', function (event) {
                    console.log('Update Chart Event:', event.detail);
                    const newData = event.detail;

                    // Cập nhật dữ liệu và tùy chọn
                    chart.data.labels = newData.labels;
                    chart.data.datasets = newData.datasets;
                    chart.options.scales.y.min = newData.options.scales.y.min;
                    chart.options.scales.y.max = newData.options.scales.y.max;
                    chart.update();
                });
            });
        </script>
    @endpush
</x-filament::page>