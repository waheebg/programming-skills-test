<?php
$pageTitle = 'Edit Category';
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require dirname(__DIR__) . '/layouts/qb_header.php';
$old = $old ?? [];
$cat = $category ?? [];
?>

<div class="page-header">
    <h1>Edit Category: <?= $h($cat['name'] ?? '') ?></h1>
    <a href="<?= url('/categories') ?>" class="btn btn-secondary">← Back to list</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>Please fix the following errors:</strong>
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= $h($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= url('/categories/update') ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">

        <div class="form-group">
            <label for="name">Category Name <span style="color:#dc2626">*</span></label>
            <input type="text" id="name" name="name" required maxlength="100"
                   value="<?= $h($old['name'] ?? $cat['name'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="2"><?= $h($old['description'] ?? $cat['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="parent_id">Parent Category (optional)</label>
            <?php $curParent = $old['parent_id'] ?? $cat['parent_id'] ?? ''; ?>
            <select id="parent_id" name="parent_id">
                <option value="">— None (top-level) —</option>
                <?php foreach ($parents as $parent): ?>
                    <option value="<?= (int) $parent['id'] ?>"
                        <?= (string) $curParent === (string) $parent['id'] ? 'selected' : '' ?>>
                        <?= $h($parent['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <?php $curStatus = $old['status'] ?? $cat['status'] ?? 'active'; ?>
            <select id="status" name="status">
                <option value="active"   <?= $curStatus === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $curStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= url('/categories') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require dirname(__DIR__) . '/layouts/qb_footer.php'; ?>
