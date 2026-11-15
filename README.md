# 不動産取引情報システム

国土交通省の取引価格情報と、賃貸物件（SUUMO / RapidAPI）を、地図と一覧で探すCakePHP 4アプリです。

## セットアップ

```bash
cp .env.example .env                                   # APIキー・ポートを記入（下記）
cp html/product_thing/config/app_local.example.php html/product_thing/config/app_local.php
docker compose up -d --build
docker compose exec web sh -c 'cd /var/www/html/product_thing && composer install'
```

http://localhost:8090/ （`.env` の `WEB_PORT`）で開きます。

`vendor/` と `config/app_local.php` はGit管理外です（`.gitignore` 済み）。クローン後は上の手順で作成してください。

## .env の主な項目

| 変数 | 用途 |
|---|---|
| `API_KEY` | 不動産情報ライブラリ（国交省）のAPIキー |
| `RAPIDAPI_KEY` | 賃貸検索（SUUMO Japan Real Estate、無料枠は月300回） |
| `GOOGLE_MAPS_API_KEY` | ストリートビュー・Googleの徒歩ルート（任意。無いときは地図データのルートと埋め込みに戻る）。HTTPリファラー制限と、Maps JavaScript API / Maps Embed API の許可が必要 |

## テスト

```bash
docker compose exec web sh -c 'cd /var/www/html/product_thing && vendor/bin/phpunit'
```

PRごとにGitHub Actionsでも実行されます。
