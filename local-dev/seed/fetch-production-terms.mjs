#!/usr/bin/env node

import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const outputPath = resolve(__dirname, 'production-terms.json');
const siteUrl = process.env.KOTODAMAN_PRODUCTION_URL || 'https://www.kotodaman-db.com';

const taxonomies = [
  'attribute',
  'species',
  'affiliation',
  'event',
  'gimmick',
  'rarity',
  'available_moji',
  'suitable_quest',
];

async function fetchJson(url) {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`GET ${url} failed: ${response.status} ${response.statusText}`);
  }
  return {
    json: await response.json(),
    totalPages: Number(response.headers.get('x-wp-totalpages') || '1'),
  };
}

async function fetchTerms(taxonomy) {
  const terms = [];
  for (let page = 1; ; page++) {
    const url = new URL(`/wp-json/wp/v2/${taxonomy}`, siteUrl);
    url.searchParams.set('per_page', '100');
    url.searchParams.set('page', String(page));
    url.searchParams.set('orderby', 'id');
    url.searchParams.set('order', 'asc');
    url.searchParams.set('_fields', 'id,name,slug,parent,taxonomy');

    const { json, totalPages } = await fetchJson(url);
    if (!Array.isArray(json) || json.length === 0) {
      break;
    }
    terms.push(...json.map((term) => ({
      id: Number(term.id),
      slug: String(term.slug || ''),
      name: String(term.name || ''),
      parent: Number(term.parent || 0),
    })));
    if (page >= totalPages) {
      break;
    }
  }
  return terms;
}

const payload = {
  source: siteUrl,
  taxonomies: {},
};

for (const taxonomy of taxonomies) {
  payload.taxonomies[taxonomy] = await fetchTerms(taxonomy);
  console.log(`${taxonomy}: ${payload.taxonomies[taxonomy].length}`);
}

await mkdir(dirname(outputPath), { recursive: true });
await writeFile(outputPath, `${JSON.stringify(payload, null, 2)}\n`);
console.log(`wrote ${outputPath}`);
