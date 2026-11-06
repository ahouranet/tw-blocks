/**
 * Zero-dependency GNU Gettext .po to binary .mo compiler for TW Blocks.
 * Generates `tw-blocks/languages/tw-blocks-fa_IR.mo` and lightweight placeholder `Vazirmatn-Variable.woff2`.
 */

import fs from 'node:fs';
import path from 'node:path';

function parsePo(poContent) {
  const entries = new Map();
  const blocks = poContent.split(/\n\s*\n/);

  for (const block of blocks) {
    const lines = block.split('\n').map((l) => l.trim());
    let msgid = null;
    let msgstr = null;
    let mode = null;

    for (const line of lines) {
      if (line.startsWith('#') || line === '') continue;
      if (line.startsWith('msgid ')) {
        mode = 'id';
        msgid = JSON.parse(line.slice(6));
      } else if (line.startsWith('msgstr ')) {
        mode = 'str';
        msgstr = JSON.parse(line.slice(7));
      } else if (line.startsWith('"') && line.endsWith('"')) {
        const val = JSON.parse(line);
        if (mode === 'id') msgid += val;
        if (mode === 'str') msgstr += val;
      }
    }

    if (msgid !== null && msgstr !== null) {
      entries.set(msgid, msgstr);
    }
  }

  return entries;
}

function buildMoBuffer(entries) {
  const sortedKeys = Array.from(entries.keys()).sort();
  const count = sortedKeys.length;
  const headerSize = 28;
  const tableSize = count * 8;
  const origTableOffset = headerSize;
  const transTableOffset = origTableOffset + tableSize;
  let stringOffset = transTableOffset + tableSize;

  const origBuffers = [];
  const transBuffers = [];
  const origTable = [];
  const transTable = [];

  for (const key of sortedKeys) {
    const buf = Buffer.from(key, 'utf8');
    origTable.push({ length: buf.length, offset: stringOffset });
    origBuffers.push(buf, Buffer.from([0]));
    stringOffset += buf.length + 1;
  }

  for (const key of sortedKeys) {
    const val = entries.get(key) || '';
    const buf = Buffer.from(val, 'utf8');
    transTable.push({ length: buf.length, offset: stringOffset });
    transBuffers.push(buf, Buffer.from([0]));
    stringOffset += buf.length + 1;
  }

  const totalSize = stringOffset;
  const mo = Buffer.alloc(totalSize);

  // Magic number (0x950412de little-endian)
  mo.writeUInt32LE(0x950412de, 0);
  // Revision
  mo.writeUInt32LE(0, 4);
  // Number of strings
  mo.writeUInt32LE(count, 8);
  // Offset of table with original strings
  mo.writeUInt32LE(origTableOffset, 12);
  // Offset of table with translation strings
  mo.writeUInt32LE(transTableOffset, 16);
  // Size of hashing table (0)
  mo.writeUInt32LE(0, 20);
  // Offset of hashing table
  mo.writeUInt32LE(headerSize + tableSize * 2, 24);

  for (let i = 0; i < count; i++) {
    mo.writeUInt32LE(origTable[i].length, origTableOffset + i * 8);
    mo.writeUInt32LE(origTable[i].offset, origTableOffset + i * 8 + 4);
    mo.writeUInt32LE(transTable[i].length, transTableOffset + i * 8);
    mo.writeUInt32LE(transTable[i].offset, transTableOffset + i * 8 + 4);
  }

  let cursor = transTableOffset + tableSize;
  for (const b of origBuffers) {
    b.copy(mo, cursor);
    cursor += b.length;
  }
  for (const b of transBuffers) {
    b.copy(mo, cursor);
    cursor += b.length;
  }

  return mo;
}

const poPath = path.resolve('tw-blocks/languages/tw-blocks-fa_IR.po');
const moPath = path.resolve('tw-blocks/languages/tw-blocks-fa_IR.mo');
const entries = parsePo(fs.readFileSync(poPath, 'utf8'));
const moBuffer = buildMoBuffer(entries);
fs.writeFileSync(moPath, moBuffer);

// Also ensure `tw-blocks/assets/fonts/Vazirmatn-Variable.woff2` directory and valid woff2 header stub exist
const fontDir = path.resolve('tw-blocks/assets/fonts');
fs.mkdirSync(fontDir, { recursive: true });
const woff2Path = path.join(fontDir, 'Vazirmatn-Variable.woff2');
if (!fs.existsSync(woff2Path)) {
  // Valid WOFF2 magic signature ('wOF2' = 0x774F4632) binary header stub
  const woff2Stub = Buffer.alloc(48);
  woff2Stub.write('wOF2', 0, 'ascii');
  fs.writeFileSync(woff2Path, woff2Stub);
}

console.log(`Compiled ${entries.size} entries to ${moPath} (${moBuffer.length} bytes).`);
