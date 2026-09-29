<?php
$pageTitle = 'Edit Question';
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require dirname(__DIR__) . '/layouts/qb_header.php';
$old = $old ?? [];
$q   = $question ?? [];

// Prefer old POST data when repopulating after validation failure
$qType = $old['question_type'] ?? $q['question_type'] ?? 'single_choice';
?>

<div class="page-header">
    <h1>Edit Question #<?= (int) $q['id'] ?></h1>
    <a href="<?= url('/questions/show?id=' . (int) $q['id']) ?>" class="btn btn-secondary">← View</a>
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
    <form method="POST" action="<?= url('/questions/update') ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
                <label for="programming_language_id">Programming Language <span style="color:#dc2626">*</span></label>
                <?php $curLang = $old['programming_language_id'] ?? $q['programming_language_id'] ?? ''; ?>
                <select id="programming_language_id" name="programming_language_id" required>
                    <option value="">— Select language —</option>
                    <?php foreach ($languages as $lang): ?>
                        <option value="<?= (int) $lang['id'] ?>"
                            <?= (string) $curLang === (string) $lang['id'] ? 'selected' : '' ?>>
                            <?= $h($lang['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="category_id">Category <span style="color:#dc2626">*</span></label>
                <?php $curCat = $old['category_id'] ?? $q['category_id'] ?? ''; ?>
                <select id="category_id" name="category_id" required>
                    <option value="">— Select category —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"
                            <?= (string) $curCat === (string) $cat['id'] ? 'selected' : '' ?>>
                            <?= $h($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="question_type">Question Type <span style="color:#dc2626">*</span></label>
                <select id="question_type" name="question_type" required
                        onchange="toggleOptionsSection(this.value)">
                    <option value="single_choice" <?= $qType === 'single_choice' ? 'selected' : '' ?>>Single Choice</option>
                    <option value="true_false"    <?= $qType === 'true_false'    ? 'selected' : '' ?>>True / False</option>
                </select>
            </div>

            <div class="form-group">
                <label for="difficulty">Difficulty <span style="color:#dc2626">*</span></label>
                <?php $curDiff = $old['difficulty'] ?? $q['difficulty'] ?? 'medium'; ?>
                <select id="difficulty" name="difficulty" required>
                    <?php foreach (['easy','medium','hard'] as $d): ?>
                        <option value="<?= $d ?>" <?= $curDiff === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="default_mark">Default Mark <span style="color:#dc2626">*</span></label>
                <input type="number" id="default_mark" name="default_mark" step="0.01" min="0.01" max="99999.99" required
                       value="<?= $h($old['default_mark'] ?? number_format((float)($q['default_mark'] ?? 1), 2)) ?>">
            </div>

            <div class="form-group">
                <label for="status">Status <span style="color:#dc2626">*</span></label>
                <?php $curStatus = $old['status'] ?? $q['status'] ?? 'draft'; ?>
                <select id="status" name="status" required>
                    <?php foreach (['draft','published','archived'] as $s): ?>
                        <option value="<?= $s ?>" <?= $curStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="question_text">Question Text <span style="color:#dc2626">*</span></label>
            <textarea id="question_text" name="question_text" rows="4" required><?= $h($old['question_text'] ?? $q['question_text'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="explanation">Explanation (optional)</label>
            <textarea id="explanation" name="explanation" rows="2"><?= $h($old['explanation'] ?? $q['explanation'] ?? '') ?></textarea>
        </div>

        <!-- Single-choice options -->
        <div id="section-single" style="display:none;">
            <h2 style="border-top:1px solid #f1f5f9; padding-top:1rem; margin-top:1.25rem;">
                Answer Options
                <span style="font-size:0.8rem; font-weight:400; color:#64748b;">— check exactly one correct answer</span>
            </h2>
            <table class="options-table" style="margin-bottom:0.5rem;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Option Text</th>
                        <th style="width:100px; text-align:center;">Correct?</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody id="options-body">
                    <?php
                    // Use old POST options if available, else current DB options
                    if (!empty($old['options'])) {
                        $renderOptions = $old['options'];
                    } else {
                        $renderOptions = array_map(fn($o) => [
                            'option_text' => $o['option_text'],
                            'is_correct'  => (bool) $o['is_correct'],
                            'sort_order'  => $o['sort_order'],
                        ], $options);
                    }
                    // Ensure at least 2 rows
                    while (count($renderOptions) < 2) {
                        $renderOptions[] = ['option_text'=>'','is_correct'=>false,'sort_order'=>count($renderOptions)+1];
                    }
                    foreach ($renderOptions as $i => $opt): ?>
                    <tr id="opt-row-<?= $i ?>">
                        <td><?= $i + 1 ?></td>
                        <td>
                            <input type="text" name="options[<?= $i ?>][option_text]"
                                   value="<?= $h($opt['option_text'] ?? '') ?>"
                                   placeholder="Option text" style="width:100%;">
                            <input type="hidden" name="options[<?= $i ?>][sort_order]" value="<?= $i + 1 ?>">
                        </td>
                        <td style="text-align:center;">
                            <input type="radio" name="correct_option" value="<?= $i ?>"
                                   onchange="markCorrect(this.value)"
                                   <?= !empty($opt['is_correct']) ? 'checked' : '' ?>>
                            <input type="hidden" name="options[<?= $i ?>][is_correct]"
                                   id="is_correct_<?= $i ?>" value="<?= !empty($opt['is_correct']) ? '1' : '0' ?>">
                        </td>
                        <td>
                            <?php if ($i >= 2): ?>
                            <button type="button" onclick="removeOptionRow(<?= $i ?>)"
                                    class="btn btn-sm btn-danger">✕</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" onclick="addOptionRow()" class="btn btn-sm btn-secondary">+ Add Option</button>
        </div>

        <!-- True/False section -->
        <div id="section-tf" style="display:none;">
            <h2 style="border-top:1px solid #f1f5f9; padding-top:1rem; margin-top:1.25rem;">Correct Answer</h2>
            <?php
            // Determine current correct TF answer
            $tfCorrect = $old['tf_correct'] ?? 'true';
            if (empty($old) && $qType === 'true_false' && !empty($options)) {
                foreach ($options as $opt) {
                    if ($opt['is_correct']) {
                        $tfCorrect = strtolower(trim($opt['option_text']));
                        break;
                    }
                }
            }
            ?>
            <div class="radio-group">
                <label>
                    <input type="radio" name="tf_correct" value="true"
                           <?= $tfCorrect === 'true' ? 'checked' : '' ?>>
                    True
                </label>
                <label>
                    <input type="radio" name="tf_correct" value="false"
                           <?= $tfCorrect === 'false' ? 'checked' : '' ?>>
                    False
                </label>
            </div>
        </div>

        <div class="actions" style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= url('/questions/show?id=' . (int) $q['id']) ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
let optionCount = <?= count($renderOptions ?? []) ?>;

function toggleOptionsSection(type) {
    document.getElementById('section-single').style.display = type === 'single_choice' ? '' : 'none';
    document.getElementById('section-tf').style.display     = type === 'true_false'    ? '' : 'none';
}

function markCorrect(index) {
    document.querySelectorAll('[id^="is_correct_"]').forEach(r => { r.value = '0'; });
    const el = document.getElementById('is_correct_' + index);
    if (el) el.value = '1';
}

function addOptionRow() {
    const i = optionCount++;
    const tbody = document.getElementById('options-body');
    const tr = document.createElement('tr');
    tr.id = 'opt-row-' + i;
    tr.innerHTML = `
        <td>${i + 1}</td>
        <td>
            <input type="text" name="options[${i}][option_text]" placeholder="Option text" style="width:100%;">
            <input type="hidden" name="options[${i}][sort_order]" value="${i + 1}">
        </td>
        <td style="text-align:center;">
            <input type="radio" name="correct_option" value="${i}" onchange="markCorrect(this.value)">
            <input type="hidden" name="options[${i}][is_correct]" id="is_correct_${i}" value="0">
        </td>
        <td>
            <button type="button" onclick="removeOptionRow(${i})" class="btn btn-sm btn-danger">✕</button>
        </td>`;
    tbody.appendChild(tr);
}

function removeOptionRow(i) {
    const row = document.getElementById('opt-row-' + i);
    if (row) row.remove();
}

(function() {
    toggleOptionsSection(document.getElementById('question_type').value);
})();
</script>

<?php require dirname(__DIR__) . '/layouts/qb_footer.php'; ?>
