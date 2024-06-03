<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>不動産取引価格情報</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .table th, .table td {
            vertical-align: middle;
        }
        .table th {
            background-color: #343a40;
            color: #fff;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4 text-center">不動産取引価格情報</h1>

    <div class="row mb-4">
        <div class="col-md-6 offset-md-3">
            <input type="text" id="searchInput" class="form-control" placeholder="検索...">
        </div>
    </div>

    <?php // デバッグ用にデータを出力 ?>

    <?php if (!empty($data)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover" id="dataTable">
                <thead>
                <tr>
                    <th class="text-center bg-success">カテゴリ</th>
                    <th class="text-center bg-success">種類</th>
                    <th class="text-center bg-success">都道府県</th>
                    <th class="text-center bg-success">市区町村</th>
                    <th class="text-center bg-success">地区名</th>
                    <th class="text-center bg-success">取引価格 (円)</th>
                    <th class="text-center bg-success">面積 (m²)</th>
                    <th class="text-center bg-success">取引時期</th>

                </tr>
                </thead>
                <tbody>
                <?php foreach ($data as $record): ?>
                    <?php if (is_array($record)): ?>
                        <tr>
                            <td class="text-center"><?= htmlspecialchars($record['PriceCategory'] ?? 'N/A'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($record['Type'] ?? 'N/A'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($record['Prefecture'] ?? 'N/A'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($record['Municipality'] ?? 'N/A'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($record['DistrictName'] ?? 'N/A'); ?></td>
                            <td class="text-right"><?= htmlspecialchars(isset($record['TradePrice']) ? number_format($record['TradePrice']) . ' 円' : 'N/A'); ?></td>
                            <td class="text-right"><?= htmlspecialchars($record['Area'] ?? 'N/A'); ?> m²</td>
                            <td class="text-center"><?= htmlspecialchars($record['Period'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">Invalid data format: <?= htmlspecialchars($record); ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <nav aria-label="Page navigation example" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <?= $this->Html->link('< ' . __('前へ'), ['?' => array_merge($this->request->getQuery(), ['page' => $page - 1])], ['class' => 'page-link', 'escape' => false]) ?>
                    </li>
                <?php endif; ?>

                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <?= $this->Html->link(__('次へ') . ' >', ['?' => array_merge($this->request->getQuery(), ['page' => $page + 1])], ['class' => 'page-link', 'escape' => false]) ?>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <p class="text-center mt-3"><?= __('ページ {0} / {1} (全{2}件)', $page, $pages, $total) ?></p>
    <?php else: ?>
        <p class="alert alert-warning text-center">データがありません。</p>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', (event) => {
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('keyup', function () {
            const filter = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('#dataTable tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                let match = false;
                cells.forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(filter)) {
                        match = true;
                    }
                });
                row.style.display = match ? '' : 'none';
            });
        });
    });
</script>
</body>
</html>
