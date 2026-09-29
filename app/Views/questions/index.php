<?php
$pageTitle = 'Questions';
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require dirname(__DIR__) . '/layouts/qb_header.php';

$totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
$qStr = fn(array $extra): string => http_build_query(array_merge($filters, $extra));
?>

<div class="page-header">
    <h1>Questions <small style="font-weight:400; font-size:1rem; color:#64748b;">(<?= (int) $total ?> total)</small></h1>
    <?php if ($canCreate): ?>
        <a href="<?= url('/questions/create') ?>" class="btn btn-primary">+ New Question</a>
    <?php endif; ?>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= $h($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= $h($error) ?></div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" action="<?= url('/questions') ?>" class="card" style="padding: 1rem;">
    <div class="filters">
        <input type="text" name="search" placeholder="Search question text…"
               value="<?= $h($filters['search'] ?? '') ?>" style="min-width:200px;">

        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach (['draft','published','archived'] as $s): ?>
                <option value="<?= $h($s) ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="difficulty">
            <option value="">All Difficulties</option>
            <?php foreach (['easy','medium','hard'] as $d): ?>
                <option value="<?= $h($d) ?>" <?= ($filters['difficulty'] ?? '') === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="type">
            <option value="">All Types</option>
            <option value="single_choice" <?= ($filters['type'] ?? '') === 'single_choice' ? 'selected' : '' ?>>Single Choice</option>
            <option value="true_false"    <?= ($filters['type'] ?? '') === 'true_false'    ? 'selected' : '' ?>>True / False</option>
        </select>

        <select name="language_id">
            <option value="">All Languages</option>
            <?php foreach ($languages as $lang): ?>
                <option value="<?= (int) $lang['id'] ?>"
                    <?= ((string) ($filters['language_id'] ?? '')) === (string) $lang['id'] ? 'selected' : '' ?>>
                    <?= $h($lang['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="category_id">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>"
                    <?= ((string) ($filters['category_id'] ?? '')) === (string) $cat['id'] ? 'selected' : '' ?>>
                    <?= $h($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="<?= url('/questions') ?>" class="btn btn-secondary">Reset</a>
    </div>
</form>

<div class="card" style="padding:0; overflow:hidden;">
<?php if (empty($questions)): ?>
    <p style="padding:1.5rem; color:#64748b;">No questions match your filters.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Question</th>
                <th>Type</th>
                <th>Language</th>
                <th>Category</th>
                <th>Difficulty</th>
                <th>Status</th>
                <th>Marks</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($questions as $q): ?>
            <tr>
                <td><?= (int) $q['id'] ?></td>
                <td>
                    <a href="<?= url('/questions/show?id=' . (int) $q['id']) ?>" style="color:#0284c7; text-decoration:none;">
                        <?= $h(mb_strimwidth($q['question_text'], 0, 80, '…')) ?>
                    </a>
                </td>
                <td>
                    <?php $typeClass = $q['question_type'] === 'single_choice' ? 'sc' : 'tf'; ?>
                    <span class="badge badge-<?= $typeClass ?>">
                        <?= $q['question_type'] === 'single_choice' ? 'Single' : 'T/F' ?>
                    </span>
                </td>
                <td><?= $h($q['language_name'] ?? '—') ?></td>
                <td><?= $h($q['category_name'] ?? '—') ?></td>
                <td><span class="badge badge-<?= $h($q['difficulty']) ?>"><?= $h($q['difficulty']) ?></span></td>
                <td><span class="badge badge-<?= $h($q['status']) ?>"><?= $h($q['status']) ?></span></td>
                <td><?= number_format((float) $q['default_mark'], 2) ?></td>
                <td>
                    <div class="actions">
                        <a href="<?= url('/questions/show?id=' . (int) $q['id']) ?>" class="btn btn-sm btn-secondary">View</a>
                        <?php if ($canUpdate): ?>
                            <a href="<?= url('/questions/edit?id=' . (int) $q['id']) ?>" class="btn btn-sm btn-warning">Edit</a>
                        <?php endif; ?>
                        <?php if ($canDelete && $q['status'] !== 'archived'): ?>
                            <form method="POST" action="<?= url('/questions/delete') ?>"
                                  onsubmit="return confirm('Delete/archive this question?');" style="margin:0;">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
        <div style="padding: 0.75rem 1rem; border-top: 1px solid #f1f5f9;">
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= url('/questions?' . $qStr(['page' => $page - 1])) ?>">‹ Prev</a>
                <?php endif; ?>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if ($p === $page): ?>
                        <span class="current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="<?= url('/questions?' . $qStr(['page' => $p])) ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= url('/questions?' . $qStr(['page' => $page + 1])) ?>">Next ›</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/layouts/qb_footer.php'; ?>
