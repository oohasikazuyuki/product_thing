<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <title>Bootstrap Header and Footer Example</title>
  <style>
    body {
    background-color:#fff7ed;
    }
    /* ヘッダーとフッターの色を #008742 に設定 */
    .navbar-custom {
      background-color: #008742 !important;
    }
    .footer-custom {
      background-color: #008742 !important;
      color: white; /* テキスト色を白に設定 */
      padding: 20px 0; /* 上下の余白を追加 */
      width: 100%;
      position: fixed; /* フッターを固定 */
      bottom: 0; /* ページの一番下に配置 */
      left: 0; /* 左端に配置 */
    }
    .wrapper {
      display: flex;
      flex-direction: column;
      min-height: 100vh; /* ページの最低高さをブラウザの高さに設定 */
      margin-bottom: 40px; /* フッターとテーブルの間に余白を追加 */
    }
    .content {
      flex: 1; /* コンテンツが残りのスペースを満たすようにする */
    }
    thead {
      display: none;
    }
    .table th:nth-child(1),
    .table td:nth-child(1) {
      width: 6cm; /* 第1列の幅を設定 */
    } 
    .table th:nth-child(2),
    .table td:nth-child(2) {
      width: 6cm; /* 第2列の幅を設定 */
    }
    .table th:nth-child(3),
    .table td:nth-child(3) {
      width: 35cm; /* 第3列の幅を設定 */
    }
    .btn-custom {
      background-color: #008742; /* 背景色を指定 */
      color: white; /* テキスト色を白に指定 */
      border: none; /* ボーダーを削除 */
      padding: 10px 20px; /* パディングを追加 */
      border-radius: 5px; /* ボーダーの角を丸くする */
      transition: background-color 0.3s ease; /* ホバー時のトランジションを追加 */
    }
    .btn-custom:hover {
      background-color: #06c755; /* ホバー時の背景色を指定 */
    }
  </style>
</head>

<body>
  <div class="wrapper">
    <header class="navbar navbar-expand-lg navbar-dark navbar-custom">
      <div class="container">
        <a class="navbar-text" style="color: white;">テキストおおおお</a> <!-- "Your Text" に変更 -->
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav ml-auto">
            <li class="nav-item">
              <a class="nav-link" href="#" style="color: white;">ユーザー登録</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#" style="color: white;">取引価格</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#" style="color: white;">地価</a>
            </li>            
          </ul>
          <span class="navbar-text ml-auto" style="color: white;">ID : 連携よろしくうううううう</span>
        </div>
      </div>
    </header>

     <!-- タイトル -->
     <div class="container mt-3">
        <div class="text-center">
          <h1>不動産取引価格情報</h1>
        </div>
        <div class="table-responsive ">
          <table class="table table-success table-striped">
            <div class="content">
              <table class="table table-success table-striped-columns">
                <thead>
                  <tr>
                    <th scope="col" style="width: 100%;">#</th>
                    <th scope="col" style="width: 40%;">First</th>
                    <th scope="col" style="width: 50%;">Last</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <th scope="row">都道府県</th>
                    <td></td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row">地域</th>
                    <td></td>
                    <td><?h($data['Municipality'])?></td><!--データ -->
                  </tr>
                  
                  <tr>
                    <th scope="row"></th>
                    <td>距離</td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row">土地</th>
                    <td>取引総額</td>
                    <td><?=h($data[''])?></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row"></th>
                    <td>坪単価</td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row"></th>
                    <td>面積</td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row"></th>
                    <td>㎡単価</td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row"></th>
                    <td>間口</td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row"></th>
                    <td>形状</td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row">今後の利用目的</th>
                    <td></td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row">前面道路</th>
                    <td>幅員</td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row"></th>
                    <td>種類</td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row"></th>
                    <td>方位</td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row">都市計画</th>
                    <td></td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row">建ぺい率</th>
                    <td></td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row">容積率</th>
                    <td></td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row">取引時期</th>
                    <td></td>
                    <td></td><!--データ -->
                  </tr>
                  <tr>
                    <th scope="row">取引の事情等</th>
                    <td></td>
                    <td></td><!--データ -->
                  </tr>
                 
                </tbody>
              </table>
            </div>
          </table>
        </div>
      </div>
      
      <footer class="footer-custom">
        <div class="container">
          <div class="row">
            <div class="col text-center">
              © 2024
            </div>
          </div>
        </div>
      </footer>

    <!-- 戻るボタン -->
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-auto">
            <button onclick="scrollToTop()" class="btn btn-custom">戻る</button>
          </div>
        </div>
      </div>
    <div style="height: 100px;"></div> <!-- フッターとテーブルの間に余白を追加 -->
  </div>
</body>
</html>
