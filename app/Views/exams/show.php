<?php
/**
 * Views/exams/show.php — Exam detail view with questions and rules.
 */

use App\Core\Csrf;

$pageTitle = 'Exam: ' . ($exam['title'] ?? '');
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/../layouts/qb_header.php';
?>

<div class="page-header">
    <h1><?= $h($exam['title']) ?></h1>
    <div class="actions">
        <?php if ($canUpdate && $exam['status'] !== 'archived'): ?>
            <a href="<?= url('/exams/edit?id=' . (int)$exam['id']) ?>" class="btn btn-warning">Edit</a>
            <?php if (in_array($exam['selection_mode'], ['manual', 'hybrid'], true)): ?>
                <a href="<?= url('/exams/questions?id=' . (int)$exam['id']) ?>" class="btn btn-secondary">Add Questions</a>
            <?php endif; ?>
            <?php if (in_array($exam['selection_mode'], ['random', 'hybrid'], true)): ?>
                <a href="<?= url('/exams/rules?id=' . (int)$exam['id']) ?>" class="btn btn-secondary">Manage Rules</a>
                <a href="<?= url('/exams/preview?id=' . (int)$exam['id']) ?>" class="btn btn-secondary">Preview Selection</a>
            <?php endif; ?>
            <?php if ($exam['status'] !== 'published'): ?>
                <form method="POST" action="<?= url('/exams/publish') ?>" style="display:inline;"
                      onsubmit="return confirm('Publish this exam? This will validate all questions and rules.');">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int)$exam['id'] ?>">
                    <button type="submit" class="btn btn-primary">Publish</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($canDelete && $exam['status'] !== 'archived'): ?>
            <form method="POST" action="<?= url('/exams/delete') ?>" style="display:inline;"
                  onsubmit="return confirm('Delete this exam?');">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int)$exam['id'] ?>">
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        <?php endif; ?>
        <?php if (!empty($canTake) && $exam['status'] === 'published'): ?>
            <form method="POST" action="<?= url('/attempts/start') ?>" style="display:inline;">
                <?= Csrf::field() ?>
                <input type="hidden" name="exam_id" value="<?= (int)$exam['id'] ?>">
                <button type="submit" class="btn btn-success">
                    <?= !empty($activeAttempt) ? '▶ Resume Attempt' : '▶ Start Exam' ?>
                </button>
            </form>
        <?php endif; ?>
        <a href="<?= url('/exams') ?>" class="btn btn-secondary">← Back</a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $h($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= $h($error) ?></div>
<?php endif; ?>

<!-- Exam Settings Card -->
<div class="card">
    <h2>Settings</h2>
    <table>
        <tbody>
        <tr><td style="width:220px;color:#64748b;">Status</td>
            <td><span class="badge badge-<?= $h($exam['status']) ?>"><?= $h($exam['status']) ?></span></td></tr>
        <tr><td style="color:#64748b;">Selection Mode</td>
            <td><span class="badge badge-sc"><?= $h($exam['selection_mode']) ?></span></td></tr>
        <tr><td style="color:#64748b;">Language</td>
            <td><?= $exam['language_name'] ? $h($exam['language_name']) : '—' ?></td></tr>
        <tr><td style="color:#64748b;">Duration</td>
            <td><?= (int)$exam['duration_minutes'] ?> minutes</td></tr>
        <tr><td style="color:#64748b;">Pass Percentage</td>
            <td><?= $h($exam['pass_percentage']) ?>%</td></tr>
        <tr><td style="color:#64748b;">Max Attempts</td>
            <td><?= $exam['max_attempts'] !== null ? (int)$exam['max_attempts'] : 'Unlimited' ?></td></tr>
        <tr><td style="color:#64748b;">Starts At</td>
            <td><?= $exam['starts_at'] ? $h($exam['starts_at']) : '—' ?></td></tr>
        <tr><td style="color:#64748b;">Ends At</td>
            <td><?= $exam['ends_at'] ? $h($exam['ends_at']) : '—' ?></td></tr>
        <tr><td style="color:#64748b;">Randomize Questions</td>
            <td><?= $exam['randomize_questions'] ? '✓ Yes' : '✗ No' ?></td></tr>
        <tr><td style="color:#64748b;">Randomize Options</td>
            <td><?= $exam['randomize_options'] ? '✓ Yes' : '✗ No' ?></td></tr>
        <tr><td style="color:#64748b;">Show Result Immediately</td>
            <td><?= $exam['show_result_immediately'] ? '✓ Yes' : '✗ No' ?></td></tr>
        <tr><td style="color:#64748b;">Show Correct Answers</td>
            <td><?= $exam['show_correct_answers'] ? '✓ Yes' : '✗ No' ?></td></tr>
        </tbody>
    </table>
    <?php if (!empty($exam['description'])): ?>
        <p style="margin-top:1rem; color:#475569;"><?= nl2br($h($exam['description'])) ?></p>
    <?php endif; ?>
</div>

<!-- Manual Questions -->
<?php if (!empty($manualQuestions) || in_array($exam['selection_mode'], ['manual', 'hybrid'], true)): ?>
<div class="card">
    <div class="page-header" style="margin-bottom:0.75rem;">
        <h2>Manual Questions (<?= count($manualQuestions) ?>)</h2>
        <?php if ($canUpdate && $exam['status'] !== 'archived' && in_array($exam['selection_mode'], ['manual', 'hybrid'], true)): ?>
            <a href="<?= url('/exams/questions?id=' . (int)$exam['id']) ?>" class="btn btn-sm btn-secondary">Add Questions</a>
        <?php endif; ?>
    </div>
    <?php if (empty($manualQuestions)): ?>
        <p style="color:#64748b;">No manually selected questions yet.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>#</th><th>Question</th><th>Type</th><th>Difficulty</th><th>Status</th><th>Mark</th>
                <?php if ($canUpdate && $exam['status'] !== 'archived'): ?>
                    <th>Action</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($manualQuestions as $q): ?>
                <tr>
                    <td><?= (int)$q['sort_order'] ?></td>
                    <td><?= $h(mb_substr($q['question_text'], 0, 80)) ?><?= mb_strlen($q['question_text']) > 80 ? '…' : '' ?></td>
                    <td><span class="badge badge-sc"><?= $h($q['question_type']) ?></span></td>
                    <td><span class="badge badge-<?= $h($q['difficulty']) ?>"><?= $h($q['difficulty']) ?></span></td>
                    <td><span class="badge badge-<?= $h($q['question_status']) ?>"><?= $h($q['question_status']) ?></span></td>
                    <td><?= $q['mark'] !== null ? $h((string)$q['mark']) : '—' ?></td>
                    <?php if ($canUpdate && $exam['status'] !== 'archived'): ?>
                        <td>
                            <form method="POST" action="<?= url('/exams/questions/remove') ?>" style="display:inline;"
                                  onsubmit="return confirm('Remove this question from the exam?');">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="exam_id"     value="<?= (int)$exam['id'] ?>">
                                <input type="hidden" name="question_id" value="<?= (int)$q['question_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Selection Rules -->
<?php if (!empty($rules) || in_array($exam['selection_mode'], ['random', 'hybrid'], true)): ?>
<div class="card">
    <div class="page-header" style="margin-bottom:0.75rem;">
        <h2>Selection Rules (<?= count($rules) ?>)</h2>
        <?php if ($canUpdate && $exam['status'] !== 'archived'): ?>
            <a href="<?= url('/exams/rules?id=' . (int)$exam['id']) ?>" class="btn btn-sm btn-secondary">Manage Rules</a>
        <?php endif; ?>
    </div>
    <?php if (empty($rules)): ?>
        <p style="color:#64748b;">No selection rules configured yet.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Order</th><th>Language</th><th>Category</th><th>Difficulty</th><th>Count</th><th>Mark</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rules as $rule): ?>
                <tr>
                    <td><?= (int)$rule['rule_order'] ?></td>
                    <td><?= $rule['language_name'] ? $h($rule['language_name']) : '—' ?></td>
                    <td><?= $rule['category_name'] ? $h($rule['category_name']) : '—' ?></td>
                    <td><?= $rule['difficulty'] ? '<span class="badge badge-' . $h($rule['difficulty']) . '">' . $h($rule['difficulty']) . '</span>' : '—' ?></td>
                    <td><?= (int)$rule['question_count'] ?></td>
                    <td><?= $rule['mark'] !== null ? $h((string)$rule['mark']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/qb_footer.php'; ?>
