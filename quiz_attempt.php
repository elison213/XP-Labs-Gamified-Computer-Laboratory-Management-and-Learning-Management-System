<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Web-only hard diagnostics for persistent 500s on this page.
set_exception_handler(function (\Throwable $e): void {
    error_log(sprintf(
        '[quiz_attempt.php] Uncaught %s: %s in %s:%d',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!doctype html><html><body style="font-family:Segoe UI,Arial,sans-serif;padding:16px;">';
    echo '<h3>Quiz Attempt Error</h3>';
    echo '<p>A server error occurred while loading this quiz attempt.</p>';
    echo '<p><code>Check storage/logs/error.log for [quiz_attempt.php] details.</code></p>';
    echo '</body></html>';
    exit;
});

register_shutdown_function(function (): void {
    $fatal = error_get_last();
    if ($fatal && in_array($fatal['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log(sprintf(
            '[quiz_attempt.php] Fatal error: %s in %s:%d',
            (string) ($fatal['message'] ?? 'unknown'),
            (string) ($fatal['file'] ?? 'unknown'),
            (int) ($fatal['line'] ?? 0)
        ));
    }
});

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\QuizService;

Auth::require();
$role = Auth::role();
$userId = Auth::id();
$previewParam = isset($_GET['preview']) ? trim((string) $_GET['preview']) : '';
$wantsPreview = $previewParam !== '' && $previewParam !== '0';
$isPreview = in_array($role, ['admin', 'teacher'], true) && $wantsPreview;

if (in_array($role, ['admin', 'teacher'], true)) {
    if (!$isPreview) {
        header('Location: quizzes_manage.php');
        exit;
    }
} else {
    Auth::requireRole('student');
}

$quizId = (int) ($_GET['quiz_id'] ?? 0);
if (!$quizId) {
    header('Location: ' . ($isPreview ? 'quizzes_manage.php' : 'my_quizzes.php'));
    exit;
}

$quizService = new QuizService();
$attempt = $isPreview
    ? $quizService->startPreviewAttempt($quizId, $userId, $role)
    : $quizService->startAttempt($quizId, $userId);
if (!$attempt['success']) {
    $error = $attempt['message'] ?? 'Unable to start quiz attempt.';
}
$db = Database::getInstance();
$shopPowerups = $isPreview ? [] : $db->fetchAll("SELECT id, code, name, icon, point_cost FROM powerups WHERE type='quiz' AND is_active=1 ORDER BY point_cost ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Attempt - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #e2e8f0; }
        .container-main { max-width: 1240px; margin: 1.5rem auto; }
        .quiz-layout { display: grid; grid-template-columns: 1fr 300px; gap: 1rem; align-items: start; }
        .quiz-layout.preview-mode { grid-template-columns: 1fr; max-width: 920px; margin-left: auto; margin-right: auto; }
        .preview-banner {
            background: linear-gradient(90deg, rgba(234,179,8,.2), rgba(245,158,11,.08));
            border: 1px solid rgba(234,179,8,.45);
            color: #fef3c7;
            border-radius: 10px;
            padding: .65rem 1rem;
            margin-bottom: 1rem;
            font-size: .9rem;
        }
        .quiz-shell { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1rem; min-height: 80vh; }
        .sticky-head { position: sticky; top: 0; z-index: 20; background: #1e293b; border-bottom: 1px solid #334155; padding-bottom: .75rem; }
        .question-card {
            background: #0f172a; border: 1px solid #334155; border-radius: 12px; padding: 1.15rem 1.25rem; margin-bottom: 1rem;
            animation: cardEnter .45s ease both;
            transition: border-color .2s ease, box-shadow .2s ease, transform .15s ease;
        }
        .question-card:nth-child(1) { animation-delay: .02s; }
        .question-card:nth-child(2) { animation-delay: .06s; }
        .question-card:nth-child(3) { animation-delay: .1s; }
        .question-card:nth-child(4) { animation-delay: .14s; }
        .question-card:nth-child(5) { animation-delay: .18s; }
        .question-card:nth-child(n+6) { animation-delay: .22s; }
        @keyframes cardEnter {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes hudPulse {
            0% { box-shadow: 0 0 0 0 rgba(34,197,94,.35); }
            70% { box-shadow: 0 0 0 10px rgba(34,197,94,0); }
            100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
        }
        .hud-success-pulse { animation: hudPulse .9s ease-out 1; }
        .question-title { font-size: 1.05rem; line-height: 1.45; max-width: 62ch; }
        .question-card .form-check { padding: .5rem .65rem; border-radius: 8px; transition: background .15s ease, border-color .15s ease; border: 1px solid transparent; }
        .question-card .form-check:hover { background: rgba(99,102,241,.08); border-color: rgba(99,102,241,.25); }
        .question-card .form-check:has(input:checked) { background: rgba(34,197,94,.12); border-color: rgba(34,197,94,.4); }
        .question-card.active { border-color: #6366f1; box-shadow: 0 0 0 1px rgba(99,102,241,.35); }
        .question-card.flash-hidden { display: none; }
        @media (prefers-reduced-motion: reduce) {
            .question-card { animation: none; }
            .hud-success-pulse { animation: none; }
        }
        .status-pill { font-size: .75rem; border-radius: 999px; padding: .2rem .55rem; }
        .autosave { font-size: .8rem; color: #94a3b8; }
        .hint-chip { background: rgba(59,130,246,.2); color: #bfdbfe; border: 1px solid rgba(59,130,246,.4); border-radius: 8px; padding: .4rem .6rem; display: inline-block; margin-right: .35rem; margin-top: .35rem; font-size: .8rem; }
        .mode-btn.active { border-color: #6366f1 !important; background: rgba(99,102,241,.25) !important; }
        .flash-controls { display: none; }
        .flash-controls.active { display: flex; }
        .powerup-side { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: .75rem; height: fit-content; position: sticky; top: 1rem; }
        .powerup-slot { border: 1px solid #334155; border-radius: 10px; padding: .6rem; margin-bottom: .5rem; background: #0f172a; }
        .powerup-gauge { height: 8px; background: #1f2937; border-radius: 999px; overflow: hidden; }
        .powerup-gauge > span { display: block; height: 100%; background: linear-gradient(90deg, #22c55e, #84cc16); }
        .powerup-count { min-width: 28px; text-align: center; border-radius: 999px; padding: .12rem .45rem; font-size: .75rem; background: #334155; }
        .powerup-count.plus { background: #2563eb; }
        .target-pill { font-size: .75rem; color: #93c5fd; }
        .hud-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .5rem .75rem;
            border-radius: 999px;
            border: 1px solid #334155;
            background: #0b1220;
            color: #e2e8f0;
            font-weight: 800;
            letter-spacing: .02em;
        }
        .hud-chip .val { font-size: 1.05rem; line-height: 1; }
        .hud-chip .lbl { font-size: .8rem; opacity: .9; }
        .hud-chip.points { border-color: rgba(234,179,8,.6); background: linear-gradient(135deg, rgba(234,179,8,.25), rgba(245,158,11,.12)); color: #fde68a; }
        .hud-chip.time { border-color: rgba(6,182,212,.55); background: linear-gradient(135deg, rgba(6,182,212,.18), rgba(59,130,246,.10)); color: #bae6fd; }
        .hud-chip.qcount { border-color: rgba(34,197,94,.45); background: linear-gradient(135deg, rgba(34,197,94,.12), rgba(132,204,22,.08)); color: #bbf7d0; }
    </style>
</head>
<body>
<div class="container-main">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php else: ?>
        <?php if (!empty($isPreview)): ?>
            <div class="preview-banner"><i class="bi bi-eye me-2"></i><strong>Preview mode</strong> — scores and points are not saved to student records. Power-ups are disabled.</div>
        <?php endif; ?>
        <div class="quiz-layout<?= !empty($isPreview) ? ' preview-mode' : '' ?>">
        <div class="quiz-shell">
            <div class="sticky-head mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1"><i class="bi bi-lightning-charge-fill me-2"></i><?= e($attempt['quiz']['title']) ?></h4>
                        <div class="text-secondary small"><?= count($attempt['questions']) ?> questions</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="hud-chip points" title="Your points">
                            <i class="bi bi-stars"></i>
                            <span class="val" id="pointBalance"><?= (int) ($attempt['point_balance'] ?? 0) ?></span>
                            <span class="lbl">PTS</span>
                        </span>
                        <span class="hud-chip qcount" title="Total questions">
                            <i class="bi bi-list-ol"></i>
                            <span class="val"><?= count($attempt['questions']) ?></span>
                            <span class="lbl">Q</span>
                        </span>
                        <span class="hud-chip time" title="Time remaining">
                            <i class="bi bi-hourglass-split"></i>
                            <span class="val" id="timeLeft"></span>
                            <span class="lbl">LEFT</span>
                        </span>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-light mode-btn active" id="modeScrollBtn">Full Scroll</button>
                            <button type="button" class="btn btn-outline-light mode-btn" id="modeFlashBtn">Flashcard</button>
                        </div>
                        <button class="btn btn-success btn-sm" id="finishBtn"><i class="bi bi-check2-circle me-1"></i>Finish Quiz</button>
                    </div>
                </div>
                <div class="flash-controls mt-2 gap-2 align-items-center" id="flashControls">
                    <button type="button" class="btn btn-sm btn-outline-light" id="prevFlashBtn"><i class="bi bi-arrow-left"></i></button>
                    <span class="small text-secondary">Question <span id="flashIndex">1</span> / <?= count($attempt['questions']) ?></span>
                    <button type="button" class="btn btn-sm btn-outline-light" id="nextFlashBtn"><i class="bi bi-arrow-right"></i></button>
                </div>
            </div>

            <form id="quizForm">
                <input type="hidden" name="attempt_id" value="<?= (int) $attempt['attempt_id'] ?>">
                <?php foreach ($attempt['questions'] as $idx => $q): ?>
                    <?php $type = (string) $q['type']; $options = $q['options'] ? json_decode($q['options'], true) : []; ?>
                    <div class="question-card" data-question-id="<?= (int) $q['id'] ?>" data-question-type="<?= e($type) ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="question-title"><span class="badge bg-secondary me-2 align-middle">Q<?= $idx + 1 ?></span><?= e($q['question_text']) ?></div>
                            <span class="status-pill bg-secondary" id="status-<?= (int) $q['id'] ?>">Not saved</span>
                        </div>

                        <div class="mt-2">
                            <?php if ($type === 'multiple_choice' && $options): ?>
                                <?php foreach ($options as $optIdx => $opt): ?>
                                    <div class="form-check option-item" data-option-value="<?= e((string) $opt) ?>">
                                        <input class="form-check-input answer-input" type="radio" name="answer_<?= (int) $q['id'] ?>" id="q<?= (int) $q['id'] ?>_opt<?= $optIdx ?>" value="<?= e((string) $opt) ?>">
                                        <label class="form-check-label" for="q<?= (int) $q['id'] ?>_opt<?= $optIdx ?>"><?= e((string) $opt) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            <?php elseif ($type === 'true_false'): ?>
                                <div class="form-check">
                                    <input class="form-check-input answer-input" type="radio" name="answer_<?= (int) $q['id'] ?>" id="q<?= (int) $q['id'] ?>_true" value="true">
                                    <label class="form-check-label" for="q<?= (int) $q['id'] ?>_true">True</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input answer-input" type="radio" name="answer_<?= (int) $q['id'] ?>" id="q<?= (int) $q['id'] ?>_false" value="false">
                                    <label class="form-check-label" for="q<?= (int) $q['id'] ?>_false">False</label>
                                </div>
                            <?php else: ?>
                                <textarea class="form-control answer-input" name="answer_<?= (int) $q['id'] ?>" rows="3" placeholder="Type your answer..."></textarea>
                            <?php endif; ?>
                        </div>

                        <div class="small text-secondary mt-2 autosave" id="autosave-<?= (int) $q['id'] ?>">Autosave ready</div>
                        <div class="assist-hints mt-2" id="hints-<?= (int) $q['id'] ?>"></div>
                    </div>
                <?php endforeach; ?>
            </form>
        </div>
        <?php if (empty($isPreview)): ?>
        <aside class="powerup-side">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong><i class="bi bi-gem me-1"></i>Powerups</strong>
                <button type="button" class="btn btn-sm btn-outline-light" id="refreshPowerupStateBtn"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
            <div class="target-pill mb-2">Target: <span id="powerupTargetLabel">Question 1</span></div>
            <div id="powerupSlots"></div>
        </aside>
        <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (empty($isPreview) && empty($error)): ?>
<div class="modal fade" id="powerupStoreModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title"><i class="bi bi-shop me-2"></i>Powerup Store</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="powerupStoreBody">
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
<?php if (empty($error)): ?>
const isPreview = <?= json_encode(!empty($isPreview)) ?>;
const csrfToken = <?= json_encode(csrf_token()) ?>;
const attemptId = <?= (int) $attempt['attempt_id'] ?>;
const timeLimit = <?= (int) ($attempt['time_limit'] ?? 0) ?>;
const appBase = <?= json_encode(rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/xplabs/quiz_attempt.php'), '/')) ?>;
const apiPhpUrl = (path) => `${appBase}${path}.php`;
let remaining = timeLimit;
const questionTimers = {};
let quizMode = 'scroll';
let currentFlashIndex = 0;
const shopCatalog = <?= json_encode(array_map(static function ($p) { return ['id' => (int) $p['id'], 'code' => $p['code'], 'name' => $p['name'], 'icon' => $p['icon'], 'point_cost' => (int) $p['point_cost']]; }, $shopPowerups), JSON_UNESCAPED_UNICODE) ?>;
let inventoryByPowerupId = {};
let currentQuestionPowerups = [];

function getAnswer(questionId) {
    const radios = document.querySelectorAll(`input[name="answer_${questionId}"]`);
    if (radios.length > 0) {
        const checked = document.querySelector(`input[name="answer_${questionId}"]:checked`);
        return checked ? checked.value : null;
    }
    const field = document.querySelector(`[name="answer_${questionId}"]`);
    return field ? field.value.trim() : null;
}

function setStatus(questionId, text, ok = false) {
    const pill = document.getElementById(`status-${questionId}`);
    if (!pill) return;
    pill.textContent = text;
    pill.className = `status-pill ${ok ? 'bg-success' : 'bg-secondary'}`;
}

async function submitSingleAnswer(questionId, powerupId = null) {
    const answer = getAnswer(questionId);
    if (answer === null || answer === '') return;
    const body = { attempt_id: attemptId, question_id: questionId, answer };
    if (powerupId) body.powerup_id = powerupId;
    const res = await fetch(apiPhpUrl('/api/quizzes/submit-answer'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify(body)
    });
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error(data.error || 'Submit failed');
    setStatus(questionId, 'Saved', true);
}

function debounceSave(questionId) {
    if (questionTimers[questionId]) clearTimeout(questionTimers[questionId]);
    setStatus(questionId, 'Saving...');
    questionTimers[questionId] = setTimeout(async () => {
        try {
            await submitSingleAnswer(questionId, null);
            const el = document.getElementById(`autosave-${questionId}`);
            if (el) el.textContent = 'Autosave successful';
        } catch (err) {
            const el = document.getElementById(`autosave-${questionId}`);
            if (el) el.textContent = 'Autosave failed, will retry on finish';
            setStatus(questionId, 'Not saved');
        }
    }, 650);
}

async function loadPowerups(questionId) {
    if (isPreview) {
        return { success: true, powerups: [], point_balance: Number(document.getElementById('pointBalance')?.textContent || 0) };
    }
    const res = await fetch(`${apiPhpUrl('/api/quizzes/powerups/list')}?attempt_id=${attemptId}&question_id=${questionId}`);
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error(data.error || 'Unable to load powerups');
    document.getElementById('pointBalance').textContent = data.point_balance ?? 0;
    currentQuestionPowerups = data.powerups || [];
    return data;
}

function addHintChip(questionId, text) {
    const hintBox = document.getElementById(`hints-${questionId}`);
    if (!hintBox) return;
    const chip = document.createElement('span');
    chip.className = 'hint-chip';
    chip.textContent = text;
    hintBox.appendChild(chip);
}

function selectorEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(String(value));
    }
    return String(value).replace(/["\\]/g, '\\$&');
}

async function applyPowerupEffect(questionId, code, payload) {
    const card = document.querySelector(`.question-card[data-question-id="${questionId}"]`);
    if (!card) return;
    if (code === 'mc_halve_choices' && Array.isArray(payload.removed_options)) {
        payload.removed_options.forEach(v => {
            const opt = card.querySelector(`.option-item[data-option-value="${selectorEscape(v)}"]`);
            if (opt) opt.style.display = 'none';
        });
        addHintChip(questionId, 'Half wrong choices removed');
    } else if (code === 'mc_reveal_answer' && payload.correct_answer !== undefined) {
        const opt = card.querySelector(`.option-item[data-option-value="${selectorEscape(payload.correct_answer)}"]`);
        if (opt) {
            opt.style.border = '1px solid #22c55e';
            opt.style.borderRadius = '6px';
            opt.style.padding = '4px 6px';
        }
        addHintChip(questionId, 'Correct answer revealed');
    } else if (code === 'fib_scramble_hint' && payload.scrambled) {
        addHintChip(questionId, `Scrambled: ${payload.scrambled}`);
    } else if (code === 'fib_reveal_letters' && payload.letter_hint) {
        addHintChip(questionId, `Letters: ${payload.letter_hint}`);
    } else if (code === 'fib_dictionary_hint' && payload.dictionary_target) {
        const resp = await fetch(`${apiPhpUrl('/api/quizzes/hint/dictionary')}?word=${encodeURIComponent(payload.dictionary_target)}`);
        const d = await resp.json();
        addHintChip(questionId, `Hint: ${d.definition || 'No definition found'}`);
    } else if (code === 'quiz_exemption') {
        addHintChip(questionId, 'Question exempted: full points granted on submit');
    }
}

async function redeemAndActivate(questionId, powerupId) {
    const redeem = await fetch(apiPhpUrl('/api/quizzes/powerups/redeem'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ attempt_id: attemptId, powerup_id: powerupId })
    });
    const redeemData = await redeem.json();
    if (!redeem.ok || !redeemData.success) throw new Error(redeemData.error || 'Unable to redeem');
    document.getElementById('pointBalance').textContent = redeemData.point_balance ?? 0;
    const activate = await fetch(apiPhpUrl('/api/quizzes/powerups/activate'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ attempt_id: attemptId, question_id: questionId, powerup_id: powerupId })
    });
    const activateData = await activate.json();
    if (!activate.ok || !activateData.success) throw new Error(activateData.error || 'Unable to activate');

    const listData = await loadPowerups(questionId);
    const selectedPowerup = (listData.powerups || []).find(p => Number(p.id) === Number(powerupId));
    if (selectedPowerup) {
        await applyPowerupEffect(questionId, selectedPowerup.code, activateData.payload || {});
    }
    await refreshPowerupSidebar();
}

function getActiveQuestionId() {
    if (quizMode === 'flash') {
        const cards = Array.from(document.querySelectorAll('.question-card'));
        return Number(cards[currentFlashIndex]?.dataset.questionId || 0);
    }
    const active = document.querySelector('.question-card.active');
    if (active) return Number(active.dataset.questionId);
    return Number(document.querySelector('.question-card')?.dataset.questionId || 0);
}

function updatePowerupTargetLabel() {
    const el = document.getElementById('powerupTargetLabel');
    if (!el) return;
    const qId = getActiveQuestionId();
    const cards = Array.from(document.querySelectorAll('.question-card'));
    const idx = cards.findIndex(c => Number(c.dataset.questionId) === qId);
    el.textContent = idx >= 0 ? `Question ${idx + 1}` : 'None';
}

async function refreshPowerupSidebar() {
    if (isPreview) return;
    const qId = getActiveQuestionId();
    if (!qId) return;
    const stateRes = await fetch(`${apiPhpUrl('/api/quizzes/powerups/state')}?attempt_id=${attemptId}`);
    const stateData = await stateRes.json();
    if (stateRes.ok && stateData.success) {
        inventoryByPowerupId = {};
        (stateData.inventory || []).forEach(row => { inventoryByPowerupId[Number(row.powerup_id)] = Number(row.quantity || 0); });
        document.getElementById('pointBalance').textContent = stateData.point_balance ?? document.getElementById('pointBalance').textContent;
    }
    const listData = await loadPowerups(qId);
    const listById = {};
    (listData.powerups || []).forEach(p => { listById[Number(p.id)] = p; });
    const slots = shopCatalog.map(p => {
        const qty = Number(inventoryByPowerupId[p.id] || 0);
        const gaugePct = Math.max(0, Math.min(100, qty * 20));
        const runtime = listById[p.id] || null;
        const canUse = runtime ? !!runtime.can_use : false;
        const canAfford = runtime ? !!runtime.can_afford : Number(document.getElementById('pointBalance').textContent || 0) >= p.point_cost;
        const countLabel = qty > 0 ? String(qty) : '+';
        const countClass = qty > 0 ? 'powerup-count' : 'powerup-count plus';
        return `
            <div class="powerup-slot">
                <div class="d-flex justify-content-between align-items-center">
                    <div><strong>${p.icon || ''} ${p.name}</strong><div class="small text-info">${p.point_cost} pts</div></div>
                    <button type="button" class="${countClass} side-powerup-btn"
                        data-powerup-id="${p.id}"
                        data-has-stock="${qty > 0 ? '1' : '0'}"
                        data-can-use="${canUse ? '1' : '0'}"
                        data-can-afford="${canAfford ? '1' : '0'}">${countLabel}</button>
                </div>
                <div class="powerup-gauge mt-2"><span style="width:${gaugePct}%"></span></div>
            </div>
        `;
    }).join('');
    document.getElementById('powerupSlots').innerHTML = slots;
    updatePowerupTargetLabel();
}

function showStoreModal() {
    if (isPreview) return;
    const pointBalance = Number(document.getElementById('pointBalance').textContent || 0);
    const html = shopCatalog.map(p => {
        const canAfford = pointBalance >= p.point_cost;
        return `
            <div class="border border-secondary rounded p-2 mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div><strong>${p.icon || ''} ${p.name}</strong><div class="small text-info">${p.point_cost} pts</div></div>
                    <button type="button" class="btn btn-sm ${canAfford ? 'btn-primary' : 'btn-outline-secondary'} store-buy-btn" data-powerup-id="${p.id}" ${canAfford ? '' : 'disabled'}>Buy</button>
                </div>
            </div>
        `;
    }).join('');
    document.getElementById('powerupStoreBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('powerupStoreModal')).show();
}

async function finishQuiz() {
    const cards = Array.from(document.querySelectorAll('.question-card'));
    for (const c of cards) {
        const qId = Number(c.dataset.questionId);
        try {
            await submitSingleAnswer(qId, null);
        } catch (err) {
            // best effort; finish still allowed
        }
    }
    const finishResp = await fetch(apiPhpUrl('/api/quizzes/finish-attempt'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ attempt_id: attemptId })
    });
    const result = await finishResp.json();
    if (result.success) {
        const chips = document.querySelectorAll('.hud-chip');
        chips.forEach(c => c.classList.add('hud-success-pulse'));
        window.location.href = `${appBase}/quiz_review.php?attempt_id=${encodeURIComponent(attemptId)}`;
        return;
    }
    alert(`Error finishing quiz: ${result.error || 'unknown'}`);
}

document.querySelectorAll('.question-card').forEach((card) => {
    card.addEventListener('click', () => {
        document.querySelectorAll('.question-card').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
    });
    const qId = Number(card.dataset.questionId);
    card.querySelectorAll('.answer-input').forEach((input) => {
        input.addEventListener('input', () => debounceSave(qId));
        input.addEventListener('change', () => debounceSave(qId));
    });
});

document.addEventListener('click', async (e) => {
    if (isPreview) return;
    const btn = e.target.closest('.side-powerup-btn');
    if (!btn) return;
    const pId = Number(btn.dataset.powerupId);
    const qId = getActiveQuestionId();
    if (!qId) return;
    const hasStock = btn.dataset.hasStock === '1';
    if (!hasStock) {
        showStoreModal();
        return;
    }
    if (btn.dataset.canUse !== '1') {
        alert('This powerup cannot be used on the current question.');
        return;
    }
    btn.disabled = true;
    try {
        await redeemAndActivate(qId, pId);
        if (quizMode === 'flash') {
            // flashcard mode auto-applies on current card by design
        }
    } catch (err) {
        alert(err.message || 'Powerup failed');
    } finally {
        btn.disabled = false;
    }
});

document.addEventListener('click', async (e) => {
    if (isPreview) return;
    const btn = e.target.closest('.store-buy-btn');
    if (!btn) return;
    const pId = Number(btn.dataset.powerupId);
    const qId = getActiveQuestionId();
    if (!qId) return;
    btn.disabled = true;
    try {
        const redeem = await fetch(apiPhpUrl('/api/quizzes/powerups/redeem'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ attempt_id: attemptId, powerup_id: pId })
        });
        const data = await redeem.json();
        if (!redeem.ok || !data.success) {
            alert(data.error || 'Insufficient balance or purchase failed');
            return;
        }
        document.getElementById('pointBalance').textContent = data.point_balance ?? document.getElementById('pointBalance').textContent;
        await refreshPowerupSidebar();
    } catch (err) {
        alert('Store purchase failed');
    } finally {
        btn.disabled = false;
    }
});

document.getElementById('finishBtn').addEventListener('click', async () => {
    document.getElementById('finishBtn').disabled = true;
    try { await finishQuiz(); } finally { document.getElementById('finishBtn').disabled = false; }
});

function updateTimer() {
    const mins = Math.floor(Math.max(0, remaining) / 60);
    const secs = Math.max(0, remaining) % 60;
    document.getElementById('timeLeft').textContent = `${mins}:${secs < 10 ? '0' : ''}${secs}`;
    if (remaining <= 0) {
        clearInterval(intervalHandle);
        finishQuiz();
        return;
    }
    remaining--;
}
const intervalHandle = setInterval(updateTimer, 1000);
updateTimer();
document.getElementById('refreshPowerupStateBtn')?.addEventListener('click', () => refreshPowerupSidebar().catch(() => {}));

const cards = Array.from(document.querySelectorAll('.question-card'));
function applyQuizMode(mode) {
    quizMode = mode;
    document.getElementById('modeScrollBtn').classList.toggle('active', mode === 'scroll');
    document.getElementById('modeFlashBtn').classList.toggle('active', mode === 'flash');
    document.getElementById('flashControls').classList.toggle('active', mode === 'flash');
    if (mode === 'scroll') {
        cards.forEach(c => c.classList.remove('flash-hidden'));
        cards.forEach(c => c.classList.remove('active'));
        cards[0]?.classList.add('active');
    } else {
        cards.forEach((c, idx) => c.classList.toggle('flash-hidden', idx !== currentFlashIndex));
        cards.forEach(c => c.classList.remove('active'));
        cards[currentFlashIndex]?.classList.add('active');
        document.getElementById('flashIndex').textContent = String(currentFlashIndex + 1);
    }
    if (!isPreview) refreshPowerupSidebar().catch(() => {});
}
document.getElementById('modeScrollBtn').addEventListener('click', () => applyQuizMode('scroll'));
document.getElementById('modeFlashBtn').addEventListener('click', () => applyQuizMode('flash'));
document.getElementById('prevFlashBtn').addEventListener('click', () => {
    currentFlashIndex = (currentFlashIndex - 1 + cards.length) % cards.length;
    applyQuizMode('flash');
});
document.getElementById('nextFlashBtn').addEventListener('click', () => {
    currentFlashIndex = (currentFlashIndex + 1) % cards.length;
    applyQuizMode('flash');
});
applyQuizMode('scroll');
<?php endif; ?>
</script>
</body>
</html>
