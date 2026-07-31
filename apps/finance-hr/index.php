<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$canAdmin = is_admin($user);

$inquiryCategories = [];
foreach (inquiry_categories() as $key => $cat) {
    $inquiryCategories[] = [
        'key' => (string) $key,
        'label' => (string) ($cat['label'] ?? $key),
        'sheetKey' => (string) ($cat['sheet_key'] ?? 'main'),
        'types' => array_values(array_map('strval', $cat['types'] ?? [])),
    ];
}
$categoriesJson = json_encode($inquiryCategories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
if ($categoriesJson === false) {
    $categoriesJson = '[]';
}
$onboardingType = (string) (app_config()['onboarding_doc_type'] ?? '');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="assets/favicon.png?v=5" type="image/png">
  <link rel="apple-touch-icon" href="assets/favicon.png?v=5">
  <title>社内お問い合わせ</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Noto Sans JP', 'Helvetica Neue', sans-serif; font-size: 14px; color: #333; background: #f5f6f8; }
    .container {
      max-width: min(1480px, calc(100vw - 24px));
      width: 100%;
      margin: 0 auto;
      padding: 20px 16px;
    }

    .page-header {
      background: #fff;
      border-radius: 10px;
      padding: 16px 20px;
      margin-bottom: 16px;
      border: 1px solid #e0e0e0;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .page-header-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .page-header h1 { font-size: 16px; font-weight: 600; flex: 1; min-width: 0; }
    .user-chip { margin-left: auto; font-size: 12px; color: #666; background: #f0f0f0; padding: 4px 10px; border-radius: 20px; }
    .logout-link { font-size: 12px; color: #1a73e8; text-decoration: none; }
    .logout-link:hover { text-decoration: underline; }

    .inquiry-form-notice {
      color: #c5221f;
      font-size: 12px;
      line-height: 1.65;
      font-weight: 500;
      padding: 10px 12px;
      background: #fff5f5;
      border: 1px solid #f5c2c7;
      border-radius: 8px;
    }

    .tabs { display: flex; gap: 0; margin-bottom: 12px; background: #fff; border-radius: 10px; overflow: hidden; border: 1px solid #e0e0e0; }
    .tab-btn { flex: 1; padding: 11px 8px; border: none; background: transparent; cursor: pointer; font-size: 13px; color: #666; border-right: 1px solid #e0e0e0; transition: background 0.15s; }
    .tab-btn:last-child { border-right: none; }
    .tab-btn.active { background: #1a73e8; color: #fff; font-weight: 500; }

    .category-bar {
      display: flex; gap: 8px; margin-bottom: 16px;
    }
    .category-btn {
      flex: 1 1 0; min-width: 0; padding: 10px 12px; border-radius: 10px;
      border: 1px solid #dadce0; background: #fff; color: #555;
      font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit;
      text-align: center;
      transition: background 0.15s, border-color 0.15s, color 0.15s;
    }
    .category-btn:hover { background: #f8f9ff; border-color: #c5cae9; }
    .category-btn.active {
      background: #e8f0fe; border-color: #1a73e8; color: #1a73e8;
      box-shadow: 0 0 0 1px rgba(26,115,232,0.2);
    }

    .card { background: #fff; border-radius: 10px; border: 1px solid #e0e0e0; overflow: hidden; margin-bottom: 16px; }
    .card-header { padding: 14px 18px; border-bottom: 1px solid #f0f0f0; font-weight: 500; font-size: 14px; color: #444; display: flex; align-items: center; gap: 8px; }

    .info-bar { display: flex; flex-wrap: wrap; gap: 12px; padding: 10px 18px; background: #f8f9ff; border-bottom: 1px solid #e8eaf6; font-size: 12px; color: #555; }
    .info-bar .info-item { display: flex; align-items: center; gap: 4px; }

    .form-body { padding: 18px; display: flex; flex-direction: column; gap: 16px; }
    .form-group label { display: block; font-size: 12px; font-weight: 500; color: #555; margin-bottom: 6px; }
    .form-group select,
    .form-group input[type="text"],
    .form-group textarea {
      width: 100%; padding: 9px 12px; border: 1px solid #dadce0; border-radius: 8px;
      font-size: 13px; color: #333; background: #fff; transition: border-color 0.2s;
    }
    .form-group select:focus,
    .form-group input:focus,
    .form-group textarea:focus { outline: none; border-color: #1a73e8; box-shadow: 0 0 0 2px rgba(26,115,232,0.15); }
    .form-group textarea { resize: vertical; min-height: 100px; line-height: 1.7; }
    .char-hint { font-size: 11px; color: #999; text-align: right; margin-top: 4px; }
    .char-hint span { color: #333; font-weight: 500; }

    .form-actions { padding: 12px 18px; border-top: 1px solid #f0f0f0; display: flex; gap: 8px; justify-content: flex-end; background: #fafafa; }
    .btn { padding: 8px 18px; border-radius: 8px; font-size: 13px; cursor: pointer; border: 1px solid #dadce0; background: #fff; color: #444; font-family: inherit; transition: background 0.15s; }
    .btn:hover { background: #f5f5f5; }
    .btn-primary { background: #1a73e8; border-color: #1a73e8; color: #fff; }
    .btn-primary:hover { background: #1557b0; }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; }

    .confirm-row { display: flex; padding: 10px 0; border-bottom: 1px solid #f5f5f5; font-size: 13px; }
    .confirm-row:last-child { border-bottom: none; }
    .confirm-label { width: 130px; flex-shrink: 0; color: #888; font-size: 12px; padding-top: 1px; }
    .confirm-value { flex: 1; color: #333; line-height: 1.7; white-space: pre-wrap; }

    .table-header { display: grid; grid-template-columns: 44px 108px minmax(0,1.1fr) minmax(0,1.2fr) 76px 72px; gap: 8px; padding: 8px 18px; font-size: 11px; color: #999; font-weight: 600; letter-spacing: 0.04em; border-bottom: 1px solid #f0f0f0; background: #fafafa; }
    .table-row { display: grid; grid-template-columns: 44px 108px minmax(0,1.1fr) minmax(0,1.2fr) 76px 72px; gap: 8px; padding: 11px 18px; border-bottom: 1px solid #f7f7f7; align-items: center; font-size: 13px; }
    .table-row:last-child { border-bottom: none; }
    .table-row:hover { background: #f8f9ff; }
    .row-num {
      font-size: 11px;
      font-weight: 700;
      color: #5c6bc0;
      text-align: center;
      font-variant-numeric: tabular-nums;
    }
    .ts { font-size: 11px; color: #888; }
    .badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: 11px; font-weight: 500; }
    .badge-pending  { background: #fff3cd; color: #856404; }
    .badge-progress { background: #cfe2ff; color: #084298; }
    .badge-done     { background: #d1e7dd; color: #0a3622; }
    .dept-tag { font-size: 11px; background: #f0f0f0; color: #555; padding: 2px 8px; border-radius: 20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; display: inline-block; vertical-align: middle; }
    .type-cell { font-size: 12px; color: #444; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dept-select {
      font-size: 11px;
      padding: 3px 6px;
      border-radius: 8px;
      border: 1px solid #dadce0;
      background: #fff;
      color: #333;
      max-width: 100%;
      cursor: pointer;
    }
    .dept-select:focus {
      outline: none;
      border-color: #1a73e8;
      box-shadow: 0 0 0 2px rgba(26,115,232,0.15);
    }
    .empty { text-align: center; padding: 32px; color: #aaa; font-size: 13px; }

    .is-dev-panel { display: none; }
    .is-dev-frame-wrap {
      background: #fff;
      border-radius: 10px;
      border: 1px solid #e0e0e0;
      overflow: hidden;
      min-height: 720px;
      width: 100%;
    }
    .is-dev-frame {
      width: 100%;
      min-height: 720px;
      height: 78vh;
      border: 0;
      display: block;
      background: #fff;
    }
    .filter-bar { display: flex; gap: 6px; padding: 10px 18px; border-bottom: 1px solid #f0f0f0; flex-wrap: wrap; }
    .filter-btn { padding: 4px 12px; border-radius: 20px; font-size: 12px; cursor: pointer; border: 1px solid #dadce0; background: #fff; color: #666; }
    .filter-btn.active { background: #e8f0fe; border-color: #1a73e8; color: #1a73e8; font-weight: 500; }

    .loading { text-align: center; padding: 24px; color: #aaa; font-size: 13px; }
    .alert-success { background: #d1e7dd; color: #0a3622; border: 1px solid #a3cfbb; border-radius: 8px; padding: 12px 16px; margin-bottom: 12px; font-size: 13px; }
    .alert-error   { background: #fce8e6; color: #c5221f; border: 1px solid #f5c2c7; border-radius: 8px; padding: 12px 16px; margin-bottom: 12px; font-size: 13px; }

    .upload-section { display: none !important; }
    .upload-section.is-visible { display: block !important; }
    .upload-box {
      display: flex; align-items: center; gap: 10px;
      border: 1px dashed #dadce0; border-radius: 8px; padding: 12px 14px;
      background: #fafafa; cursor: pointer; transition: border-color 0.15s, background 0.15s;
    }
    .upload-box:hover, .upload-box.dragover { border-color: #1a73e8; background: #f0f6ff; }
    .upload-box input[type="file"] { display: none; }
    .upload-icon { font-size: 20px; color: #888; flex-shrink: 0; }
    .upload-text { font-size: 13px; color: #333; }
    .upload-hint { font-size: 11px; color: #999; margin-top: 2px; }
    .file-list { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; }
    .file-item {
      display: flex; align-items: center; gap: 8px; padding: 8px 10px;
      border: 1px solid #e8eaed; border-radius: 8px; background: #fff; font-size: 12px;
    }
    .file-item-thumb {
      width: 40px; height: 40px; border-radius: 6px; object-fit: cover;
      border: 1px solid #e8eaed; flex-shrink: 0;
    }
    .file-item-icon {
      width: 40px; height: 40px; border-radius: 6px; background: #f0f4ff;
      display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;
    }
    .file-item-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .file-item-remove {
      border: none; background: #f1f3f4; color: #666; width: 24px; height: 24px;
      border-radius: 50%; cursor: pointer; font-size: 14px; line-height: 1; flex-shrink: 0;
    }
    .file-item-remove:hover { background: #fce8e6; color: #c5221f; }

    @media (max-width: 640px) {
      .table-header, .table-row { grid-template-columns: 40px 86px 1fr 72px; }
      .table-header > *:nth-child(4), .table-row > *:nth-child(4),
      .table-header > *:nth-child(5), .table-row > *:nth-child(5) { display: none; }
    }
  </style>
</head>
<body>
<div class="container">

  <div class="page-header">
    <div class="page-header-row">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1a73e8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
      <h1>社内に関するお問い合わせ</h1>
      <div class="user-chip" id="user-name-chip">読み込み中...</div>
      <?php if ($canAdmin): ?>
      <a class="logout-link" href="admin.php">担当者画面へ</a>
      <?php endif; ?>
      <a class="logout-link" href="logout.php">社員サイトへ</a>
    </div>
    <p class="inquiry-form-notice">
      お問い合わせフォームは情報システム部で管理されます。内容をみられては困る内容は入力を控えて、総務・経理の担当からの対応時に情報を伝えるようにしてください。
    </p>
  </div>

  <div class="tabs">
    <button type="button" class="tab-btn active" onclick="showTab('history')">📋 履歴一覧</button>
    <button type="button" class="tab-btn" onclick="showTab('new-form')">✉️ 新規お問い合わせ</button>
    <button type="button" class="tab-btn" onclick="showTab('confirm')" id="tab-confirm-btn" style="display:none">✅ 確認画面</button>
  </div>

  <div class="category-bar" id="category-bar" role="tablist" aria-label="問い合わせカテゴリ"></div>

  <div id="alert-area"></div>

  <div id="is-dev-panel" class="is-dev-panel">
    <div class="is-dev-frame-wrap">
      <iframe
        id="is-dev-frame"
        class="is-dev-frame"
        title="開発依頼"
        src="about:blank"
      ></iframe>
    </div>
  </div>

  <div id="tab-history">
    <div class="card">
      <div class="card-header">
        📋 <span id="history-title">お問い合わせ履歴（最大50件）</span>
      </div>
      <div class="filter-bar">
        <span style="font-size:12px;color:#888;align-self:center;margin-right:2px">進捗</span>
        <button type="button" class="filter-btn active" onclick="filterHistory('all',this)">すべて</button>
        <button type="button" class="filter-btn" onclick="filterHistory('未対応',this)">未対応</button>
        <button type="button" class="filter-btn" onclick="filterHistory('対応中',this)">対応中</button>
        <button type="button" class="filter-btn" onclick="filterHistory('解決済',this)">解決済</button>
      </div>
      <div class="table-header">
        <span title="お問い合わせ番号">行</span>
        <span>日時</span>
        <span>お問い合わせ内容</span>
        <span>タイトル</span>
        <span>担当部署</span>
        <span>進捗</span>
      </div>
      <div id="history-list"><div class="loading">読み込み中...</div></div>
    </div>
  </div>

  <div id="tab-new-form" style="display:none">
    <div class="card">
      <div class="card-header" id="form-card-header">✉️ お問い合わせ</div>
      <div class="info-bar">
        <div class="info-item">👤 氏名：<strong id="f-name">—</strong></div>
        <div class="info-item">🪪 社員ID：<strong id="f-employee-id">—</strong></div>
        <div class="info-item">🏢 所属会社：<strong id="f-company">—</strong></div>
        <div class="info-item">🏬 所属部署：<strong id="f-dept">—</strong></div>
      </div>
      <div class="form-body">
        <div class="form-group">
          <label>（１）お問い合わせ内容を選択してください。</label>
          <select id="f-type">
            <option value="">選択してください ▼</option>
          </select>
        </div>
        <div class="form-group">
          <label>（２）お問合せ内容のタイトル（30字以内を目安にタイトルをつけてください）</label>
          <input type="text" id="f-title" maxlength="30" placeholder="例：〇〇〇について" oninput="updateChar()">
          <div class="char-hint"><span id="char-count">0</span> / 30字</div>
        </div>
        <div class="form-group">
          <label>（３）お問い合わせ内容を記載してください。</label>
          <textarea id="f-body" placeholder="詳細をご記入ください..."></textarea>
        </div>
        <div class="form-group upload-section" id="upload-section">
          <label>（４）入社書類を添付してください <span style="color:#c5221f">*</span></label>
          <div class="upload-box" id="upload-box">
            <input type="file" id="f-attachments" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" multiple>
            <span class="upload-icon">📎</span>
            <div>
              <div class="upload-text">クリックまたはドラッグ＆ドロップでファイルを追加</div>
              <div class="upload-hint">画像・PDF・Word・Excel（1ファイル5MB以内、最大10件）</div>
            </div>
          </div>
          <div class="file-list" id="file-list"></div>
        </div>
      </div>
      <div class="form-actions">
        <button type="button" class="btn" onclick="showTab('history')">キャンセル</button>
        <button type="button" class="btn btn-primary" onclick="goToConfirm()">内容を確認 ›</button>
      </div>
    </div>
  </div>

  <div id="tab-confirm" style="display:none">
    <div class="card">
      <div class="card-header">✅ 確認画面</div>
      <div class="info-bar">
        <div class="info-item">👤 氏名：<strong id="c-name">—</strong></div>
        <div class="info-item">🪪 社員ID：<strong id="c-employee-id">—</strong></div>
        <div class="info-item">🏢 所属会社：<strong id="c-company">—</strong></div>
        <div class="info-item">🏬 所属部署：<strong id="c-dept">—</strong></div>
      </div>
      <div style="padding:16px 18px">
        <div class="confirm-row"><span class="confirm-label">カテゴリ</span><span class="confirm-value" id="c-category">—</span></div>
        <div class="confirm-row"><span class="confirm-label">（１）質問内容</span><span class="confirm-value" id="c-type">—</span></div>
        <div class="confirm-row"><span class="confirm-label">（２）タイトル</span><span class="confirm-value" id="c-title">—</span></div>
        <div class="confirm-row"><span class="confirm-label">（３）問い合わせ内容</span><span class="confirm-value" id="c-body">—</span></div>
        <div class="confirm-row" id="c-attachments-row" style="display:none"><span class="confirm-label">（４）添付ファイル</span><span class="confirm-value" id="c-attachments">—</span></div>
      </div>
      <div class="form-actions">
        <button type="button" class="btn" onclick="showTab('new-form')">‹ 戻る</button>
        <button type="button" class="btn btn-primary" id="submit-btn" onclick="submitForm()">送信</button>
      </div>
    </div>
  </div>

</div>

<script>
  var userProfile = {};
  var allHistory = [];
  var currentFilter = 'all';
  var INQUIRY_CATEGORIES = <?= $categoriesJson ?>;
  var currentCategoryKey = (INQUIRY_CATEGORIES[0] && INQUIRY_CATEGORIES[0].key) || 'hr';
  var ONBOARDING_DOC_TYPE = <?= json_encode($onboardingType, JSON_UNESCAPED_UNICODE) ?>;
  var ONBOARDING_DOC_VALUE = 'onboarding-docs';
  var MAX_ATTACHMENTS = 10;
  var MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;
  var selectedFiles = [];
  var currentMainTab = 'history';
  var DEV_REQUEST_LIST_URL = '/development-requests?embed=1';
  var DEV_REQUEST_CREATE_URL = '/development-requests/create?embed=1';
  var isDevDetailView = false;

  function findCategory(key) {
    for (var i = 0; i < INQUIRY_CATEGORIES.length; i++) {
      if (INQUIRY_CATEGORIES[i].key === key) return INQUIRY_CATEGORIES[i];
    }
    return null;
  }

  function currentCategory() {
    return findCategory(currentCategoryKey) || INQUIRY_CATEGORIES[0] || { key: 'finance', label: '経理', types: [] };
  }

  function visibleCategories() {
    if (isIsCategory() && isDevDetailView) {
      return INQUIRY_CATEGORIES.filter(function (cat) { return cat.key === 'is'; });
    }
    return INQUIRY_CATEGORIES;
  }

  function renderCategoryBar() {
    var bar = document.getElementById('category-bar');
    if (!bar) return;
    bar.innerHTML = visibleCategories().map(function (cat) {
      var active = cat.key === currentCategoryKey ? ' active' : '';
      var key = String(cat.key || '').replace(/[^a-z0-9_-]/gi, '');
      return (
        '<button type="button" class="category-btn' + active + '" data-category="' +
        key +
        '" onclick="selectCategory(\'' +
        key +
        '\')">' +
        escapeHtml(cat.label) +
        '</button>'
      );
    }).join('');
  }

  function selectCategory(key) {
    if (!findCategory(key)) return;
    currentCategoryKey = key;
    if (key !== 'is') {
      isDevDetailView = false;
    }
    renderCategoryBar();
    rebuildTypeOptions();
    updateFormHeader();
    renderHistory();
    syncIsDevPanel();
  }

  function isIsCategory() {
    return currentCategoryKey === 'is';
  }

  function syncIsDevPanel() {
    var panel = document.getElementById('is-dev-panel');
    var history = document.getElementById('tab-history');
    var form = document.getElementById('tab-new-form');
    var confirm = document.getElementById('tab-confirm');
    var confirmBtn = document.getElementById('tab-confirm-btn');
    if (!panel || !history || !form || !confirm) return;

    if (isIsCategory()) {
      panel.style.display = 'block';
      history.style.display = 'none';
      form.style.display = 'none';
      confirm.style.display = 'none';
      if (confirmBtn) confirmBtn.style.display = 'none';
      var mode = currentMainTab === 'new-form' ? 'create' : 'list';
      var next = mode === 'create' ? DEV_REQUEST_CREATE_URL : DEV_REQUEST_LIST_URL;
      var frame = document.getElementById('is-dev-frame');
      var force = isDevDetailView || !frame || frame.getAttribute('data-src') !== next;
      loadIsDevFrame(mode, force);
      return;
    }

    panel.style.display = 'none';
    isDevDetailView = false;
    renderCategoryBar();
    if (currentMainTab === 'history') {
      history.style.display = '';
      form.style.display = 'none';
      confirm.style.display = 'none';
    } else if (currentMainTab === 'new-form') {
      history.style.display = 'none';
      form.style.display = '';
      confirm.style.display = 'none';
    } else if (currentMainTab === 'confirm') {
      history.style.display = 'none';
      form.style.display = 'none';
      confirm.style.display = '';
      if (confirmBtn) confirmBtn.style.display = '';
    }
  }

  function isDevRequestDetailUrl(url) {
    try {
      var path = new URL(url, window.location.origin).pathname;
      return /^\/development-requests\/\d+(\/)?$/.test(path);
    } catch (e) {
      return false;
    }
  }

  function syncDevDetailFromFrame() {
    var frame = document.getElementById('is-dev-frame');
    if (!frame || !isIsCategory()) {
      if (isDevDetailView) {
        isDevDetailView = false;
        renderCategoryBar();
      }
      return;
    }
    var href = '';
    try {
      href = frame.contentWindow && frame.contentWindow.location
        ? String(frame.contentWindow.location.href || '')
        : '';
    } catch (e) {
      href = frame.getAttribute('src') || '';
    }
    var next = isDevRequestDetailUrl(href);
    if (next === isDevDetailView) return;
    isDevDetailView = next;
    renderCategoryBar();
  }

  function bindIsDevFrameEvents() {
    var frame = document.getElementById('is-dev-frame');
    if (!frame || frame.getAttribute('data-bound') === '1') return;
    frame.setAttribute('data-bound', '1');
    frame.addEventListener('load', syncDevDetailFromFrame);
  }

  function loadIsDevFrame(mode, force) {
    var frame = document.getElementById('is-dev-frame');
    if (!frame) return;
    bindIsDevFrameEvents();
    var next = mode === 'create' ? DEV_REQUEST_CREATE_URL : DEV_REQUEST_LIST_URL;
    if (!force && frame.getAttribute('data-src') === next && !isDevDetailView) return;
    isDevDetailView = false;
    renderCategoryBar();
    frame.setAttribute('data-src', next);
    frame.src = next;
  }

  function updateFormHeader() {
    var cat = currentCategory();
    var header = document.getElementById('form-card-header');
    if (header) {
      header.textContent = '✉️ ' + (cat.label || '') + 'に関するお問い合わせ';
    }
  }

  function rebuildTypeOptions(preserveValue) {
    var sel = document.getElementById('f-type');
    if (!sel) return;
    var prev = preserveValue ? sel.value : '';
    var cat = currentCategory();
    var html = '<option value="">選択してください ▼</option>';
    (cat.types || []).forEach(function (type) {
      var value = type.indexOf('入社書類提出') >= 0 ? ONBOARDING_DOC_VALUE : type;
      html += '<option value="' + escapeHtml(value) + '">' + escapeHtml(type) + '</option>';
    });
    sel.innerHTML = html;
    if (prev) {
      var found = false;
      for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === prev) {
          sel.value = prev;
          found = true;
          break;
        }
      }
      if (!found) sel.value = '';
    }
    toggleUploadSection();
  }

  function escapeHtml(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function showAlert(type, html, ms) {
    var area = document.getElementById('alert-area');
    area.innerHTML = '<div class="alert-' + type + '">' + html + '</div>';
    if (ms) setTimeout(function () { area.innerHTML = ''; }, ms);
  }

  function apiGet(url) {
    return fetch(url, { credentials: 'same-origin' }).then(function (res) {
      return res.json().then(function (data) {
        if (res.status === 401 && data && data.redirect) {
          window.location.href = data.redirect;
          return new Promise(function () {});
        }
        if (!res.ok) {
          var err = new Error((data && data.error) || 'リクエストに失敗しました');
          throw err;
        }
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
        if (res.status === 401 && data && data.redirect) {
          window.location.href = data.redirect;
          return new Promise(function () {});
        }
        if (!res.ok) {
          var err = new Error((data && data.error) || 'リクエストに失敗しました');
          throw err;
        }
        return data;
      });
    });
  }

  window.onload = function () {
    renderCategoryBar();
    rebuildTypeOptions();
    updateFormHeader();

    var params = new URLSearchParams(window.location.search || '');
    var initialCategory = params.get('category');
    if (initialCategory && findCategory(initialCategory)) {
      selectCategory(initialCategory);
    } else {
      syncIsDevPanel();
    }

    apiGet('api/profile.php')
      .then(function (profile) {
        if (!profile) return;
        userProfile = profile;
        document.getElementById('user-name-chip').textContent = profile.fullName || '—';
        document.getElementById('f-name').textContent = profile.fullName || '—';
        document.getElementById('f-employee-id').textContent = profile.employeeId || '—';
        document.getElementById('f-company').textContent = profile.company || '—';
        document.getElementById('f-dept').textContent = profile.department || '—';
        document.getElementById('c-name').textContent = profile.fullName || '—';
        document.getElementById('c-employee-id').textContent = profile.employeeId || '—';
        document.getElementById('c-company').textContent = profile.company || '—';
        document.getElementById('c-dept').textContent = profile.department || '—';
        document.getElementById('history-title').textContent =
          (profile.fullName || '') + 'さん：お問い合わせ履歴（最大50件）';
      })
      .catch(handleError);

    loadHistory();

    var typeSelect = document.getElementById('f-type');
    if (typeSelect) {
      typeSelect.addEventListener('change', toggleUploadSection);
    }
    toggleUploadSection();
  };

  function loadHistory() {
    document.getElementById('history-list').innerHTML = '<div class="loading">読み込み中...</div>';
    apiGet('api/history.php')
      .then(function (data) {
        allHistory = data && Array.isArray(data) ? data : [];
        renderHistory();
      })
      .catch(function (err) {
        allHistory = [];
        document.getElementById('history-list').innerHTML =
          '<div class="empty">データ取得に失敗しました（' +
          escapeHtml((err && err.message) || 'unknown') +
          '）</div>';
      });
  }

  function renderHistory() {
    if (!allHistory) allHistory = [];

    var cat = currentCategory();
    var byDept = allHistory.filter(function (r) {
      if (r.category) return r.category === currentCategoryKey;
      if (r.sheetKey) return r.sheetKey === (cat.sheetKey || 'main');
      return false;
    });

    var filtered =
      currentFilter === 'all'
        ? byDept
        : byDept.filter(function (r) { return r.status === currentFilter; });

    if (!filtered || filtered.length === 0) {
      document.getElementById('history-list').innerHTML =
        '<div class="empty">' + escapeHtml(cat.label || '') + 'の該当がありません</div>';
      return;
    }

    document.getElementById('history-list').innerHTML = filtered
      .map(function (r) {
        var typeLabel = escapeHtml(r.type || '—');
        var rowNo = r.row != null && r.row !== '' ? String(r.row) : '—';
        var deptLabel = escapeHtml(r.categoryLabel || ((findCategory(r.category) || {}).label) || '—');
        return (
          '<div class="table-row">' +
          '<span class="row-num" title="お問い合わせ番号">' +
          escapeHtml(rowNo) +
          '</span>' +
          '<span class="ts">' + escapeHtml(r.timestamp || '—') + '</span>' +
          '<span class="type-cell" title="' + typeLabel + '">' + typeLabel + '</span>' +
          '<span title="' + escapeHtml(r.title || '') + '">' + escapeHtml(r.title || '—') + '</span>' +
          '<span title="' + deptLabel + '">' + deptLabel + '</span>' +
          '<span>' + badgeHtml(r.status || '未対応') + '</span>' +
          '</div>'
        );
      })
      .join('');
  }

  function badgeHtml(s) {
    var cls = s === '未対応' ? 'badge-pending' : s === '対応中' ? 'badge-progress' : 'badge-done';
    return '<span class="badge ' + cls + '">' + escapeHtml(s) + '</span>';
  }

  function filterHistory(f, el) {
    currentFilter = f;
    var statusBar = el && el.parentElement;
    if (statusBar) {
      statusBar.querySelectorAll('.filter-btn').forEach(function (b) { b.classList.remove('active'); });
    }
    if (el) el.classList.add('active');
    renderHistory();
  }

  function showTab(id) {
    currentMainTab = id === 'confirm' ? 'confirm' : (id === 'new-form' ? 'new-form' : 'history');

    document.querySelectorAll('.tab-btn').forEach(function (b, i) {
      b.classList.remove('active');
      if ((i === 0 && currentMainTab === 'history') || (i === 1 && currentMainTab === 'new-form') || (i === 2 && currentMainTab === 'confirm')) {
        b.classList.add('active');
      }
    });

    if (isIsCategory()) {
      if (id === 'confirm') {
        currentMainTab = 'new-form';
      }
      syncIsDevPanel();
      return;
    }

    ['history', 'new-form', 'confirm'].forEach(function (t) {
      document.getElementById('tab-' + t).style.display = 'none';
    });
    document.getElementById('tab-' + id).style.display = 'block';
    if (id === 'confirm') {
      document.getElementById('tab-confirm-btn').style.display = '';
    }
    if (id === 'new-form') {
      rebuildTypeOptions(true);
      updateFormHeader();
      toggleUploadSection();
    }
    if (id === 'history') {
      renderHistory();
    }
  }

  function getInquiryTypeValue() {
    var sel = document.getElementById('f-type');
    if (!sel) return '';
    var opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return '';
    if (opt.value === ONBOARDING_DOC_VALUE) return ONBOARDING_DOC_TYPE;
    return opt.value;
  }

  function isOnboardingDocType(type) {
    var t = String(type || '').trim();
    return t === ONBOARDING_DOC_VALUE || t === ONBOARDING_DOC_TYPE || t.indexOf('入社書類提出') >= 0;
  }

  function toggleUploadSection() {
    var section = document.getElementById('upload-section');
    if (!section) return;
    var show = isOnboardingDocType(document.getElementById('f-type').value);
    if (show) {
      section.classList.add('is-visible');
      section.style.display = 'block';
    } else {
      section.classList.remove('is-visible');
      section.style.display = 'none';
      clearAttachments();
    }
  }

  function isAllowedFile(file) {
    if (!file) return false;
    if (file.type && file.type.indexOf('image/') === 0) return true;
    var ext = (file.name || '').split('.').pop().toLowerCase();
    return ['pdf', 'doc', 'docx', 'xls', 'xlsx'].indexOf(ext) !== -1;
  }

  function renderFileList() {
    var list = document.getElementById('file-list');
    list.innerHTML = selectedFiles.map(function (item, index) {
      var isImage = item.file.type && item.file.type.indexOf('image/') === 0;
      var thumb = isImage
        ? '<img class="file-item-thumb" src="' + item.previewUrl + '" alt="">'
        : '<span class="file-item-icon">📄</span>';
      return (
        '<div class="file-item">' +
        thumb +
        '<span class="file-item-name" title="' + escapeHtml(item.file.name) + '">' +
        escapeHtml(item.file.name) +
        '</span>' +
        '<button type="button" class="file-item-remove" onclick="removeAttachment(' + index + ')" title="削除">×</button>' +
        '</div>'
      );
    }).join('');
  }

  function addFiles(fileList) {
    var files = Array.prototype.slice.call(fileList || []);
    for (var i = 0; i < files.length; i++) {
      var file = files[i];
      if (!isAllowedFile(file)) {
        alert('対応形式: 画像・PDF・Word・Excel\n（' + file.name + '）');
        return;
      }
      if (file.size > MAX_ATTACHMENT_BYTES) {
        alert('1ファイル5MB以内にしてください: ' + file.name);
        return;
      }
      if (selectedFiles.length >= MAX_ATTACHMENTS) {
        alert('添付は最大 ' + MAX_ATTACHMENTS + ' 件までです。');
        return;
      }
      var previewUrl = '';
      if (file.type && file.type.indexOf('image/') === 0) {
        previewUrl = URL.createObjectURL(file);
      }
      selectedFiles.push({ file: file, previewUrl: previewUrl });
    }
    renderFileList();
  }

  function removeAttachment(index) {
    var item = selectedFiles[index];
    if (item && item.previewUrl) URL.revokeObjectURL(item.previewUrl);
    selectedFiles.splice(index, 1);
    renderFileList();
  }

  function clearAttachments() {
    selectedFiles.forEach(function (item) {
      if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
    });
    selectedFiles = [];
    var input = document.getElementById('f-attachments');
    if (input) input.value = '';
    renderFileList();
  }

  function readAttachments(callback) {
    if (!selectedFiles.length) {
      callback([]);
      return;
    }
    var results = [];
    var pending = selectedFiles.length;
    var failed = false;
    selectedFiles.forEach(function (item) {
      var reader = new FileReader();
      reader.onload = function (e) {
        if (failed) return;
        var dataUrl = String(e.target.result || '');
        var comma = dataUrl.indexOf(',');
        results.push({
          name: item.file.name,
          mimeType: item.file.type,
          data: comma >= 0 ? dataUrl.slice(comma + 1) : dataUrl
        });
        pending--;
        if (pending === 0) callback(results);
      };
      reader.onerror = function () {
        failed = true;
        callback(null);
      };
      reader.readAsDataURL(item.file);
    });
  }

  (function initUploadBox() {
    var uploadBox = document.getElementById('upload-box');
    var fileInput = document.getElementById('f-attachments');
    if (!uploadBox || !fileInput) return;

    uploadBox.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
      addFiles(fileInput.files);
      fileInput.value = '';
    });
    uploadBox.addEventListener('dragover', function (e) {
      e.preventDefault();
      uploadBox.classList.add('dragover');
    });
    uploadBox.addEventListener('dragleave', function () {
      uploadBox.classList.remove('dragover');
    });
    uploadBox.addEventListener('drop', function (e) {
      e.preventDefault();
      uploadBox.classList.remove('dragover');
      addFiles(e.dataTransfer.files);
    });
  })();

  function updateChar() {
    document.getElementById('char-count').textContent =
      document.getElementById('f-title').value.length;
  }

  function goToConfirm() {
    var type = getInquiryTypeValue();
    var title = document.getElementById('f-title').value.trim();
    var body = document.getElementById('f-body').value.trim();
    if (!type || !title || !body) {
      alert('すべての項目を入力してください。');
      return;
    }
    if (isOnboardingDocType(type) && selectedFiles.length === 0) {
      alert('入社書類提出の場合は、書類ファイルまたは画像を1件以上添付してください。');
      return;
    }
    document.getElementById('c-category').textContent = currentCategory().label || '—';
    document.getElementById('c-type').textContent = type;
    document.getElementById('c-title').textContent = title;
    document.getElementById('c-body').textContent = body;
    var attachRow = document.getElementById('c-attachments-row');
    if (isOnboardingDocType(type) && selectedFiles.length > 0) {
      attachRow.style.display = '';
      document.getElementById('c-attachments').textContent =
        selectedFiles.map(function (item) { return item.file.name; }).join('\n');
    } else {
      attachRow.style.display = 'none';
      document.getElementById('c-attachments').textContent = '—';
    }
    document.getElementById('tab-confirm-btn').style.display = '';
    showTab('confirm');
  }

  function submitForm() {
    var btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = '送信中...';

    function doSubmit(attachments) {
      apiPost('api/submit.php', {
        category: currentCategoryKey,
        type: document.getElementById('c-type').textContent,
        title: document.getElementById('c-title').textContent,
        body: document.getElementById('c-body').textContent,
        attachments: attachments || []
      })
        .then(function (res) {
          showTab('history');
          var msg = '✅ お問い合わせを送信しました。担当者よりご連絡いたします。';
          if (res && res.chatNotified === false) {
            msg += '（※ 通知失敗';
            if (res.chatError) msg += ': ' + res.chatError;
            msg += '）';
          }
          showAlert('success', msg, res && res.chatNotified === false ? 8000 : 5000);
          document.getElementById('f-type').value = '';
          document.getElementById('f-title').value = '';
          document.getElementById('f-body').value = '';
          document.getElementById('char-count').textContent = '0';
          clearAttachments();
          toggleUploadSection();
          document.getElementById('tab-confirm-btn').style.display = 'none';
          btn.disabled = false;
          btn.textContent = '送信';
          loadHistory();
        })
        .catch(function (err) {
          btn.disabled = false;
          btn.textContent = '送信';
          handleError(err);
        });
    }

    var type = document.getElementById('c-type').textContent;
    if (isOnboardingDocType(type) && selectedFiles.length > 0) {
      readAttachments(function (attachments) {
        if (attachments === null) {
          btn.disabled = false;
          btn.textContent = '送信';
          showAlert('error', 'ファイルの読み込みに失敗しました。', 6000);
          return;
        }
        doSubmit(attachments);
      });
    } else {
      doSubmit([]);
    }
  }

  function handleError(err) {
    var msg = err && err.message ? err.message : '不明なエラー';
    showAlert('error', '⚠️ エラーが発生しました: ' + escapeHtml(msg), 6000);
  }
</script>
</body>
</html>
