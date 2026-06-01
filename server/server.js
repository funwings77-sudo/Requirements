'use strict';
require('dotenv').config();
const path = require('path');
const express = require('express');
const cors = require('cors');
const pool = require('./db');

const app = express();
app.use(cors());
app.use(express.json({ limit: '2mb' }));

// 상위 폴더(웹페이지가 있는 곳)를 정적 호스팅 → 페이지와 API 동일 출처
app.use(express.static(path.join(__dirname, '..')));

// =====================================================================
//  필드 정의 (프런트엔드 키 ↔ DB 컬럼 ↔ 코드 테이블)
// =====================================================================
const FIELD_DEFS = [
  { key: 'rank',       col: 'rank_id',       type: 'fk',   group: 'rank' },
  { key: 'category',   col: 'category_id',   type: 'fk',   group: 'category', required: true },
  { key: 'major',      col: 'major',         type: 'text' },
  { key: 'middle',     col: 'middle',        type: 'text' },
  { key: 'name',       col: 'name',          type: 'text', required: true },
  { key: 'purpose',    col: 'purpose',       type: 'text' },
  { key: 'detail',     col: 'detail',        type: 'text' },
  { key: 'link',       col: 'link',          type: 'text' },
  { key: 'doc',        col: 'doc',           type: 'text' },
  { key: 'requester',  col: 'requester',     type: 'text' },
  { key: 'reqPart',    col: 'req_part_id',   type: 'fk',   group: 'part' },
  { key: 'reqDate',    col: 'req_date',      type: 'date' },
  { key: 'doPart',     col: 'do_part_id',    type: 'fk',   group: 'part' },
  { key: 'doPerson',   col: 'do_person',     type: 'text' },
  { key: 'priority',   col: 'priority_id',   type: 'fk',   group: 'priority' },
  { key: 'importance', col: 'importance_id', type: 'fk',   group: 'level' },
  { key: 'difficulty', col: 'difficulty_id', type: 'fk',   group: 'level' },
  { key: 'effort',     col: 'effort',        type: 'num' },
  { key: 'version',    col: 'version_id',    type: 'fk',   group: 'version' },
  { key: 'status',     col: 'status_id',     type: 'fk',   group: 'status' },
  { key: 'reviewer',   col: 'reviewer',      type: 'text' },
  { key: 'approveDate',col: 'approve_date',  type: 'date' },
  { key: 'startDate',  col: 'start_date',    type: 'date' },
  { key: 'endDate',    col: 'end_date',      type: 'date' },
  { key: 'prodDate',   col: 'prod_date',     type: 'date' },
  { key: 'remark',     col: 'remark',        type: 'text' },
];

// group → 코드 테이블
const GROUP_TABLE = {
  category: 'cat_category', part: 'cat_part', priority: 'cat_priority',
  level: 'cat_level', version: 'cat_version', status: 'cat_status', rank: 'cat_rank',
};

// =====================================================================
//  코드 캐시 (label → id)
// =====================================================================
let lookups = null;       // { group: [{id, code, label}] }
let labelToId = null;     // { group: Map(label → id) }

async function loadLookups() {
  const next = {}, map = {};
  for (const [group, table] of Object.entries(GROUP_TABLE)) {
    const [rows] = await pool.query(
      `SELECT id, code, label FROM ${table} ORDER BY sort_order, id`
    );
    next[group] = rows;
    map[group] = new Map(rows.map(r => [r.label, r.id]));
  }
  lookups = next; labelToId = map;
  return lookups;
}
async function ensureLookups() { if (!lookups) await loadLookups(); }

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

// 프런트엔드 객체 → { col: value } (검증 + label→id 변환)
function toRow(body) {
  const row = {};
  for (const d of FIELD_DEFS) {
    let v = body[d.key];
    v = (v === undefined || v === null) ? '' : String(v).trim();

    if (d.type === 'text') {
      row[d.col] = v === '' ? null : v;
    } else if (d.type === 'date') {
      if (v === '') row[d.col] = null;
      else if (DATE_RE.test(v)) row[d.col] = v;
      else throw httpError(400, `날짜 형식 오류(${d.key}): YYYY-MM-DD`);
    } else if (d.type === 'num') {
      if (v === '') row[d.col] = null;
      else if (!isNaN(Number(v))) row[d.col] = Number(v);
      else throw httpError(400, `숫자 형식 오류(${d.key})`);
    } else if (d.type === 'fk') {
      if (v === '') {
        if (d.required) throw httpError(400, `필수 항목 누락: ${d.key}`);
        row[d.col] = null;
      } else {
        const id = labelToId[d.group] && labelToId[d.group].get(v);
        if (!id) throw httpError(400, `허용되지 않은 값(${d.key}): "${v}"`);
        row[d.col] = id;
      }
    }
    if (d.required && d.type === 'text' && row[d.col] == null) {
      throw httpError(400, `필수 항목 누락: ${d.key}`);
    }
  }
  return row;
}

function httpError(status, message) {
  const e = new Error(message); e.status = status; return e;
}

// =====================================================================
//  라우트
// =====================================================================
app.get('/api/health', async (req, res, next) => {
  try { await pool.query('SELECT 1'); res.json({ ok: true }); }
  catch (e) { next(e); }
});

// 드롭다운 옵션 (프런트엔드가 select 를 DB 기준으로 구성)
app.get('/api/options', async (req, res, next) => {
  try { await ensureLookups(); res.json(lookups); }
  catch (e) { next(e); }
});

// 목록 (필터: q, category, priority, status)
app.get('/api/requirements', async (req, res, next) => {
  try {
    const where = [], params = [];
    const { q, category, priority, status } = req.query;
    if (category) { where.push('category = ?'); params.push(category); }
    if (priority) { where.push('priority = ?'); params.push(priority); }
    if (status)   { where.push('status = ?');   params.push(status); }
    if (q) {
      where.push('(name LIKE ? OR detail LIKE ? OR purpose LIKE ? OR major LIKE ? OR middle LIKE ?)');
      const like = `%${q}%`; params.push(like, like, like, like, like);
    }
    const sql = 'SELECT * FROM v_requirement'
      + (where.length ? ' WHERE ' + where.join(' AND ') : '')
      + ' ORDER BY sortOrder, id';
    const [rows] = await pool.query(sql, params);
    res.json(rows);
  } catch (e) { next(e); }
});

// 목록 순서 저장: { ids: [id, id, ...] } 순서대로 sort_order = 1..N
// ※ '/:id' 라우트보다 먼저 정의해야 'reorder' 가 id 로 잡히지 않음
app.put('/api/requirements/reorder', async (req, res, next) => {
  const ids = Array.isArray(req.body && req.body.ids) ? req.body.ids : null;
  if (!ids) return res.status(400).json({ error: 'ids 배열이 필요합니다' });
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    let i = 1;
    for (const rid of ids) {
      await conn.query('UPDATE requirement SET sort_order = ? WHERE id = ?', [i++, Number(rid)]);
    }
    await conn.commit();
    res.json({ ok: true, count: ids.length });
  } catch (e) {
    await conn.rollback();
    next(e);
  } finally {
    conn.release();
  }
});

// 단건
app.get('/api/requirements/:id', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM v_requirement WHERE id = ?', [req.params.id]);
    if (!rows.length) return res.status(404).json({ error: '없는 요구사항' });
    res.json(rows[0]);
  } catch (e) { next(e); }
});

// 생성
app.post('/api/requirements', async (req, res, next) => {
  try {
    await ensureLookups();
    const row = toRow(req.body);
    const [[mx]] = await pool.query('SELECT COALESCE(MAX(sort_order),0)+1 AS next FROM requirement');
    row.sort_order = mx.next;                       // 신규 항목은 목록 맨 아래
    const cols = Object.keys(row);
    const sql = `INSERT INTO requirement (${cols.join(', ')}) VALUES (${cols.map(() => '?').join(', ')})`;
    const [r] = await pool.query(sql, cols.map(c => row[c]));
    const [out] = await pool.query('SELECT * FROM v_requirement WHERE id = ?', [r.insertId]);
    res.status(201).json(out[0]);
  } catch (e) { next(e); }
});

// 수정
app.put('/api/requirements/:id', async (req, res, next) => {
  try {
    await ensureLookups();
    const row = toRow(req.body);
    const cols = Object.keys(row);
    const sql = `UPDATE requirement SET ${cols.map(c => `${c} = ?`).join(', ')} WHERE id = ?`;
    const [r] = await pool.query(sql, [...cols.map(c => row[c]), req.params.id]);
    if (!r.affectedRows) return res.status(404).json({ error: '없는 요구사항' });
    const [out] = await pool.query('SELECT * FROM v_requirement WHERE id = ?', [req.params.id]);
    res.json(out[0]);
  } catch (e) { next(e); }
});

// 삭제
app.delete('/api/requirements/:id', async (req, res, next) => {
  try {
    const [r] = await pool.query('DELETE FROM requirement WHERE id = ?', [req.params.id]);
    if (!r.affectedRows) return res.status(404).json({ error: '없는 요구사항' });
    res.json({ ok: true });
  } catch (e) { next(e); }
});

// 대시보드 집계
app.get('/api/dashboard', async (req, res, next) => {
  try {
    const [[summary]] = await pool.query('SELECT * FROM v_dashboard_summary');
    const [category] = await pool.query('SELECT label, cnt FROM v_dashboard_category');
    const [priority] = await pool.query('SELECT label, cnt FROM v_dashboard_priority');
    const [status]   = await pool.query('SELECT label, cnt FROM v_dashboard_status');
    const [version]  = await pool.query('SELECT label, cnt FROM v_dashboard_version');
    res.json({ summary, category, priority, status, version });
  } catch (e) { next(e); }
});

// 에러 핸들러
app.use((err, req, res, next) => {
  const status = err.status || 500;
  if (status >= 500) console.error(err);
  res.status(status).json({ error: err.message || '서버 오류' });
});

const PORT = Number(process.env.PORT || 3000);
app.listen(PORT, async () => {
  console.log(`\n  메디힘 요구사항 API + 웹  →  http://localhost:${PORT}\n`);
  try {
    await loadLookups();
    console.log('  ✓ MySQL 연결 및 코드 캐시 로드 완료');
  } catch (e) {
    console.error('  ✗ MySQL 연결 실패 — .env 설정 및 schema/seed 적용 여부를 확인하세요.');
    console.error('    ', e.code || e.message);
  }
});
