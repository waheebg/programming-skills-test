<?php
$pageTitle = 'View Question';
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require dirname(__DIR__) . '/layouts/qb_header.php';
$q = $question ?? [];
?>

<div class="page-header">
    <h1>Question #<?= (int) $q['id'] ?></h1>
    <div class="actions">
        <?php if ($canUpdate && ($q['status'] ?? '') !== 'archived'): ?>
            <a href="<?= url('/questions/edit?id=' . (int) $q['id']) ?>" class="btn btn-warning">Edit</a>
        <?php endif; ?>
        <?php if ($canDelete && ($q['status'] ?? '') !== 'archived'): ?>
            <form method="POST" action="<?= url('/questions/delete') ?>"
                  onsubmit="return confirm('Delete or archive this question?');" style="margin:0;">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        <?php endif; ?>
        <a href="<?= url('/questions') ?>" class="btn btn-secondary">← Back</a>
    </div>
</div>

<div class="card">
    <div style="margin-bottom:1rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
        <span class="badge badge-<?= $h($q['status'] ?? '') ?>"><?= $h($q['status'] ?? '') ?></span>
        <span class="badge badge-<?= $h($q['difficulty'] ?? '') ?>"><?= $h($q['difficulty'] ?? '') ?></span>
        <?php $typeClass = ($q['question_type'] ?? '') === 'single_choice' ? 'sc' : 'tf'; ?>
        <span class="badge badge-<?= $typeClass ?>">
            <?= $h($q['question_type'] === 'single_choice' ? 'Single Choice' : 'True / False') ?>
        </span>
        <span class="badge" style="background:#e0f2fe;color:#0369a1;">
            <?= $h($q['language_name'] ?? '—') ?>
        </span>
        <span class="badge" style="background:#ede9fe;color:#7c3aed;">
            <?= $h($q['category_name'] ?? '—') ?>
        </span>
    </div>

    <p style="font-size:1.05rem; line-height:1.6; margin:0 0 1rem;"><?= $h($q['question_text'] ?? '') ?></p>

    <p style="font-size:0.85rem; color:#64748b;">
        Default mark: <strong><?= number_format((float) ($q['default_mark'] ?? 1), 2) ?></strong>
        &nbsp;·&nbsp;
        Created by: <strong><?= $h($q['created_by_username'] ?? '—') ?></strong>
    </p>

    <?php if (!empty($q['explanation'])): ?>
        <div style="background:#f8fafc; border-left:3px solid #0284c7; padding: 0.75rem 1rem; border-radius:4px; margin-top:1rem;">
            <p style="font-size:0.85rem; font-weight:600; color:#0369a1; margin:0 0 0.35rem;">Explanation</p>
            <p style="margin:0; font-size:0.9rem;"><?= $h($q['explanation']) ?></p>
        </div>
    <?php endif; ?>
</div>

<h2>Options</h2>
<div class="card" style="padding:0; overflow:hidden;">
<?php if (empty($options)): ?>
    <p style="padding:1rem; color:#94a3b8;">No options defined.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Option Text</th>
                <th>Correct?</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($options as $opt): ?>
            <tr>
                <td><?= (int) $opt['sort_order'] ?></td>
                <td><?= $h($opt['option_text']) ?></td>
                <td>
                    <?php if ($opt['is_correct']): ?>
                        <span class="correct-badge">✓ Correct</span>
                    <?php else: ?>
                        <span class="wrong-badge">✗</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/layouts/qb_footer.php'; ?>
