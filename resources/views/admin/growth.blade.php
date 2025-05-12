@extends('layouts.app')

@section('title', 'Biểu đồ tăng trưởng')

@section('content')
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
        <h1>Biểu đồ tăng trưởng số người theo dõi</h1>
        <div style="margin-bottom: 20px;">
            <label for="since">Từ ngày:</label>
            <input type="date" id="since" value="2025-04-05" onchange="updateChart()">
            <label for="until" style="margin-left: 20px;">Đến ngày:</label>
            <input type="date" id="until" value="2025-04-12" onchange="updateChart()">
        </div>
        <canvas id="growthChart" style="max-height: 400px;"></canvas>
    </div>

    <script>
        let chartInstance = null;

        async function fetchChartData() {
            const since = document.getElementById('since').value;
            const until = document.getElementById('until').value;
            const response = await fetch(`/analytics/growth-chart-data?since=${since}&until=${until}`);
            return await response.json();
        }

        async function updateChart() {
            const data = await fetchChartData();

            // Hủy biểu đồ cũ nếu có
            if (chartInstance) {
                chartInstance.destroy();
            }

            // Tạo biểu đồ mới
            const ctx = document.getElementById('growthChart').getContext('2d');
            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: data.datasets,
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Số người theo dõi'
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
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Tăng trưởng số người theo dõi theo thời gian'
                        }
                    }
                }
            });
        }

        // Vẽ biểu đồ lần đầu khi trang được tải
        document.addEventListener('DOMContentLoaded', updateChart);
    </script>
@endsection