<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_permission_config_admin();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>権限設定 — 社内お問い合わせ</title>
  <style>
    :root {
      --bg: #f8fafc;
      --card: #fff;
      --border: #e2e8f0;
      --text: #0f172a;
      --muted: #64748b;
      --primary: #334155;
      --success: #047857;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Noto Sans JP', sans-serif; font-size: 14px; color: var(--text); background: var(--bg); }
    .container { max-width: 1200px; margin: 0 auto; padding: 20px 16px 48px; }
    .header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
    .header h1 { font-size: 18px; }
    .header a { color: var(--primary); text-decoration: none; font-size: 13px; font-weight: 600; }
    .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; margin-bottom: 16px; }
    .card h2 { font-size: 15px; margin-bottom: 8px; }
    .hint { color: var(--muted); font-size: 12px; margin-bottom: 12px; line-height: 1.6; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { border: 1px solid var(--border); padding: 8px 10px; vertical-align: top; }
    th { background: #f1f5f9; text-align: left; font-weight: 600; }
    input[type=text], select, textarea { width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 6px; font: inherit; }
    textarea { min-height: 72px; resize: vertical; }
    .btn { border: none; border-radius: 8px; padding: 10px 18px; font: inherit; font-weight: 700; cursor: pointer; }
    .btn-primary { background: var(--primary); color: #fff; }
    .btn-secondary { background: #e2e8f0; color: var(--text); }
    .actions { display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap; }
    .status { margin-top: 12px; font-size: 13px; }
    .status.ok { color: var(--success); }
    .status.err { color: #b91c1c; }
    .group-row td:first-child { font-weight: 600; white-space: nowrap; }
    .matrix-wrap { overflow-x: auto; }
    .type-cell { min-width: 220px; }
    .small { font-size: 11px; color: var(--muted); }
    .cat-block { border: 1px solid var(--border); border-radius: 8px; padding: 12px; margin-bottom: 12px; }
    .cat-head { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
    .cat-head input { max-width: 180px; }
    .type-list { display: grid; gap: 6px; }
    .type-item { display: flex; gap: 8px; align-items: center; }
    .type-item input { flex: 1; }
    .linkish { background: none; border: none; color: var(--primary); cursor: pointer; font: inherit; font-size: 12px; }
  </style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>権限設定</h1>
    <a href="admin.php">← 担当者画面</a>
    <a href="index.php">ユーザー画面</a>
  </div>

  <div class="card">
    <h2>使い方</h2>
    <p class="hint">
      部署グループ・問い合わせ分類・編集/閲覧権限をここで変更できます（ソース修正不要）。<br>
      経理部は「経理部 + 経理課」「経理部 + 総務課」のように <strong>match_mode=all</strong> で課別に判定します。<br>
      人事は「人事課」キーワードのみ。権限は <strong>edit</strong>（編集） / <strong>view</strong>（閲覧のみ） / 空欄（不可）。<br>
      この画面を開けるのは <code>FINANCE_HR_PERMISSION_CONFIG_ADMIN_EMAILS</code>（未設定時は FINANCE_HR_ADMIN_EMAILS）のみです。
    </p>
  </div>

  <div class="card">
    <h2>1. 部署グループ</h2>
    <div id="groups-wrap"></div>
    <button type="button" class="linkish" id="add-group">+ グループを追加</button>
  </div>

  <div class="card">
    <h2>2. 問い合わせ分類（ユーザー画面の選択肢）</h2>
    <div id="categories-wrap"></div>
    <button type="button" class="linkish" id="add-category">+ カテゴリを追加</button>
  </div>

  <div class="card">
    <h2>3. 権限マトリクス</h2>
    <p class="hint">各問い合わせ種別について、部署グループごとに edit / view / none を設定します。</p>
    <div class="matrix-wrap">
      <table id="matrix-table">
        <thead></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

  <div class="actions">
    <button type="button" class="btn btn-primary" id="save-btn">保存</button>
    <button type="button" class="btn btn-secondary" id="reload-btn">再読み込み</button>
  </div>
  <div class="status" id="status"></div>
</div>

<script>
let state = { department_groups: {}, inquiry_categories: {}, type_permission_matrix: {} };

function groupList() {
  return Object.values(state.department_groups || {});
}

function allTypes() {
  const types = [];
  Object.values(state.inquiry_categories || {}).forEach(cat => {
    (cat.types || []).forEach(t => {
      const v = String(t || '').trim();
      if (v) types.push(v);
    });
  });
  return [...new Set(types)];
}

function syncMatrixKeys() {
  const matrix = { ...(state.type_permission_matrix || {}) };
  const types = allTypes();
  types.forEach(type => {
    if (!matrix[type]) matrix[type] = {};
  });
  Object.keys(matrix).forEach(type => {
    if (!types.includes(type)) delete matrix[type];
  });
  state.type_permission_matrix = matrix;
}

function renderGroups() {
  const wrap = document.getElementById('groups-wrap');
  const groups = groupList();
  wrap.innerHTML = `
    <table>
      <thead>
        <tr>
          <th>ID</th><th>表示名</th><th>所属キーワード（カンマ区切り）</th><th>match_mode</th><th></th>
        </tr>
      </thead>
      <tbody>
        ${groups.map(g => `
          <tr class="group-row" data-id="${escapeAttr(g.id)}">
            <td><input type="text" data-field="id" value="${escapeAttr(g.id)}"></td>
            <td><input type="text" data-field="label" value="${escapeAttr(g.label || '')}"></td>
            <td><input type="text" data-field="keywords" value="${escapeAttr((g.department_keywords || []).join(', '))}"></td>
            <td>
              <select data-field="match_mode">
                <option value="any" ${g.match_mode !== 'all' ? 'selected' : ''}>any（いずれか）</option>
                <option value="all" ${g.match_mode === 'all' ? 'selected' : ''}>all（すべて）</option>
              </select>
            </td>
            <td><button type="button" class="linkish" data-action="remove-group">削除</button></td>
          </tr>
        `).join('')}
      </tbody>
    </table>`;

  wrap.querySelectorAll('[data-action="remove-group"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.closest('tr').dataset.id;
      delete state.department_groups[id];
      collectGroupsFromDom();
      renderAll();
    });
  });
}

function renderCategories() {
  const wrap = document.getElementById('categories-wrap');
  const cats = Object.values(state.inquiry_categories || {});
  wrap.innerHTML = cats.map(cat => `
    <div class="cat-block" data-key="${escapeAttr(cat.key)}">
      <div class="cat-head">
        <label>key <input type="text" data-field="key" value="${escapeAttr(cat.key)}"></label>
        <label>sheet_key <input type="text" data-field="sheet_key" value="${escapeAttr(cat.sheet_key || cat.key)}"></label>
        <label>表示名 <input type="text" data-field="label" value="${escapeAttr(cat.label || cat.key)}"></label>
        <button type="button" class="linkish" data-action="remove-category">カテゴリ削除</button>
      </div>
      <div class="type-list">
        ${(cat.types || []).map((t, i) => `
          <div class="type-item">
            <input type="text" data-type-index="${i}" value="${escapeAttr(t)}">
            <button type="button" class="linkish" data-action="remove-type">削除</button>
          </div>
        `).join('')}
      </div>
      <button type="button" class="linkish" data-action="add-type">+ 分類を追加</button>
    </div>
  `).join('');

  wrap.querySelectorAll('[data-action="remove-category"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const key = btn.closest('.cat-block').dataset.key;
      delete state.inquiry_categories[key];
      collectCategoriesFromDom();
      renderAll();
    });
  });
  wrap.querySelectorAll('[data-action="add-type"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const block = btn.closest('.cat-block');
      const key = block.dataset.key;
      const cat = state.inquiry_categories[key];
      if (!cat.types) cat.types = [];
      cat.types.push('新しい問い合わせ分類');
      renderAll();
    });
  });
  wrap.querySelectorAll('[data-action="remove-type"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const block = btn.closest('.cat-block');
      const key = block.dataset.key;
      const idx = [...block.querySelectorAll('.type-item')].indexOf(btn.closest('.type-item'));
      state.inquiry_categories[key].types.splice(idx, 1);
      collectCategoriesFromDom();
      renderAll();
    });
  });
}

function renderMatrix() {
  syncMatrixKeys();
  const groups = groupList();
  const types = allTypes();
  const thead = document.querySelector('#matrix-table thead');
  const tbody = document.querySelector('#matrix-table tbody');
  thead.innerHTML = `<tr><th class="type-cell">問い合わせ分類</th>${groups.map(g => `<th>${escapeHtml(g.label || g.id)}</th>`).join('')}</tr>`;
  tbody.innerHTML = types.map(type => `
    <tr data-type="${escapeAttr(type)}">
      <td class="type-cell">${escapeHtml(type)}</td>
      ${groups.map(g => {
        const val = (state.type_permission_matrix[type] || {})[g.id] || 'none';
        return `<td>
          <select data-group="${escapeAttr(g.id)}">
            <option value="none" ${val === 'none' ? 'selected' : ''}>none</option>
            <option value="view" ${val === 'view' ? 'selected' : ''}>view</option>
            <option value="edit" ${val === 'edit' ? 'selected' : ''}>edit</option>
          </select>
        </td>`;
      }).join('')}
    </tr>
  `).join('');
}

function renderAll() {
  renderGroups();
  renderCategories();
  renderMatrix();
}

function collectGroupsFromDom() {
  const next = {};
  document.querySelectorAll('#groups-wrap tbody tr').forEach(tr => {
    const oldId = tr.dataset.id;
    const id = tr.querySelector('[data-field="id"]').value.trim();
    if (!id) return;
    const label = tr.querySelector('[data-field="label"]').value.trim() || id;
    const keywords = tr.querySelector('[data-field="keywords"]').value.split(',').map(s => s.trim()).filter(Boolean);
    const match_mode = tr.querySelector('[data-field="match_mode"]').value;
    next[id] = { id, label, department_keywords: keywords, match_mode };
    if (oldId !== id && state.department_groups[oldId]) {
      delete state.department_groups[oldId];
    }
  });
  state.department_groups = next;
}

function collectCategoriesFromDom() {
  const next = {};
  document.querySelectorAll('#categories-wrap .cat-block').forEach(block => {
    const oldKey = block.dataset.key;
    const key = block.querySelector('[data-field="key"]').value.trim();
    if (!key) return;
    const sheet_key = block.querySelector('[data-field="sheet_key"]').value.trim() || key;
    const label = block.querySelector('[data-field="label"]').value.trim() || key;
    const types = [...block.querySelectorAll('.type-item input')].map(i => i.value.trim()).filter(Boolean);
    next[key] = { key, sheet_key, label, types };
    if (oldKey !== key && state.inquiry_categories[oldKey]) {
      delete state.inquiry_categories[oldKey];
    }
  });
  state.inquiry_categories = next;
}

function collectMatrixFromDom() {
  syncMatrixKeys();
  document.querySelectorAll('#matrix-table tbody tr').forEach(tr => {
    const type = tr.dataset.type;
    const row = state.type_permission_matrix[type] || {};
    tr.querySelectorAll('select[data-group]').forEach(sel => {
      const gid = sel.dataset.group;
      const val = sel.value;
      if (val === 'none') delete row[gid];
      else row[gid] = val;
    });
    state.type_permission_matrix[type] = row;
  });
}

function collectStateFromDom() {
  collectGroupsFromDom();
  collectCategoriesFromDom();
  collectMatrixFromDom();
}

function escapeHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
}
function escapeAttr(s) { return escapeHtml(s); }

async function loadConfig() {
  setStatus('読み込み中…');
  const res = await fetch('api/permissions_config.php');
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || '読み込み失敗');
  state = data.config;
  renderAll();
  setStatus('読み込み完了', true);
}

async function saveConfig() {
  collectStateFromDom();
  setStatus('保存中…');
  const res = await fetch('api/permissions_config.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ config: state }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || '保存失敗');
  state = data.config;
  renderAll();
  setStatus('保存しました', true);
}

function setStatus(msg, ok) {
  const el = document.getElementById('status');
  el.textContent = msg;
  el.className = 'status ' + (ok ? 'ok' : (msg.includes('…') ? '' : 'err'));
}

document.getElementById('save-btn').addEventListener('click', () => saveConfig().catch(e => setStatus(e.message)));
document.getElementById('reload-btn').addEventListener('click', () => loadConfig().catch(e => setStatus(e.message)));
document.getElementById('add-group').addEventListener('click', () => {
  collectStateFromDom();
  const id = 'group_' + Date.now();
  state.department_groups[id] = { id, label: '新グループ', department_keywords: [], match_mode: 'any' };
  renderAll();
});
document.getElementById('add-category').addEventListener('click', () => {
  collectStateFromDom();
  const key = 'cat_' + Date.now();
  state.inquiry_categories[key] = { key, sheet_key: key, label: '新カテゴリ', types: [] };
  renderAll();
});

loadConfig().catch(e => setStatus(e.message));
</script>
</body>
</html>
