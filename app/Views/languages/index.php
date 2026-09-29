<?php
$pageTitle = 'Programming Languages';
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require dirname(__DIR__) . '/layouts/qb_header.php';
?>

<div class="page-header">
    <h1>Programming Languages</h1>
    <?php if ($canManage): ?>
        <a href="<?= url('/languages/create') ?>" class="btn btn-primary">+ New Language</a>
    <?php endif; ?>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= $h($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= $h($error) ?></div>
<?php endif; ?>

<div class="card" style="padding:0; overflow:hidden;">
<?php if (empty($languages)): ?>
    <p style="padding:1.5rem; color:#64748b;">No programming languages found.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Description</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($languages as $lang): ?>
            <tr>
                <td><?= (int) $lang['id'] ?></td>
                <td><strong><?= $h($lang['name']) ?></strong></td>
                <td><code><?= $h($lang['slug']) ?></code></td>
                <td><?= $h(mb_strimwidth($lang['description'] ?? '', 0, 80, '…')) ?></td>
                <td>
                    <span class="badge badge-<?= $h($lang['status']) ?>">
                        <?= $h($lang['status']) ?>
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <?php if ($canManage): ?>
                            <a href="<?= url('/languages/edit?id=' . (int)$lang['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                            <form method="POST" action="<?= url('/languages/delete') ?>" onsubmit="return confirm('Delete this language?');" style="margin:0;">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int) $lang['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        <?php else: ?>
                            <span style="color:#94a3b8; font-size:0.8rem;">View only</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/layouts/qb_footer.php'; ?>
