<?php
/**
 * Views/attempts/take.php — Student Exam-Taking Interface (Phase 7).
 *
 * Variables:
 *  - $attempt: array
 *  - $exam: array
 *  - $questions: array<int, array>
 *  - $answers: array<int, array> (keyed by attempt_question_id)
 *  - $remaining_seconds: int
 *  - $success: ?string
 *  - $error: ?string
 *  - $info: ?string
 */

use App\Core\Csrf;

$pageTitle = 'Exam: ' . ($exam['title'] ?? 'Taking Exam');
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$csrfToken = Csrf::getToken();
$totalQ = count($questions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $h($pageTitle) ?> — Programming Skills Test</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }
        .exam-header {
            background: #0f172a;
            color: #f8fafc;
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .exam-title-box h1 {
            font-size: 1.15rem;
            margin: 0;
            font-weight: 700;
        }
        .exam-meta {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 0.2rem;
        }
        .timer-box {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            background: #1e293b;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid #334155;
        }
        .timer-label {
            font-size: 0.8rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .timer-value {
            font-family: monospace;
            font-size: 1.35rem;
            font-weight: 700;
            color: #38bdf8;
        }
        .timer-warning {
            color: #f59e0b !important;
            animation: pulse 1s infinite alternate;
        }
        .timer-danger {
            color: #ef4444 !important;
            animation: pulse 0.5s infinite alternate;
        }
        @keyframes pulse {
            from { opacity: 1; }
            to { opacity: 0.4; }
        }
        .container {
            max-width: 1100px;
            margin: 1.5rem auto;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 1.5rem;
        }
        @media (max-width: 850px) {
            .container { grid-template-columns: 1fr; }
        }
        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
        }
        .question-card {
            display: none;
        }
        .question-card.active {
            display: block;
        }
        .q-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 0.75rem;
            margin-bottom: 1rem;
        }
        .q-num {
            font-weight: 700;
            font-size: 1.1rem;
            color: #0f172a;
        }
        .q-mark {
            background: #e0f2fe;
            color: #0369a1;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .q-text {
            font-size: 1.05rem;
            line-height: 1.6;
            color: #1e293b;
            margin-bottom: 1.5rem;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .options-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.75rem;
        }
        .option-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            background: #ffffff;
        }
        .option-item:hover {
            border-color: #93c5fd;
            background: #f8fafc;
        }
        .option-item.selected {
            border-color: #0284c7;
            background: #f0f9ff;
        }
        .option-item input[type="radio"] {
            margin-top: 0.2rem;
            cursor: pointer;
            accent-color: #0284c7;
            width: 18px;
            height: 18px;
        }
        .option-letter {
            font-weight: 700;
            color: #64748b;
            min-width: 20px;
        }
        .option-text {
            flex: 1;
            color: #1e293b;
            font-size: 0.95rem;
            line-height: 1.4;
        }
        .nav-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 1.25rem;
        }
        .save-indicator {
            font-size: 0.82rem;
            font-weight: 500;
            color: #64748b;
        }
        .save-indicator.saved { color: #166534; }
        .save-indicator.saving { color: #0284c7; }
        .save-indicator.error { color: #dc2626; }
        .btn {
            display: inline-block;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: background 0.15s;
        }
        .btn-primary   { background:#0284c7; color:#fff; }
        .btn-primary:hover { background:#0369a1; }
        .btn-secondary { background:#fff; color:#334155; border-color:#cbd5e1; }
        .btn-secondary:hover { background:#f1f5f9; }
        .btn-success   { background:#16a34a; color:#fff; }
        .btn-success:hover { background:#15803d; }
        .btn-danger    { background:#ef4444; color:#fff; }
        .btn-danger:hover { background:#dc2626; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .sidebar {
            position: sticky;
            top: 80px;
            height: fit-content;
        }
        .sidebar-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 0.75rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .palette-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }
        .palette-btn {
            padding: 0.6rem 0.2rem;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            cursor: pointer;
            transition: all 0.15s;
        }
        .palette-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
        .palette-btn.active {
            border-color: #0284c7;
            box-shadow: 0 0 0 2px rgba(2,132,199,0.3);
            font-weight: 700;
        }
        .palette-btn.answered {
            background: #dcfce7;
            color: #166534;
            border-color: #86efac;
        }
        .palette-btn.active.answered {
            border-color: #0284c7;
        }
        .palette-legend {
            display: flex;
            gap: 1rem;
            font-size: 0.78rem;
            color: #64748b;
            margin-bottom: 1.25rem;
        }
        .legend-item { display: flex; align-items: center; gap: 0.35rem; }
        .legend-dot { width: 10px; height: 10px; border-radius: 3px; border: 1px solid #cbd5e1; }
        .legend-dot.answered { background: #dcfce7; border-color: #86efac; }
        .legend-dot.unanswered { background: #fff; border-color: #cbd5e1; }
        .progress-box { margin-bottom: 1.25rem; }
        .progress-bar-bg {
            background: #e2e8f0;
            height: 8px;
            border-radius: 9999px;
            overflow: hidden;
            margin-top: 0.4rem;
        }
        .progress-bar-fill {
            background: #16a34a;
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        .alert-success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
        .alert-error   { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
        .alert-info    { background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; }
    </style>
</head>
<body>

<header class="exam-header">
    <div class="exam-title-box">
        <h1><?= $h($exam['title']) ?></h1>
        <div class="exam-meta">
            Attempt #<?= (int)$attempt['attempt_number'] ?> &bull;
            <?= $totalQ ?> Question<?= $totalQ === 1 ? '' : 's' ?> &bull;
            Duration: <?= (int)$exam['duration_minutes'] ?> min
        </div>
    </div>
    <div class="timer-box">
        <span class="timer-label">Time Left:</span>
        <span id="timer-display" class="timer-value">--:--</span>
    </div>
</header>

<div class="container">
    <main>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $h($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $h($error) ?></div>
        <?php endif; ?>
        <?php if ($info): ?>
            <div class="alert alert-info"><?= $h($info) ?></div>
        <?php endif; ?>

        <?php foreach ($questions as $idx => $q): ?>
            <?php
            $aqId = (int)$q['id'];
            $savedOptionId = isset($answers[$aqId]) ? (int)$answers[$aqId]['selected_option_id'] : null;
            $letters = ['A', 'B', 'C', 'D', 'E', 'F'];
            ?>
            <div class="card question-card <?= $idx === 0 ? 'active' : '' ?>" id="card-<?= $idx ?>" data-index="<?= $idx ?>" data-aq-id="<?= $aqId ?>">
                <div class="q-header">
                    <span class="q-num">Question <?= $idx + 1 ?> of <?= $totalQ ?></span>
                    <span class="q-mark"><?= number_format((float)$q['mark'], 2) ?> Mark<?= (float)$q['mark'] == 1 ? '' : 's' ?></span>
                </div>

                <div class="q-text"><?= $h($q['question_text_snapshot']) ?></div>

                <div class="options-list">
                    <?php foreach ($q['options'] as $oIdx => $opt): ?>
                        <?php
                        $optId = (int)$opt['id'];
                        $isCheck = ($savedOptionId !== null && $savedOptionId === $optId);
                        $letter = $letters[$oIdx] ?? ($oIdx + 1);
                        ?>
                        <label class="option-item <?= $isCheck ? 'selected' : '' ?>" id="opt-label-<?= $optId ?>">
                            <input type="radio"
                                   name="question_<?= $aqId ?>"
                                   value="<?= $optId ?>"
                                   data-aq-id="<?= $aqId ?>"
                                   data-opt-id="<?= $optId ?>"
                                   data-q-index="<?= $idx ?>"
                                   <?= $isCheck ? 'checked' : '' ?>
                                   onchange="onSelectOption(this)">
                            <span class="option-letter"><?= $letter ?>.</span>
                            <span class="option-text"><?= $h($opt['option_text']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="nav-controls">
                    <span class="save-indicator" id="save-status-<?= $aqId ?>">
                        <?= $savedOptionId !== null ? '✓ Answer saved' : '' ?>
                    </span>
                    <div style="display:flex; gap:0.5rem;">
                        <button type="button" class="btn btn-secondary"
                                onclick="goToQuestion(<?= $idx - 1 ?>)"
                                <?= $idx === 0 ? 'disabled' : '' ?>>
                            ← Previous
                        </button>
                        <?php if ($idx < $totalQ - 1): ?>
                            <button type="button" class="btn btn-primary"
                                    onclick="goToQuestion(<?= $idx + 1 ?>)">
                                Next →
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-success"
                                    onclick="confirmSubmit()">
                                Submit Exam ✓
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </main>

    <aside class="sidebar">
        <div class="card">
            <h2 class="sidebar-title">
                <span>Questions</span>
                <span id="answered-count-text" style="font-size:0.8rem; font-weight:normal; color:#64748b;">
                    0/<?= $totalQ ?> answered
                </span>
            </h2>

            <div class="progress-box">
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" id="progress-fill"></div>
                </div>
            </div>

            <div class="palette-grid">
                <?php foreach ($questions as $idx => $q): ?>
                    <?php
                    $aqId = (int)$q['id'];
                    $isAns = isset($answers[$aqId]) && $answers[$aqId]['selected_option_id'] !== null;
                    ?>
                    <button type="button"
                            class="palette-btn <?= $idx === 0 ? 'active' : '' ?> <?= $isAns ? 'answered' : '' ?>"
                            id="palette-btn-<?= $idx ?>"
                            onclick="goToQuestion(<?= $idx ?>)">
                        <?= $idx + 1 ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="palette-legend">
                <div class="legend-item">
                    <span class="legend-dot answered"></span>
                    <span>Answered</span>
                </div>
                <div class="legend-item">
                    <span class="legend-dot unanswered"></span>
                    <span>Unanswered</span>
                </div>
            </div>

            <form id="submit-exam-form" method="POST" action="<?= url('/attempts/submit') ?>" onsubmit="return confirmSubmit();">
                <?= Csrf::field() ?>
                <input type="hidden" name="attempt_id" value="<?= (int)$attempt['id'] ?>">
                <button type="submit" class="btn btn-success" style="width:100%;">
                    Submit Exam
                </button>
            </form>
        </div>
    </aside>
</div>

<script>
    // --- Server-provided parameters ---
    const ATTEMPT_ID = <?= (int)$attempt['id'] ?>;
    const CSRF_TOKEN = '<?= $csrfToken ?>';
    const TOTAL_QUESTIONS = <?= $totalQ ?>;
    const SAVE_ANSWER_URL = '<?= url('/attempts/save-answer') ?>';
    let remainingSeconds = <?= (int)$remaining_seconds ?>;
    let currentQuestionIndex = 0;

    // --- Timer implementation ---
    const timerDisplay = document.getElementById('timer-display');

    function formatTime(secs) {
        const m = Math.floor(secs / 60);
        const s = secs % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function updateTimer() {
        if (remainingSeconds <= 0) {
            timerDisplay.textContent = '00:00';
            timerDisplay.classList.add('timer-danger');
            clearInterval(timerInterval);
            autoSubmitOnExpiry();
            return;
        }

        timerDisplay.textContent = formatTime(remainingSeconds);

        if (remainingSeconds <= 60) {
            timerDisplay.classList.add('timer-danger');
            timerDisplay.classList.remove('timer-warning');
        } else if (remainingSeconds <= 300) {
            timerDisplay.classList.add('timer-warning');
            timerDisplay.classList.remove('timer-danger');
        }

        remainingSeconds--;
    }

    updateTimer();
    const timerInterval = setInterval(updateTimer, 1000);

    function autoSubmitOnExpiry() {
        alert('Time has expired! Submitting your exam automatically.');
        const form = document.getElementById('submit-exam-form');
        form.submit();
    }

    // --- Question Navigation ---
    function goToQuestion(index) {
        if (index < 0 || index >= TOTAL_QUESTIONS) return;

        // Hide current
        const currentCard = document.getElementById('card-' + currentQuestionIndex);
        if (currentCard) currentCard.classList.remove('active');

        const currentPal = document.getElementById('palette-btn-' + currentQuestionIndex);
        if (currentPal) currentPal.classList.remove('active');

        // Show target
        currentQuestionIndex = index;
        const targetCard = document.getElementById('card-' + index);
        if (targetCard) targetCard.classList.add('active');

        const targetPal = document.getElementById('palette-btn-' + index);
        if (targetPal) targetPal.classList.add('active');

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // --- Answer Saving via AJAX ---
    function onSelectOption(radio) {
        const aqId = radio.dataset.aqId;
        const optId = radio.dataset.optId;
        const qIndex = parseInt(radio.dataset.qIndex, 10);
        const statusEl = document.getElementById('save-status-' + aqId);

        // Update selected styles on options
        const card = document.getElementById('card-' + qIndex);
        card.querySelectorAll('.option-item').forEach(el => el.classList.remove('selected'));
        const activeLabel = document.getElementById('opt-label-' + optId);
        if (activeLabel) activeLabel.classList.add('selected');

        if (statusEl) {
            statusEl.textContent = 'Saving…';
            statusEl.className = 'save-indicator saving';
        }

        // Send AJAX request to save answer
        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('attempt_id', ATTEMPT_ID);
        formData.append('attempt_question_id', aqId);
        formData.append('selected_option_id', optId);
        formData.append('ajax', '1');

        fetch(SAVE_ANSWER_URL, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (statusEl) {
                    statusEl.textContent = '✓ Answer saved';
                    statusEl.className = 'save-indicator saved';
                }
                const paletteBtn = document.getElementById('palette-btn-' + qIndex);
                if (paletteBtn) paletteBtn.classList.add('answered');
                updateProgress();
            } else {
                if (data.expired) {
                    alert('Exam time has ended!');
                    location.reload();
                    return;
                }
                if (statusEl) {
                    statusEl.textContent = '✕ Save failed';
                    statusEl.className = 'save-indicator error';
                }
            }
        })
        .catch(err => {
            console.error('Error saving answer:', err);
            if (statusEl) {
                statusEl.textContent = '✕ Network error';
                statusEl.className = 'save-indicator error';
            }
        });
    }

    function updateProgress() {
        const answeredCount = document.querySelectorAll('.palette-btn.answered').length;
        const countText = document.getElementById('answered-count-text');
        if (countText) {
            countText.textContent = answeredCount + '/' + TOTAL_QUESTIONS + ' answered';
        }
        const fill = document.getElementById('progress-fill');
        if (fill && TOTAL_QUESTIONS > 0) {
            const pct = Math.round((answeredCount / TOTAL_QUESTIONS) * 100);
            fill.style.width = pct + '%';
        }
    }

    updateProgress();

    function confirmSubmit() {
        const answered = document.querySelectorAll('.palette-btn.answered').length;
        const unanswered = TOTAL_QUESTIONS - answered;
        let msg = 'Are you sure you want to submit your exam?\n\n' +
                  'Answered: ' + answered + ' of ' + TOTAL_QUESTIONS;
        if (unanswered > 0) {
            msg += '\nWarning: You have ' + unanswered + ' unanswered question(s)!';
        }
        msg += '\n\nOnce submitted, you cannot change your answers.';
        return confirm(msg);
    }
</script>
</body>
</html>
