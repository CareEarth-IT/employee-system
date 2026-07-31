<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_admin();

$categoryOptions = [];
foreach (inquiry_categories() as $key => $cat) {
    $categoryOptions[] = [
        'key' => (string) $key,
        'label' => (string) ($cat['label'] ?? $key),
        'sheet_key' => (string) ($cat['sheet_key'] ?? 'main'),
    ];
}
$categoriesJson = json_encode($categoryOptions, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="assets/favicon.png?v=5" type="image/png">
  <link rel="apple-touch-icon" href="assets/favicon.png?v=5">
  <title>担当者画面 — 社内お問い合わせ</title>
  <style>
    :root {
      --indigo-950: #0f172a;
      --indigo-900: #1e293b;
      --indigo-800: #334155;
      --indigo-700: #475569;
      --indigo-600: #64748b;
      --indigo-500: #94a3b8;
      --indigo-200: #cbd5e1;
      --indigo-100: #e2e8f0;
      --indigo-50: #f1f5f9;
      --surface: #f8fafc;
      --card: #ffffff;
      --text: #0f172a;
      --text-muted: #475569;
      --border: #e2e8f0;
      --border-strong: #cbd5e1;
      --success-bg: #ecfdf5;
      --success-text: #065f46;
      --success-border: #6ee7b7;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Noto Sans JP', 'Helvetica Neue', sans-serif;
      font-size: 14px;
      color: var(--text);
      background: linear-gradient(180deg, var(--indigo-50) 0%, var(--surface) 320px);
      min-height: 100vh;
    }
    .container { max-width: 1320px; margin: 0 auto; padding: 20px 16px; }

    .page-header {
      background: var(--card);
      border-radius: 12px;
      padding: 14px 20px;
      margin-bottom: 16px;
      border: 1px solid var(--border-strong);
      box-shadow: 0 2px 8px rgba(26, 35, 126, 0.08);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .page-header h1 { font-size: 15px; font-weight: 700; color: var(--indigo-950); letter-spacing: 0.02em; }
    .admin-badge {
      margin-left: auto;
      font-size: 11px;
      background: var(--indigo-50);
      color: var(--indigo-900);
      padding: 5px 12px;
      border-radius: 20px;
      font-weight: 600;
      border: 1px solid var(--indigo-200);
    }
    .header-links { display: flex; align-items: center; gap: 10px; margin-left: 8px; }
    .header-links a { font-size: 12px; color: var(--indigo-700); text-decoration: none; font-weight: 600; }
    .header-links a:hover { text-decoration: underline; }

    .card {
      background: var(--card);
      border-radius: 12px;
      border: 1px solid var(--border-strong);
      box-shadow: 0 2px 12px rgba(26, 35, 126, 0.07);
      overflow: hidden;
      margin-bottom: 16px;
    }
    .tabs { display: flex; border-bottom: 1px solid var(--border); background: var(--indigo-50); }
    .tab {
      padding: 11px 20px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      border-bottom: 3px solid transparent;
      color: var(--text-muted);
      transition: color 0.15s, background 0.15s;
    }
    .tab.active {
      color: var(--indigo-950);
      border-bottom-color: var(--indigo-700);
      font-weight: 700;
      background: var(--card);
    }
    .tab:hover:not(.active) { color: var(--indigo-800); background: rgba(255,255,255,0.5); }

    .category-bar {
      display: flex;
      gap: 8px;
      padding: 12px 14px 4px;
      background: var(--card);
    }
    .category-btn {
      flex: 1 1 0;
      min-width: 0;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid var(--border-strong);
      background: #fff;
      color: var(--text-muted);
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      font-family: inherit;
      text-align: center;
      transition: background 0.15s, border-color 0.15s, color 0.15s, box-shadow 0.15s;
    }
    .category-btn:hover {
      background: var(--indigo-50);
      border-color: var(--indigo-200);
      color: var(--indigo-900);
    }
    .category-btn.active {
      background: #e8f0fe;
      border-color: #1a73e8;
      color: #1a73e8;
      box-shadow: 0 0 0 1px rgba(26,115,232,0.2);
    }

    .table-header,
    .table-row {
      display: grid;
      grid-template-columns:
        96px
        minmax(0, 1.2fr)
        minmax(0, 0.95fr)
        minmax(0, 0.8fr)
        82px
        minmax(0, 0.75fr)
        minmax(0, 0.65fr)
        minmax(140px, 1.35fr)
        50px;
      gap: 6px;
      padding: 9px 12px;
      align-items: center;
    }
    .table-header {
      font-size: 10px;
      color: var(--indigo-950);
      font-weight: 800;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      border-bottom: 2px solid var(--indigo-700);
      background: linear-gradient(180deg, var(--indigo-100) 0%, var(--indigo-50) 100%);
    }
    .table-row {
      font-size: 11px;
      color: var(--text);
      font-weight: 500;
      border-bottom: 1px solid #e8eaf6;
      cursor: pointer;
      transition: background 0.12s, box-shadow 0.12s;
    }
    .table-row:hover {
      background: var(--indigo-50);
      box-shadow: inset 3px 0 0 var(--indigo-600);
    }
    .table-row:nth-child(even) { background: #fafbff; }
    .table-row:nth-child(even):hover { background: var(--indigo-50); }

    .flag-done {
      font-size: 10px;
      color: var(--success-text);
      font-weight: 700;
      background: var(--success-bg);
      padding: 3px 8px;
      border-radius: 20px;
      border: 1px solid var(--success-border);
    }
    .flag-pending {
      font-size: 10px;
      color: var(--indigo-900);
      font-weight: 700;
      background: #e3e7fc;
      padding: 3px 8px;
      border-radius: 20px;
      border: 1px solid var(--indigo-200);
    }

    .detail-panel {
      display: none;
      background: linear-gradient(180deg, #f0f2fb 0%, #e8eaf6 100%);
      border-top: 2px solid var(--indigo-500);
      padding: 18px;
    }
    .detail-panel.open { display: block; }
    .detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
      margin-bottom: 14px;
    }
    .detail-field label {
      font-size: 11px;
      color: var(--indigo-800);
      font-weight: 700;
      display: block;
      margin-bottom: 5px;
      letter-spacing: 0.02em;
    }
    .detail-field .val {
      font-size: 13px;
      background: var(--card);
      border: 1px solid var(--border-strong);
      border-radius: 10px;
      padding: 10px 12px;
      color: var(--text);
      font-weight: 500;
      line-height: 1.65;
      box-shadow: 0 1px 2px rgba(26, 35, 126, 0.04);
    }
    .detail-field .val-auto {
      font-size: 13px;
      background: var(--indigo-50);
      border: 1px solid var(--indigo-200);
      border-radius: 10px;
      padding: 10px 12px;
      color: var(--indigo-900);
      font-weight: 600;
      line-height: 1.65;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .detail-field .val-auto .auto-hint {
      font-size: 10px;
      color: var(--indigo-500);
      font-weight: 500;
      margin-left: auto;
    }
    .detail-field input[type="text"] {
      width: 100%;
      padding: 9px 12px;
      border: 1px solid var(--border-strong);
      border-radius: 10px;
      font-size: 13px;
      color: var(--text);
      background: var(--card);
      font-family: inherit;
      font-weight: 500;
    }
    .detail-field input[type="text"]:focus {
      outline: none;
      border-color: var(--indigo-600);
      box-shadow: 0 0 0 3px rgba(92, 107, 192, 0.25);
    }
    .detail-field.full { grid-column: 1 / -1; }
    .detail-field select,
    .detail-field textarea {
      width: 100%;
      padding: 9px 12px;
      border: 1px solid var(--border-strong);
      border-radius: 10px;
      font-size: 13px;
      color: var(--text);
      background: var(--card);
      font-family: inherit;
      font-weight: 500;
    }
    .detail-field textarea { resize: vertical; min-height: 76px; line-height: 1.7; }
    .detail-field textarea.readonly-like {
      background: #eceff5;
      color: var(--text);
      border-color: #b0bec5;
      cursor: default;
      resize: none;
    }

    .read-only-banner {
      grid-column: 1 / -1;
      font-size: 12px;
      font-weight: 700;
      color: var(--indigo-950);
      background: linear-gradient(90deg, var(--indigo-100) 0%, #e3e7fc 100%);
      border: 1px solid var(--indigo-600);
      border-left: 4px solid var(--indigo-700);
      border-radius: 10px;
      padding: 12px 14px;
      margin-bottom: 6px;
      box-shadow: 0 1px 4px rgba(26, 35, 126, 0.1);
    }

    .update-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 12px;
    }
    .updated-at {
      font-size: 11px;
      color: var(--indigo-700);
      font-weight: 600;
    }
    .flag-section {
      margin-top: 14px;
      padding-top: 14px;
      border-top: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .flag-label {
      font-size: 12px;
      color: var(--indigo-800);
      font-weight: 700;
    }
    .flag-approve-btn {
      padding: 6px 16px;
      border: 2px solid var(--indigo-600);
      border-radius: 24px;
      font-size: 12px;
      font-weight: 600;
      color: var(--indigo-800);
      background: var(--card);
      cursor: pointer;
      transition: background 0.15s, color 0.15s;
    }
    .flag-approve-btn:hover {
      background: var(--indigo-700);
      color: #fff;
      border-color: var(--indigo-700);
    }

    .btn {
      padding: 8px 18px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      border: 1px solid var(--border-strong);
      background: var(--card);
      color: var(--indigo-900);
      font-family: inherit;
    }
    .btn-primary {
      background: var(--indigo-700);
      border-color: var(--indigo-700);
      color: #fff;
    }
    .btn-primary:hover { background: var(--indigo-800); border-color: var(--indigo-800); }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; }

    .empty { text-align: center; padding: 36px; color: var(--text-muted); font-size: 14px; font-weight: 500; }
    .loading { text-align: center; padding: 28px; color: var(--indigo-600); font-size: 14px; font-weight: 500; }
    .ts { font-size: 10px; color: var(--indigo-900); font-weight: 600; }
    .cell-ell {
      font-size: 10px;
      color: var(--text);
      font-weight: 500;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .taiousha-cell {
      font-size: 10px;
      color: var(--indigo-800);
      font-weight: 700;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .tantousha-cell {
      font-size: 10px;
      color: var(--indigo-950);
      font-weight: 600;
      white-space: normal;
      word-break: break-word;
      line-height: 1.4;
    }
    .table-row-title {
      font-size: 11px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      color: var(--indigo-950);
      font-weight: 700;
    }
    .table-row-subname {
      font-size: 10px;
      color: var(--text-muted);
      font-weight: 500;
      margin-top: 2px;
    }
    .alert-error {
      background: #ffebee;
      color: #b71c1c;
      border: 1px solid #ef9a9a;
      border-radius: 10px;
      padding: 12px 16px;
      margin: 8px 14px;
      font-size: 12px;
      font-weight: 600;
    }
  </style>
</head>
<body>
<div class="container">
  <div class="page-header">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
    </svg>
    <h1>社内お問い合わせ — 担当者画面</h1>
    <div class="admin-badge" id="admin-badge">管理者</div>
    <div class="header-links">
      <a href="index.php">ユーザー画面</a>
      <a href="permissions.php" id="permissions-link" style="display:none">権限設定</a>
      <a href="logout.php">社員サイトへ</a>
    </div>
  </div>

  <div class="card">
    <div class="category-bar" id="category-bar" role="tablist" aria-label="問い合わせカテゴリ"></div>
    <div class="tabs" id="status-tabs">
      <div class="tab active" onclick="filterAdmin('all', this)">すべて</div>
      <div class="tab" onclick="filterAdmin('未対応', this)">未対応</div>
      <div class="tab" onclick="filterAdmin('対応中', this)">対応中</div>
      <div class="tab" onclick="filterAdmin('解決済', this)">解決済</div>
    </div>
    <div class="table-header">
      <span>日時</span>
      <span>タイトル / 氏名</span>
      <span>所属会社 / 部門</span>
      <span>内容分類</span>
      <span>進捗</span>
      <span>対応内容</span>
      <span>対応者</span>
      <span>担当者</span>
      <span>上役確認</span>
    </div>
    <div id="admin-list"><div class="loading">読み込み中...</div></div>
  </div>
</div>

<script>
  var allData = [];
  var currentFilter = 'all';
  var adminName = '';
  var adminSession = { isRegistered: false, staffLabel: '', isHrStaff: false };
  var INQUIRY_CATEGORIES = <?= $categoriesJson ?: '[]' ?>;
  var currentCategoryKey = (INQUIRY_CATEGORIES[0] && INQUIRY_CATEGORIES[0].key) || 'hr';

  function findCategory(key) {
    for (var i = 0; i < INQUIRY_CATEGORIES.length; i++) {
      if (INQUIRY_CATEGORIES[i].key === key) return INQUIRY_CATEGORIES[i];
    }
    return null;
  }

  function categoryKeyForRow(r) {
    if (r && r.category) return r.category;
    var sheetKey = (r && r.sheetKey) || 'main';
    if (sheetKey === 'hr') return 'hr';
    if (sheetKey === 'is') return 'is';
    return 'finance';
  }

  function renderCategoryBar() {
    var bar = document.getElementById('category-bar');
    if (!bar) return;
    bar.innerHTML = INQUIRY_CATEGORIES.map(function (cat) {
      var active = cat.key === currentCategoryKey ? ' active' : '';
      var key = String(cat.key || '').replace(/[^a-z0-9_-]/gi, '');
      return (
        '<button type="button" class="category-btn' + active + '" data-category="' +
        key +
        '" onclick="selectCategory(\'' +
        key +
        '\')">' +
        attrEscape(cat.label) +
        '</button>'
      );
    }).join('');
  }

  function selectCategory(key) {
    if (!findCategory(key)) return;
    currentCategoryKey = key;
    renderCategoryBar();
    renderList();
  }

  function apiGet(url) {
    return fetch(url, { credentials: 'same-origin' }).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok) throw new Error((data && data.error) || 'リクエストに失敗しました');
        return data;
      });
    });
  }

  function apiPost(url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    }).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok) throw new Error((data && data.error) || 'リクエストに失敗しました');
        return data;
      });
    });
  }

  function rowDomId(r) {
    return (r.sheetKey || 'main') + '-' + r.row;
  }

  function rowKeyArg(r) {
    return "'" + String(r.sheetKey || 'main') + "'," + r.row;
  }

  function findIndexByRow(rowNum, sheetKey) {
    sheetKey = sheetKey || 'main';
    for (var j = 0; j < allData.length; j++) {
      if (allData[j].row === rowNum && (allData[j].sheetKey || 'main') === sheetKey) return j;
    }
    return -1;
  }

  function attrEscape(s) {
    if (s == null || s === '') return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function formatAttachmentsHtml(attachments) {
    if (!attachments) return '—';
    var urls = String(attachments).split('\n').filter(function (u) { return u.trim(); });
    if (!urls.length) return '—';
    return urls.map(function (url, i) {
      return '<a href="' + attrEscape(url.trim()) + '" target="_blank" rel="noopener">添付 ' + (i + 1) + '</a>';
    }).join('<br>');
  }

  function isRowLocked(r) {
    return r && r.flag === '済';
  }

  function isRowReadOnly(r) {
    return isRowLocked(r) || (r && r.access === 'view');
  }

  function updateAdminBadge() {
    var el = document.getElementById('admin-badge');
    if (!adminSession.isRegistered) {
      el.textContent = '担当者未登録（管理者権限が必要）';
      return;
    }
    var role = adminSession.staffLabel ? '担当：' + adminSession.staffLabel + '　' : '';
    el.textContent = role + (adminName || adminSession.fullName || '管理者');
  }

  window.onload = function () {
    renderCategoryBar();
    apiGet('api/admin_session.php')
      .then(function (session) {
        adminSession = session || { isRegistered: false, staffLabel: '' };
        adminName = adminSession.fullName || '';
        updateAdminBadge();
        if (adminSession.canManagePermissions) {
          document.getElementById('permissions-link').style.display = '';
        }
        loadData();
      })
      .catch(function (err) {
        document.getElementById('admin-badge').textContent = '管理者（プロフィール取得失敗）';
        console.error(err);
        loadData();
      });
  };

  function loadData() {
    document.getElementById('admin-list').innerHTML = '<div class="loading">読み込み中...</div>';
    apiGet('api/admin_list.php')
      .then(function (data) {
        allData = data && Array.isArray(data) ? data : [];
        if (!adminSession.isRegistered) {
          document.getElementById('admin-list').innerHTML =
            '<div class="alert-error">⚠️ 管理画面の担当部署として判定できませんでした。所属（人事課・経理部 経理課/総務課・情報システム部）を確認してください。</div>';
          return;
        }
        renderList();
        openRowFromQueryParam();
      })
      .catch(function (err) {
        allData = [];
        var msg = err && err.message ? err.message : '不明なエラー';
        document.getElementById('admin-list').innerHTML =
          '<div class="alert-error">⚠️ データ取得に失敗しました: ' + attrEscape(msg) + '</div>';
      });
  }

  function renderList() {
    if (!allData) allData = [];

    var byCategory = allData.filter(function (r) {
      return categoryKeyForRow(r) === currentCategoryKey;
    });

    var filtered =
      currentFilter === 'all'
        ? byCategory
        : byCategory.filter(function (r) {
            return r.status === currentFilter;
          });

    if (!filtered || filtered.length === 0) {
      var cat = findCategory(currentCategoryKey);
      var label = cat && cat.label ? cat.label : '';
      document.getElementById('admin-list').innerHTML =
        '<div class="empty">' + attrEscape(label) + 'の該当がありません</div>';
      return;
    }

    document.getElementById('admin-list').innerHTML = filtered
      .map(function (r) {
        var rowNum = r.row;
        var domId = rowDomId(r);
        var rowArgs = rowKeyArg(r);
        var sheetBadge =
          r.sheetKey === 'hr'
            ? '<span style="font-size:10px;background:#fce7f3;color:#9d174d;padding:2px 6px;border-radius:8px;margin-right:4px">人事</span>'
            : r.sheetKey === 'is'
              ? '<span style="font-size:10px;background:#e0e7ff;color:#3730a3;padding:2px 6px;border-radius:8px;margin-right:4px">情シス</span>'
              : '<span style="font-size:10px;background:#ecfdf5;color:#047857;padding:2px 6px;border-radius:8px;margin-right:4px">経理</span>';
        var locked = isRowReadOnly(r);
        var flagHtml =
          r.flag === '済'
            ? '<span class="flag-done">✔ 済</span>'
            : '<span class="flag-pending">未</span>';
        var statusBg =
          r.status === '未対応' ? '#fff8e1' : r.status === '対応中' ? '#e3f2fd' : '#e8f5e9';

        var selectHtml = locked
          ? '<select disabled style="font-size:10px;padding:3px 6px;border-radius:10px;border:1px solid #94a3b8;width:100%;background:#e2e8f0;color:#0f172a;font-weight:700">' +
            '<option>' +
            attrEscape(r.status || '未対応') +
            '</option></select>'
          : '<select onchange="quickUpdateStatus(' +
            rowArgs +
            ',this.value)" style="font-size:10px;padding:3px 6px;border-radius:10px;border:1px solid #cbd5e1;width:100%;background:' +
            statusBg +
            ';color:#0f172a;font-weight:700">' +
            ['未対応', '対応中', '解決済']
              .map(function (s) {
                return (
                  '<option value="' +
                  s +
                  '"' +
                  (r.status === s ? ' selected' : '') +
                  '>' +
                  s +
                  '</option>'
                );
              })
              .join('') +
            '</select>';

        return (
          '<div>' +
          '<div class="table-row" onclick="toggleDetail(' +
          rowArgs +
          ')">' +
          '<span class="ts">' +
          sheetBadge +
          attrEscape(r.timestamp || '—') +
          '</span>' +
          '<span style="overflow:hidden">' +
          '<div class="table-row-title">' +
          attrEscape(r.title || '—') +
          '</div>' +
          '<div class="table-row-subname">' +
          attrEscape(r.name || '—') +
          '</div>' +
          '</span>' +
          '<span class="cell-ell">' +
          attrEscape(r.company || '—') +
          ' / ' +
          attrEscape(r.dept || '—') +
          '</span>' +
          '<span class="cell-ell">' +
          attrEscape(r.type || '—') +
          '</span>' +
          '<span onclick="event.stopPropagation()">' +
          selectHtml +
          '</span>' +
          '<span class="cell-ell">' +
          attrEscape(r.comment || '—') +
          '</span>' +
          '<span class="taiousha-cell">' +
          attrEscape(r.taiousha || '—') +
          '</span>' +
          '<span class="tantousha-cell">' +
          attrEscape(r.tantousha || '—') +
          '</span>' +
          '<span style="text-align:center">' +
          flagHtml +
          '</span>' +
          '</div>' +
          '<div class="detail-panel" id="detail-' +
          domId +
          '">' +
          buildDetailHtml(r) +
          '</div>' +
          '</div>'
        );
      })
      .join('');
  }

  function buildDetailHtml(r) {
    var rowNum = r.row;
    var sheetKey = r.sheetKey || 'main';
    var domId = rowDomId(r);
    var rowArgs = rowKeyArg(r);
    var flagLocked = isRowLocked(r);
    var readOnly = isRowReadOnly(r);
    var taioushaDisplay = r.taiousha || adminName || '—';

    var lockBanner = '';
    if (flagLocked) {
      lockBanner =
        '<div class="read-only-banner">上役確認済のため、進捗・対応内容・担当者は変更できません（閲覧のみ）。</div>';
    } else if (r.access === 'view') {
      lockBanner =
        '<div class="read-only-banner">この分類では閲覧のみです（対応者ではありません）。進捗・対応内容・担当者は変更できません。</div>';
    }

    var statusField = readOnly
      ? '<div class="detail-field"><label>進捗</label><div class="val">' +
        attrEscape(r.status || '—') +
        '</div></div>'
      : '<div class="detail-field"><label>進捗</label>' +
        '<select id="status-' +
        domId +
        '">' +
        ['未対応', '対応中', '解決済']
          .map(function (s) {
            return (
              '<option value="' +
              s +
              '"' +
              (r.status === s ? ' selected' : '') +
              '>' +
              s +
              '</option>'
            );
          })
          .join('') +
        '</select></div>';

    var currentCategory = r.category || categoryKeyFromSheet(r.sheetKey || 'main');
    var categoryField = readOnly
      ? '<div class="detail-field"><label>担当部署</label><div class="val">' +
        attrEscape(r.categoryLabel || categoryLabelFromKey(currentCategory) || '—') +
        '</div></div>'
      : '<div class="detail-field"><label>担当部署</label>' +
        '<select id="category-' +
        domId +
        '">' +
        INQUIRY_CATEGORIES.map(function (cat) {
          return (
            '<option value="' +
            attrEscape(cat.key) +
            '"' +
            (cat.key === currentCategory ? ' selected' : '') +
            '>' +
            attrEscape(cat.label) +
            '</option>'
          );
        }).join('') +
        '</select></div>';

    var tantoushaField = readOnly
      ? '<div class="detail-field"><label>担当者</label><div class="val">' +
        attrEscape(r.tantousha || '—') +
        '</div></div>'
      : '<div class="detail-field"><label>担当者 <span style="font-size:10px;color:#64748b;font-weight:500">（手入力・任意）</span></label>' +
        '<input type="text" id="tantousha-' +
        domId +
        '" value="' +
        attrEscape(r.tantousha || '') +
        '" placeholder="案件の担当者名を入力"></div>';

    var commentField = readOnly
      ? '<div class="detail-field full"><label>対応内容（閲覧のみ）</label>' +
        '<textarea readonly class="readonly-like" id="comment-readonly-' +
        domId +
        '">' +
        attrEscape(r.comment || '') +
        '</textarea></div>'
      : '<div class="detail-field full"><label>対応内容</label>' +
        '<textarea id="comment-' +
        domId +
        '">' +
        attrEscape(r.comment || '') +
        '</textarea></div>';

    var saveBar = readOnly
      ? '<div class="update-bar"><span class="updated-at">更新日時: ' +
        attrEscape(r.updatedAt || '—') +
        '</span></div>'
      : '<div class="update-bar">' +
        '<span class="updated-at">更新日時: ' +
        attrEscape(r.updatedAt || '—') +
        '</span>' +
        '<button type="button" class="btn btn-primary" onclick="event.stopPropagation();saveUpdate(' +
        rowArgs +
        ')" style="font-size:12px;padding:7px 18px">保存</button>' +
        '</div>';

    return (
      lockBanner +
      '<div class="detail-grid">' +
      '<div class="detail-field"><label>氏名</label><div class="val">' +
      attrEscape(r.name || '—') +
      '</div></div>' +
      '<div class="detail-field"><label>所属会社 / 部門</label><div class="val">' +
      attrEscape(r.company || '—') +
      ' / ' +
      attrEscape(r.dept || '—') +
      '</div></div>' +
      '<div class="detail-field full"><label>メール</label><div class="val">' +
      attrEscape(r.email || '—') +
      '</div></div>' +
      '<div class="detail-field full"><label>質問内容分類</label><div class="val">' +
      attrEscape(r.type || '—') +
      '</div></div>' +
      '<div class="detail-field full"><label>タイトル</label><div class="val">' +
      attrEscape(r.title || '—') +
      '</div></div>' +
      '<div class="detail-field full"><label>問い合わせ内容</label><div class="val" style="white-space:pre-wrap">' +
      attrEscape(r.body || '—') +
      '</div></div>' +
      (r.attachments
        ? '<div class="detail-field full"><label>添付ファイル</label><div class="val">' +
          formatAttachmentsHtml(r.attachments) +
          '</div></div>'
        : '') +
      categoryField +
      statusField +
      tantoushaField +
      '<div class="detail-field full"><label>対応者 <span style="font-size:10px;color:#64748b;font-weight:500">（自動）</span></label>' +
      '<div class="val-auto">👤 ' +
      attrEscape(taioushaDisplay) +
      '<span class="auto-hint">保存時に記録</span></div></div>' +
      commentField +
      '</div>' +
      saveBar +
      '<div class="flag-section">' +
      '<span class="flag-label">上役確認フラグ：</span>' +
      (r.flag === '済'
        ? '<span class="flag-done">✔ 上役確認済</span><span class="updated-at" style="margin-left:8px">' +
          attrEscape(r.flagAt || '') +
          '</span>'
        : readOnly
          ? '<span class="updated-at">（閲覧のみのため操作不可）</span>'
          : '<button type="button" class="flag-approve-btn" onclick="event.stopPropagation();approveRow(' +
            rowArgs +
            ')">✔ 上役確認済にする</button>') +
      '</div>'
    );
  }

  function toggleDetail(sheetKey, rowNum) {
    var idx = findIndexByRow(rowNum, sheetKey);
    if (idx === -1) return;
    var panel = document.getElementById('detail-' + rowDomId(allData[idx]));
    if (!panel) return;
    var isOpen = panel.classList.contains('open');
    document.querySelectorAll('.detail-panel').forEach(function (p) {
      p.classList.remove('open');
    });
    if (!isOpen) panel.classList.add('open');
  }

  function categoryKeyFromSheet(sheetKey) {
    sheetKey = sheetKey || 'main';
    for (var i = 0; i < INQUIRY_CATEGORIES.length; i++) {
      if ((INQUIRY_CATEGORIES[i].sheet_key || '') === sheetKey) {
        return INQUIRY_CATEGORIES[i].key;
      }
    }
    return sheetKey === 'hr' ? 'hr' : sheetKey === 'is' ? 'is' : 'finance';
  }

  function categoryLabelFromKey(key) {
    for (var i = 0; i < INQUIRY_CATEGORIES.length; i++) {
      if (INQUIRY_CATEGORIES[i].key === key) return INQUIRY_CATEGORIES[i].label;
    }
    return key || '';
  }

  function sheetKeyFromCategory(key) {
    for (var i = 0; i < INQUIRY_CATEGORIES.length; i++) {
      if (INQUIRY_CATEGORIES[i].key === key) {
        return INQUIRY_CATEGORIES[i].sheet_key || 'main';
      }
    }
    return 'main';
  }

  function quickUpdateStatus(sheetKey, rowNum, newStatus) {
    var item = null;
    for (var k = 0; k < allData.length; k++) {
      if (allData[k].row === rowNum && (allData[k].sheetKey || 'main') === sheetKey) {
        item = allData[k];
        break;
      }
    }
    if (!item || isRowReadOnly(item)) return;

    apiPost('api/admin_update.php', {
      row: rowNum,
      sheetKey: sheetKey,
      status: newStatus,
      comment: item.comment || '',
      tantousha: item.tantousha || '',
      category: item.category || categoryKeyFromSheet(sheetKey)
    })
      .then(function (res) {
        if (!res) return;
        if (res.success === false) {
          alert(res.message || '更新できませんでした。');
          renderList();
          return;
        }
        var idx = findIndexByRow(rowNum, sheetKey);
        if (idx === -1) {
          loadData();
          return;
        }
        allData[idx].status = newStatus;
        allData[idx].updatedAt = res.updatedAt || '';
        allData[idx].tantousha = res.tantousha || '';
        allData[idx].taiousha = res.taiousha || '';
        renderList();
      })
      .catch(function (err) {
        alert('エラー: ' + (err && err.message ? err.message : '不明なエラー'));
      });
  }

  function saveUpdate(sheetKey, rowNum) {
    var idx = findIndexByRow(rowNum, sheetKey);
    if (idx === -1 || isRowReadOnly(allData[idx])) return;

    var domId = rowDomId(allData[idx]);
    var statusEl = document.getElementById('status-' + domId);
    var commentEl = document.getElementById('comment-' + domId);
    var tantoushaEl = document.getElementById('tantousha-' + domId);
    var categoryEl = document.getElementById('category-' + domId);
    if (!statusEl || !commentEl || !tantoushaEl || !categoryEl) return;

    var status = statusEl.value;
    var comment = commentEl.value;
    var tantousha = tantoushaEl.value.trim();
    var category = categoryEl.value;

    apiPost('api/admin_update.php', {
      row: rowNum,
      sheetKey: sheetKey,
      status: status,
      comment: comment,
      tantousha: tantousha,
      category: category
    })
      .then(function (res) {
        if (!res) return;
        if (res.success === false) {
          alert(res.message || '保存できませんでした。');
          renderList();
          return;
        }
        idx = findIndexByRow(rowNum, sheetKey);
        if (idx === -1) {
          loadData();
          return;
        }
        var nextSheet = res.sheetKey || sheetKeyFromCategory(category) || sheetKey;
        allData[idx].status = status;
        allData[idx].comment = comment;
        allData[idx].tantousha = res.tantousha || '';
        allData[idx].taiousha = res.taiousha || '';
        allData[idx].updatedAt = res.updatedAt || '';
        allData[idx].sheetKey = nextSheet;
        allData[idx].category = res.category || category;
        allData[idx].categoryLabel = res.categoryLabel || categoryLabelFromKey(category);
        renderList();
        setTimeout(function () {
          var panel = document.getElementById('detail-' + rowDomId(allData[idx]));
          if (panel) panel.classList.add('open');
        }, 10);
      })
      .catch(function (err) {
        alert('エラー: ' + (err && err.message ? err.message : '不明なエラー'));
      });
  }

  function approveRow(sheetKey, rowNum) {
    var idx = findIndexByRow(rowNum, sheetKey);
    if (idx === -1 || isRowReadOnly(allData[idx])) return;
    if (!confirm('上役確認済にしますか？この操作後は編集できなくなります。')) return;

    var domId = rowDomId(allData[idx]);

    apiPost('api/admin_approve.php', {
      row: rowNum,
      sheetKey: sheetKey
    })
      .then(function (res) {
        if (!res) return;
        if (!res.success) {
          alert(res.message || 'エラーが発生しました。');
          return;
        }
        idx = findIndexByRow(rowNum, sheetKey);
        if (idx === -1) {
          loadData();
          return;
        }
        allData[idx].flag = '済';
        allData[idx].flagAt = res.flagAt || '';
        renderList();
        setTimeout(function () {
          var panel = document.getElementById('detail-' + domId);
          if (panel) panel.classList.add('open');
        }, 10);
      })
      .catch(function (err) {
        alert('エラー: ' + (err && err.message ? err.message : '不明なエラー'));
      });
  }

  function filterAdmin(f, el) {
    currentFilter = f;
    document.querySelectorAll('#status-tabs .tab').forEach(function (t) {
      t.classList.remove('active');
    });
    el.classList.add('active');
    renderList();
  }

  function openRowFromQueryParam() {
    var row = 0;
    var sheetKey = 'main';
    try {
      var params = new URLSearchParams(window.location.search);
      row = parseInt(params.get('row'), 10);
      var sheet = params.get('sheet');
      if (sheet === 'hr' || sheet === 'is' || sheet === 'main') sheetKey = sheet;
    } catch (e) {
      return;
    }
    if (!row || row < 1) return;

    currentCategoryKey = categoryKeyForRow({ sheetKey: sheetKey });
    renderCategoryBar();

    currentFilter = 'all';
    document.querySelectorAll('#status-tabs .tab').forEach(function (t, i) {
      t.classList.toggle('active', i === 0);
    });
    renderList();

    setTimeout(function () {
      var idx = findIndexByRow(row, sheetKey);
      if (idx === -1) return;
      var panel = document.getElementById('detail-' + rowDomId(allData[idx]));
      if (!panel) return;
      document.querySelectorAll('.detail-panel').forEach(function (p) {
        p.classList.remove('open');
      });
      panel.classList.add('open');
      panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 150);
  }
</script>
</body>
</html>
