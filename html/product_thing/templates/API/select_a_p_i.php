<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>条件選択画面</title>
    <?= $this->Html->css('https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css') ?>
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4 text-center">条件選択画面</h1>
    <div class="mb-3 d-flex justify-content-between">
        <div>
            <?= $this->Html->link('防災調査画面', ['controller' => 'AreaAnalysis', 'action' => 'safetySurvey'], ['class' => 'btn btn-outline-danger btn-sm']) ?>
            <?= $this->Html->link('学校調査画面', ['controller' => 'AreaAnalysis', 'action' => 'schoolSurvey'], ['class' => 'btn btn-outline-primary btn-sm']) ?>
        </div>
        <div>
            <?= $this->Html->link('API探索（別機能）', ['controller' => 'ApiExplorer', 'action' => 'apiExplorer'], ['class' => 'btn btn-outline-info']) ?>
        </div>
    </div>
    <?= $this->Form->create(null, ['url' => ['controller' => 'PriceSearch', 'action' => 'selectAPI'], 'class' => 'needs-validation', 'novalidate' => true]) ?>
    <div class="form-group">
        <?= $this->Form->control('prefecture', [
            'label' => ['text' => '都道府県', 'class' => 'form-label'],
            'type' => 'select',
            'options' => $prefectures,
            'default' => $selectedPrefecture ?? '',
            'class' => 'form-control',
            'empty' => '選択してください',
            'required' => true,
        ]) ?>
        <div class="invalid-feedback">
            都道府県を選択してください。
        </div>
    </div>
    <div class="form-group">
        <?= $this->Form->control('city', [
            'label' => ['text' => '市区町村(区を持つ市は区を選択してください)', 'class' => 'form-label'],
            'type' => 'select',
            'options' => $cityID,
            'class' => 'form-control',
            'empty' => '選択してください',
            'required' => true,
        ]) ?>
        <div class="invalid-feedback">
            市区町村を選択してください。
        </div>
    </div>
    <div class="form-group">
        <?= $this->Form->control('year', [
            'label' => ['text' => '売買年度', 'class' => 'form-label'],
            'type' => 'select',
            'options' => $years,
            'default' => $selectedYear ?? '',
            'class' => 'form-control',
            'empty' => '選択してください',
            'required' => true,
        ]) ?>
        <div class="invalid-feedback">
            売買年度を選択してください。
        </div>
    </div>
    <?= $this->Form->button(__('検索'), ['class' => 'btn btn-primary btn-block']) ?>
    <?= $this->Form->end() ?>
</div>

<?= $this->Html->script('https://code.jquery.com/jquery-3.5.1.slim.min.js') ?>
<?= $this->Html->script('https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js') ?>
<?= $this->Html->script('https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js') ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('.needs-validation');
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                alert('全てのフィールドを入力してください。');
            } else {
                form.classList.add('was-validated');
            }
        });

        const prefecture = document.getElementById('prefecture');
        const year = document.getElementById('year');
        const requestCities = function () {
            if (prefecture.value) {
                const query = new URLSearchParams({
                    prefecture: prefecture.value,
                    year: year.value || ''
                });
                window.location.href = form.action + '?' + query.toString();
            }
        };

        prefecture.addEventListener('change', requestCities);
        year.addEventListener('change', requestCities);
    });
</script>
</body>
</html>
