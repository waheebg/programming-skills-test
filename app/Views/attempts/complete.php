<?php
/**
 * Views/attempts/complete.php — Attempt Submission / Completion View (Phase 7).
 *
 * Variables:
 *  - $attempt: array
 *  - $total_questions: int
 *  - $answered_questions: int
 *  - $success: ?string
 *  - $warning: ?string
 *  - $error: ?string
 *  - $info: ?string
 */

$pageTitle = 'Exam Completed';
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/../layouts/qb_header.php';
?>

<div class="page-header">
    <h1>Exam Status</h1>
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

<div class="card" style="text-align: center; padding: 2.5rem 1.5rem;">
    <?php if ($attempt['status'] === 'submitted'): ?>
        <div style="font-size: 3rem; margin-bottom: 0.5rem; color: #16a34a;">✓</div>
        <h2 style="color: #16a34a; margin-bottom: 0.5rem;">Exam Submitted Successfully</h2>
        <p style="color: #64748b; margin-bottom: 1.5rem;">Your answers have been securely recorded.</p>
    <?php elseif ($attempt['status'] === 'expired'): ?>
        <div style="font-size: 3rem; margin-bottom: 0.5rem; color: #f59e0b;">⏱</div>
        <h2 style="color: #d97706; margin-bottom: 0.5rem;">Exam Time Expired</h2>
        <p style="color: #64748b; margin-bottom: 1.5rem;">The time limit for this exam has ended. Your saved answers were submitted.</p>
    <?php else: ?>
        <h2 style="margin-bottom: 0.5rem;">Exam Status: <?= ucfirst($h($attempt['status'])) ?></h2>
    <?php endif; ?>

    <div style="max-width: 480px; margin: 0 auto; text-align: left; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
        <table style="font-size: 0.95rem;">
            <tbody>
                <tr>
                    <td style="color: #64748b; padding: 0.5rem 0;">Exam</td>
                    <td style="font-weight: 600; text-align: right;"><?= $h($attempt['exam_title'] ?? 'Exam') ?></td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 0.5rem 0;">Attempt #</td>
                    <td style="font-weight: 600; text-align: right;"><?= (int)$attempt['attempt_number'] ?></td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 0.5rem 0;">Status</td>
                    <td style="text-align: right;">
                        <span class="badge badge-<?= $h($attempt['status']) ?>">
                            <?= ucfirst($h($attempt['status'])) ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 0.5rem 0;">Started At</td>
                    <td style="text-align: right;"><?= $h($attempt['started_at']) ?></td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 0.5rem 0;">Submitted At</td>
                    <td style="text-align: right;"><?= $attempt['submitted_at'] ? $h($attempt['submitted_at']) : '—' ?></td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 0.5rem 0;">Questions Answered</td>
                    <td style="font-weight: 600; text-align: right;">
                        <?= (int)$answered_questions ?> of <?= (int)$total_questions ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 2rem; display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
        <a href="<?= url('/attempts/result?id=' . (int)$attempt['id']) ?>" class="btn btn-primary">View Results & Score</a>
        <a href="<?= url('/exams') ?>" class="btn btn-secondary">Browse Exams</a>
        <a href="<?= url('/dashboard') ?>" class="btn btn-secondary">Dashboard</a>
    </div>
</div>

<?php require __DIR__ . '/../layouts/qb_footer.php'; ?>
