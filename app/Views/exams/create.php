<?php
/**
 * Views/exams/create.php — Create a new exam.
 */

use App\Core\Csrf;

$pageTitle = 'Create Exam';
$h  = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$old = $old ?? [];
require __DIR__ . '/../layouts/qb_header.php';
?>

<div class="page-header">
    <h1>Create Exam</h1>
    <a href="<?= url('/exams') ?>" class="btn btn-secondary">← Back to Exams</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>Please fix the following errors:</strong>
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= $h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= url('/exams/store') ?>">
        <?= Csrf::field() ?>

        <div class="form-group">
            <label for="title">Title <span style="color:#dc2626">*</span></label>
            <input type="text" id="title" name="title" maxlength="200"
                   value="<?= $h($old['title'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description"><?= $h($old['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="programming_language_id">Programming Language</label>
            <select id="programming_language_id" name="programming_language_id">
                <option value="">— Any Language —</option>
                <?php foreach ($languages as $lang): ?>
                    <option value="<?= (int)$lang['id'] ?>"
                        <?= ((int)($old['programming_language_id'] ?? 0)) === (int)$lang['id'] ? 'selected' : '' ?>>
                        <?= $h($lang['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="selection_mode">Selection Mode <span style="color:#dc2626">*</span></label>
            <select id="selection_mode" name="selection_mode" required>
                <?php foreach (['manual' => 'Manual', 'random' => 'Random', 'hybrid' => 'Hybrid (Manual + Random)'] as $val => $label): ?>
                    <option value="<?= $h($val) ?>"
                        <?= ($old['selection_mode'] ?? 'manual') === $val ? 'selected' : '' ?>>
                        <?= $h($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
                <label for="duration_minutes">Duration (minutes) <span style="color:#dc2626">*</span></label>
                <input type="number" id="duration_minutes" name="duration_minutes"
                       min="1" max="32767" value="<?= (int)($old['duration_minutes'] ?? 60) ?>" required>
            </div>
            <div class="form-group">
                <label for="pass_percentage">Pass Percentage (%) <span style="color:#dc2626">*</span></label>
                <input type="number" id="pass_percentage" name="pass_percentage"
                       min="0" max="100" step="0.01"
                       value="<?= $h($old['pass_percentage'] ?? '50.00') ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="max_attempts">Max Attempts (leave blank for unlimited)</label>
            <input type="number" id="max_attempts" name="max_attempts" min="1"
                   value="<?= $h($old['max_attempts'] ?? '') ?>">
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
                <label for="starts_at">Starts At (optional)</label>
                <input type="datetime-local" id="starts_at" name="starts_at"
                       value="<?= $h($old['starts_at'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="ends_at">Ends At (optional)</label>
                <input type="datetime-local" id="ends_at" name="ends_at"
                       value="<?= $h($old['ends_at'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Options</label>
            <div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.3rem;">
                <label style="font-weight:normal; display:flex; gap:0.5rem; align-items:center;">
                    <input type="checkbox" name="randomize_questions" value="1"
                        <?= !empty($old['randomize_questions']) ? 'checked' : '' ?>>
                    Randomize question order for each attempt
                </label>
                <label style="font-weight:normal; display:flex; gap:0.5rem; align-items:center;">
                    <input type="checkbox" name="randomize_options" value="1"
                        <?= !empty($old['randomize_options']) ? 'checked' : '' ?>>
                    Randomize answer options order
                </label>
                <label style="font-weight:normal; display:flex; gap:0.5rem; align-items:center;">
                    <input type="checkbox" name="show_result_immediately" value="1"
                        <?= ($old['show_result_immediately'] ?? '1') ? 'checked' : '' ?>>
                    Show result immediately after submission
                </label>
                <label style="font-weight:normal; display:flex; gap:0.5rem; align-items:center;">
                    <input type="checkbox" name="show_correct_answers" value="1"
                        <?= !empty($old['show_correct_answers']) ? 'checked' : '' ?>>
                    Show correct answers after submission
                </label>
            </div>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $val => $label): ?>
                    <option value="<?= $h($val) ?>"
                        <?= ($old['status'] ?? 'draft') === $val ? 'selected' : '' ?>>
                        <?= $h($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Create Exam</button>
            <a href="<?= url('/exams') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layouts/qb_footer.php'; ?>
