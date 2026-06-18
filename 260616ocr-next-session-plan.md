# OCR下書き作成 次セッション作業計画

## 目的

スクショOCRからWordPressの `character` 下書きを作る機能について、現状の実装を壊さずに、全テストキャラでの取得状況を再測定し、残っている抽出不足を優先度順に改善する。

初期ゴールは「OCRで新規下書きを作る」まで。既存投稿への反映、DBエディタDOMへの自動流し込み、一括反映UIはこの計画の対象外。

## 作業開始時の重要前提

- repo: `/home/colon/temp/kotodaman-db`
- current branch想定: `feat/ocr-draft-import`
- WordPress/PHP/WP-CLI作業はホストではなくdevcontainer内で実行する。
- devcontainer名: `devcontainer-wordpress-1`
- WordPress path: `/var/www/html`
- devcontainer workspace: `/var/www/html/wp-content/themes/cocoon-child-master`
- ローカルWordPress URL: `http://localhost:8080`
- API keyは `.devcontainer/.env` 経由でコンテナへ渡される。ブラウザへ渡さない。
- GitHub操作は注意。ローカルの `gh` は別ユーザでログインしている可能性があるため、そのままPR作成などに使わない。
- PR #12 のhead branchは `origin/feat/spec-json-schema` として更新してきた運用。
- `other_resolutions/` はキャラ単位ではないため、全キャラsurveyから除外する。

## 現在のGit状態

最後に作ったコミット:

```text
ad14e40 OCR下書きの抽出とACF保存を安定化
```

このコミットに含まれる変更:

- `lib/acf/ocr/field-extractor.php`
  - 使用可能文字の抽出を単一ひらがなに制限。
  - `以上`, UI文言, リーダー/とくせい断片などの混入を抑制。
  - わざ/すごわざ名から `詳細`, `発動例`, `とじる` を除去。
  - main画面からも属性/種族を拾う。
  - `void`, `heaven`, `rainbow`, `demon`, `beast`, `spirit`, `yokai` の抽出mapping追加。
- `lib/acf/ocr/spec-json-acf-adapter.php`
  - 存在しない `character_name` ACF保存を削除。
  - `available_moji` term解決を厳密化。
  - `available_moji_loop` を本番ACF構造で保存。
  - `sugowaza_condition` を本番repeater構造で保存。
  - `waza_group_loop` / `sugowaza_group_loop` に最小攻撃rowを保存。
- `local-dev/seed/seed.php`
  - seed versionを `3` に更新。
  - fixtureで必要な属性/種族/使用可能文字termを追加。

最後の確認時点で、追跡済みファイルの未コミット差分はなし。未追跡ファイルは多数あるが、この作業では原則触らない。

未追跡として見えていたもの:

```text
260423exim
260423pidstat-mini.log
260512-import_specjson_wip.md
260608ocr-migration-plan.md
260611ocr-migration-plan.md
AGENTS.md
documents/issue-3-trait-search-plan.md
documents/search-json-schema-change-plan.md
karyoku-hotfix-plan.md
node_modules/
pr-hotfix-karyoku.md
testdata/
wip-img/
```

## 既に検証済みのこと

以下は `ad14e40` 作成前に確認済み。

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 php -l lib/acf/ocr/field-extractor.php
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 php -l lib/acf/ocr/spec-json-acf-adapter.php
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 php -l local-dev/seed/seed.php
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval-file local-dev/seed/ocr-smoke.php --path=/var/www/html --allow-root
node --check lib/acf/ocr/acf-ocr-draft.js
```

追加確認済み:

- seed追加termの存在確認OK。
- 保存済みDanto OCR normalized dataのreplay OK。
- Dantoの文字は `た,だ,そ,ぞ` まで綺麗に抽出される。
- Dantoの `ん` は保存済みraw OCR自体に存在しなかったため、現抽出ロジックだけでは補えない。

## 主な関連ファイル

- `260611ocr-migration-plan.md`
  - OCR実装全体の元計画。
- `lib/acf/ocr/koto-ocr.php`
  - OCR機能の読み込み入口。
- `lib/acf/ocr/backend-interface.php`
  - OCR backend interface。
- `lib/acf/ocr/config.php`
  - backend/model/API key設定。
- `lib/acf/ocr/openrouter-vlm-backend.php` または同等backendファイル
  - OpenRouter VLM呼び出し。
- `lib/acf/ocr/ocr-normalizer.php`
  - VLM responseの正規化。
- `lib/acf/ocr/screen-classifier.php`
  - 画面種別判定。
- `lib/acf/ocr/field-extractor.php`
  - raw OCR textからfield候補を抽出。次に触る可能性が高い。
- `lib/acf/ocr/structure/spec-fragments.php`
  - extracted fieldsからpartial spec fragmentを作る。
- `lib/acf/ocr/draft-spec-builder.php`
  - draft specとwarningsを組み立てる。
- `lib/acf/ocr/spec-json-acf-adapter.php`
  - draft spec fragmentをACF保存データへ変換。次に触る可能性が高い。
- `lib/acf/ocr/draft-persister.php`
  - 下書きpost作成、ACF/meta保存、OCR debug meta保存。
- `lib/acf/ocr/admin.php`
  - DBエディタOCR UI/AJAX、OCR確認パネル。
- `lib/acf/ocr/acf-ocr-draft.js`
  - OCR UI JS。ブラウザでは画像縮小を行う。
- `lib/acf/ocr/acf-ocr-draft.css`
  - OCR UI CSS。
- `local-dev/seed/ocr-smoke.php`
  - OCR smoke test。
- `local-dev/seed/seed.php`
  - local taxonomy term seed。
- `acf-json/group_69204fa4dd82e.json`
  - キャラクター基本データACF group。
- `acf-json/group_6937900895bf1.json`
  - わざ/すごわざACF group。
- `acf-json/group_693790ee221c3.json`
  - とくせいACF group。
- `acf-json/group_693971a11a6b2.json`
  - 祝福とくせいACF group。
- `local-dev/seed/ocr-fixtures/live-openrouter/input/`
  - OCR調査対象のスクショfixtureと期待 `spec.json`。外部repoの `kotodaman-ss-to-spec/test-data` から移植済み。

## ローカル環境の確認手順

作業開始時に必ず確認する。

```bash
docker ps --format '{{.Names}}'
```

`devcontainer-wordpress-1` がない場合は起動/復旧が必要。

ACF Pro/field group確認の例:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp plugin list --path=/var/www/html --allow-root
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp acf field-group list --path=/var/www/html --allow-root
```

bootstrap再実行が必要な場合:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 bash .devcontainer/bootstrap.sh
```

ACF JSON syncが必要な場合:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp acf json sync --path=/var/www/html --allow-root
```

## テストデータ対象

`local-dev/seed/ocr-fixtures/live-openrouter/input/` のうち、`spec.json` とOCR可能画像を持つ以下9キャラを対象とする。

- `comforgo`
- `danto`
- `itadori_okkotsu_dream`
- `karen_ibutsu`
- `mashiro_grand`
- `mashiro7`
- `nhelp_grand`
- `yomitaru_light`
- `yomitaru_water`

除外:

- `other_resolutions/`

前回surveyの対象画像数:

- `comforgo`: 11画像
- `danto`: 9画像
- `itadori_okkotsu_dream`: 11画像
- `karen_ibutsu`: 9画像
- `mashiro_grand`: 11画像
- `mashiro7`: 8画像
- `nhelp_grand`: 17画像
- `yomitaru_light`: 10画像
- `yomitaru_water`: 9画像

## 前回surveyで見えた課題

### 全体

- 文字抽出が過剰に拾っていた。
  - `以上`, `ATK`, UI断片, リーダー/とくせい文言が混入。
  - `ad14e40` で単一ひらがな制限を入れて大きく改善したはず。
- わざ/すごわざ名にUI文言が混入していた。
  - `詳細 発動例`, `とじる` など。
  - `ad14e40` で除去を追加済み。
- 属性/種族mappingが不足していた。
  - `void`, `heaven`, `demon`, `beast`, `spirit` など。
  - `ad14e40` で追加済み。
- 本文rawは多く取れるが、構造化は浅い。
  - とくせい/祝福/リーダー/EXはraw確認中心。
  - 攻撃row化は最小限。

### キャラ別の前回概況

- `comforgo`
  - 名前OK、属性/種族OK。
  - 文字抽出が大量誤爆。
  - わざ名 `奏でる` が `奏は` になるなどOCR揺れ。
  - `オーバーヒート` が `オーバービート` になる揺れ。
- `danto`
  - 名前OK、属性/種族OK。
  - 文字 `ん` が未取得。
  - 保存済みrawには `ん` 自体が無かった。
- `itadori_okkotsu_dream`
  - 名前OK、種族OK。
  - `void` 属性が未対応だったが `ad14e40` で対応。
  - わざ/すごわざ名に `詳細 発動例` 混入していたが `ad14e40` で対策。
- `karen_ibutsu`
  - 属性OK。
  - `beast` 種族未抽出だったが `ad14e40` で対応。
  - ランダム攻撃などはraw止まり。
- `mashiro7`
  - `heaven` 属性未対応だったが `ad14e40` で対応。
  - 神種族未抽出だったが、main画面抽出追加で改善可能性あり。
- `mashiro_grand`
  - わざ/すごわざ名OK。
  - わざ/すごわざ本文が名前だけになるケースあり。
  - 文字・属性・種族なしだった。
- `nhelp_grand`
  - OpenRouter 413で完了せず。
  - `Downloaded image content cannot exceed 30MB`。
- `yomitaru_light`
  - 名前は取れるが期待specは `&amp;` 表記なので単純比較ではmismatch。
  - 属性OK。
  - `demon` 種族未抽出だったが `ad14e40` で対応。
  - わざ/すごわざ名にUI文言混入していたが `ad14e40` で対策。
  - 文字抽出は誤爆していたが `ad14e40` で改善可能性あり。
- `yomitaru_water`
  - 名前に `イィ` / `イイ` のOCR揺れ。
  - `言祓` / `言祝` のOCR揺れ。
  - `spirit` 種族未抽出だったが `ad14e40` で対応。

## 次にやること: 優先順

### 1. surveyをファイル出力で再実行する

前回はtool出力がtruncateされた。必ずコンテナ内またはworkspace内のファイルにJSON出力し、そのファイルを読む。

目的:

- `ad14e40` 後の改善状況を定量確認する。
- 再実行結果をキャラ別/項目別に一覧化する。
- APIコストをかけるため、実行前にユーザへ確認してもよい。

出力したい項目:

- folder
- expected name / got name / status
- expected chars / got chars / status
- expected attribute / got attribute / status
- expected species / got species / status
- expected waza_name / got waza_name / status
- expected sugowaza_name / got sugowaza_name / status
- got sugowaza_condition
- trait1 raw presence
- trait2 raw presence
- blessing raw presence
- leader raw presence
- warnings
- classifications
- error

注意:

- `nhelp_grand` は413になりやすい。まず通常実行で失敗を記録するか、最初から分割/縮小対策を入れるか決める。
- API再実行はコストがある。

### 2. `nhelp_grand` 413対策

原因:

- WP-CLI surveyではブラウザJSの画像縮小を通らないため、原画像がOpenRouterの30MB制限に当たった可能性が高い。

対策候補:

- WP-CLI survey用に画像を縮小してから送る。
- OCR backend呼び出し前に共通の画像縮小処理をPHP側にも入れる。
- 17枚を複数requestへ分割し、結果をmergeする。

推奨順:

1. survey script側だけで縮小/分割して、まず調査を完了させる。
2. UIでも同じ制限に当たるなら、共通化して本実装へ入れる。

### 3. 文字不足への対応

現状:

- 誤爆抑制は入った。
- OCR rawに出ていない文字は抽出では補えない。
- Dantoの `ん` はこのケース。

対策候補:

- VLM promptを強める。
  - 「使用可能文字」「文字変換」「文字追加」「コピーガード・スーパー化」などを必ず個別文字配列として出させる。
  - 画面上に見える使用可能文字を本文とは別の `blocks` regionに出させる。
- main画面だけではなく、とくせい画面の「文字追加」「文字変換」からも安全に補完する。
  - ただし、現在は誤爆回避のため文脈制限を強めている。安易にquoted text全体へ戻さない。

### 4. わざ/すごわざ本文の構造化を広げる

現状:

- 攻撃本文に `攻撃` がある場合のみ、最小のattack rowを保存。
- 倍率/数値は安全確定しないため空欄 + warning。
- `敵全体`, `ランダム`, `強力/超強力/超絶強力/爆絶強力` 程度を判定。

次の拡張候補:

- 被ダメージ増加状態付与。
- ATK強化/弱体。
- 手札/このターン/指定グループ対象のバフ。
- 追加攻撃。
- 属性/種族条件。
- `ランダムな敵に複数回` のhit/target表現。

注意:

- ACF field構造をまたぐ拡張になる場合は、既存ACF JSONを読んでから最小変更にする。
- 数値倍率はまだ手入力前提でよい。

### 5. すごわざ条件の拡張

現状:

- `char_count`: `N文字以上`
- `combo`: `Nコンボ以上`

未対応:

- `「た」「だ」からはじまる4文字以上`
- `2文字以上9コンボ以上` の複合解釈
- グループ/種族/属性同時発動条件
- 特定文字開始条件

推奨:

- まずACFの `sugowaza_condition` field構造を確認する。
- `start_char` など既存選択肢があるなら、そこに合わせる。
- なければ無理に新schemaを作らずraw warningに残す。

### 6. とくせい/祝福のrow化精度

現状:

- OCR rawは保存される。
- `koto_ocr_apply_existing_auto_input_rules()` により、既存CSVルールに乗るものはACF row化される。
- OCR崩れや複合効果はraw確認前提。

次の改善:

- OCR textをCSV auto-inputに渡す前に、UI文言や改行を正規化する。
- `①`, `②` は同一とくせい内効果番号なので、画面単位の割当は維持する。
- 祝福は複数Lv行になりやすいため、raw保存を優先し、row化は慎重に進める。

## survey再実行時の実装方針

survey scriptは `local-dev/seed/ocr-live-openrouter-survey.php` としてrepoに追加済み。

入力:

- repo内fixture: `local-dev/seed/ocr-fixtures/live-openrouter/input/`
- 対象case: `input/{case}/spec.json` とOCR可能画像を持つディレクトリを自動検出。
- 期待値: `input/{case}/spec.json` をgoldenとして使用。

実行:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval-file local-dev/seed/ocr-live-openrouter-survey.php --path=/var/www/html --allow-root
```

結果は `local-dev/seed/ocr-fixtures/live-openrouter/report.json` と `recordings/*.json` に保存される。recording未作成case、またはbackend hash変更後のcaseは通常実行でもOpenRouter APIを叩く。

## 期待specとの比較時の注意

- `spec.json` の `chars` はオブジェクト配列。比較時は `val` だけを見る。
- 名前はHTML entity差がある。
  - `&amp;` と `&` は比較前にnormalizeする。
- OCR由来の表記揺れは区別する。
  - `イィ` vs `イイ`
  - `言祓` vs `言祝`
  - `奏でる` vs `奏は`
- 属性/種族はslugで比較する。
- わざ/すごわざ名はUI文言除去後で比較する。
- raw本文があるが構造化できていないものは、`rawあり/構造化未対応` と記録する。

## 実装修正時の方針

- 最小の正しい変更を優先する。
- OCR抽出の誤爆を再発させない。
- 使用可能文字は「単一ひらがな」に厳格制限する方針を維持する。
- quoted text全体から拾う処理を広げる場合は、文脈制限を必ず入れる。
- ACF field構造を変更/拡張する前に、ACF JSONと既存表示側を読む。
- 保存できない/確信できない値はraw + warningに残す。
- API keyや画像を永続保存しない。
- ユーザの未追跡ファイルや別作業差分は触らない。

## 検証コマンド

PHP lint:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 bash -lc 'for f in lib/acf/ocr/*.php lib/acf/ocr/structure/*.php local-dev/seed/seed.php; do php -l "$f" || exit 1; done'
```

JS syntax:

```bash
node --check lib/acf/ocr/acf-ocr-draft.js
```

OCR smoke:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval-file local-dev/seed/ocr-smoke.php --path=/var/www/html --allow-root
```

seed反映:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval-file local-dev/seed/seed.php --path=/var/www/html --allow-root
```

追加term確認例:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval '$checks=[["attribute","void"],["attribute","heaven"],["species","demon"],["species","beast"],["species","spirit"],["available_moji","ょ"],["available_moji","ゅ"]]; foreach ($checks as $check) { [$tax,$slug]=$check; $term=get_term_by("slug",$slug,$tax); if (!$term || is_wp_error($term)) { WP_CLI::error("missing $tax:$slug"); } } WP_CLI::success("seed terms ok");' --path=/var/www/html --allow-root
```

保存済みOCR draft replay例:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval '$ids=get_posts(["post_type"=>"character","post_status"=>"draft","meta_key"=>"_koto_ocr_normalized","numberposts"=>10,"fields"=>"ids"]); foreach ($ids as $id) { $normalized=json_decode((string)get_post_meta($id,"_koto_ocr_normalized",true), true); if (!$normalized) continue; $extracted=koto_ocr_extract_fields($normalized); $built=koto_ocr_build_spec_fragments($extracted); $spec=$built["fragment"] ?? []; WP_CLI::line(wp_json_encode(["id"=>$id,"title"=>get_the_title($id),"chars"=>$spec["chars"]??[],"attribute"=>$spec["attribute"]??null,"species"=>$spec["species"]??null,"waza"=>$spec["waza"]["name"]??null,"sugowaza"=>$spec["sugowaza"]["name"]??null], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }' --path=/var/www/html --allow-root
```

## PR/Push方針

現在の最新ローカルcommit `ad14e40` はまだpushしていない。

PR #12 を更新するなら、過去の運用では次のremote branchを使っていた。

```text
origin/feat/spec-json-schema
```

注意:

- `gh` は別ユーザでログインしている可能性があるため、そのまま使わない。
- push前には必ず `git status`, `git diff`, `git log --oneline -10` を確認する。
- force pushが必要な運用だった場合も、ユーザ確認を取る。

## 完了条件

最低限:

- `ad14e40` 後の全キャラsurvey結果がtruncateなしで残っている。
- キャラ別/項目別の比較表が作られている。
- `nhelp_grand` の413が「再現・原因記録」または「対策実装済み」になっている。
- 追加修正をした場合はlint/smokeが通っている。

理想:

- `comforgo`, `danto`, `itadori_okkotsu_dream`, `karen_ibutsu`, `mashiro7`, `yomitaru_light`, `yomitaru_water` で、属性/種族/わざ名/すごわざ名の主要項目が概ね取れる。
- 使用可能文字は誤爆なし。未取得はOCR raw不足として明確に分類されている。
- 次のPR更新対象commitが整理されている。
