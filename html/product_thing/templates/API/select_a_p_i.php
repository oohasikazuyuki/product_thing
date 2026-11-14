<?php $this->assign('title', '取引価格検索'); ?>
<div class="hero">
    <h1>物件の取引価格を、地図で探す</h1>
    <p>都道府県・市区町村・年を選ぶだけで、国土交通省の取引価格情報を地図と一覧で確認できます。航空写真やストリートビューで周辺も見られます。</p>
</div>

<div class="row justify-content-center">
<div class="col-lg-10">
<div class="card">
    <div class="card-body p-4">
        <?= $this->Form->create(null, ['url' => ['controller' => 'PriceSearch', 'action' => 'selectAPI'], 'class' => 'needs-validation', 'novalidate' => true, 'id' => 'searchForm']) ?>
        <div class="form-row">
            <div class="form-group col-md-4">
                <?= $this->Form->control('prefecture', [
                    'label' => ['text' => '都道府県', 'class' => 'form-label'],
                    'type' => 'select',
                    'options' => $prefectures,
                    'default' => $selectedPrefecture ?? '',
                    'class' => 'form-control',
                    'empty' => '選択してください',
                    'required' => true,
                    'templates' => ['inputContainer' => '{{content}}', 'inputContainerError' => '{{content}}'],
                ]) ?>
                <div class="invalid-feedback">都道府県を選択してください。</div>
            </div>
            <div class="form-group col-md-4">
                <?= $this->Form->control('city', [
                    'label' => ['text' => '市区町村（区を持つ市は区を選択）', 'class' => 'form-label'],
                    'type' => 'select',
                    'options' => $cityID,
                    'class' => 'form-control',
                    'empty' => '選択してください',
                    'required' => true,
                    'templates' => ['inputContainer' => '{{content}}', 'inputContainerError' => '{{content}}'],
                ]) ?>
                <div class="invalid-feedback">市区町村を選択してください。</div>
                <small id="cityStatus" class="form-text text-muted" role="status"></small>
            </div>
            <div class="form-group col-md-4">
                <?= $this->Form->control('year', [
                    'label' => ['text' => '売買年', 'class' => 'form-label'],
                    'type' => 'select',
                    'options' => $years,
                    'default' => $selectedYear ?? '',
                    'class' => 'form-control',
                    'empty' => '選択してください',
                    'required' => true,
                    'templates' => ['inputContainer' => '{{content}}', 'inputContainerError' => '{{content}}'],
                ]) ?>
                <div class="invalid-feedback">売買年を選択してください。</div>
            </div>
        </div>
        <?= $this->Form->button('検索', ['class' => 'btn btn-success btn-block', 'id' => 'searchButton']) ?>
        <?= $this->Form->end() ?>
    </div>
</div>
</div>
</div>

<div class="row mt-4 justify-content-center">
    <div class="col-lg-10">
        <h2 class="h6 text-muted mb-3">そのほかの調査</h2>
        <div class="row">
            <div class="col-md-6 mb-3">
                <?= $this->Html->link('<h2>防災・規制調査</h2><p>都市計画・用途地域・災害リスクを地図に重ねて確認</p>', ['controller' => 'AreaAnalysis', 'action' => 'safetySurvey'], ['class' => 'card feature-card', 'escape' => false]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <?= $this->Html->link('<h2>学校・生活調査</h2><p>学校区・保育園・医療施設など暮らしの環境を確認</p>', ['controller' => 'AreaAnalysis', 'action' => 'schoolSurvey'], ['class' => 'card feature-card', 'escape' => false]) ?>
            </div>
        </div>
    </div>
</div>

<?php $this->start('script'); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('searchForm');
    const prefecture = document.getElementById('prefecture');
    const city = document.getElementById('city');
    const year = document.getElementById('year');
    const cityStatus = document.getElementById('cityStatus');
    const button = document.getElementById('searchButton');
    let cityRequest = 0;
    city.disabled = !prefecture.value;
    if (!prefecture.value) { cityStatus.textContent = '先に都道府県を選んでください。'; }

    // 市区町村だけ差し替える（ページ遷移しない）。同じページをfetchして #city の選択肢を取り出す。
    const refreshCities = async function () {
        const current = ++cityRequest;
        city.innerHTML = '<option value="">選択してください</option>';
        if (!prefecture.value) { cityStatus.textContent = '先に都道府県を選んでください。'; city.disabled = true; return; }
        cityStatus.textContent = '市区町村を読み込み中…';
        city.disabled = true;
        try {
            const query = new URLSearchParams({ prefecture: prefecture.value, year: year.value || '' });
            const res = await fetch(form.action + '?' + query, { credentials: 'same-origin' });
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            if (current !== cityRequest) return;
            const options = doc.querySelectorAll('#city option');
            city.innerHTML = '';
            options.forEach(function (o) { city.appendChild(document.importNode(o, true)); });
            cityStatus.textContent = options.length > 1 ? '' : '市区町村を取得できませんでした。年を変えるか、時間をおいて再度お試しください。';
        } catch (e) {
            if (current !== cityRequest) return;
            cityStatus.textContent = '市区町村の取得に失敗しました。通信状況を確認してください。';
        } finally {
            if (current === cityRequest) city.disabled = false;
        }
    };

    prefecture.addEventListener('change', refreshCities);
    year.addEventListener('change', function () { if (prefecture.value) refreshCities(); });

    form.addEventListener('submit', function (event) {
        form.classList.add('was-validated');
        if (!form.checkValidity()) {
            event.preventDefault();
            return;
        }
        button.disabled = true;
        button.textContent = '検索中…';
    });
});
</script>
<?php $this->end(); ?>
