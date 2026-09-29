<?php
/**
 * Views/exams/preview.php — Hybrid selection preview.
 */

$pageTitle = 'Selection Preview — ' . ($exam['title'] ?? '');
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/../layouts/qb_header.php';
?>

<div class="page-header">
    <h1>Selection Preview: "<?= $h($exam['title']) ?>"</h1>
    <a href="<?= url('/exams/show?id=' . (int)$exam['id']) ?>" class="btn btn-secondary">← Back to Exam</a>
</div>

<?php if (!$preview['success'] && !empty($preview['errors'])): ?>
    <div class="alert alert-error">
        <strong>Insufficient Questions:</strong>
        <ul>
            <?php foreach ($preview['errors'] as $err): ?>
                <li><?= $h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="alert alert-warning">
    <strong>Note:</strong> This is a preview. Random picks change each time and are not saved.
    Total questions in this preview: <strong><?= (int)$preview['total'] ?></strong>
</div>

<!-- Manual Questions -->
<div class="card">
    <h2>Manual Questions (<?= count($preview['manual']) ?>)</h2>
    <?php if (empty($preview['manual'])): ?>
        <p style="color:#64748b;">None.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr><th>#</th><th>Question</th><th>Type</th><th>Difficulty</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($preview['manual'] as $q): ?>
                <tr>
                    <td><?= (int)$q['sort_order'] ?></td>
                    <td><?= $h(mb_substr($q['question_text'], 0, 100)) ?><?= mb_strlen($q['question_text']) > 100 ? '…' : '' ?></td>
                    <td><span class="badge badge-sc"><?= $h($q['question_type']) ?></span></td>
                    <td><span class="badge badge-<?= $h($q['difficulty']) ?>"><?= $h($q['difficulty']) ?></span></td>
                    <td><span class="badge badge-<?= $h($q['question_status']) ?>"><?= $h($q['question_status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Random Picks -->
<div class="card">
    <h2>Random Picks (<?= count($preview['random']) ?>)</h2>
    <?php if (empty($preview['random'])): ?>
        <p style="color:#64748b;">None (no rules configured or all insufficient).</p>
    <?php else: ?>
        <table>
            <thead>
            <tr><th>Rule #</th><th>Question</th><th>Type</th><th>Difficulty</th><th>Language</th><th>Category</th></tr>
            </thead>
            <tbody>
            <?php foreach ($preview['random'] as $q): ?>
                <tr>
                    <td><?= (int)$q['from_rule'] ?></td>
                    <td><?= $h(mb_substr($q['question_text'], 0, 100)) ?><?= mb_strlen($q['question_text']) > 100 ? '…' : '' ?></td>
                    <td><span class="badge badge-sc"><?= $h($q['question_type']) ?></span></td>
                    <td><span class="badge badge-<?= $h($q['difficulty']) ?>"><?= $h($q['difficulty']) ?></span></td>
                    <td><?= $q['language_name'] ? $h($q['language_name']) : '—' ?></td>
                    <td><?= $q['category_name'] ? $h($q['category_name']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/qb_footer.php'; ?>
