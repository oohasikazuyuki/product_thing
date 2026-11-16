<form method="get" aria-label="調査条件" action="<?= h($this->Url->build(['controller' => 'AreaAnalysis', 'action' => $targetAction])) ?>" class="card card-body mb-3 analysis-filter-form">
    <div class="form-row align-items-end">
        <div class="col-md-3">
            <label class="small mb-1" for="f-area">都道府県</label>
            <select id="f-area" name="area" class="form-control form-control-sm" required>
                <?php foreach (($areaOptions ?? []) as $code => $name): ?>
                    <option value="<?= h($code) ?>" <?= ($area ?? '') === (string)$code ? 'selected' : '' ?>><?= h($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="small mb-1" for="f-city">市区町村</label>
            <select id="f-city" name="city" class="form-control form-control-sm" required>
                <?php foreach (($cityOptions ?? []) as $code => $name): ?>
                    <option value="<?= h($code) ?>" <?= ($city ?? '') === (string)$code ? 'selected' : '' ?>><?= h($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="small mb-1" for="f-year">年</label>
            <select id="f-year" name="year" class="form-control form-control-sm" required>
                <?php foreach (($yearOptions ?? []) as $code => $name): ?>
                    <option value="<?= h($code) ?>" <?= ($year ?? '') === (string)$code ? 'selected' : '' ?>><?= h($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="small mb-1" for="f-district">地区名（任意）</label>
            <select id="f-district" name="district" class="form-control form-control-sm">
                <option value="">すべて</option>
                <?php foreach (($districtOptions ?? []) as $name): ?>
                    <option value="<?= h($name) ?>" <?= ($district ?? '') === (string)$name ? 'selected' : '' ?>><?= h($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-12 mt-2">
            <button type="submit" class="btn btn-success btn-block">この条件で調査する</button>
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
