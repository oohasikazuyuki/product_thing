<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>不動産取引価格情報</title>
    <?= $this->Html->css('https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css') ?>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
    <style>
        .table th, .table td {
            vertical-align: middle;
        }
        .table th {
            background-color: #343a40;
            color: green;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
<?= $this->Html->link('不動産情報', ['controller' => 'YourController', 'action' => 'selectAPI'], ['class' => 'navbar-brand']) ?>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ml-auto">
            <li class="nav-item active">
                <?= $this->Html->link('ホーム', ['controller' => 'API', 'action' => 'selectAPI'], ['class' => 'nav-link']) ?>
            </li>
           
        </ul>
    </div>
</nav>

<div class="container mt-5">
    <?= $this->Flash->render() ?>
    <?= $this->fetch('content') ?>
</div>

<?= $this->Html->script('https://code.jquery.com/jquery-3.5.1.slim.min.js') ?>
<?= $this->Html->script('https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js') ?>
<?= $this->Html->script('https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js') ?>
</body>
</html>
