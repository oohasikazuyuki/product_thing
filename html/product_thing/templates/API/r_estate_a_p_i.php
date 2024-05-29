<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
<h1>不動産取引データ</h1>
<?php
if(!empty($data)):?>
   <div>
       <?php foreach ($data as $key => $value):?>
            <p><strong><?php h($key)?></strong><?php h($value)?></p>
       <?php endforeach;?>
   </div>
    <?php else:?>
    <p>データがありません</p>
<?php endif;?>


</body>
</html>
