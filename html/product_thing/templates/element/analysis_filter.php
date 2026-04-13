<form method="get" action="<?= h($this->Url->build(['controller' => 'AreaAnalysis', 'action' => $targetAction])) ?>" class="card card-body mb-3 analysis-filter-form">
    <div class="form-row align-items-end">
        <div class="col-md-3">
            <label class="small mb-1">都道府県</label>
            <select name="area" class="form-control form-control-sm" required>
                <?php foreach (($areaOptions ?? []) as $code => $name): ?>
                    <option value="<?= h($code) ?>" <?= ($area ?? '') === (string)$code ? 'selected' : '' ?>><?= h($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="small mb-1">市区町村</label>
            <select name="city" class="form-control form-control-sm" required>
                <?php foreach (($cityOptions ?? []) as $code => $name): ?>
                    <option value="<?= h($code) ?>" <?= ($city ?? '') === (string)$code ? 'selected' : '' ?>><?= h($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="small mb-1">年</label>
            <select name="year" class="form-control form-control-sm" required>
                <?php foreach (($yearOptions ?? []) as $code => $name): ?>
                    <option value="<?= h($code) ?>" <?= ($year ?? '') === (string)$code ? 'selected' : '' ?>><?= h($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="small mb-1">地区名（任意）</label>
            <select name="district" class="form-control form-control-sm">
                <option value="">すべて</option>
                <?php foreach (($districtOptions ?? []) as $name): ?>
                    <option value="<?= h($name) ?>" <?= ($district ?? '') === (string)$name ? 'selected' : '' ?>><?= h($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-12 mt-2">
            <button type="submit" class="btn btn-sm btn-success btn-block">調査実行</button>
        </div>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.analysis-filter-form');
    if (!form) return;
    const areaSelect = form.querySelector('select[name="area"]');
    const citySelect = form.querySelector('select[name="city"]');
    const districtSelect = form.querySelector('select[name="district"]');

    if (areaSelect) {
        areaSelect.addEventListener('change', function () {
            if (districtSelect) {
                districtSelect.value = '';
            }
            form.submit();
        });
    }
    if (citySelect) {
        citySelect.addEventListener('change', function () {
            if (districtSelect) {
                districtSelect.value = '';
            }
            form.submit();
        });
    }
});
</script>
