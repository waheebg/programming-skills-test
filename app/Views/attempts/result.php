<?php
/**
 * Views/attempts/result.php — Student Exam Result & Evaluation View (Phase 8).
 *
 * Variables:
 *  - $attempt: array
 *  - $exam: array
 *  - $result: ?array
 *  - $questions: array<int, array>
 *  - $can_view_result: bool
 *  - $can_view_correct_answers: bool
 *  - $is_staff: bool
 *  - $success: ?string
 *  - $warning: ?string
 *  - $error: ?string
 *  - $info: ?string
 */

$pageTitle = 'Exam Result - ' . ($exam['title'] ?? 'Exam');
$h = fn(?string $v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/../layouts/qb_header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="margin: 0;"><?= $h($exam['title'] ?? 'Exam') ?></h1>
        <p style="color: #64748b; margin-top: 0.25rem;">
            Attempt #<?= (int)($attempt['attempt_number'] ?? 1) ?>
            &bull; Participant: <?= $h(($attempt['first_name'] ?? '') . ' ' . ($attempt['last_name'] ?? '')) ?> (<?= $h($attempt['username'] ?? '') ?>)
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= url('/exams') ?>" class="btn btn-secondary">&larr; Back to Exams</a>
        <a href="<?= url('/dashboard') ?>" class="btn btn-secondary">Dashboard</a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $h($success) ?></div>
<?php endif; ?>
<?php if ($warning): ?>
    <div class="alert alert-warning"><?= $h($warning) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= $h($error) ?></div>
<?php endif; ?>
<?php if ($info): ?>
    <div class="alert alert-info"><?= $h($info) ?></div>
<?php endif; ?>

<?php if (!$can_view_result): ?>
    <!-- Result Withheld State -->
    <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
        <div style="font-size: 3rem; margin-bottom: 0.75rem; color: #3b82f6;">🔒</div>
        <h2 style="color: #1e293b; margin-bottom: 0.75rem;">Results Withheld by Instructor</h2>
        <p style="color: #64748b; max-width: 540px; margin: 0 auto 1.5rem; line-height: 1.6;">
            Your submission has been securely recorded and evaluated. However, this exam is configured to release scores and feedback at a later time by the instructor.
        </p>
        <div style="display: inline-flex; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.5rem; text-align: left; gap: 2rem;">
            <div>
                <span style="color: #64748b; font-size: 0.85rem; display: block;">Status</span>
                <span class="badge badge-<?= $h($attempt['status'] ?? 'submitted') ?>" style="margin-top: 0.25rem;">
                    <?= ucfirst($h($attempt['status'] ?? 'submitted')) ?>
                </span>
            </div>
            <div>
                <span style="color: #64748b; font-size: 0.85rem; display: block;">Submitted At</span>
                <strong style="display: block; margin-top: 0.25rem;"><?= $attempt['submitted_at'] ? $h($attempt['submitted_at']) : '—' ?></strong>
            </div>
            <div>
                <span style="color: #64748b; font-size: 0.85rem; display: block;">Questions Total</span>
                <strong style="display: block; margin-top: 0.25rem;"><?= count($questions) ?></strong>
            </div>
        </div>
    </div>
<?php elseif ($result): ?>
    <!-- Full Result Scorecards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Primary Outcome Card -->
        <div class="card" style="text-align: center; padding: 2rem; border-top: 4px solid <?= $result['passed'] ? '#16a34a' : '#dc2626' ?>;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">
                <?= $result['passed'] ? '🎉' : '⚠️' ?>
            </div>
            <div style="font-size: 2.25rem; font-weight: 700; color: <?= $result['passed'] ? '#16a34a' : '#dc2626' ?>; margin-bottom: 0.25rem;">
                <?= $result['passed'] ? 'PASSED' : 'FAILED' ?>
            </div>
            <p style="color: #64748b; margin-bottom: 1.25rem; font-size: 0.95rem;">
                Pass requirement: <?= number_format((float)($exam['pass_percentage'] ?? 50), 2) ?>%
            </p>
            <div style="font-size: 2.75rem; font-weight: 800; color: #0f172a; line-height: 1;">
                <?= number_format((float)$result['percentage'], 2) ?><span style="font-size: 1.5rem; font-weight: 600; color: #64748b;">%</span>
            </div>
            <div style="margin-top: 0.5rem; font-size: 1.1rem; color: #475569; font-weight: 600;">
                Score: <?= number_format((float)$result['score'], 2) ?> / <?= number_format((float)$result['total_marks'], 2) ?> marks
            </div>
        </div>

        <!-- Question Statistics Grid -->
        <div class="card" style="padding: 1.75rem;">
            <h3 style="margin-top: 0; margin-bottom: 1.25rem; font-size: 1.15rem; color: #1e293b; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                Performance Summary
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <span style="font-size: 0.85rem; color: #64748b; display: block;">Total Questions</span>
                    <span style="font-size: 1.6rem; font-weight: 700; color: #0f172a;"><?= (int)$result['total_questions'] ?></span>
                </div>
                <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <span style="font-size: 0.85rem; color: #64748b; display: block;">Answered</span>
                    <span style="font-size: 1.6rem; font-weight: 700; color: #3b82f6;"><?= (int)$result['answered_questions'] ?></span>
                </div>
                <div style="background: #ecfdf5; padding: 1rem; border-radius: 8px; border: 1px solid #a7f3d0;">
                    <span style="font-size: 0.85rem; color: #047857; display: block;">Correct Answers</span>
                    <span style="font-size: 1.6rem; font-weight: 700; color: #16a34a;"><?= (int)$result['correct_answers'] ?></span>
                </div>
                <div style="background: #fef2f2; padding: 1rem; border-radius: 8px; border: 1px solid #fecaca;">
                    <span style="font-size: 0.85rem; color: #b91c1c; display: block;">Wrong Answers</span>
                    <span style="font-size: 1.6rem; font-weight: 700; color: #dc2626;"><?= (int)$result['wrong_answers'] ?></span>
                </div>
            </div>
            <?php 
                $unanswered = (int)$result['total_questions'] - (int)$result['answered_questions'];
                if ($unanswered > 0):
            ?>
                <div style="margin-top: 1rem; font-size: 0.9rem; color: #d97706; background: #fffbeb; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #fde68a;">
                    ⚠️ <?= $unanswered ?> question<?= $unanswered > 1 ? 's were' : ' was' ?> left unanswered.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Questions Breakdown Section -->
    <div class="card" style="padding: 1.75rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
            <h2 style="margin: 0; font-size: 1.35rem; color: #1e293b;">Question Review</h2>
            <?php if (!$can_view_correct_answers): ?>
                <span style="font-size: 0.85rem; color: #64748b; background: #f1f5f9; padding: 0.35rem 0.75rem; border-radius: 6px;">
                    ℹ️ Correct answers are hidden per exam configuration
                </span>
            <?php endif; ?>
        </div>

        <?php foreach ($questions as $idx => $q): ?>
            <div style="margin-bottom: 2rem; padding: 1.25rem; border-radius: 8px; border: 1px solid <?= $q['status'] === 'correct' ? '#bbf7d0' : ($q['status'] === 'wrong' ? '#fecaca' : '#e2e8f0') ?>; background: <?= $q['status'] === 'correct' ? '#f0fdf4' : ($q['status'] === 'wrong' ? '#fff5f5' : '#ffffff') ?>;">
                <!-- Question Header -->
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <div>
                        <span style="font-weight: 700; font-size: 1.1rem; color: #1e293b;">Question <?= $idx + 1 ?></span>
                        <span class="badge" style="margin-left: 0.5rem; font-size: 0.75rem; text-transform: uppercase;">
                            <?= $h(str_replace('_', ' ', $q['question_type'])) ?>
                        </span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <?php if ($q['status'] === 'correct'): ?>
                            <span style="background: #16a34a; color: white; padding: 0.25rem 0.6rem; border-radius: 4px; font-weight: 600; font-size: 0.85rem;">
                                ✓ Correct
                            </span>
                        <?php elseif ($q['status'] === 'wrong'): ?>
                            <span style="background: #dc2626; color: white; padding: 0.25rem 0.6rem; border-radius: 4px; font-weight: 600; font-size: 0.85rem;">
                                ✗ Incorrect
                            </span>
                        <?php else: ?>
                            <span style="background: #f59e0b; color: white; padding: 0.25rem 0.6rem; border-radius: 4px; font-weight: 600; font-size: 0.85rem;">
                                ⚪ Unanswered
                            </span>
                        <?php endif; ?>

                        <span style="font-weight: 700; color: #334155; font-size: 0.95rem;">
                            <?= number_format((float)$q['awarded_mark'], 2) ?> / <?= number_format((float)$q['mark'], 2) ?> pts
                        </span>
                    </div>
                </div>

                <!-- Question Text -->
                <div style="font-size: 1.05rem; color: #1e293b; margin-bottom: 1.25rem; line-height: 1.5; white-space: pre-wrap;"><?= $h($q['question_text']) ?></div>

                <!-- Options -->
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <?php foreach ($q['options'] as $opt): 
                        $isSelected = !empty($opt['is_selected']);
                        $isCorrect  = !empty($opt['is_correct']);
                        
                        $bgColor = '#ffffff';
                        $borderColor = '#e2e8f0';
                        $textColor = '#334155';

                        if ($isSelected && $can_view_correct_answers) {
                            if ($isCorrect) {
                                $bgColor = '#dcfce7';
                                $borderColor = '#16a34a';
                            } else {
                                $bgColor = '#fee2e2';
                                $borderColor = '#ef4444';
                            }
                        } elseif ($isSelected && !$can_view_correct_answers) {
                            $bgColor = '#eff6ff';
                            $borderColor = '#3b82f6';
                        } elseif (!$isSelected && $can_view_correct_answers && $isCorrect) {
                            $bgColor = '#f0fdf4';
                            $borderColor = '#22c55e';
                        }
                    ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; border-radius: 6px; border: 1.5px solid <?= $borderColor ?>; background: <?= $bgColor ?>; color: <?= $textColor ?>;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <span style="font-weight: 600;"><?= $isSelected ? '◉' : '○' ?></span>
                                <span><?= $h($opt['option_text']) ?></span>
                            </div>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <?php if ($isSelected): ?>
                                    <span style="font-size: 0.75rem; font-weight: 600; padding: 0.15rem 0.5rem; border-radius: 4px; background: #3b82f6; color: white;">
                                        Your Choice
                                    </span>
                                <?php endif; ?>

                                <?php if ($can_view_correct_answers && $isCorrect): ?>
                                    <span style="font-size: 0.75rem; font-weight: 600; padding: 0.15rem 0.5rem; border-radius: 4px; background: #16a34a; color: white;">
                                        Correct Choice
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Explanation (if permitted) -->
                <?php if ($can_view_correct_answers && !empty($q['explanation'])): ?>
                    <div style="margin-top: 1rem; padding: 0.75rem 1rem; background: #f8fafc; border-left: 4px solid #3b82f6; border-radius: 4px;">
                        <span style="font-weight: 600; font-size: 0.85rem; color: #1e40af; display: block; margin-bottom: 0.25rem;">Explanation:</span>
                        <div style="color: #475569; font-size: 0.95rem; line-height: 1.5;"><?= $h($q['explanation']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/qb_footer.php'; ?>
