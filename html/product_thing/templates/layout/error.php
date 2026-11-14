<?php
/**
 * 本番用エラーページのレイアウト（アプリ本体と同じデザイン）
 *
 * @var \App\View\AppView $this
 */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>エラー | 不動産取引情報システム</title>
    <?= $this->Html->meta('icon') ?>
    <?= $this->Html->css('https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css') ?>
    <?= $this->Html->css('app') ?>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
</head>
<body>
<nav class="navbar navbar-dark navbar-app">
    <?= $this->Html->link('不動産取引情報システム', '/', ['class' => 'navbar-brand']) ?>
</nav>
<main class="container mt-5">
    <div class="card"><div class="card-body p-4 text-center">
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
        <div class="mt-4">
            <?= $this->Html->link('トップへ戻る', '/', ['class' => 'btn btn-success mr-2']) ?>
            <a href="javascript:history.back()" class="btn btn-outline-secondary">前のページへ戻る</a>
        </div>
    </div></div>
</main>
</body>
</html>
