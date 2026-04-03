<?php
/**
 * XPLabs - Analytics Dashboard (Teacher/Admin)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole(['admin', 'teacher']);

$db = Database::getInstance();
$userId = Auth::id();
$role = $_SESSION['user_role'];

// Get teacher's courses
$teacherCourses = $db->fetchAll(
    "SELECT id, name FROM courses WHERE teacher_id = ? AND status = 'active' ORDER BY name",
    [$userId]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root { --accent: #6366f1; }
        body { background: #f1f5f9; color: #1e293b; font-family: 'Segoe UI', system-ui, sans-serif; min-height: 100vh; }
        .main-content { margin-left: 260px; padding: 2rem; }
        .xp-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .xp-card .card-header { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 1rem 1.5rem; }
        .xp-card .card-header h5 { margin: 0; font-weight: 600; color: #1e293b; }
        .xp-card .card-body { padding: 1.5rem; }
        .stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; text-align: center; }
        .stat-card .value { font-size: 2rem; font-weight: 700; color: var(--accent); }
        .stat-card .label { font-size: 0.8rem; color: #64748b; text-transform: uppercase; }
        .nav-tabs .nav-link { color: #64748b; border: none; }
        .nav-tabs .nav-link.active { color: var(--accent); border-bottom: 2px solid var(--accent); }
        .chart-container { position: relative; height: 300px; }
        .form-select, .form-control { border-color: #e2e8f0; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-graph-up me-2"></i>Analytics Dashboard</h2>
                <p class="text-muted mb-0">Student performance and lab usage insights</p>
            </div>
            <select class="form-select w-auto" id="courseFilter">
                <option value="">All Courses</option>
                <?php foreach ($teacherCourses as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value" id="statAvgQuiz">-</div>
                    <div class="label">Avg Quiz Score</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value" id="statAttendance">-</div>
                    <div class="label">Attendance Rate</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value" id="statLabUsage">-</div>
                    <div class="label">Lab Utilization</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="value" id="statFunRating">-</div>
                    <div class="label">Avg Fun Rating</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="analyticsTabs">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabQuiz">Quiz Performance</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabAttendance">Attendance</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabLab">Lab Usage</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabFeedback">Activity Feedback</a></li>
        </ul>

        <div class="tab-content">
            <!-- Quiz Performance Tab -->
            <div class="tab-pane fade show active" id="tabQuiz">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="xp-card">
                            <div class="card-header"><h5>Quiz Scores by Quiz</h5></div>
                            <div class="card-body">
                                <div class="chart-container"><canvas id="quizScoresChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="xp-card">
                            <div class="card-header"><h5>Score Distribution</h5></div>
                            <div class="card-body">
                                <div class="chart-container"><canvas id="scoreDistChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Tab -->
            <div class="tab-pane fade" id="tabAttendance">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="xp-card">
                            <div class="card-header"><h5>Attendance Rate by Student</h5></div>
                            <div class="card-body">
                                <div class="chart-container"><canvas id="attendanceChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="xp-card">
                            <div class="card-header"><h5>Daily Attendance Trend</h5></div>
                            <div class="card-body">
                                <div class="chart-container"><canvas id="attendanceTrendChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lab Usage Tab -->
            <div class="tab-pane fade" id="tabLab">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="xp-card">
                            <div class="card-header"><h5>Station Utilization</h5></div>
                            <div class="card-body">
                                <div class="chart-container"><canvas id="stationChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="xp-card">
                            <div class="card-header"><h5>Peak Hours Heatmap</h5></div>
                            <div class="card-body">
                                <div id="heatmapContainer" class="text-center py-4">
                                    <p class="text-muted">Loading heatmap data...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Feedback Tab -->
            <div class="tab-pane fade" id="tabFeedback">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="xp-card">
                            <div class="card-header"><h5>Fun Rating Distribution</h5></div>
                            <div class="card-body">
                                <div class="chart-container"><canvas id="funRatingChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="xp-card">
                            <div class="card-header"><h5>Difficulty Breakdown</h5></div>
                            <div class="card-body">
                                <div class="chart-container"><canvas id="difficultyChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const courseFilter = document.getElementById('courseFilter');
    let charts = {};

    // Initialize charts
    function initCharts() {
        // Quiz Scores Bar Chart
        charts.quizScores = new Chart(document.getElementById('quizScoresChart'), {
            type: 'bar',
            data: { labels: [], datasets: [{ label: 'Avg Score (%)', data: [], backgroundColor: '#6366f1' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
        });

        // Score Distribution Doughnut
        charts.scoreDist = new Chart(document.getElementById('scoreDistChart'), {
            type: 'doughnut',
            data: { labels: ['Excellent (90-100)', 'Good (75-89)', 'Fair (60-74)', 'Needs Work (<60)'], datasets: [{ data: [0,0,0,0], backgroundColor: ['#22c55e','#3b82f6','#eab308','#ef4444'] }] },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Attendance Bar Chart
        charts.attendance = new Chart(document.getElementById('attendanceChart'), {
            type: 'bar',
            data: { labels: [], datasets: [{ label: 'Attendance %', data: [], backgroundColor: '#22c55e' }] },
            options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: { x: { beginAtZero: true, max: 100 } } }
        });

        // Attendance Trend Line
        charts.attendanceTrend = new Chart(document.getElementById('attendanceTrendChart'), {
            type: 'line',
            data: { labels: [], datasets: [{ label: 'Students Present', data: [], borderColor: '#6366f1', fill: true, backgroundColor: 'rgba(99,102,241,0.1)' }] },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Station Utilization
        charts.station = new Chart(document.getElementById('stationChart'), {
            type: 'bar',
            data: { labels: [], datasets: [{ label: 'Usage Hours', data: [], backgroundColor: '#6366f1' }] },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Fun Rating
        charts.funRating = new Chart(document.getElementById('funRatingChart'), {
            type: 'bar',
            data: { labels: ['1⭐','2⭐','3⭐','4⭐','5⭐'], datasets: [{ label: 'Count', data: [0,0,0,0,0], backgroundColor: ['#ef4444','#f97316','#eab308','#84cc16','#22c55e'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        // Difficulty
        charts.difficulty = new Chart(document.getElementById('difficultyChart'), {
            type: 'pie',
            data: { labels: ['Easy', 'Medium', 'Hard'], datasets: [{ data: [0,0,0], backgroundColor: ['#22c55e','#eab308','#ef4444'] }] },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // Load analytics data
    async function loadAnalytics() {
        const courseId = courseFilter.value;
        const params = courseId ? `?course_id=${courseId}` : '';

        try {
            // Quiz Analytics
            const quizRes = await fetch(`api/analytics/quizzes.php${params}`);
            const quizData = await quizRes.json();
            if (quizData.success) {
                document.getElementById('statAvgQuiz').textContent = quizData.avg_score + '%';
                charts.quizScores.data.labels = quizData.quizzes.map(q => q.title.substring(0, 15));
                charts.quizScores.data.datasets[0].data = quizData.quizzes.map(q => q.avg_score);
                charts.quizScores.update();
                charts.scoreDist.data.datasets[0].data = [quizData.distribution.excellent, quizData.distribution.good, quizData.distribution.fair, quizData.distribution.poor];
                charts.scoreDist.update();
            }

            // Attendance Analytics
            const attRes = await fetch(`api/analytics/attendance.php${params}`);
            const attData = await attRes.json();
            if (attData.success) {
                document.getElementById('statAttendance').textContent = attData.rate + '%';
                charts.attendance.data.labels = attData.students.map(s => s.name.substring(0, 12));
                charts.attendance.data.datasets[0].data = attData.students.map(s => s.rate);
                charts.attendance.update();
                charts.attendanceTrend.data.labels = attData.trend.map(t => t.date);
                charts.attendanceTrend.data.datasets[0].data = attData.trend.map(t => t.count);
                charts.attendanceTrend.update();
            }

            // Lab Usage Analytics
            const labRes = await fetch(`api/analytics/lab-usage.php${params}`);
            const labData = await labRes.json();
            if (labData.success) {
                document.getElementById('statLabUsage').textContent = labData.utilization + '%';
                charts.station.data.labels = labData.stations.map(s => s.code);
                charts.station.data.datasets[0].data = labData.stations.map(s => s.hours);
                charts.station.update();
                renderHeatmap(labData.heatmap);
            }

            // Feedback Analytics
            const fbRes = await fetch(`api/analytics/feedback.php${params}`);
            const fbData = await fbRes.json();
            if (fbData.success) {
                document.getElementById('statFunRating').textContent = fbData.avg_fun + '⭐';
                charts.funRating.data.datasets[0].data = fbData.fun_distribution;
                charts.funRating.update();
                charts.difficulty.data.datasets[0].data = [fbData.difficulty.easy, fbData.difficulty.medium, fbData.difficulty.hard];
                charts.difficulty.update();
            }
        } catch (err) {
            console.error('Error loading analytics:', err);
        }
    }

    function renderHeatmap(heatmap) {
        const container = document.getElementById('heatmapContainer');
        if (!heatmap || !heatmap.length) {
            container.innerHTML = '<p class="text-muted">No data available</p>';
            return;
        }
        const days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
        const hours = Array.from({length: 12}, (_, i) => i + 7); // 7AM-6PM
        let html = '<table class="table table-sm table-bordered" style="font-size:0.7rem;"><thead><tr><th></th>';
        hours.forEach(h => html += `<th>${h > 12 ? h-12 : h}${h >= 12 ? 'PM' : 'AM'}</th>`);
        html += '</tr></thead><tbody>';
        heatmap.forEach((row, i) => {
            html += `<tr><td>${days[i] || ''}</td>`;
            row.forEach(val => {
                const intensity = Math.min(val / 10, 1);
                const bg = `rgba(99,102,241,${intensity})`;
                html += `<td style="background:${bg};color:${intensity > 0.5 ? '#fff' : '#333'}">${val || ''}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    }

    // Init
    initCharts();
    loadAnalytics();
    courseFilter.addEventListener('change', loadAnalytics);
    </script>
</body>
</html>