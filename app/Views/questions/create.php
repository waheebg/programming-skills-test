<?php
$pageTitle = 'Create Question';
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require dirname(__DIR__) . '/layouts/qb_header.php';
$old = $old ?? [];
?>

<div class="page-header">
    <h1>New Question</h1>
    <a href="<?= url('/questions') ?>" class="btn btn-secondary">← Back to list</a>
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
    <form method="POST" action="<?= url('/questions/store') ?>" id="question-form">
        <?= \App\Core\Csrf::field() ?>

        <!-- Core fields -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
                <label for="programming_language_id">Programming Language <span style="color:#dc2626">*</span></label>
                <select id="programming_language_id" name="programming_language_id" required>
                    <option value="">— Select language —</option>
                    <?php foreach ($languages as $lang): ?>
                        <option value="<?= (int) $lang['id'] ?>"
                            <?= ((string) ($old['programming_language_id'] ?? '')) === (string) $lang['id'] ? 'selected' : '' ?>>
                            <?= $h($lang['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="category_id">Category <span style="color:#dc2626">*</span></label>
                <select id="category_id" name="category_id" required>
                    <option value="">— Select category —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"
                            <?= ((string) ($old['category_id'] ?? '')) === (string) $cat['id'] ? 'selected' : '' ?>>
                            <?= $h($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="question_type">Question Type <span style="color:#dc2626">*</span></label>
                <select id="question_type" name="question_type" required
                        onchange="toggleOptionsSection(this.value)">
                    <option value="">— Select type —</option>
                    <option value="single_choice" <?= ($old['question_type'] ?? '') === 'single_choice' ? 'selected' : '' ?>>Single Choice</option>
                    <option value="true_false"    <?= ($old['question_type'] ?? '') === 'true_false'    ? 'selected' : '' ?>>True / False</option>
                </select>
            </div>

            <div class="form-group">
                <label for="difficulty">Difficulty <span style="color:#dc2626">*</span></label>
                <select id="difficulty" name="difficulty" required>
                    <option value="">— Select —</option>
                    <?php foreach (['easy','medium','hard'] as $d): ?>
                        <option value="<?= $d ?>" <?= ($old['difficulty'] ?? '') === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="default_mark">Default Mark <span style="color:#dc2626">*</span></label>
                <input type="number" id="default_mark" name="default_mark" step="0.01" min="0.01" max="99999.99" required
                       value="<?= $h($old['default_mark'] ?? '1.00') ?>">
            </div>

            <div class="form-group">
                <label for="status">Status <span style="color:#dc2626">*</span></label>
                <select id="status" name="status" required>
                    <?php foreach (['draft','published','archived'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($old['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="question_text">Question Text <span style="color:#dc2626">*</span></label>
            <textarea id="question_text" name="question_text" rows="4" required
                      placeholder="Enter the full question text here…"><?= $h($old['question_text'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="explanation">Explanation (optional)</label>
            <textarea id="explanation" name="explanation" rows="2"
                      placeholder="Explain why the correct answer is correct…"><?= $h($old['explanation'] ?? '') ?></textarea>
        </div>

        <!-- ── Single-choice options section ─── -->
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
                    $oldOptions = $old['options'] ?? [
                        ['option_text'=>'','is_correct'=>false,'sort_order'=>1],
                        ['option_text'=>'','is_correct'=>false,'sort_order'=>2],
                        ['option_text'=>'','is_correct'=>false,'sort_order'=>3],
                        ['option_text'=>'','is_correct'=>false,'sort_order'=>4],
                    ];
                    foreach ($oldOptions as $i => $opt): ?>
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

        <!-- ── True/False section ─── -->
        <div id="section-tf" style="display:none;">
            <h2 style="border-top:1px solid #f1f5f9; padding-top:1rem; margin-top:1.25rem;">
                Correct Answer
            </h2>
            <div class="radio-group">
                <label>
                    <input type="radio" name="tf_correct" value="true"
                           <?= ($old['tf_correct'] ?? 'true') === 'true' ? 'checked' : '' ?>>
                    True
                </label>
                <label>
                    <input type="radio" name="tf_correct" value="false"
                           <?= ($old['tf_correct'] ?? '') === 'false' ? 'checked' : '' ?>>
                    False
                </label>
            </div>
        </div>

        <div class="actions" style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary">Create Question</button>
            <a href="<?= url('/questions') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
let optionCount = <?= count($oldOptions ?? []) ?>;

function toggleOptionsSection(type) {
    document.getElementById('section-single').style.display = type === 'single_choice' ? '' : 'none';
    document.getElementById('section-tf').style.display     = type === 'true_false'    ? '' : 'none';
}

function markCorrect(index) {
    const rows = document.querySelectorAll('[id^="is_correct_"]');
    rows.forEach(r => { r.value = '0'; });
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

// Init on page load
(function() {
    const sel = document.getElementById('question_type');
    if (sel && sel.value) toggleOptionsSection(sel.value);
})();
</script>

<?php require dirname(__DIR__) . '/layouts/qb_footer.php'; ?>
