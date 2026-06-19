# OCR Web UI E2E Test Report 2026-06-19

## 目的

画像OCRから作成する `character` 下書きについて、実際のWeb UI操作に近い経路で、どの情報がACFへ正しく保存・表示され、どこが未達かを確認した。

## 実行環境

- ローカルWordPress: `http://localhost:8080`
- container: `devcontainer-wordpress-1`
- branch: `feat/ocr-draft-import`
- OCR model: `google/gemini-3.1-flash-lite`
- OpenRouter API key: devcontainer内で設定済み
- 実操作ツール: `bunx agent-browser`
- 対象fixture: `local-dev/seed/ocr-fixtures/live-openrouter/input/*`
- taxonomy terms: 本番公開REST由来の `local-dev/seed/production-terms.json` をlocal seedへimport済み

## 直近の関連コミット

- `df9c14f add: 本番taxonomy termsをローカルseedへ同期する`
- `32c736b fix: OCRとくせい自動入力の白紙行を避ける`
- `e8a07df fix: OCR下書きで保存済みACF欄を明示する`
- `582af05 fix: OCR下書きのACF行を初期表示で展開する`

## 実操作の方法

基本的には以下の実ブラウザ操作で確認した。

1. `bunx agent-browser open http://localhost:8080/wp-login.php`
2. `admin / admin` でログイン
3. `http://localhost:8080/wp-admin/admin.php?page=koto-acf-editor` を開く
4. `OCRから新規下書きを作成` を展開
5. OCR対象画像を `input[data-koto-ocr-input]` に投入
6. `OCR実行して下書きを作成` をクリック
7. 作成された下書きのACFをWP-CLIと実ブラウザのDBエディタ表示で確認

画像投入は主にブラウザ内JavaScriptで同一オリジンURLからfixture画像を `fetch` し、`File` として `DataTransfer` に詰めてfile inputへ設定した。これは実ブラウザの `change` event、ブラウザ側resize、UIの `fetch(admin-ajax.php)` 経路を通すため。

一部ケースではこの方式でfixture画像URLが403になったため、`agent-browser upload` でローカルファイルを直接file inputへ入れる方式も試した。

## 作成された下書き

| case | postId | title | Web UI OCR作成 |
|---|---:|---|---|
| `yomitaru_water` | 701 | 依依たる想い・イィタル&ヨヨヨミ | 成功 |
| `comforgo` | 702 | 激熱の音言神・コンフォーゴ | 成功 |
| `danto` | 703 | 騒擾の音聖神・ダント | 成功 |
| `itadori_okkotsu_dream` | 704 | 虎杖悠仁vs乙骨憂太 | 成功 |
| `karen_ibutsu` | 705 | 孤高ナル威圧・カレン(音遺物) | 成功 |
| `mashiro7` | 706 | 言祝ぎの祝典に・ましろ(7th Anniv.) | 成功 |
| `yomitaru_light` | 707 | 世世に廻る・ヨヨヨミ&イィタル | 成功 |
| `nhelp_grand` | なし | なし | 失敗 |
| `mashiro_grand` | なし | なし | 失敗 |

## Web UI表示についての重要な観察

以前は、ACF repeater行が折りたたまれていたため、わざ・とくせい欄が「白紙」に見えた。

`582af05` により、OCR下書きの場合はDBエディタ表示時に左フォーム内のACF repeater / accordion行を自動展開するようにした。実ブラウザで以下を確認済み。

- `postId=696` の `わざ、すごわざ` グループで、初期表示から `わざの名前: 同じ師を持つ二人` と詳細行が展開表示される
- `postId=696` の `とくせい` グループで、`スマッシュブレイカー`、`その他とくせい` raw文言、`呪術廻戦` などが展開表示される

## 成功7ケースの詳細結果

### `yomitaru_water` / postId 701

正しく取得・保存できたもの:

- 名前: OK
- 属性: `water` OK
- 種族: `spirit` OK
- わざ名: `なかよしこよし！` OK
- すごわざ名: `次に繋ぐ言祓` OK
- わざ/すごわざの攻撃行: ACFへ構造化保存あり
- とくせい: 行として保存あり。`first_trait_loop=4`, `second_trait_loop=6`

問題:

- 使用文字 expected `い, よ, ょ, す, ず, し` に対し、実保存は `い, よ, す, ず, し`。`ょ` が欠落
- すごわざ条件は `4文字以上` のみ。開始文字などは不足
- とくせい1は大半が `その他` raw
- `second_trait_loop` の一部に `gimmick:` 空表示が残る
- 祝福は `0件`

### `comforgo` / postId 702

正しく取得・保存できたもの:

- 名前: OK
- 属性: `fire` OK
- 種族: `dragon` OK
- わざ名: `奏でるは激熱の旋律` OK
- すごわざ名: OCR上は `激震のオーバービート`
- わざ/すごわざ攻撃行: ACFへ構造化保存あり
- とくせい: `first_trait_loop=1`, `second_trait_loop=6`
- ギミック例: `ヒールブレイカー`, `弱体ガード`

問題:

- 使用文字 expected `こ, ご, け, げ` に対し、実保存は `こ, け, げ`。`ご` が欠落
- expectedのすごわざ名は `激震のオーバーヒート` だがOCRは `激震のオーバービート`
- すごわざ開始文字 expectedに近いが `ご` が欠落
- とくせい内のグループ所属文言は `その他` raw
- 祝福は `0件`

### `danto` / postId 703

正しく取得・保存できたもの:

- 名前: OK
- 属性: `wood` OK
- 種族: `artifact` OK
- わざ攻撃行: ACFへ構造化保存あり
- とくせい: `first_trait_loop=2`, `second_trait_loop=6`

問題:

- 使用文字 expected `た, だ, そ, ぞ, ん` に対し、実保存は `た, ん, そ, ぞ`。`だ` が欠落
- わざ/すごわざ分類が崩れている
- `waza_name` は `第四楽章・騒乱の調べ`、expected waza nameは `騒擾`
- `sugowaza_name` が空。expectedは `第四楽章・騒乱の調べ`
- `sugowaza_group_loop` が実質空
- 祝福は `0件`

### `itadori_okkotsu_dream` / postId 704

正しく取得・保存できたもの:

- 名前: OK
- 属性: `void` OK
- 種族: `hero` OK
- わざ名: `俺の役割` OK
- すごわざ名: `同じ師を持つ二人` OK
- わざ/すごわざ攻撃行: ACFへ構造化保存あり
- とくせい: `first_trait_loop=5`, `second_trait_loop=8`
- `コピーガード`, `チェンジガード`, `スマッシュブレイカー` はterm解決して保存
- `呪術廻戦` は本番term import後にterm解決して保存

問題:

- 使用文字 expected `い, ゆ, ゅ, お, り, か` に対し、実保存は `ゆ, お, り, ゅ, か`。`い` が欠落
- とくせい1はほぼ `その他` raw
- 祝福は `0件`

### `karen_ibutsu` / postId 705

正しく取得・保存できたもの:

- 名前: OK
- 属性: `wood` OK
- 種族: `beast` OK
- 使用文字: expectedを満たす。重複は正規化される
- わざ名: `勇言隊の戦士` OK
- すごわざ名: `音遺物「コラーヴ」` OK
- ランダム対象攻撃: ACFへ構造化保存あり
- とくせい: `first_trait_loop=1`, `second_trait_loop=3`
- `ウォールブレイカー`, `スーパーヒールブレイカー` 保存あり

問題:

- とくせい1の複雑なグループ条件は `その他` raw
- すごわざ条件は `4文字以上` のみで追加条件は不足気味
- 祝福は `0件`

### `mashiro7` / postId 706

正しく取得・保存できたもの:

- 名前: OK
- 属性: `heaven` OK
- 種族: `god` OK
- 使用文字: expected `ま, し, ろ, う` を満たす
- わざ名: `一歩ずつ、進化を` OK
- すごわざ名: `言祝ぎのケーキ` OK
- とくせい: `first_trait_loop=3`, `second_trait_loop=2`
- `フリーズブレイカー`, `スマッシュブレイカー` 保存あり

問題:

- わざ/すごわざの強さ表記が弱めに入る箇所あり。`sugowaza` は `attack_prefix=none`
- とくせい1の複雑条件は `その他` raw
- 祝福は `0件`

### `yomitaru_light` / postId 707

正しく取得・保存できたもの:

- 名前: OK
- 属性: `light` OK
- 種族: `demon` OK
- わざ名: `ずーっと、ずっと` OK
- すごわざ名: `黄泉を照らす言祓` OK
- わざ/すごわざ攻撃行: ACFへ構造化保存あり
- とくせい: `first_trait_loop=4`, `second_trait_loop=6`
- `フリーズブレイカー` 保存あり

問題:

- 使用文字 expected `よ, ょ, い, す, ず, し` に対し、実保存は `よ, す, ず`。`ょ, い, し` が欠落
- `second_trait_loop` の一部に `gimmick:` 空表示が残る
- とくせいの多くが `その他` raw
- 祝福は `0件`

## E2E失敗ケース

### `nhelp_grand`

実ブラウザUIで下書き作成に至らなかった。

試行内容:

- ブラウザ内 `fetch` でfixture画像を `File` 化してinputへ投入
- `agent-browser upload` でローカルPNGを直接投入
- `/tmp/opencode/ocr-e2e-resized/nhelp_grand/*.jpg` に事前縮小して投入

観察:

- ブラウザ内 `fetch` では画像URLが403になり、HTMLを画像として送ってしまい、サーバー側で `対応していない画像形式です。PNG/JPEG/WebPのみ利用できます。`
- ローカルファイルuploadではブラウザ/CDATAが長時間固まる
- 縮小JPEG uploadでは `Failed to fetch`

未確認:

- OCR精度そのものは今回のWeb UI E2Eでは評価できていない
- 既存recording/cacheベースの評価は別途可能

### `mashiro_grand`

`nhelp_grand` と同様、実ブラウザUIで下書き作成に至らなかった。

観察:

- ブラウザ内 `fetch` では画像URLが403
- ローカルファイルuploadではブラウザ/CDPが詰まる
- 縮小JPEG uploadでは `Failed to fetch`

未確認:

- OCR精度そのものは今回のWeb UI E2Eでは評価できていない

## 横断的に正しく取れている情報

- 名前: 成功7ケースすべてOK
- 属性: 成功7ケースすべてOK
- 種族: 成功7ケースすべてOK
- わざ名: 多くはOK。`danto` は分類崩れでNG
- すごわざ名: 多くはOK。`danto` はNG、`comforgo` は表記差あり
- わざ/すごわざの基本攻撃行: 多くはACFへ構造化保存される
- ギミック系とくせい: 本番term同期後はかなり改善。例: `コピーガード`, `チェンジガード`, `スマッシュブレイカー`, `フリーズブレイカー`, `ウォールブレイカー`, `スーパーヒールブレイカー`

## 横断的に弱い情報

- 祝福: 全成功ケースで `blessing_trait_loop=0`
- 使用文字: 小書き文字、濁点、メイン画面以外由来の文字が抜ける
- すごわざ条件: `char_count` は取れるが、開始文字・複数条件は落ちやすい
- 複雑なとくせい: CSV未対応が多く `その他` rawに落ちる
- わざ/すごわざ分類: `danto` のように通常わざ/すごわざのmodal順や名称分類が崩れるケースあり
- 数値倍率: 現仕様では安全のため空欄。これは意図的だが、ユーザー観点では未入力に見える

---

# 改善案

## 0. 次回セッション開始時に読むべきもの

- `AGENTS.md`
- このファイル: `260619-ocr-e2e-test-report.md`
- 関連コード:
  - `lib/acf/ocr/koto-ocr.php`
  - `lib/acf/ocr/backends/openrouter-vlm.php`
  - `lib/acf/ocr/field-extractor.php`
  - `lib/acf/ocr/spec-json-acf-adapter.php`
  - `lib/acf/ocr/draft-persister.php`
  - `lib/acf/acf-auto-input.php`
  - `lib/acf/acf-editor.js`
  - `local-dev/seed/ocr-tests.php`
  - `local-dev/seed/ocr-live-openrouter-survey.php`
  - `local-dev/seed/production-terms.json`

## 1. 最優先: Web UI E2E失敗2ケースの安定化

対象:

- `nhelp_grand`
- `mashiro_grand`

現象:

- ブラウザ内 `fetch` でfixture画像URLが403
- `agent-browser upload` 原寸PNGではブラウザ/CDPが固まりやすい
- 事前縮小JPEGでも `Failed to fetch`

推定原因:

- fixture画像の一部がWordPress/Apacheから直接配信できない権限/パス状態
- 画像枚数とサイズが大きく、ブラウザ側resizeまたはupload fetchが詰まる
- `agent-browser` CDP timeoutとUI fetch timeoutが絡んでいる可能性

改善案:

1. fixture画像をWeb配信用の安全な一時ディレクトリへコピーするE2E helperを作る。
2. `wp-content/uploads/ocr-e2e/{case}/...` のようなApacheが確実に返す場所を使う。
3. ブラウザ側でURL fetchする場合は、事前にHTTP 200・正しいMIMEを検証する。
4. 大きいfixtureはE2E用に事前縮小fixtureを生成する。
5. OCR UIの `resizeIfNeeded()` のエラー/進捗を表示し、`Failed to fetch` の原因を区別する。

次回作業のゴール:

- 9/9 casesでWeb UI E2Eから下書き作成まで完了する
- 失敗時は `data-koto-ocr-result` に原因が明確に表示される

## 2. 祝福自動入力の実装

現状:

- `blessing_trait_loop` は全成功ケースで0件
- `acf-auto-input.php` では `祝福` が `return null` になっている
- CSVにも祝福専用の実装が薄い

改善案:

1. `koto_parse_text_by_type()` の `祝福` を実装する。
2. まずは以下だけ対応する。
   - `HP基礎値中UP`
   - `属性キラー+`
   - `種族キラー+`
   - `属性ダメージ軽減+`
   - `文字変換「...」`
   - `スーパー化` 系
   - `バリア貫通xx%`
   - `自身クリティカル発生率xx%UP`
3. 祝福専用のCSV行を追加するか、OCR専用の小さなparserを `lib/acf/ocr` 側に置く。
4. `blessing_trait_loop` のACF構造は `acf-json/group_693971a11a6b2.json` を参照。

注意:

- 祝福はレベル/必要ptの概念があるため、通常とくせいと完全同一扱いできない。
- 最初はraw保存より1段階良い「その他」行保存でも価値がある。

## 3. 使用文字抽出の補強

弱い例:

- `yomitaru_water`: `ょ` 欠落
- `comforgo`: `ご` 欠落
- `danto`: `だ` 欠落
- `itadori_okkotsu_dream`: `い` 欠落
- `yomitaru_light`: `ょ`, `い`, `し` 欠落

改善案:

1. main画面の `main_char_ball` だけでなく、以下からunionを取る。
   - main preview text
   - trait `文字変換に「...」`
   - blessing `文字変換「...」`
   - sugowaza conditionの開始文字
   - golden-like spec_jsonがあるfixtureではテスト補助
2. `koto_ocr_extract_chars_from_image()` / `available-moji.php` の正規化を強化する。
3. 小書き文字 `ゃゅょ` と濁点/半濁点を落とさないテストを追加する。
4. 重複文字はACF上で重複保存しないが、OCR評価ではmissingを見られるようにする。

## 4. わざ/すごわざ分類崩れの修正

代表:

- `danto` で `waza_name` / `sugowaza_name` が入れ替わり・欠落気味

現状の関連コード:

- `field-extractor.php` の `skill_modal_order:first/second` 補正
- `screen-classifier.php`
- `backends/openrouter-vlm.php` prompt

改善案:

1. main画面の `main_waza_preview` / `main_sugowaza_preview` を名前照合に使う。
2. modal headerがmain previewのどちらに近いかで `waza` / `sugowaza` を再分類する。
3. 「最初のskill modalはwaza、2番目はsugowaza」だけに頼らない。
4. `danto` をfixture regressionとして追加する。

## 5. とくせいCSV対応の拡充

現状:

- `その他` rawに落ちることで白紙は避けられる
- ただし、構造化率はまだ低い

優先して追加したい文言:

- `属性ATKxxx%UP`
- `種族ATKxxx%UP`
- `グループHPxxx%・ATKxxx%UP`
- `デッキ内全員のATKを「...」×xx%UP`
- `属性相性不利な敵に対して...無効`
- `ドロー時にHPをxx%回復`
- `コンボ+`
- `このコトダマンは「...」のグループに属しているものとして扱われる`
- `モードシフト`, `変身`, `黒閃`

注意:

- CSVの `{$character_target}` / `{$whose_trait}` / `{$affiliation}` 変数処理は既にある。
- `production-terms.json` により本番termはローカルで解決できるようになった。
- termが見つからないとACF taxonomy fieldが空に見えるため、term同期は前提。

## 6. E2Eレポート生成の自動化

今回の集計はWP-CLI ad hocで実施した。

改善案:

1. `local-dev/seed/ocr-webui-e2e-report.php` のような専用スクリプトを作る。
2. 入力:
   - case名
   - postId
   - expected `spec.json`
3. 出力:
   - Markdown
   - JSON
4. 比較項目:
   - name
   - attribute
   - species
   - chars missing/extra
   - waza name/detail
   - sugowaza name/detail/conditions
   - trait row count and summaries
   - blessing row count
   - warnings
5. Web UI実行ログも保存する。

## 7. 実ブラウザE2Eで使った情報

ログイン:

- URL: `http://localhost:8080/wp-login.php`
- user: `admin`
- password: `admin`

DBエディタ:

- URL: `http://localhost:8080/wp-admin/admin.php?page=koto-acf-editor`

OCR panel selectors:

- panel: `[data-koto-ocr-panel]`
- toggle: `[data-koto-ocr-toggle]`
- file input: `input[data-koto-ocr-input]`
- submit: `[data-koto-ocr-submit]`
- result: `[data-koto-ocr-result]`

DB editor group keys:

- 基本データ: `group_69204fa4dd82e`
- わざ、すごわざ: `group_6937900895bf1`
- とくせい: `group_693790ee221c3`
- 祝福: `group_693971a11a6b2`

Fixture dirs:

- `local-dev/seed/ocr-fixtures/live-openrouter/input/{case}/`

Cases:

- `comforgo`
- `danto`
- `itadori_okkotsu_dream`
- `karen_ibutsu`
- `mashiro7`
- `mashiro_grand`
- `nhelp_grand`
- `yomitaru_light`
- `yomitaru_water`

Successful E2E post mapping from this run:

- `yomitaru_water` -> `701`
- `comforgo` -> `702`
- `danto` -> `703`
- `itadori_okkotsu_dream` -> `704`
- `karen_ibutsu` -> `705`
- `mashiro7` -> `706`
- `yomitaru_light` -> `707`

Failed E2E cases:

- `nhelp_grand`
- `mashiro_grand`

## 8. 次回の推奨作業順

1. `nhelp_grand` / `mashiro_grand` のWeb UI upload失敗を直す。
2. 9/9 casesでWeb UI E2Eの下書き作成を通す。
3. E2Eレポート生成スクリプトを作る。
4. 祝福parserを最小実装する。
5. 使用文字missingを減らす。
6. `danto` のわざ/すごわざ分類を直す。
7. とくせいCSV対応を増やして `その他` raw比率を下げる。

---

# 追記 2026-06-20

## 実施した改善

- `local-dev/seed/ocr-webui-e2e-prepare-images.php` を追加し、fixture PNGを `/tmp/kotodaman-ocr-e2e/{case}/` へE2E用JPEGとして準備できるようにした。
- OCR UIの進捗/エラー表示を改善し、画像準備中・送信中・fetch失敗箇所が分かるようにした。
- `koto_parse_text_by_type()` の `祝福` 分岐を実装し、少なくとも `other_traits` raw行として `blessing_trait_loop` に保存されるようにした。

## Web UI E2E再実行結果

画像準備コマンド:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval-file local-dev/seed/ocr-webui-e2e-prepare-images.php cases=nhelp_grand,mashiro_grand max_side=1200 quality=78 --path=/var/www/html --allow-root
docker cp devcontainer-wordpress-1:/tmp/kotodaman-ocr-e2e /tmp/opencode/kotodaman-ocr-e2e
```

E2Eでは `/tmp/opencode/kotodaman-ocr-e2e/{case}/*.jpg` を `bunx agent-browser upload` で `input[data-koto-ocr-input]` に投入する。

結果:

| case | postId | title | Web UI OCR作成 | 祝福保存 |
|---|---:|---|---|---:|
| `mashiro_grand` | 710 | 言の葉を紡ぐ・ましろ | 成功 | 6件 |
| `nhelp_grand` | 711 | おたすけ天使です！・ンヘループ(天佑) | 成功 | 6件 |

これにより、前回失敗していた2ケースを含めて9/9ケースでWeb UI E2Eから下書き作成まで到達した。

確認した保存概要:

- `mashiro_grand`: 使用文字 `ま, ろ, う, ん, し, い`、わざ `ことばの光たち`、すごわざ `真白き願い`、とくせい1 `2件`、とくせい2 `9件`、祝福 `6件`
- `nhelp_grand`: 使用文字 `ん, た, す, け, お, と`、わざ `職位が天使を育てる？`、すごわざ `成り行き任せで大団円！`、とくせい1 `6件`、とくせい2 `19件`、祝福 `6件`

## 残課題

- 祝福は現状raw fallback中心。`HP基礎値UP`、属性/種族キラー、ダメージ軽減、コンボ+、バリア貫通などを構造化する余地がある。
- 祝福OCR文言に「HP基礎値UPHPの基礎値を200UP」のようなラベル+説明の結合が残る。表示上は空欄を避けられるが、文言整形は追加改善対象。
- 既存成功7ケースの祝福保存は、修正後に再実行すれば改善される見込み。今回の実ブラウザ再実行は失敗2ケースに限定した。

---

# 追記 2026-06-20 再実行と自動レポート

## 自動レポート生成

`local-dev/seed/ocr-webui-e2e-report.php` を追加し、`case:postId` の対応を渡すと以下を自動比較してMarkdown/JSONを出力するようにした。

- expected `spec.json` と保存済みACFの名前・属性・種族・使用文字
- わざ名、すごわざ名、わざ/すごわざ行数、すごわざ条件行数
- とくせい1/2行数、祝福行数、祝福サンプル
- OpenRouter usage/cost（レスポンスに含まれる場合）

実行例:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval-file local-dev/seed/ocr-webui-e2e-report.php cases=yomitaru_water:712,comforgo:713,danto:714,itadori_okkotsu_dream:715,karen_ibutsu:716,mashiro7:717,yomitaru_light:718,mashiro_grand:719,nhelp_grand:720 out=/tmp/kotodaman-ocr-e2e-report --path=/var/www/html --allow-root
```

## 修正後Web UI E2E 9ケース再実行

画像準備は `local-dev/seed/ocr-webui-e2e-prepare-images.php` で `/tmp/kotodaman-ocr-e2e` に生成し、ホスト側 `/tmp/opencode/kotodaman-ocr-e2e` へ `docker cp` した上で `bunx agent-browser upload` を使った。

| case | postId | title | attr | species | chars | waza | sugowaza | traits | blessing | cost USD |
|---|---:|---|---|---|---|---|---|---:|---:|---:|
| `yomitaru_water` | 712 | OK | OK | OK | missing `ょ,し` | OK | OK | 4/6 | 6 | 0.006314 |
| `comforgo` | 713 | OK | OK | OK | missing `ご` | OK | NG | 1/6 | 5 | 0.007108 |
| `danto` | 714 | OK | OK | OK | OK | OK | OK | 2/6 | 6 | 0.005371 |
| `itadori_okkotsu_dream` | 715 | OK | OK | OK | missing `ゅ` | OK | OK | 5/8 | 6 | 0.008564 |
| `karen_ibutsu` | 716 | OK | OK | OK | OK | OK | OK | 1/3 | 6 | 0.005904 |
| `mashiro7` | 717 | NG | OK | OK | OK | OK | OK | 3/2 | 6 | 0.004759 |
| `yomitaru_light` | 718 | OK | OK | OK | missing `ょ,い,し` | OK | OK | 4/6 | 6 | 0.006358 |
| `mashiro_grand` | 719 | OK | OK | OK | OK | OK | OK | 2/9 | 6 | 0.007340 |
| `nhelp_grand` | 720 | OK | OK | OK | OK | OK | OK | 6/19 | 6 | 0.013032 |

集計:

- 作成成功: 9/9
- 名前OK: 8/9（`mashiro7` が `言祝ぎの祝典に` -> `言祝ぎの祝典`）
- 属性OK: 9/9
- 種族OK: 9/9
- 使用文字complete: 5/9
- わざ名OK: 9/9
- すごわざ名OK: 8/9（`comforgo` が `オーバーヒート` -> `オーバービート`）
- 祝福非0件: 9/9
- OpenRouter requests: 95
- OpenRouter cost: 0.06475125 USD

本セッション開始時との差分:

- Web UI E2E作成成功が 7/9 -> 9/9 に改善
- 祝福保存が 0/7（成功済みケース）かつ2ケース未確認 -> 9/9 非0件に改善
- `danto` のわざ/すごわざ分類は、今回の再実行では expected 名と一致
- 使用文字missingはまだ残るが、`danto` は `だ` 欠落が解消

---

# 追記 2026-06-20 mashiro_grand 手動E2E指摘後の修正

手動で `mashiro_grand` を再実行したところ、`#721` で以下の問題を確認した。

- 属性が `冥` ではなく `天` として保存された
- わざ/すごわざが入れ替わった
- レアリティ、CV、EXスキルが未保存だった
- 自動レポートがレアリティ/CV/EX/効果詳細を見ておらず、実用上の未入力を検知できていなかった

修正内容:

- 基本属性/種族/名前/レアリティは `main_attribute_icon`, `main_species_icon`, `main_rarity_text`, `main_waza_preview`, `main_sugowaza_preview` を持つ信頼できるmain画面だけから採用する
- わざ/すごわざmodalは画像順ではなく、main画面の `main_waza_preview` / `main_sugowaza_preview` とmodal headerを照合して分類する
- profile画面を追加し、`cv_text` から `voice_actor` を保存する
- EXスキルは `modal_body` から `ex_skill_label`, `ex_skill_name`, `ex_skill_discription` へ最低限保存する
- わざ/すごわざ効果に `ATKを強化` -> `atk_buff`、`ダメージを軽減` -> `def_buff` を最低限保存する
- 自動レポートに `rarity`, `cv`, `EX`, 効果値空欄数を追加する

最終確認:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval-file local-dev/seed/ocr-webui-e2e-report.php cases=mashiro_grand:725 out=/tmp/kotodaman-ocr-e2e-report-mashiro-final --path=/var/www/html --allow-root
```

| case | postId | title | attr | species | rarity | cv | chars | waza | sugowaza | values | traits | blessing | EX | cost USD |
|---|---:|---|---|---|---|---|---|---|---|---|---:|---:|---|---:|
| `mashiro_grand` | 725 | OK | OK | OK | OK | OK | OK | OK | OK | 2 blank | 2/9 | 6 | OK | 0.006883 |

保存確認:

- `attribute`: `void/冥`
- `species`: `god/神`
- `rarity`: `grand/グランド`
- `voice_actor`: `青山吉能`
- `name_ruby`: `ましろ`
- `waza_name`: `ことばの光たち`
- `waza_group_loop`: 1行、詳細に `atk_buff` と `attack`
- `sugowaza_name`: `真白き願い`
- `sugowaza_group_loop`: 1行、詳細に `atk_buff`, `def_buff`, `attack`
- `blessing_trait_loop`: 6行
- `ex_skill_label`: `新たな「ことば」の力`
- `ex_skill_name`: `全文字付与`
- `ex_skill_discription`: 保存あり

残る注意点:

- `values 2 blank` は攻撃倍率値で、現仕様では安全のため未入力にしている。
- 祝福はまだraw fallback中心で、文言結合や構造化は追加改善対象。
