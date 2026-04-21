<?php
/**
 * XPLabs - Award Points (Teacher/Admin)
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;

Auth::requireRole(['admin', 'teacher']);

$db = Database::getInstance();
$userId = Auth::id();
$role = $_SESSION['user_role'];

// Get all students
$students = $db->fetchAll(
    "SELECT id, first_name, last_name, lrn, grade_level, section 
     FROM users 
     WHERE role = 'student' AND is_active = 1 
     ORDER BY last_name, first_name"
);

// Get recent awards
$recentAwards = $db->fetchAll(
    "SELECT pa.*, 
            u.first_name as student_first, u.last_name as student_last,
            t.first_name as teacher_first, t.last_name as teacher_last
     FROM point_awards pa
     JOIN users u ON pa.user_id = u.id
     JOIN users t ON pa.awarded_by = t.id
     ORDER BY pa.created_at DESC
     LIMIT 20"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Award Points - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-main: #f1f5f9;
            --bg-card: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
            --text-muted: #64748b;
            --accent: #6366f1;
            --green: #22c55e;
            --yellow: #eab308;
        }
        body { background: #f1f5f9; color: #1e293b; font-family: 'Segoe UI', system-ui, sans-serif; min-height: 100vh; }
        .main-content { margin-left: 260px; padding: 2rem; }
        .xp-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .xp-card .card-header { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 1rem 1.5rem; }
        .xp-card .card-header h5 { margin: 0; font-weight: 600; color: #1e293b; }
        .xp-card .card-body { padding: 1.5rem; }
        .student-select { max-height: 400px; overflow-y: auto; }
        .student-item { padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: all 0.2s; margin-bottom: 0.5rem; }
        .student-item:hover { border-color: var(--accent); background: rgba(99, 102, 241, 0.05); }
        .student-item.selected { border-color: var(--accent); background: rgba(99, 102, 241, 0.1); }
        .point-btn { padding: 0.75rem 1.5rem; border: 2px solid #e2e8f0; border-radius: 8px; background: #fff; cursor: pointer; transition: all 0.2s; font-weight: 600; }
        .point-btn:hover { border-color: var(--accent); color: var(--accent); }
        .point-btn.selected { border-color: var(--accent); background: var(--accent); color: var(--text); }
        .award-type-btn { padding: 0.5rem 1rem; border: 1px solid #e2e8f0; border-radius: 20px; background: #fff; cursor: pointer; transition: all 0.2s; font-size: 0.85rem; }
        .award-type-btn:hover { border-color: var(--accent); }
        .award-type-btn.selected { border-color: var(--accent); background: var(--accent); color: var(--text); }
        .award-history-item { padding: 0.75rem 0; border-bottom: 1px solid #e2e8f0; }
        .award-history-item:last-child { border-bottom: none; }
        .award-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .award-badge.behavior { background: rgba(34, 197, 94, 0.1); color: #16a34a; }
        .award-badge.participation { background: rgba(99, 102, 241, 0.1); color: #6366f1; }
        .award-badge.achievement { background: rgba(234, 179, 8, 0.1); color: #ca8a04; }
        .award-badge.helping_others { background: rgba(59, 130, 246, 0.1); color: #2563eb; }
        .award-badge.improvement { background: rgba(168, 85, 247, 0.1); color: #9333ea; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-award me-2"></i>Award Points</h2>
                <p class="text-muted mb-0">Recognize student achievements and good behavior</p>
            </div>
        </div>

        <div class="row g-4">
            <!-- Award Form -->
            <div class="col-lg-5">
                <div class="xp-card">
                    <div class="card-header">
                        <h5><i class="bi bi-gift me-2"></i>Adjust Points</h5>
                    </div>
                    <div class="card-body">
                        <form id="awardForm">
                            <!-- Mode -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Action</label>
                                <div class="d-flex gap-2">
                                    <input type="radio" class="btn-check" name="mode" id="modeAward" value="award" checked>
                                    <label class="btn btn-outline-success" for="modeAward"><i class="bi bi-plus-circle me-1"></i>Award</label>

                                    <input type="radio" class="btn-check" name="mode" id="modeDeduct" value="deduct">
                                    <label class="btn btn-outline-danger" for="modeDeduct"><i class="bi bi-dash-circle me-1"></i>Deduct</label>
                                </div>
                            </div>
                            <!-- Student Selection -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Select Student</label>
                                <input type="text" class="form-control mb-2" id="studentSearch" placeholder="Search students...">
                                <div class="student-select" id="studentList">
                                    <?php foreach ($students as $s): ?>
                                    <div class="student-item" data-id="<?= $s['id'] ?>" data-name="<?= e(strtolower($s['first_name'] . ' ' . $s['last_name'])) ?>">
                                        <div class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                        <small class="text-muted"><?= e($s['grade_level'] . ' - ' . $s['section']) ?> | LRN: <?= e($s['lrn']) ?></small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="user_id" id="selectedStudentId" required>
                            </div>

                            <!-- Points -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Points</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button" class="point-btn" data-points="5">5</button>
                                    <button type="button" class="point-btn" data-points="10">10</button>
                                    <button type="button" class="point-btn" data-points="25">25</button>
                                    <button type="button" class="point-btn" data-points="50">50</button>
                                    <button type="button" class="point-btn" data-points="100">100</button>
                                </div>
                                <input type="number" name="points" id="customPoints" class="form-control mt-2" placeholder="Or enter custom amount" min="1">
                            </div>

                            <!-- Award Type -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Award Type</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button" class="award-type-btn" data-type="behavior">🎯 Behavior</button>
                                    <button type="button" class="award-type-btn" data-type="participation">🙋 Participation</button>
                                    <button type="button" class="award-type-btn" data-type="achievement">🏆 Achievement</button>
                                    <button type="button" class="award-type-btn" data-type="helping_others">🤝 Helping Others</button>
                                    <button type="button" class="award-type-btn" data-type="improvement">📈 Improvement</button>
                                </div>
                                <input type="hidden" name="award_type" id="selectedAwardType" value="other">
                            </div>

                            <!-- Reason -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Reason</label>
                                <textarea name="reason" class="form-control" rows="2" placeholder="Why is this student being awarded?" required></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-check-circle me-1"></i> Submit
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Awards -->
            <div class="col-lg-7">
                <div class="xp-card">
                    <div class="card-header">
                        <h5><i class="bi bi-clock-history me-2"></i>Recent Awards</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recentAwards as $award): ?>
                        <div class="award-history-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold">
                                        <?= e($award['student_first'] . ' ' . $award['student_last']) ?>
                                        <?php if ((int)$award['points'] >= 0): ?>
                                            <span class="text-success ms-2">+<?= (int)$award['points'] ?> pts</span>
                                        <?php else: ?>
                                            <span class="text-danger ms-2"><?= (int)$award['points'] ?> pts</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted small"><?= e($award['reason']) ?></div>
                                    <div class="mt-1">
                                        <span class="award-badge <?= $award['award_type'] ?>"><?= ucwords(str_replace('_', ' ', $award['award_type'])) ?></span>
                                        <small class="text-muted ms-2">by <?= e($award['teacher_first'] . ' ' . $award['teacher_last']) ?></small>
                                    </div>
                                </div>
                                <small class="text-muted"><?= date('M j, g:i A', strtotime($award['created_at'])) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($recentAwards)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-award fs-1 d-block mb-2"></i>
                            <p>No awards given yet</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const csrfToken = <?= json_encode(csrf_token()) ?>;
    // Student selection
    document.querySelectorAll('.student-item').forEach(item => {
        item.addEventListener('click', function() {
            document.querySelectorAll('.student-item').forEach(i => i.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('selectedStudentId').value = this.dataset.id;
        });
    });

    // Student search
    document.getElementById('studentSearch').addEventListener('input', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.student-item').forEach(item => {
            item.style.display = item.dataset.name.includes(query) ? '' : 'none';
        });
    });

    // Points selection
    document.querySelectorAll('.point-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.point-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('customPoints').value = this.dataset.points;
        });
    });

    // Award type selection
    document.querySelectorAll('.award-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.award-type-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('selectedAwardType').value = this.dataset.type;
        });
    });

    // Form submission
    document.getElementById('awardForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        
        if (!data.user_id) {
            alert('Please select a student');
            return;
        }
        if (!data.points || data.points < 1) {
            alert('Please enter points');
            return;
        }

        try {
            const response = await fetch('api/awards/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify(data)
            });
            const text = await response.text();
            let result = {};
            try {
                result = text ? JSON.parse(text) : {};
            } catch (parseError) {
                result = { error: text || 'Invalid server response' };
            }
            
            if (response.ok && result.success) {
                alert(result.message);
                location.reload();
            } else {
                alert('Error: ' + (result.error || 'Unknown error'));
            }
        } catch (err) {
            alert('Network error: unable to reach server');
        }
    });
    </script>
</body>
</html>
