<?php
/**
 * Views/exams/questions.php — Question picker for manual selection.
 */

use App\Core\Csrf;

$pageTitle = 'Add Questions — ' . ($exam['title'] ?? '');
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/../layouts/qb_header.php';
?>

<div class="page-header">
    <h1>Add Questions to "<?= $h($exam['title']) ?>"</h1>
    <a href="<?= url('/exams/show?id=' . (int)$exam['id']) ?>" class="btn btn-secondary">← Back to Exam</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $h($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= $h($error) ?></div>
<?php endif; ?>

<div class="card">
    <form method="GET" action="<?= url('/exams/questions') ?>" style="margin-bottom:1.5rem;">
        <input type="hidden" name="id" value="<?= (int)$exam['id'] ?>">
        <div class="filters">
            <select name="language_id">
                <option value="">All Languages</option>
                <?php foreach ($languages as $lang): ?>
                    <option value="<?= (int)$lang['id'] ?>"
                        <?= ((int)($filters['language_id'] ?? 0)) === (int)$lang['id'] ? 'selected' : '' ?>>
                        <?= $h($lang['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="category_id">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>"
                        <?= ((int)($filters['category_id'] ?? 0)) === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= $h($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="difficulty">
                <option value="">All Difficulties</option>
                <?php foreach (['easy', 'medium', 'hard'] as $d): ?>
                    <option value="<?= $h($d) ?>" <?= ($filters['difficulty'] ?? '') === $d ? 'selected' : '' ?>>
                        <?= ucfirst($h($d)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="type">
                <option value="">All Types</option>
                <option value="single_choice" <?= ($filters['type'] ?? '') === 'single_choice' ? 'selected' : '' ?>>Single Choice</option>
                <option value="true_false" <?= ($filters['type'] ?? '') === 'true_false' ? 'selected' : '' ?>>True/False</option>
            </select>
            <input type="text" name="search" placeholder="Search…" value="<?= $h($filters['search'] ?? '') ?>">
            <button type="submit" class="btn btn-secondary">Filter</button>
            <a href="<?= url('/exams/questions?id=' . (int)$exam['id']) ?>" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <?php if (empty($questions)): ?>
        <p style="color:#64748b; text-align:center; padding:2rem 0;">No published questions found matching these filters.</p>
    <?php else: ?>
        <form method="POST" action="<?= url('/exams/questions/add-bulk') ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="exam_id" value="<?= (int)$exam['id'] ?>">

            <div style="margin-bottom:0.75rem; display:flex; justify-content:space-between; align-items:center;">
                <span style="color:#64748b; font-size:0.875rem;"><?= $total ?> question(s) found</span>
                <button type="submit" class="btn btn-primary">Add Selected Questions</button>
            </div>
            <table>
                <thead>
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="chk-all" title="Select all"></th>
                    <th>Question</th>
                    <th>Type</th>
                    <th>Difficulty</th>
                    <th>Language</th>
                    <th>Category</th>
                    <th>Mark</th>
                    <th>In Exam</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($questions as $q): ?>
                    <?php $inExam = in_array((int)$q['id'], $selectedIds, true); ?>
                    <tr <?= $inExam ? 'style="background:#f0fdf4;"' : '' ?>>
                        <td>
                            <?php if (!$inExam): ?>
                                <input type="checkbox" name="question_ids[]" value="<?= (int)$q['id'] ?>" class="row-chk">
                            <?php else: ?>
                                <span title="Already in exam" style="color:#16a34a;">✓</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $h(mb_substr($q['question_text'], 0, 80)) ?><?= mb_strlen($q['question_text']) > 80 ? '…' : '' ?></td>
                        <td><span class="badge badge-sc"><?= $h($q['question_type']) ?></span></td>
                        <td><span class="badge badge-<?= $h($q['difficulty']) ?>"><?= $h($q['difficulty']) ?></span></td>
                        <td><?= $q['language_name'] ? $h($q['language_name']) : '—' ?></td>
                        <td><?= $q['category_name'] ? $h($q['category_name']) : '—' ?></td>
                        <td><?= $h($q['default_mark']) ?></td>
                        <td><?= $inExam ? '<span style="color:#16a34a;">Yes</span>' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top:0.75rem; display:flex; justify-content:flex-end;">
                <button type="submit" class="btn btn-primary">Add Selected Questions</button>
            </div>
        <?php endif; ?>
    </form>

    <?php
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($totalPages > 1):
        $queryBase = http_build_query(array_filter(array_merge($filters, ['id' => $exam['id'], 'page' => '__PAGE__'])));
    ?>
        <div class="pagination" style="margin-top:1rem;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php $queryStr = str_replace('page=__PAGE__', "page={$p}", $queryBase); ?>
                <?php if ($p === $page): ?>
                    <span class="current"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= url('/exams/questions?' . $queryStr) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('chk-all').addEventListener('change', function() {
    document.querySelectorAll('.row-chk').forEach(c => c.checked = this.checked);
});
</script>

<?php require __DIR__ . '/../layouts/qb_footer.php'; ?>
