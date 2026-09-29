<?php
/**
 * Views/exams/rules.php — Manage selection rules for random/hybrid exams.
 */

use App\Core\Csrf;

$pageTitle = 'Selection Rules — ' . ($exam['title'] ?? '');
$h  = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$old = $old ?? [];
require __DIR__ . '/../layouts/qb_header.php';
?>

<div class="page-header">
    <h1>Selection Rules: "<?= $h($exam['title']) ?>"</h1>
    <a href="<?= url('/exams/show?id=' . (int)$exam['id']) ?>" class="btn btn-secondary">← Back to Exam</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $h($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= $h($error) ?></div>
<?php endif; ?>

<!-- Add Rule Form -->
<div class="card">
    <h2>Add New Rule</h2>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>Errors:</strong>
            <ul><?php foreach ($errors as $err): ?><li><?= $h($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <form method="POST" action="<?= url('/exams/rules/store') ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="exam_id" value="<?= (int)$exam['id'] ?>">

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
            <div class="form-group">
                <label for="programming_language_id">Language (optional)</label>
                <select id="programming_language_id" name="programming_language_id">
                    <option value="">— Any Language —</option>
                    <?php
                    $selLangId = (int)($old['programming_language_id'] ?? 0);
                    foreach ($languages as $lang): ?>
                        <option value="<?= (int)$lang['id'] ?>"
                            <?= $selLangId === (int)$lang['id'] ? 'selected' : '' ?>>
                            <?= $h($lang['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="category_id">Category (optional)</label>
                <select id="category_id" name="category_id">
                    <option value="">— Any Category —</option>
                    <?php
                    $selCatId = (int)($old['category_id'] ?? 0);
                    foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"
                            <?= $selCatId === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= $h($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="difficulty">Difficulty (optional)</label>
                <select id="difficulty" name="difficulty">
                    <option value="">— Any Difficulty —</option>
                    <?php
                    $selDiff = $old['difficulty'] ?? '';
                    foreach (['easy', 'medium', 'hard'] as $d): ?>
                        <option value="<?= $h($d) ?>" <?= $selDiff === $d ? 'selected' : '' ?>>
                            <?= ucfirst($h($d)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
                <label for="question_count">Question Count <span style="color:#dc2626">*</span></label>
                <input type="number" id="question_count" name="question_count" min="1"
                       value="<?= (int)($old['question_count'] ?? 5) ?>" required>
            </div>
            <div class="form-group">
                <label for="mark">Mark per Question (optional, overrides question default)</label>
                <input type="number" id="mark" name="mark" min="0" step="0.01"
                       value="<?= $h($old['mark'] ?? '') ?>">
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Add Rule</button>
        </div>
    </form>
</div>

<!-- Existing Rules -->
<div class="card">
    <h2>Current Rules (<?= count($rules) ?>)</h2>
    <?php if (empty($rules)): ?>
        <p style="color:#64748b;">No rules configured yet.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>#</th><th>Language</th><th>Category</th><th>Difficulty</th>
                <th>Count</th><th>Mark</th><th>Available</th><th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rules as $rule): ?>
                <?php
                // Count available matching published questions
                $excludeIds = \App\Models\ExamQuestion::questionIds((int)$exam['id']);
                $available  = \App\Models\ExamQuestionRule::countMatchingPublished($rule, $excludeIds);
                $needed     = (int)$rule['question_count'];
                $ok         = $available >= $needed;
                ?>
                <tr>
                    <td><?= (int)$rule['rule_order'] ?></td>
                    <td><?= $rule['language_name'] ? $h($rule['language_name']) : '—' ?></td>
                    <td><?= $rule['category_name'] ? $h($rule['category_name']) : '—' ?></td>
                    <td><?= $rule['difficulty'] ? '<span class="badge badge-' . $h($rule['difficulty']) . '">' . $h($rule['difficulty']) . '</span>' : '—' ?></td>
                    <td><?= $needed ?></td>
                    <td><?= $rule['mark'] !== null ? $h((string)$rule['mark']) : '—' ?></td>
                    <td>
                        <span style="color:<?= $ok ? '#16a34a' : '#dc2626' ?>; font-weight:600;">
                            <?= $available ?>
                            <?= !$ok ? ' ⚠ (insufficient)' : '' ?>
                        </span>
                    </td>
                    <td class="actions">
                        <form method="POST" action="<?= url('/exams/rules/delete') ?>" style="display:inline;"
                              onsubmit="return confirm('Remove this rule?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="rule_id" value="<?= (int)$rule['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/qb_footer.php'; ?>
