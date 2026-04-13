<form method="get" action="<?= h($this->Url->build(['controller' => 'AreaAnalysis', 'action' => $targetAction])) ?>" class="card card-body mb-3">
    <div class="form-row align-items-end">
        <div class="col-md-3">
            <label class="small mb-1">都道府県コード</label>
            <input name="area" class="form-control form-control-sm" value="<?= h($area ?? '') ?>" placeholder="13" required>
        </div>
        <div class="col-md-4">
            <label class="small mb-1">市区町村コード</label>
            <input name="city" class="form-control form-control-sm" value="<?= h($city ?? '') ?>" placeholder="13101" required>
        </div>
        <div class="col-md-3">
            <label class="small mb-1">年</label>
            <input name="year" class="form-control form-control-sm" value="<?= h($year ?? '') ?>" placeholder="2024" required>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-success btn-block">調査実行</button>
        </div>
    </div>
</form>
