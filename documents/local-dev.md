# ローカル開発環境

このrepoは WordPress の子テーマなので、ローカルで動かすには WordPress 本体、MariaDB、Cocoon 親テーマ、ローカル用の初期化コードが必要です。用意した devcontainer はそれらをまとめて立ち上げ、最小限のダミーデータも投入します。

## 作成されるもの

- `http://localhost:8080` で動く WordPress
- MariaDB
- `wp-content/themes/cocoon-master` に配置される Cocoon 親テーマ
- `wp-content/themes/cocoon-child-master` にマウントされるこのrepo
- ACF free プラグイン
- 足りない投稿タイプや taxonomy を登録するローカル用 mu-plugin
- ダミーの `character` 投稿、ターム、固定ページ
- `lib/character-search/all_characters_search.json`

## 初回起動

1. このrepoを devcontainer で開きます。
2. `postCreateCommand` の bootstrap 完了を待ちます。
3. `http://localhost:8080` を開きます。
4. 管理画面に入る場合は `http://localhost:8080/wp-admin` へアクセスし、以下でログインします。

- ユーザー名: `admin`
- パスワード: `admin`

## 手動で bootstrap を再実行する

コンテナ内でセットアップをやり直したい場合は、以下を実行します。

```bash
bash .devcontainer/bootstrap.sh
```

## 検索JSONだけ再生成する

```bash
wp eval 'koto_generate_search_json_all();' --path=/var/www/html --allow-root
```

## 補足

- この環境は ACF Pro ではなく ACF free を使います。そのため、本番と同じ入力UIは再現せず、ローカル用ダミーデータでは `_spec_json` や検索用 meta を直接書き込みます。
- ローカル用 runtime plugin は `local-dev/plugin/kotodaman-local-runtime.php` にあり、bootstrap 時に `mu-plugins` へコピーされます。
- ダミーデータ投入スクリプトは `local-dev/seed/seed.php` にあります。
- 作業ツリーに `lib/character-search/all_characters_search.json` が既にある場合、bootstrap はそれを保持し、再生成しません。
