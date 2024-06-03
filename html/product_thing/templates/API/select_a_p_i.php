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
    <?= $this->Form->create(null, ['url' => ['action' => 'selectAPI'], 'class' => 'needs-validation', 'novalidate' => true]) ?>
    <div class="form-group">
        <?= $this->Form->control('prefecture', [
            'label' => ['text' => '都道府県', 'class' => 'form-label'],
            'type' => 'select',
            'options' => $prefectures,
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

        // 都道府県が変更されたときにのみフォームを送信
        document.getElementById('prefecture').addEventListener('change', function() {
            if (this.value) form.submit();
        });
    });
</script>
</body>
</html>
