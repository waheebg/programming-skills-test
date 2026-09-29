<?php
/**
 * Views/exams/edit.php — Edit an existing exam.
 */

use App\Core\Csrf;

$pageTitle = 'Edit Exam';
$h  = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$old = $old ?? [];
// Use old input from flash or fall back to exam data
$val = fn(string $key, $default = '') => $h((string)($old[$key] ?? $exam[$key] ?? $default));
require __DIR__ . '/../layouts/qb_header.php';
?>

<div class="page-header">
    <h1>Edit Exam</h1>
    <a href="<?= url('/exams/show?id=' . (int)$exam['id']) ?>" class="btn btn-secondary">← Back</a>
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
    <form method="POST" action="<?= url('/exams/update') ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int)$exam['id'] ?>">

        <div class="form-group">
            <label for="title">Title <span style="color:#dc2626">*</span></label>
            <input type="text" id="title" name="title" maxlength="200"
                   value="<?= $val('title') ?>" required>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description"><?= $val('description') ?></textarea>
        </div>

        <div class="form-group">
            <label for="programming_language_id">Programming Language</label>
            <select id="programming_language_id" name="programming_language_id">
                <option value="">— Any Language —</option>
                <?php
                $selectedLangId = (int)($old['programming_language_id'] ?? $exam['programming_language_id'] ?? 0);
                foreach ($languages as $lang): ?>
                    <option value="<?= (int)$lang['id'] ?>"
                        <?= $selectedLangId === (int)$lang['id'] ? 'selected' : '' ?>>
                        <?= $h($lang['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="selection_mode">Selection Mode <span style="color:#dc2626">*</span></label>
            <select id="selection_mode" name="selection_mode" required>
                <?php
                $selMode = $old['selection_mode'] ?? $exam['selection_mode'] ?? 'manual';
                foreach (['manual' => 'Manual', 'random' => 'Random', 'hybrid' => 'Hybrid (Manual + Random)'] as $v => $label): ?>
                    <option value="<?= $h($v) ?>" <?= $selMode === $v ? 'selected' : '' ?>>
                        <?= $h($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
                <label for="duration_minutes">Duration (minutes) <span style="color:#dc2626">*</span></label>
                <input type="number" id="duration_minutes" name="duration_minutes"
                       min="1" max="32767"
                       value="<?= (int)($old['duration_minutes'] ?? $exam['duration_minutes'] ?? 60) ?>" required>
            </div>
            <div class="form-group">
                <label for="pass_percentage">Pass Percentage (%) <span style="color:#dc2626">*</span></label>
                <input type="number" id="pass_percentage" name="pass_percentage"
                       min="0" max="100" step="0.01"
                       value="<?= $val('pass_percentage', '50.00') ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="max_attempts">Max Attempts (leave blank for unlimited)</label>
            <input type="number" id="max_attempts" name="max_attempts" min="1"
                   value="<?= $h((string)($old['max_attempts'] ?? $exam['max_attempts'] ?? '')) ?>">
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
                <label for="starts_at">Starts At (optional)</label>
                <input type="datetime-local" id="starts_at" name="starts_at"
                       value="<?= $h(str_replace(' ', 'T', ($old['starts_at'] ?? $exam['starts_at'] ?? ''))) ?>">
            </div>
            <div class="form-group">
                <label for="ends_at">Ends At (optional)</label>
                <input type="datetime-local" id="ends_at" name="ends_at"
                       value="<?= $h(str_replace(' ', 'T', ($old['ends_at'] ?? $exam['ends_at'] ?? ''))) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Options</label>
            <div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.3rem;">
                <?php
                $chk = function(string $field) use ($old, $exam): bool {
                    if (isset($old[$field])) return !empty($old[$field]);
                    return !empty($exam[$field]);
                };
                ?>
                <label style="font-weight:normal; display:flex; gap:0.5rem; align-items:center;">
                    <input type="checkbox" name="randomize_questions" value="1" <?= $chk('randomize_questions') ? 'checked' : '' ?>>
                    Randomize question order
                </label>
                <label style="font-weight:normal; display:flex; gap:0.5rem; align-items:center;">
                    <input type="checkbox" name="randomize_options" value="1" <?= $chk('randomize_options') ? 'checked' : '' ?>>
                    Randomize answer options
                </label>
                <label style="font-weight:normal; display:flex; gap:0.5rem; align-items:center;">
                    <input type="checkbox" name="show_result_immediately" value="1" <?= $chk('show_result_immediately') ? 'checked' : '' ?>>
                    Show result immediately
                </label>
                <label style="font-weight:normal; display:flex; gap:0.5rem; align-items:center;">
                    <input type="checkbox" name="show_correct_answers" value="1" <?= $chk('show_correct_answers') ? 'checked' : '' ?>>
                    Show correct answers
                </label>
            </div>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php
                $currentStatus = $old['status'] ?? $exam['status'] ?? 'draft';
                foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $v => $label): ?>
                    <option value="<?= $h($v) ?>" <?= $currentStatus === $v ? 'selected' : '' ?>>
                        <?= $h($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= url('/exams/show?id=' . (int)$exam['id']) ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layouts/qb_footer.php'; ?>
