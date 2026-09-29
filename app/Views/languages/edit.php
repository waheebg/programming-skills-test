<?php
$pageTitle = 'Edit Language';
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require dirname(__DIR__) . '/layouts/qb_header.php';
$old = $old ?? [];
$lang = $language ?? [];
?>

<div class="page-header">
    <h1>Edit Language: <?= $h($lang['name'] ?? '') ?></h1>
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
    <form method="POST" action="<?= url('/languages/update') ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $lang['id'] ?>">

        <div class="form-group">
            <label for="name">Language Name <span style="color:#dc2626">*</span></label>
            <input type="text" id="name" name="name" required maxlength="80"
                   value="<?= $h($old['name'] ?? $lang['name'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3"><?= $h($old['description'] ?? $lang['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <?php $curStatus = $old['status'] ?? $lang['status'] ?? 'active'; ?>
            <select id="status" name="status">
                <option value="active"   <?= $curStatus === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $curStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= url('/languages') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require dirname(__DIR__) . '/layouts/qb_footer.php'; ?>
