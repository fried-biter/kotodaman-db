# OCR prompt改善 次セッション計画

## 目的

実画像をOpenRouter OCRへ投げた結果を元に、VLM promptとbackend処理を改善する。通常の回帰では保存済みrecordingを使い、promptまたはbackend sourceが変わった場合だけlive OCRを再実行する。

初期ゴールは「OpenRouterの生OCR JSONが安定してparseでき、抽出モジュールへ入れた時に主要項目のmatch率が上がる」こと。ACF schema拡張や既存投稿への反映は対象外。

## 現在のbranch / commit

- repo: `/home/colon/temp/kotodaman-db`
- branch: `feat/ocr-draft-import`
- 最新commit: `9ca6de8 test: 実画像OpenRouter OCRの記録と比較を追加`

直近のOCR関連commit:

```text
9ca6de8 test: 実画像OpenRouter OCRの記録と比較を追加
7412ea9 test: OCR読み取りを7キャラのmatrixで検証
74cf350 fix: OCRすごわざ条件の開始文字をACFへ保存
0a7dcb4 refactor: OCR抽出ヘルパーを責務別に分割
950e66b test: OCR下書き変換のキャラ単位契約を追加
201f45a test: OCR抽出モジュールの基礎ケースを固定
a890da7 test: OCR抽出のWP-CLIテスト基盤を追加
```

## 重要な前提

- WordPress/PHP/WP-CLI作業はdevcontainer内で実行する。
- devcontainer名: `devcontainer-wordpress-1`
- WordPress path: `/var/www/html`
- devcontainer workspace: `/var/www/html/wp-content/themes/cocoon-child-master`
- OpenRouter API keyはコンテナ環境に存在する。ブラウザへ渡さない。
- GitHub操作時はローカル`gh`のログインユーザに注意する。
- 未追跡ファイルが多数あるが、この作業では原則触らない。

## 今回追加した評価基盤

### PoC非依存の手書きfixtureテスト

- runner: `local-dev/seed/ocr-tests.php`
- fixture root: `local-dev/seed/ocr-fixtures/`
- 7キャラmatrix: `local-dev/seed/ocr-fixtures/characters/reading-matrix.json`

実行:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval-file local-dev/seed/ocr-tests.php --path=/var/www/html --allow-root
```

最新結果:

```text
Success: 8 OCR test(s) passed, 0 pending.
```

このテストは実画像OCRではなく、プラグインが受け取るべき`normalized OCR JSON`契約に対して、抽出モジュールが正しく読むかを固定する。

### 実画像OpenRouter OCR recording / report

- script: `local-dev/seed/ocr-live-openrouter-survey.php`
- input fixture: `local-dev/seed/ocr-fixtures/live-openrouter/input/`
- raw recording: `local-dev/seed/ocr-fixtures/live-openrouter/recordings/*.json`
- report: `local-dev/seed/ocr-fixtures/live-openrouter/report.json`

実画像とgolden `spec.json` は外部repoではなく、このrepo内の `local-dev/seed/ocr-fixtures/live-openrouter/input/` に同梱する。survey対象は固定7件ではなく、`input/{case}/spec.json` とOCR可能画像を持つディレクトリから自動検出する。

実行:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval-file local-dev/seed/ocr-live-openrouter-survey.php --path=/var/www/html --allow-root
```

2回目以降は、recording内の以下が一致する場合APIを叩かずcacheを使う。新規caseやbackend hash変更後のcaseは通常実行でもlive OCRされるため、APIコストに注意する。

- `metadata.model`
- `metadata.backend_source_hash`

強制再取得する場合:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval-file local-dev/seed/ocr-live-openrouter-survey.php refresh=1 --path=/var/www/html --allow-root
```

注意: promptは `lib/acf/ocr/backends/openrouter-vlm.php` にあるため、prompt変更時はbackend hashが変わり、通常実行でも再OCRされる。

## 実OCR結果の概要

初期recordingがある対象7キャラ:

- `comforgo`
- `danto`
- `itadori_okkotsu_dream`
- `karen_ibutsu`
- `mashiro7`
- `yomitaru_light`
- `yomitaru_water`

repo内fixtureには外部 `kotodaman-ss-to-spec/test-data` を丸ごと移植済み。`spec.json` を持つ `mashiro_grand` / `nhelp_grand` なども今後のsurvey対象になる。

結果一覧:

| case | result | 主な差分 |
|---|---:|---|
| `comforgo` | `6/12 matched` | 文字`ご`欠落、わざ名`奏でる`→`奏では`、すごわざ名`オーバーヒート`→`オーバービート`、攻撃対象/強度差分 |
| `danto` | error | OpenRouter応答がJSON parse失敗 |
| `itadori_okkotsu_dream` | error | OpenRouter応答がJSON parse失敗 |
| `karen_ibutsu` | `8/12 matched` | 種族未取得、文字が`こ`のみ、攻撃強度が期待より強く読まれる |
| `mashiro7` | `9/12 matched` | 種族未取得、文字が`ろ`のみ、すごわざ攻撃対象/強度差分 |
| `yomitaru_light` | `8/12 matched` | 種族未取得、文字一部欠落、攻撃強度/対象差分 |
| `yomitaru_water` | `6/12 matched` | 名前`イィ`→`イイ`、種族未取得、文字一部欠落、すごわざ名`言祓`→`言祝`、攻撃強度差分 |

## 実OCRで見えた問題分類

### 1. JSON parse失敗

該当:

- `danto`
- `itadori_okkotsu_dream`

症状:

- OpenRouter応答の`choices[0].message.content`がJSON風文字列だが、`json_decode()`できない。
- エラーメッセージ上は途中まで正しい`images`配列に見える。

次に見るファイル:

- `local-dev/seed/ocr-fixtures/live-openrouter/recordings/danto.json`
- `local-dev/seed/ocr-fixtures/live-openrouter/recordings/itadori_okkotsu_dream.json`
- `lib/acf/ocr/backends/openrouter-vlm.php`

改善候補:

- promptで「JSON以外の前後文字、Markdown、途中省略、末尾コメント禁止」を強める。
- `response_format: json_object`は既に使用中。出力schemaをさらに小さくし、長すぎる`fullText`を避ける。
- backend側でJSON salvageを追加する。例: 最初の`{`から最後の`}`まで切り出す。ただし壊れたJSONを無理に採用する場合はwarningを残す。
- 画像枚数が多い場合はrequestを分割し、1 responseあたりのJSON量を減らす。

優先度: 最優先。parseできないと後続モジュール評価に入れない。

### 2. 種族未取得

該当:

- `karen_ibutsu`: expected `beast`, actual empty
- `mashiro7`: expected `god`, actual empty
- `yomitaru_light`: expected `demon`, actual empty
- `yomitaru_water`: expected `spirit`, actual empty

原因候補:

- promptは`main_species_icon`を要求しているが、実OCRではblock regionに出ていないケースが多い。
- `fullText`に種族名が十分入っていない。
- `field-extractor`は`種族: X`や`X種族`文脈を主に見るため、孤立icon OCRに弱い。

prompt改善候補:

- main画面では「属性アイコン」「種族アイコン」を必ず独立blockにするよう明記する。
- `main_species_icon` blockのtextは1文字でもよいので、龍/神/魔/獣/物/英/霊/妖を必ず読むよう指示する。
- region名を厳密に指定する。`layout.main.species_icon`のようなPoC名ではなく、このプラグインの`main_species_icon`へ寄せる。

抽出側改善候補:

- `main_species_icon` regionから孤立文字を安全にmappingする。
- `main`画面の上部アイコン近辺だけ孤立文字mappingを許可する。

### 3. 使用可能文字の欠落

該当例:

- `comforgo`: expected `こ,ご,け,げ`, actual `こ,け,げ`
- `karen_ibutsu`: expected `か,が,こ`, actual `こ`
- `mashiro7`: expected `ま,し,ろ,う`, actual `ろ`
- `yomitaru_light`: expected `よ,ょ,い,す,ず,し`, actual `い,す,ず`
- `yomitaru_water`: expected `い,よ,ょ,す,ず,し`, actual `よ,す`

原因候補:

- main画面の文字玉が読み落とされる。
- trait画面の「文字変換に...を追加する」から補完できるが、現抽出は限定的。
- OCR自体が濁点/拗音を落とすことがある。

prompt改善候補:

- main画面では`main_char_ball`として文字玉を必ず独立block化する。
- とくせい画面では「文字変換」「文字追加」「サブ属性として...文字」を本文とは別に`trait_available_moji`のようなblockへ出す。
- 使用可能文字は文章中に埋めず、読めた個別文字を区切り付きで書くよう指示する。

抽出側改善候補:

- `trait`画面の`文字変換に「...」を追加`から安全に補完する。
- `main_char_ball` regionから単一ひらがなを採用する。
- `chars`は最初の候補だけでなく、全screenからunionする。現状は`spec-fragments.php`で`fields['chars'][0]`のみ採用している。

### 4. わざ/すごわざ名のOCR揺れ

該当:

- `comforgo`: `奏でるは激熱の旋律` expected, actual `奏では激熱の旋律`
- `comforgo`: `激震のオーバーヒート` expected, actual `激震のオーバービート`
- `yomitaru_water`: `次に繋ぐ言祓` expected, actual `次に繋ぐ言祝`
- `yomitaru_water`: `イィタル` expected, actual `イイタル`

原因候補:

- OCR自体の表記揺れ。
- promptで「推測/補正禁止」にしているため、ゲーム知識による修正は期待しない方がよい。

対応方針:

- promptで「濁点、半濁点、小書きかな、長音、祓/祝など似た漢字を特に注意」と書く。
- ただし自動補正辞書は慎重に。公式名の候補DBがない限り、生OCRを尊重してwarningに残す。

### 5. 攻撃rowの対象/強度差分

該当多数:

- 実OCRでは`爆絶強力`が出るため`most_strong`になるが、synthetic expectedでは`very_strong`にしているケースがある。
- `yomitaru_light`のすごわざは本文先頭の`敵単体`を拾い、expectedの`all_oppo`とズレる。
- `mashiro7`すごわざは「文字数に応じて威力が上昇する天属性攻撃」でprefixが`none`になる。

原因候補:

- synthetic expectedが簡略化されており、実画像の本文の方が公式specに近い場合がある。
- 現row化は本文全体から最初に見えるtarget/prefixを雑に拾う。
- 複数効果・複数攻撃・文字数依存攻撃を1 rowに潰している。

対応方針:

- prompt改善だけでなく、adapter側のskill parser改善が必要。
- まずは実OCR本文をrawとして保持し、攻撃rowの自動生成は最小/警告付きでよい。
- 比較reportでは攻撃target/prefixを厳密一致にしすぎると、prompt改善の評価を誤る可能性がある。

## 次セッションの推奨作業順

### Step 1: parse失敗対策

担当subagent候補: `ocr-openrouter-json-stability`

作業:

- `openrouter-vlm.php`のpromptを短く/厳格にする。
- `images[].fullText`と`blocks[]`の必須schemaを明確化する。
- JSON以外を返さない指示を強める。
- 必要ならbackend側にsafe JSON extractionを追加する。
- `danto`と`itadori_okkotsu_dream`を`refresh=1`で再取得し、parse可能になるか確認。

完了条件:

- 7/7 caseでrecordingに`payload`が保存され、`error`が消える。

### Step 2: promptでregionを安定させる

担当subagent候補: `ocr-openrouter-region-prompt`

作業:

- main screenで以下のregionを必ず出すようprompt修正。
  - `main_name_text`
  - `main_attribute_icon`
  - `main_species_icon`
  - `main_char_ball`
  - `main_waza_preview`
  - `main_sugowaza_preview`
- modal screenで以下を必ず分ける。
  - `modal_header_title`
  - `modal_body`
  - `modal_trigger`
- trait screenで文字補完用blockを明示する。
  - `trait_body`
  - `trait_available_moji`

完了条件:

- `report.json`の`normalized_diff.actual_images[].block_regions`に期待regionが増える。
- 種族/文字のmatch率が改善する。

### Step 3: 抽出側の不足を補う

担当subagent候補: `ocr-extractor-live-gaps`

作業:

- `lib/acf/ocr/extraction/basic-terms.php`
  - `main_species_icon`の孤立文字mappingを追加。
  - `main_attribute_icon`も同様に対応。
- `lib/acf/ocr/extraction/available-moji.php`
  - `main_char_ball`から採用。
  - `trait_available_moji`またはtrait本文の安全な文字変換/追加文脈から採用。
- `lib/acf/ocr/structure/spec-fragments.php`
  - `fields['chars'][0]`のみではなく、chars候補をunionする。

完了条件:

- 7キャラ手書きmatrixは維持。
- live reportでchars/speciesのmismatchが減る。

### Step 4: live reportの比較軸を調整

担当subagent候補: `ocr-live-report-quality`

作業:

- 攻撃target/prefixは現時点でstrict mismatchにしすぎない。
- `raw_present`, `name`, `condition`, `terms`, `chars`を主指標にする。
- skill構造化は別指標として分離する。

完了条件:

- prompt改善の効果が、攻撃rowの未成熟に埋もれないreportになる。

## 検証コマンド

PHP lint:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 bash -lc 'for f in lib/acf/ocr/*.php lib/acf/ocr/extraction/*.php lib/acf/ocr/structure/*.php local-dev/seed/ocr-tests.php local-dev/seed/ocr-live-openrouter-survey.php; do php -l "$f" || exit 1; done'
```

手書きfixture回帰:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval-file local-dev/seed/ocr-tests.php --path=/var/www/html --allow-root
```

live OCR report再生成。prompt/backend hashが同じならcache利用:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval-file local-dev/seed/ocr-live-openrouter-survey.php --path=/var/www/html --allow-root
```

prompt/backend変更後に強制再取得:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval-file local-dev/seed/ocr-live-openrouter-survey.php refresh=1 --path=/var/www/html --allow-root
```

既存smoke:

```bash
docker exec -w /var/www/html/wp-content/themes/cocoon-child-master devcontainer-wordpress-1 wp eval-file local-dev/seed/ocr-smoke.php --path=/var/www/html --allow-root
```

JS syntax:

```bash
node --check lib/acf/ocr/acf-ocr-draft.js
```

## 注意点

- `live-openrouter/recordings/*.json`にはAPI keyは含まれていないことを確認済み。
- 実画像そのものも `local-dev/seed/ocr-fixtures/live-openrouter/input/` に保存する。recordingにはOCR payloadと画像ファイル名/サイズが入る。
- `danto`/`itadori_okkotsu_dream`のparse失敗recordingにはpayloadがなく、errorのみ入っている。
- `report.json`は大きい。要約は上記表を参照し、詳細確認時だけ読む。
- prompt改善でbackend hashが変わると、通常実行でもlive OCRが再実行される。APIコストに注意する。
