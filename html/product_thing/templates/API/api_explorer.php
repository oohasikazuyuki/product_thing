<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>不動産情報ライブラリ API探索</title>
    <?= $this->Html->css('https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css') ?>
</head>
<body>
<div class="container mt-5 mb-5">
    <h1 class="mb-3">不動産情報ライブラリ API探索（別機能）</h1>
    <p class="text-muted">API IDとクエリを指定して、レスポンスを確認できます。</p>

    <div class="mb-3">
        <?= $this->Html->link('価格情報画面に戻る', ['controller' => 'API', 'action' => 'selectAPI'], ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger"><?= h($errorMessage) ?></div>
    <?php endif; ?>

    <?= $this->Form->create(null, ['url' => ['action' => 'apiExplorer']]) ?>
    <div class="form-group">
        <?= $this->Form->control('api_id', [
            'label' => 'API ID',
            'type' => 'select',
            'options' => $apiOptions,
            'default' => $selectedApi,
            'class' => 'form-control',
            'required' => true,
        ]) ?>
    </div>
    <div class="form-group">
        <?= $this->Form->control('query_string', [
            'label' => 'クエリ文字列（例: area=13&city=13101&year=2024）',
            'type' => 'text',
            'value' => $queryString,
            'class' => 'form-control',
            'required' => true,
        ]) ?>
    </div>
    <?= $this->Form->button('APIを実行', ['class' => 'btn btn-primary']) ?>
    <?= $this->Form->end() ?>

    <?php if (!empty($requestUrl)): ?>
        <hr>
        <h2 class="h5">実行URL</h2>
        <code><?= h($requestUrl) ?></code>
    <?php endif; ?>

    <?php if (is_array($resultData)): ?>
        <hr>
        <h2 class="h5">レスポンス概要</h2>
        <p class="mb-2">
            件数:
            <?php if (isset($resultData['data']) && is_array($resultData['data'])): ?>
                <?= count($resultData['data']) ?>
            <?php else: ?>
                data配列なし
            <?php endif; ?>
        </p>
        <details open>
            <summary>レスポンスJSON</summary>
            <pre class="bg-light p-3 mt-2" style="max-height: 540px; overflow: auto;"><?= h($rawResult) ?></pre>
        </details>
    <?php endif; ?>
</div>
</body>
</html>
