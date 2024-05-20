<!-- select_a_p_i.php -->
<h1>条件選択画面</h1>
<?= $this->Form->create(null, ['url' => ['controller' => 'API', 'action' => 'selectAPI']]) ?>
<?= $this->Form->control('prefecture', [
    'label' => '都道府県',
    'type' => 'select',
    'options' => $prefectures,
    'onchange' => 'this.form.submit()'
]) ?>
<?= $this->Form->control('city', [
    'label' => '市区町村',
    'type' => 'select',
    'options' => $cities
]) ?>

<?= $this->Form->control('year', [
    'label' => '売買年度',
    'type' => 'select',
    'options' => $years
]) ?>
<?= $this->Form->control('quaters', [
    'label' => '四半期',
    'type' => 'select',
    'options' => $quarters
]) ?>




<?= $this->Form->button('検索') ?>
<?= $this->Form->end() ?>

<script>
    document.getElementById('prefecture').addEventListener('change', function() {
        this.form.submit();
    });
</script>
