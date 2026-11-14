<?php
$controller = $this->request->getParam('controller');
$action = $this->request->getParam('action');
$isActive = fn(string $c, ?string $a = null) => $controller === $c && ($a === null || $action === $a);
$navItems = [
    ['取引価格検索', ['controller' => 'PriceSearch', 'action' => 'selectAPI'], in_array($controller, ['PriceSearch', 'API'], true)],
    ['防災・規制調査', ['controller' => 'AreaAnalysis', 'action' => 'safetySurvey'], $isActive('AreaAnalysis', 'safetySurvey')],
    ['学校・生活調査', ['controller' => 'AreaAnalysis', 'action' => 'schoolSurvey'], $isActive('AreaAnalysis', 'schoolSurvey')],
    ['API Explorer', ['controller' => 'ApiExplorer', 'action' => 'apiExplorer'], $isActive('ApiExplorer')],
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($this->fetch('title') ?: '不動産取引情報システム') ?></title>
    <?= $this->Html->css('https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css') ?>
    <?= $this->Html->css('app') ?>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <?= $this->Html->link('不動産取引情報システム', ['controller' => 'PriceSearch', 'action' => 'selectAPI'], ['class' => 'navbar-brand']) ?>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="メニューを開く">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ml-auto">
            <?php foreach ($navItems as [$label, $url, $active]): ?>
                <li class="nav-item<?= $active ? ' active' : '' ?>">
                    <?= $this->Html->link($label, $url, ['class' => 'nav-link', 'aria-current' => $active ? 'page' : null]) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>

<div class="<?= h($this->fetch('containerClass') ?: 'container') ?> mt-4 mb-5">
    <?= $this->Flash->render() ?>
    <?= $this->fetch('content') ?>
</div>

<?= $this->Html->script('https://code.jquery.com/jquery-3.5.1.slim.min.js') ?>
<?= $this->Html->script('https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js') ?>
<?= $this->Html->script('https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js') ?>
<?= $this->fetch('script') ?>
</body>
</html>
