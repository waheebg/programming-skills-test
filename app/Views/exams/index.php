<?php
/**
 * Views/exams/index.php — Exam list with filters and pagination.
 *
 * Variables:
 *  $exams, $total, $page, $perPage, $filters, $languages,
 *  $canCreate, $canUpdate, $canDelete, $success, $error
 */

use App\Core\Csrf;

$pageTitle = 'Exams';
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/../layouts/qb_header.php';
?>

<div class="page-header">
    <h1>Exams</h1>
    <?php if ($canCreate): ?>
        <a href="<?= url('/exams/create') ?>" class="btn btn-primary">+ New Exam</a>
    <?php endif; ?>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $h($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= $h($error) ?></div>
<?php endif; ?>

<div class="card">
    <form method="GET" action="<?= url('/exams') ?>" class="filters">
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach (['draft', 'published', 'archived'] as $s): ?>
                <option value="<?= $h($s) ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($h($s)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="selection_mode">
            <option value="">All Modes</option>
            <?php foreach (['manual', 'random', 'hybrid'] as $m): ?>
                <option value="<?= $h($m) ?>" <?= ($filters['selection_mode'] ?? '') === $m ? 'selected' : '' ?>><?= ucfirst($h($m)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="language_id">
            <option value="">All Languages</option>
            <?php foreach ($languages as $lang): ?>
                <option value="<?= (int)$lang['id'] ?>" <?= ((int)($filters['language_id'] ?? 0)) === (int)$lang['id'] ? 'selected' : '' ?>>
                    <?= $h($lang['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="search" placeholder="Search title…" value="<?= $h($filters['search'] ?? '') ?>">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="<?= url('/exams') ?>" class="btn btn-secondary">Reset</a>
    </form>

    <?php if (empty($exams)): ?>
        <p style="color:#64748b; text-align:center; padding:2rem 0;">No exams found.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Title</th>
                <th>Mode</th>
                <th>Language</th>
                <th>Duration</th>
                <th>Questions</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($exams as $exam): ?>
                <tr>
                    <td><?= $h($exam['title']) ?></td>
                    <td><span class="badge badge-sc"><?= $h($exam['selection_mode']) ?></span></td>
                    <td><?= $exam['language_name'] ? $h($exam['language_name']) : '—' ?></td>
                    <td><?= (int)$exam['duration_minutes'] ?> min</td>
                    <td>
                        <?php
                        $ruleQ = (int)($exam['rule_question_count'] ?? 0);
                        $manQ  = (int)($exam['manual_count'] ?? 0);
                        $totalQ = $manQ + $ruleQ;
                        echo $totalQ . ' (' . $manQ . 'M + ' . $ruleQ . 'R)';
                        ?>
                    </td>
                    <td><span class="badge badge-<?= $h($exam['status']) ?>"><?= $h($exam['status']) ?></span></td>
                    <td class="actions">
                        <a href="<?= url('/exams/show?id=' . (int)$exam['id']) ?>" class="btn btn-sm btn-secondary">View</a>
                        <?php if (!empty($canTake) && $exam['status'] === 'published'): ?>
                            <form method="POST" action="<?= url('/attempts/start') ?>" style="display:inline;">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="exam_id" value="<?= (int)$exam['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">Take</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canUpdate): ?>
                            <a href="<?= url('/exams/edit?id=' . (int)$exam['id']) ?>" class="btn btn-sm btn-warning">Edit</a>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <form method="POST" action="<?= url('/exams/delete') ?>" style="display:inline;" onsubmit="return confirm('Delete this exam?');">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int)$exam['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages > 1):
            $queryBase = http_build_query(array_filter(array_merge($filters, ['page' => '__PAGE__'])));
        ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php $q = str_replace('page=__PAGE__', "page={$p}", $queryBase); ?>
                    <?php if ($p === $page): ?>
                        <span class="current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="<?= url('/exams?' . $q) ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/qb_footer.php'; ?>
