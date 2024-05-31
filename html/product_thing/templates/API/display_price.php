<!DOCTYPE html>
<html>
<head>
    <title>不動産取引価格情報</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
    </style>
</head>
<body>
<h1>不動産取引価格情報</h1>

<?php // デバッグ用にデータを出力 ?>
<!--<pre><?php print_r($data); ?></pre>=-->

<?php if (!empty($data)): ?>
    <table>
        <thead>
        <tr>
            <th>カテゴリ</th>
            <th>種類</th>
            <th>都道府県</th>
            <th>市区町村</th>
            <th>地区名</th>
            <th>取引価格 (円)</th>
            <th>面積 (m²)</th>
            <th>取引時期</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($data as $record):?>
                <tr>
                    <td><?= htmlspecialchars($record['PriceCategory'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($record['Type'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($record['Prefecture'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($record['Municipality'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($record['DistrictName'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars(isset($record['TradePrice']) ? number_format($record['TradePrice']) . ' 円' : 'N/A'); ?></td>
                    <td><?= htmlspecialchars($record['Area'] ?? 'N/A'); ?> m²</td>
                    <td><?= htmlspecialchars($record['Period'] ?? 'N/A'); ?></td>
                </tr>

                <tr>
                    <td colspan="9">Invalid data format: <?= h($record); ?></td>
                </tr>

        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="paginator">
        <ul class="pagination">
            <?php if ($page > 1): ?>
                <li><?= $this->Html->link('< ' . __('前へ'), ['?' => array_merge($this->request->getQuery(), ['page' => $page - 1])], ['escape' => false]) ?></li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <li><?= $this->Html->link($i, ['?' => array_merge($this->request->getQuery(), ['page' => $i])]) ?></li>
            <?php endfor; ?>
            <?php if ($page < $pages): ?>
                <li><?= $this->Html->link(__('次へ') . ' >', ['?' => array_merge($this->request->getQuery(), ['page' => $page + 1])], ['escape' => false]) ?></li>
            <?php endif; ?>
        </ul>
        <p><?= __('ページ {0} / {1} (全{2}件)', $page, $pages, $total) ?></p>
    </div>
<?php else: ?>
    <p>データがありません。</p>
<?php endif; ?>
</body>
</html>
