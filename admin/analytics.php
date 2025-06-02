<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';

if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$page_title = 'Analytics';
$stats = getDashboardStats();

// Get analytics data
$content_analytics = getContentAnalytics();
$trending_content = getTrendingContent(10);
$category_stats = getCategoryStats();
$trimester_content_stats = getTrimesterContentStats();
$recent_views = getRecentContentViews(20);

include '../includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4">Analytics Overview</h2>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-primary">Total Users</h5>
                    <h2 class="mb-0"><?php echo number_format($stats['total_users']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-success">Total Content</h5>
                    <h2 class="mb-0"><?php echo number_format($stats['total_content']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-info">Total Views</h5>
                    <h2 class="mb-0"><?php echo number_format($stats['total_views']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-warning">Avg Views/Content</h5>
                    <h2 class="mb-0">
                        <?php echo $stats['total_content'] > 0 ? number_format($stats['total_views'] / $stats['total_content'], 1) : '0'; ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card p-3">
                <h5>Users by Trimester</h5>
                <canvas id="trimesterChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3">
                <h5>Content by Category</h5>
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Content Performance -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Performing Content</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Trimester</th>
                                    <th>Views</th>
                                    <th>Type</th>
                                    <th>Featured</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($trending_content)): ?>
                                    <?php foreach ($trending_content as $content): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($content['title']); ?></strong>
                                                <br><small class="text-muted">Created:
                                                    <?php echo date('M d, Y', strtotime($content['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge badge-secondary"><?php echo htmlspecialchars($content['category']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">T<?php echo $content['trimester']; ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo number_format($content['actual_view_count']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo ucfirst($content['content_type']); ?>
                                            </td>
                                            <td>
                                                <?php if ($content['is_featured']): ?>
                                                    <span class="badge badge-warning">Featured</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No content data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Content by Trimester</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($trimester_content_stats)): ?>
                        <?php foreach ($trimester_content_stats as $stat): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Trimester <?php echo $stat['trimester']; ?></span>
                                <div>
                                    <span class="badge badge-primary"><?php echo $stat['content_count']; ?></span>
                                    <small class="text-muted"><?php echo number_format($stat['total_views']); ?> views</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No data available</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Category Performance</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($category_stats)): ?>
                        <?php foreach ($category_stats as $stat): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo ucfirst(str_replace('_', ' ', $stat['category'])); ?></span>
                                <div>
                                    <span class="badge badge-success"><?php echo $stat['content_count']; ?></span>
                                    <small class="text-muted"><?php echo number_format($stat['total_views']); ?> views</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


    <!-- Monthly Trends Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Monthly Content Creation Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlyTrendsChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Performance Alerts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning">
                    <h6 class="mb-0 text-dark">⚠️ Low Performing Content</h6>
                </div>
                <div class="card-body">
                    <?php
                    $low_performing = getLowPerformingContent(5);
                    if (!empty($low_performing)):
                        ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($low_performing as $content): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <strong><?php echo htmlspecialchars(substr($content['title'], 0, 40)); ?>...</strong>
                                        <br><small class="text-muted"><?php echo $content['days_old']; ?> days old •
                                            <?php echo $content['view_count']; ?> views</small>
                                    </div>
                                    <a href="content_edit.php?id=<?php echo $content['id']; ?>"
                                        class="btn btn-sm btn-outline-warning">Optimize</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">All content performing well! 🎉</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">📈 Content Type Performance</h6>
                </div>
                <div class="card-body">
                    <?php
                    $content_type_stats = getContentTypeStats();
                    if (!empty($content_type_stats)):
                        ?>
                        <?php foreach ($content_type_stats as $stat): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-capitalize"><?php echo $stat['content_type']; ?></span>
                                <div class="text-right">
                                    <span class="badge badge-success"><?php echo $stat['content_count']; ?></span>
                                    <br><small class="text-muted"><?php echo number_format($stat['avg_views'], 1); ?> avg
                                        views</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>

        // Monthly Trends Chart
        <?php $monthly_stats = getMonthlyContentStats(6); ?>
        const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php
                    if (!empty($monthly_stats)) {
                        foreach (array_reverse($monthly_stats) as $stat) {
                            echo "'" . date('M Y', strtotime($stat['month'] . '-01')) . "',";
                        }
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Content Created',
                    data: [
                        <?php
                        if (!empty($monthly_stats)) {
                            foreach (array_reverse($monthly_stats) as $stat) {
                                echo $stat['content_created'] . ',';
                            }
                        }
                        ?>
                    ],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y'
                }, {
                    label: 'Total Views',
                    data: [
                        <?php
                        if (!empty($monthly_stats)) {
                            foreach (array_reverse($monthly_stats) as $stat) {
                                echo $stat['total_views'] . ',';
                            }
                        }
                        ?>
                    ],
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Content Created'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Total Views'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Content Creation and View Trends'
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Trimester Chart
        const trimesterCtx = document.getElementById('trimesterChart').getContext('2d');
        new Chart(trimesterCtx, {
            type: 'doughnut',
            data: {
                labels: ['1st Trimester', '2nd Trimester', '3rd Trimester'],
                datasets: [{
                    data: [
                        <?php echo $stats['trimester_1'] ?? 0; ?>,
                        <?php echo $stats['trimester_2'] ?? 0; ?>,
                        <?php echo $stats['trimester_3'] ?? 0; ?>
                    ],
                    backgroundColor: ['#ff6384', '#36a2eb', '#ffce56'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php
                    if (!empty($category_stats)) {
                        foreach ($category_stats as $stat) {
                            echo "'" . ucfirst(str_replace('_', ' ', $stat['category'])) . "',";
                        }
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Content Count',
                    data: [
                        <?php
                        if (!empty($category_stats)) {
                            foreach ($category_stats as $stat) {
                                echo $stat['content_count'] . ',';
                            }
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }, {
                    label: 'Total Views',
                    data: [
                        <?php
                        if (!empty($category_stats)) {
                            foreach ($category_stats as $stat) {
                                echo $stat['total_views'] . ',';
                            }
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(255, 99, 132, 0.8)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    </script>

    <?php include '../includes/admin_footer.php'; ?>