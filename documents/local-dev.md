# ローカル開発環境

このrepoは WordPress の子テーマなので、ローカルで動かすには WordPress 本体、MariaDB、Cocoon 親テーマ、ローカル用の初期化コードが必要です。用意した devcontainer はそれらをまとめて立ち上げ、最小限のダミーデータも投入します。

## 作成されるもの

- `http://localhost:8080` で動く WordPress
- MariaDB
- `wp-content/themes/cocoon-master` に配置される Cocoon 親テーマ
- `wp-content/themes/cocoon-child-master` にマウントされるこのrepo
- ACF PRO プラグイン
- 足りない投稿タイプや taxonomy を登録するローカル用 mu-plugin
- ダミーの `character` 投稿、ターム、固定ページ
- `lib/character-search/all_characters_search.json`

## 初回起動

1. `.devcontainer/.env.example` を `.devcontainer/.env` にコピーします。
2. Product Owner から受け取った ACF PRO license key を `.devcontainer/.env` の `ACF_PRO_LICENSE` に設定します。
3. このrepoを devcontainer で開きます。
4. `postCreateCommand` の bootstrap 完了を待ちます。
5. `http://localhost:8080` を開きます。
6. 管理画面に入る場合は `http://localhost:8080/wp-admin` へアクセスし、以下でログインします。

- ユーザー名: `admin`
- パスワード: `admin`

## 手動で bootstrap を再実行する

コンテナ内でセットアップをやり直したい場合は、以下を実行します。

```bash
bash .devcontainer/bootstrap.sh
```

## ACF PRO

- ACF PRO は Composer 経由で `wp-content/plugins/advanced-custom-fields-pro` にインストールされます。
- `ACF_PRO_LICENSE` は WordPress の `ACF_PRO_LICENSE` 定数にも渡されるため、管理画面での手動入力は不要です。
- `ACF_PRO_SITE_URL` は Composer 認証時のサイトURLです。未設定の場合は `http://localhost:8080` を使います。
- ライセンスキーは `.devcontainer/.env` にだけ置き、リポジトリへコミットしません。

## ACF local JSON

- ACF field group の local JSON は `acf-json/` に置きます。
- bootstrap は `acf-json/` ディレクトリを作成し、`wp acf json sync` で同期します。
- テーマは環境に関係なく `acf-json/` だけを ACF JSON の保存・読み込み先として使います。
- Product Owner から受け取った field group / taxonomy / post type JSON は `acf-json/*.json` として配置してください。
- `docker compose up --force-recreate` やdevcontainerのRebuildだけではDB volumeは残ります。DB内のACF定義を含めて作り直す場合は、volumeを削除してください。

## ローカル環境を完全に作り直す

コンテナ再作成だけでは MariaDB の named volume は削除されません。DB内のACF定義も含めてproduction由来の `acf-json/` から作り直す場合は、以下でvolumeごと削除してからdevcontainerを起動します。

```bash
docker compose -f .devcontainer/docker-compose.yml down --volumes
docker compose -f .devcontainer/docker-compose.yml up -d --build
```

## 検索JSONだけ再生成する

```bash
wp eval 'koto_generate_search_json_all();' --path=/var/www/html --allow-root
```

## 補足

- ローカル用 runtime plugin は `local-dev/plugin/kotodaman-local-runtime.php` にあり、bootstrap 時に `mu-plugins` へコピーされます。
- ダミーデータ投入スクリプトは `local-dev/seed/seed.php` にあります。
- 作業ツリーに `lib/character-search/all_characters_search.json` が既にある場合、bootstrap はそれを保持し、再生成しません。
