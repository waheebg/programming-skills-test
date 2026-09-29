<?php
$pageTitle = 'Categories';
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require dirname(__DIR__) . '/layouts/qb_header.php';
?>

<div class="page-header">
    <h1>Categories</h1>
    <?php if ($canManage): ?>
        <a href="<?= url('/categories/create') ?>" class="btn btn-primary">+ New Category</a>
    <?php endif; ?>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= $h($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= $h($error) ?></div>
<?php endif; ?>

<div class="card" style="padding:0; overflow:hidden;">
<?php if (empty($categories)): ?>
    <p style="padding:1.5rem; color:#64748b;">No categories found.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Parent</th>
                <th>Slug</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $cat): ?>
            <tr>
                <td><?= (int) $cat['id'] ?></td>
                <td>
                    <?php if ($cat['parent_id']): ?>
                        <span style="color:#94a3b8; margin-right:4px;">└</span>
                    <?php endif; ?>
                    <strong><?= $h($cat['name']) ?></strong>
                </td>
                <td><?= $h($cat['parent_name'] ?? '—') ?></td>
                <td><code><?= $h($cat['slug']) ?></code></td>
                <td>
                    <span class="badge badge-<?= $h($cat['status']) ?>">
                        <?= $h($cat['status']) ?>
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <?php if ($canManage): ?>
                            <a href="<?= url('/categories/edit?id=' . (int) $cat['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                            <form method="POST" action="<?= url('/categories/delete') ?>"
                                  onsubmit="return confirm('Delete this category?');" style="margin:0;">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
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
