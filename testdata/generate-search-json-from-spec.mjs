import { promises as fs } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const repoRoot = path.resolve(__dirname, '..');
const specDir = path.join(repoRoot, 'testdata', 'spec-json');
const outputPath = path.join(repoRoot, 'lib', 'character-search', 'all_characters_search.json');

const ATTR_NUM = {
  fire: 1,
  water: 2,
  wood: 3,
  light: 4,
  dark: 5,
  void: 6,
  heaven: 7,
  rainbow: 8,
};

const SPECIES_NUM = {
  god: 1,
  demon: 2,
  hero: 3,
  dragon: 4,
  beast: 5,
  spirit: 6,
  artifact: 7,
  yokai: 8,
};

const UNLOCK_MAP = {
  default: 'def',
  first_trait: '1',
  second_trait: '2',
  blessing: 'bl',
  super_change: 'schange',
  super_copy: 'scopy',
  super_both: 'sboth',
};

const CONNECTOR = new Set(['い', 'う', 'ん']);
const SMALL_YUYO = new Set(['ゅ', 'ょ']);
const AXIS_I = new Set(['あ', 'か', 'さ', 'た', 'な', 'は', 'ま', 'や', 'ら', 'わ', 'が', 'ざ', 'だ', 'ば', 'ぱ', 'え', 'け', 'せ', 'て', 'ね', 'へ', 'め', 'れ', 'げ', 'ぜ', 'で', 'べ', 'ぺ', 'す', 'ず']);
const AXIS_U = new Set(['く', 'す', 'つ', 'ふ', 'ゆ', 'ぐ', 'ず', 'づ', 'ぶ', 'ぷ', 'お', 'こ', 'そ', 'と', 'の', 'ほ', 'も', 'よ', 'ろ', 'ご', 'ぞ', 'ど', 'ぼ', 'ぽ']);
const AXIS_YOUON = new Set(['き', 'し', 'ち', 'に', 'ひ', 'み', 'り', 'ぎ', 'じ', 'ぢ', 'び', 'ぴ', 'う', 'ゃ']);

const GIMMICK_LABELS = {
  shield: 'シールドブレイカー',
  needle: 'トゲガード',
  change: 'チェンジガード',
  weakening: '弱体ガード',
  wall: 'ウォールブレイカー',
  shock: 'ビリビリガード',
  healing: 'ヒールブレイカー',
  copy: 'コピーガード',
  freezing: 'フリーズブレイカー',
  landmine: '地雷ガード',
  smash: 'スマッシュブレイカー',
  balloon: 'バルーンガード',
  healing_core: 'ヒールコア',
  attack_core: 'アタックコア',
  attack_buff_core: 'アタックバフコア',
  super_attack_core: 'スーパーアタックコア',
};

function uniqueBilingualPairs(pairs) {
  const seen = new Set();
  const en = [];
  const jp = [];

  for (const pair of pairs) {
    const enValue = String(pair.en || '').trim();
    const jpValue = String(pair.jp || '').trim();
    if (!enValue || !jpValue) continue;

    const key = `${enValue}\t${jpValue}`;
    if (seen.has(key)) continue;

    seen.add(key);
    en.push(enValue);
    jp.push(jpValue);
  }

  return { en, jp };
}

function gimmickSlugToLabel(slug) {
  if (GIMMICK_LABELS[slug]) {
    return GIMMICK_LABELS[slug];
  }

  if (slug.startsWith('super_')) {
    const baseSlug = slug.slice('super_'.length);
    const baseLabel = GIMMICK_LABELS[baseSlug] || baseSlug;
    return `スーパー${baseLabel}`;
  }

  return slug;
}

function collectAxisTags(chars) {
  const tags = [];

  for (const char of chars) {
    const value = char?.val || '';
    if (CONNECTOR.has(value)) tags.push('char_connector');
    if (SMALL_YUYO.has(value)) tags.push('char_small_yuyo');
    if (AXIS_I.has(value)) tags.push('axis_i');
    if (AXIS_U.has(value)) tags.push('axis_u');
    if (AXIS_YOUON.has(value)) tags.push('axis_youon');
  }

  return [...new Set(tags)];
}

function flattenTargetGroup(item) {
  const type = item?.type || '';
  const objects = Array.isArray(item?.obj) ? item.obj : [];

  if (type === 'self' || type === 'all') {
    return { ty: type, slgs: '' };
  }

  const slgs = objects
    .map((object) => {
      const slug = object?.slug || '';
      const name = object?.name || '';

      if (type === 'other') return name;
      if (type === 'attr') return slug ? (ATTR_NUM[slug] || 1) : 1;
      if (type === 'species') return slug ? (SPECIES_NUM[slug] || 1) : 1;
      return slug;
    })
    .filter((value) => value !== '');

  return { ty: type, slgs };
}

function flattenLeader(item) {
  return {
    ty: item?.type || '',
    cond: (item?.conditions || []).map((condition) => ({
      ty: condition?.type || '',
      vals: condition?.val || [],
      tgts: (condition?.cond_targets || []).map((target) => ({
        ...flattenTargetGroup(target),
        ttl: target?.total_tf || false,
        num: target?.need_num || 0,
      })),
    })),
    lm_wave: item?.limit_wave || 0,
    per_unit: item?.per_unit || false,
    effs: (item?.main_eff || []).map((effect) => {
      const mergedValues = {};
      for (const valueRaw of effect?.value_raws || []) {
        const key = valueRaw?.status === 'resistance' ? (valueRaw?.resist || '') : (valueRaw?.status || '');
        if (key) {
          mergedValues[key] = valueRaw?.value || 0;
        }
      }

      return {
        tgts: (effect?.targets || []).map(flattenTargetGroup),
        vals: mergedValues,
      };
    }),
    oth_val: item?.exp ?? item?.buff_count ?? 0,
    convs: item?.converge_rate || {},
    trn: item?.turn_count || 0,
  };
}

function buildCharacter(spec) {
  const chars = (spec.chars || []).map((item) => ({
    val: item?.val || '',
    attr: ATTR_NUM[String(item?.attr || '').trim()] || 0,
    unlock: UNLOCK_MAP[String(item?.unlock || 'default').trim()] || 'def',
  }));

  const groups = uniqueBilingualPairs(
    (spec.groups || []).map((item) => ({
      en: item?.slug || '',
      jp: item?.name || '',
    })),
  );

  const gimmickPairs = [];
  const traitContents = [
    ...(spec.trait1?.contents || []),
    ...(spec.trait2?.contents || []),
    ...(spec.blessing?.contents || []),
  ];

  for (const trait of traitContents) {
    if ((trait?.type || '') !== 'gimmick' || !trait?.sub_type) {
      continue;
    }

    gimmickPairs.push({
      en: trait.sub_type,
      jp: gimmickSlugToLabel(trait.sub_type),
    });
  }

  const gimmicks = uniqueBilingualPairs(gimmickPairs);
  const rarity = spec.rarity || 0;
  const rarityDetail = spec.rarity_detail || 'none';
  const rarityTags = [];
  if (rarity) rarityTags.push(String(rarity));
  if (rarityDetail) rarityTags.push(rarityDetail);

  return {
    id: spec.id || 0,
    thumb_url: '',
    name: spec.name || '',
    pre_name: spec.pre_evo_name || '',
    ano_name: spec.another_image_name || '',
    name_ruby: spec.name_ruby || '',
    chars,
    attr: ATTR_NUM[spec.attribute] || 0,
    sub_attrs: (spec.sub_attributes || []).map((item) => ATTR_NUM[item] || 0).filter(Boolean),
    spe: SPECIES_NUM[spec.species] || 0,
    group_en: groups.en,
    group_jp: groups.jp,
    events: [],
    rar: rarity,
    rar_d: rarityDetail,
    rar_t: [...new Set(rarityTags)],
    date: spec.release_date || '',
    cv: spec.cv || '',
    acq: spec.acquisition || '',
    hp99: spec._val_99_hp || 0,
    atk99: spec._val_99_atk || 0,
    hp120: spec._val_120_hp || 0,
    atk120: spec._val_120_atk || 0,
    hptal: spec.talent_hp || 0,
    atktal: spec.talent_atk || 0,
    pri: spec.priority || 0,
    hnd_buff: spec.buff_counts_hand || [0, 0, 0, 0, 0, 0],
    bd_buff: spec.buff_counts_board || [0, 0, 0, 0, 0, 0],
    debuf: spec.debuff_counts || [0, 0, 0, 0, 0, 0],
    gimmick_en: gimmicks.en,
    gimmick_jp: gimmicks.jp,
    leader: (spec.leader || []).map(flattenLeader),
    ls_hp: spec.max_ls_hp || 0,
    ls_atk: spec.max_ls_atk || 0,
    axis: collectAxisTags(chars),
    waza_t: '',
    sugo_t: '',
    koto_t: '',
    t1_t: '',
    t2_t: '',
    bles_t: '',
  };
}

async function main() {
  const fileNames = (await fs.readdir(specDir))
    .filter((fileName) => fileName.endsWith('.json'))
    .sort();

  const output = [];
  for (const fileName of fileNames) {
    const filePath = path.join(specDir, fileName);
    const raw = await fs.readFile(filePath, 'utf8');
    const spec = JSON.parse(raw);
    output.push(buildCharacter(spec));
  }

  await fs.writeFile(outputPath, JSON.stringify(output, null, 0), 'utf8');
  process.stdout.write(`Generated ${output.length} characters to ${outputPath}\n`);
}

main().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
});
