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
    <p class="text-muted">デベロッパー向けに、全API IDを選んで実行URL・cURL・JSONレスポンスを確認できます。</p>

    <div class="mb-3">
        <?= $this->Html->link('価格情報画面に戻る', '/price-search/select', ['class' => 'btn btn-outline-secondary']) ?>
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
            'label' => 'クエリ文字列（例: area=13&city=13101&year=2024 / lat=35.6812&lon=139.7671&radius=1000）',
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
        <?php if (!empty($curlExample)): ?>
            <h2 class="h5 mt-3">cURLサンプル</h2>
            <pre class="bg-light p-3"><?= h($curlExample) ?></pre>
        <?php endif; ?>
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

    <hr>
    <h2 class="h5 mb-3">利用可能APIカタログ</h2>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped">
            <thead class="thead-dark">
            <tr>
                <th>ID</th>
                <th>分類</th>
                <th>API名</th>
                <th>出典・整備情報</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($apiCatalog as $api): ?>
                <tr>
                    <td><code><?= h($api['id']) ?></code></td>
                    <td><?= h($api['category']) ?></td>
                    <td><?= h($api['name']) ?></td>
                    <td><?= h($api['source']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
