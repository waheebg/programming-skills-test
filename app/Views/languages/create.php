<?php
$pageTitle = 'Create Language';
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require dirname(__DIR__) . '/layouts/qb_header.php';
$old = $old ?? [];
?>

<div class="page-header">
    <h1>New Programming Language</h1>
    <a href="<?= url('/languages') ?>" class="btn btn-secondary">← Back to list</a>
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
    <form method="POST" action="<?= url('/languages/store') ?>">
        <?= \App\Core\Csrf::field() ?>

        <div class="form-group">
            <label for="name">Language Name <span style="color:#dc2626">*</span></label>
            <input type="text" id="name" name="name" required maxlength="80"
                   value="<?= $h($old['name'] ?? '') ?>" placeholder="e.g. Python">
            <p class="form-hint">Max 80 characters. Must be unique.</p>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3"
                      placeholder="Optional description"><?= $h($old['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="active"   <?= ($old['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Create Language</button>
            <a href="<?= url('/languages') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require dirname(__DIR__) . '/layouts/qb_footer.php'; ?>
