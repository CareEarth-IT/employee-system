<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>開発依頼内容一覧</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif;
      font-size: 16px;
      background: #f5f5f5;
      color: #222;
      padding: 20px;
    }

    .page {
      max-width: 100%;
      margin: 0 auto;
    }

    .top-nav-wrap {
      width: 100%;
      text-align: center;
      margin-bottom: 16px;
    }

    .top-nav {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .top-nav a {
      text-decoration: none;
      font-size: 13px;
      padding: 8px 16px;
      border-radius: 4px;
      border: 1px solid #bbb;
      background: #fff;
      color: #333;
    }

    .top-nav a.active {
      background: #1a56a0;
      border-color: #1a56a0;
      color: #fff;
    }

    .page-header {
      border-bottom: 3px solid #222;
      padding-bottom: 10px;
      margin-bottom: 14px;
    }

    .page-header h1 {
      font-size: 16px;
      font-weight: bold;
    }

    .table-wrap {
      overflow-x: auto;
      background: #fff;
      border: 1px solid #bbb;
    }

    table {
      width: 100%;
      min-width: 1200px;
      border-collapse: collapse;
      font-size: 13px;
    }

    th, td {
      border: 1px solid #bbb;
      padding: 8px 10px;
      vertical-align: top;
      text-align: left;
    }

    th {
      font-weight: bold;
      white-space: nowrap;
    }

    th.col-base { background: #d9d9d9; }
    th.col-admin { background: #d9ead3; }

    td.col-center { text-align: center; white-space: nowrap; }

    .sort-note {
      display: block;
      font-size: 11px;
      font-weight: normal;
      color: #1a56a0;
      margin-top: 2px;
    }

    .col-title {
      font-size: 12px;
      line-height: 1.45;
      max-width: 180px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      word-break: break-all;
    }

    .btn-detail {
      display: inline-block;
      padding: 4px 12px;
      border: 1px solid #666;
      border-radius: 3px;
      background: #fff;
      color: #222;
      text-decoration: none;
      font-size: 12px;
      white-space: nowrap;
    }

    .btn-detail:hover { background: #f0f0f0; }

    .footer-note {
      margin-top: 12px;
      font-size: 13px;
      color: #444;
    }

    .loading, .empty {
      padding: 32px;
      text-align: center;
      color: #777;
      font-size: 14px;
      background: #fff;
      border: 1px solid #bbb;
    }

    .alert-error {
      background: #ffebee;
      border: 1px solid #e57373;
      color: #c62828;
      padding: 10px 14px;
      border-radius: 3px;
      margin-bottom: 12px;
      font-size: 13px;
      display: none;
    }

    .type-filters {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      margin-bottom: 14px;
    }

    .type-filter-btn {
      min-width: 5rem;
      padding: 8px 18px;
      font-size: 13px;
      font-weight: 600;
      border: 1px solid #bbb;
      border-radius: 4px;
      background: #fff;
      color: #333;
      cursor: pointer;
    }

    .type-filter-btn:hover {
      border-color: #1a56a0;
      color: #1a56a0;
    }

    .type-filter-btn.is-active {
      background: #1a56a0;
      border-color: #1a56a0;
      color: #fff;
    }

    .date-filter {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-left: 4px;
    }

    .date-filter__label {
      font-size: 13px;
      font-weight: 600;
      color: #333;
      white-space: nowrap;
    }

    .date-filter__input {
      padding: 7px 10px;
      font-size: 13px;
      font-weight: 600;
      border: 1px solid #bbb;
      border-radius: 4px;
      background: #fff;
      color: #333;
      min-width: 11rem;
    }

    .date-filter__input:focus {
      outline: none;
      border-color: #1a56a0;
    }
  </style>
</head>
<body>
<div class="page">
  <div class="top-nav-wrap">
    <nav class="top-nav">
      <a href="{{ $createUrl }}">新規依頼</a>
      <a href="{{ $listUrl }}" class="active">開発依頼内容一覧</a>
    </nav>
  </div>

  <div class="page-header">
    <h1>開発依頼内容一覧</h1>
  </div>

  <div class="type-filters" role="toolbar" aria-label="Type・依頼日で絞り込み">
    @foreach ($typeFilters as $filter)
      <button type="button" class="type-filter-btn" data-type="{{ $filter }}">{{ $filter }}</button>
    @endforeach
    <label class="date-filter" for="dateFilter">
      <span class="date-filter__label">依頼日</span>
      <input type="date" id="dateFilter" class="date-filter__input" lang="ja">
    </label>
  </div>

  <div class="alert-error" id="errorMsg"></div>
  <div class="loading" id="loading">読み込み中...</div>
  <div class="table-wrap" id="tableWrap" style="display:none;">
    <table>
      <thead>
        <tr>
          <th class="col-base">詳細表示</th>
          <th class="col-base">ID</th>
          <th class="col-base">
            依頼日
            <span class="sort-note">依頼日時順</span>
          </th>
          <th class="col-base">部署／課</th>
          <th class="col-base">依頼者</th>
          <th class="col-base">Type</th>
          <th class="col-base">タイトル</th>
          <th class="col-admin">進捗</th>
          <th class="col-admin">備考</th>
          <th class="col-admin">予想工数(h)</th>
          <th class="col-admin">実工数(h)</th>
          <th class="col-admin">開発終了目標</th>
          <th class="col-admin">更新日</th>
          <th class="col-admin">開発担当</th>
          <th class="col-admin">管理者</th>
        </tr>
      </thead>
      <tbody id="listBody"></tbody>
    </table>
  </div>
  <div class="empty" id="emptyMsg" style="display:none;">該当する依頼がありません。</div>

  <p class="footer-note">一覧は依頼日から過去３か月分を表示</p>
</div>

<script>
  var detailAccess = @json($detailAccess);
  var allItems = @json($listItems);
  var detailUrlTemplate = @json($detailUrlTemplate);
  var activeTypeFilter = '';
  var activeDateFilter = '';

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function detailUrl(id) {
    return detailUrlTemplate.replace('__ID__', encodeURIComponent(String(id)));
  }

  function renderList(items) {
    var loading = document.getElementById('loading');
    var tableWrap = document.getElementById('tableWrap');
    var emptyMsg = document.getElementById('emptyMsg');
    var body = document.getElementById('listBody');

    loading.style.display = 'none';

    if (!items || !items.length) {
      tableWrap.style.display = 'none';
      emptyMsg.style.display = 'block';
      body.innerHTML = '';
      return;
    }

    emptyMsg.style.display = 'none';
    tableWrap.style.display = 'block';

    body.innerHTML = items.map(function (item) {
      var detailCell = detailAccess.canView ?
        '<a class="btn-detail" href="' + detailUrl(item.id) + '">詳細</a>' :
        '—';

      return (
        '<tr>' +
        '<td class="col-center">' + detailCell + '</td>' +
        '<td class="col-center">' + escapeHtml(item.id) + '</td>' +
        '<td class="col-center">' + escapeHtml(item.requestDate || '—') + '</td>' +
        '<td>' + escapeHtml(item.department || '—') + '</td>' +
        '<td>' + escapeHtml(item.requesterName || '—') + '</td>' +
        '<td class="col-center">' + escapeHtml(item.contentTypeLabel || '—') + '</td>' +
        '<td><div class="col-title" title="' + escapeHtml(item.title) + '">' +
          escapeHtml(item.titleShort || '—') + '</div></td>' +
        '<td>' + escapeHtml(item.progress || '—') + '</td>' +
        '<td>' + escapeHtml(item.remarks || '') + '</td>' +
        '<td class="col-center">' + escapeHtml(item.estimatedHours || '') + '</td>' +
        '<td class="col-center">' + escapeHtml(item.actualHours || '') + '</td>' +
        '<td class="col-center">' + escapeHtml(item.devTarget || '') + '</td>' +
        '<td class="col-center">' + escapeHtml(item.updatedAt || '') + '</td>' +
        '<td>' + escapeHtml(item.devAssignee || '未') + '</td>' +
        '<td>' + escapeHtml(item.manager || '—') + '</td>' +
        '</tr>'
      );
    }).join('');
  }

  function isoDateToRequestDate(isoDate) {
    if (!isoDate) {
      return '';
    }
    var parts = String(isoDate).split('-');
    if (parts.length !== 3) {
      return '';
    }
    return parts[0].slice(-2) + '/' + parts[1] + '/' + parts[2];
  }

  function syncTypeFilterButtons() {
    document.querySelectorAll('.type-filter-btn').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-type') === activeTypeFilter);
    });
  }

  function applyFilters() {
    var filtered = allItems;

    if (activeTypeFilter === '完了') {
      filtered = filtered.filter(function (item) {
        return item.progress === '完了';
      });
    } else {
      filtered = filtered.filter(function (item) {
        return item.progress !== '完了';
      });

      if (activeTypeFilter) {
        filtered = filtered.filter(function (item) {
          return item.contentTypeLabel === activeTypeFilter;
        });
      }
    }

    if (activeDateFilter) {
      var targetDate = isoDateToRequestDate(activeDateFilter);
      filtered = filtered.filter(function (item) {
        return item.requestDate === targetDate;
      });
    }

    renderList(filtered);
  }

  function applyTypeFilter(type) {
    activeTypeFilter = activeTypeFilter === type ? '' : type;
    syncTypeFilterButtons();
    applyFilters();
  }

  document.querySelectorAll('.type-filter-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      applyTypeFilter(btn.getAttribute('data-type'));
    });
  });

  document.getElementById('dateFilter').addEventListener('change', function (e) {
    activeDateFilter = e.target.value || '';
    applyFilters();
  });

  applyFilters();
</script>
</body>
</html>
