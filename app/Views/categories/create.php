<?php
$pageTitle = 'Create Category';
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require dirname(__DIR__) . '/layouts/qb_header.php';
$old = $old ?? [];
?>

<div class="page-header">
    <h1>New Category</h1>
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
    <form method="POST" action="<?= url('/categories/store') ?>">
        <?= \App\Core\Csrf::field() ?>

        <div class="form-group">
            <label for="name">Category Name <span style="color:#dc2626">*</span></label>
            <input type="text" id="name" name="name" required maxlength="100"
                   value="<?= $h($old['name'] ?? '') ?>" placeholder="e.g. Arrays and Strings">
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="2"
                      placeholder="Optional description"><?= $h($old['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="parent_id">Parent Category (optional)</label>
            <select id="parent_id" name="parent_id">
                <option value="">— None (top-level) —</option>
                <?php foreach ($parents as $parent): ?>
                    <option value="<?= (int) $parent['id'] ?>"
                        <?= ((string) ($old['parent_id'] ?? '')) === (string) $parent['id'] ? 'selected' : '' ?>>
                        <?= $h($parent['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="active"   <?= ($old['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Create Category</button>
            <a href="<?= url('/categories') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require dirname(__DIR__) . '/layouts/qb_footer.php'; ?>
