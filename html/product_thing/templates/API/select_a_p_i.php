<!-- select_a_p_i.php -->
<h1>条件選択画面</h1>
<?= $this->Form->create() ?>
<?= $this->Form->control('prefecture', [
    'label' => '都道府県',
    'type' => 'select',
    'options' => $prefectures,
]) ?>
<?= $this->Form->control('city', [
    'label' => '市区町村',
    'type' => 'select',
    'options' => $cityID
]) ?>

<?= $this->Form->control('year', [
    'label' => '売買年度',
    'type' => 'select',
    'options' => $years
]) ?>






<?= $this->Form->submit('検索') ?>
<?= $this->Form->end() ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    form.addEventListener('submit', function(event) {
       

        // 必須フィールドのチェック
        const prefecture = document.getElementById('prefecture').value;
        const city = document.getElementById('city').value;
        const year = document.getElementById('year').value;
        

        if (prefecture && city && year) {
          event.preventDefault();
            form.submit();
        } else {
            alert('全てのフィールドを入力してください。');
        }
    });

    // 都道府県が変更されたときのみフォームを送信
    document.getElementById('prefecture').addEventListener('change', function() {
        if (this.value) form.submit();
    });
});
</script>
