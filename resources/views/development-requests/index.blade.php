@extends(!empty($embed) ? 'layouts.embed' : 'layouts.app')

@section('title', '開発依頼内容一覧 - CE-Group 社員専用')

@section('mainWidthClass', 'max-w-[96rem]')

@push('styles')
<style>
  .dev-list-page {
    font-family: 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif;
    font-size: 13px;
    color: #333;
    padding: {{ !empty($embed) ? '8px 4px 20px' : '0' }};
    background: {{ !empty($embed) ? '#f5f5f5' : 'transparent' }};
  }

  .dev-list-page .top-nav-wrap {
    width: 100%;
    text-align: center;
    margin-bottom: 16px;
  }

  .dev-list-page .top-nav {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .dev-list-page .top-nav a {
    text-decoration: none;
    font-size: 13px;
    padding: 8px 16px;
    border-radius: 4px;
    border: 1px solid #bbb;
    background: #fff;
    color: #333;
  }

  .dev-list-page .top-nav a.active {
    background: #1a56a0;
    border-color: #1a56a0;
    color: #fff;
  }

  .dev-list-page .list-panel {
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 20px 20px 16px;
  }

  .dev-list-page .list-header {
    border-bottom: 2px solid #1a56a0;
    padding-bottom: 10px;
    margin-bottom: 16px;
  }

  .dev-list-page .list-header h1 {
    font-size: 18px;
    font-weight: bold;
    color: #1a56a0;
  }

  .dev-list-page .filter-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
  }

  .dev-list-page .filter-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 4.5rem;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid #c5cdd8;
    background: #fff;
    color: #445;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    line-height: 1.3;
    transition: background .15s, border-color .15s, color .15s;
  }

  .dev-list-page .filter-chip:hover {
    border-color: #1a56a0;
    color: #1a56a0;
  }

  .dev-list-page .filter-chip.active {
    background: #1a56a0;
    border-color: #1a56a0;
    color: #fff;
  }

  .dev-list-page .date-filter {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
    font-size: 12px;
    font-weight: 600;
    color: #445;
  }

  .dev-list-page .date-filter input[type="date"] {
    padding: 5px 8px;
    border: 1px solid #bbb;
    border-radius: 3px;
    font-size: 13px;
    font-family: inherit;
    color: #333;
    background: #fff;
  }

  .dev-list-page .date-filter input[type="date"]:focus {
    outline: none;
    border-color: #1a56a0;
    box-shadow: 0 0 0 2px rgba(26,86,160,0.15);
  }

  .dev-list-page .clear-link {
    font-size: 12px;
    color: #1a56a0;
    text-decoration: none;
  }

  .dev-list-page .clear-link:hover {
    text-decoration: underline;
  }

  .dev-list-page .table-wrap {
    overflow-x: auto;
    border: 1px solid #d7dde5;
    border-radius: 4px;
  }

  .dev-list-page table {
    width: 100%;
    min-width: 1180px;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 12px;
  }

  .dev-list-page thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f0f4f8;
    color: #334;
    font-weight: 700;
    text-align: left;
    padding: 10px 10px;
    border-bottom: 1px solid #c5cdd8;
    white-space: nowrap;
    vertical-align: bottom;
  }

  .dev-list-page thead th.th-meta {
    background: #e8f1fb;
  }

  .dev-list-page thead th .sub {
    display: block;
    margin-top: 2px;
    font-size: 10px;
    font-weight: 500;
    color: #1a56a0;
  }

  .dev-list-page tbody td {
    padding: 9px 10px;
    border-bottom: 1px solid #e8ecf0;
    vertical-align: middle;
    color: #333;
    background: #fff;
  }

  .dev-list-page tbody tr:hover td {
    background: #f7fafc;
  }

  .dev-list-page tbody tr:last-child td {
    border-bottom: none;
  }

  .dev-list-page .cell-center {
    text-align: center;
    white-space: nowrap;
  }

  .dev-list-page .btn-detail {
    display: inline-block;
    padding: 4px 12px;
    border: 1px solid #1a56a0;
    border-radius: 3px;
    background: #fff;
    color: #1a56a0;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
  }

  .dev-list-page .btn-detail:hover {
    background: #1a56a0;
    color: #fff;
  }

  .dev-list-page .title-cell {
    max-width: 200px;
  }

  .dev-list-page .title-cell div {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-all;
    line-height: 1.4;
  }

  .dev-list-page .progress-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    background: #eef4fb;
    color: #1a56a0;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
  }

  .dev-list-page .empty-state {
    text-align: center;
    padding: 48px 16px;
    color: #889;
    font-size: 13px;
  }

  .dev-list-page .footer-note {
    margin-top: 12px;
    font-size: 11px;
    color: #777;
    line-height: 1.7;
  }

  @media (max-width: 768px) {
    .dev-list-page .date-filter {
      margin-left: 0;
      width: 100%;
    }
    .dev-list-page .list-panel {
      padding: 16px 12px 12px;
    }
  }
</style>
@endpush

@section('content')
@php
    $embedQuery = !empty($embed) ? ['embed' => 1] : [];
@endphp
<div class="dev-list-page">
  @if (empty($embed))
  <div class="top-nav-wrap">
    <nav class="top-nav">
      <a href="{{ route('development-requests.create', $embedQuery) }}">新規依頼</a>
      <a href="{{ route('development-requests.index', $embedQuery) }}" class="active">開発依頼内容一覧</a>
    </nav>
  </div>
  @endif

  <div class="list-panel">
    <div class="list-header">
      <h1>開発依頼内容一覧</h1>
    </div>

    <form method="GET" action="{{ route('development-requests.index') }}" class="filter-row">
      @if (!empty($embed))
        <input type="hidden" name="embed" value="1">
      @endif

      @foreach ($typeFilters as $filter)
        <a
          href="{{ route('development-requests.index', array_filter(['type' => $activeType === $filter ? null : $filter, 'date' => $activeDate ?: null, 'embed' => !empty($embed) ? 1 : null])) }}"
          class="filter-chip{{ $activeType === $filter ? ' active' : '' }}"
        >{{ $filter }}</a>
      @endforeach

      <label class="date-filter">
        <span>依頼日</span>
        <input
          type="date"
          name="date"
          value="{{ $activeDate }}"
          onchange="this.form.submit()"
        >
        @if ($activeType !== '')
          <input type="hidden" name="type" value="{{ $activeType }}">
        @endif
        @if ($activeType !== '' || $activeDate !== '')
          <a href="{{ route('development-requests.index', $embedQuery) }}" class="clear-link">クリア</a>
        @endif
      </label>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>詳細</th>
            <th>ID</th>
            <th>
              依頼日
              <span class="sub">依頼日時順</span>
            </th>
            <th>部署／課</th>
            <th>依頼者</th>
            <th>Type</th>
            <th>タイトル</th>
            <th class="th-meta">進捗</th>
            <th class="th-meta">備考</th>
            <th class="th-meta">予想工数(h)</th>
            <th class="th-meta">実工数(h)</th>
            <th class="th-meta">開発終了目標</th>
            <th class="th-meta">更新日</th>
            <th class="th-meta">開発担当</th>
            <th class="th-meta">管理者</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($requests as $item)
            <tr>
              <td class="cell-center">
                @if ($canViewDetail)
                  <a href="{{ route('development-requests.show', $item) }}{{ !empty($embed) ? '?embed=1' : '' }}" class="btn-detail">詳細</a>
                @else
                  —
                @endif
              </td>
              <td class="cell-center">{{ $item->request_number }}</td>
              <td class="cell-center">{{ $item->request_date?->format('y/m/d') ?? '—' }}</td>
              <td>{{ $item->requester_department ?: '—' }}</td>
              <td>{{ $item->requester_name ?: '—' }}</td>
              <td class="cell-center">{{ $item->contentTypeLabel() }}</td>
              <td class="title-cell">
                <div title="{{ $item->title }}">{{ $item->titleShort() }}</div>
              </td>
              <td>
                @if ($item->progress)
                  <span class="progress-badge">{{ $item->progress }}</span>
                @else
                  —
                @endif
              </td>
              <td>{{ $item->remarks ?: '—' }}</td>
              <td class="cell-center">{{ $item->estimated_hours !== null && $item->estimated_hours !== '' ? $item->estimated_hours : '—' }}</td>
              <td class="cell-center">{{ $item->actual_hours !== null && $item->actual_hours !== '' ? $item->actual_hours : '—' }}</td>
              <td class="cell-center">{{ $item->development_target_date?->format('y/m/d') ?? '—' }}</td>
              <td class="cell-center">{{ $item->updated_at?->format('y/m/d') ?? '—' }}</td>
              <td>{{ $item->development_assignee ?: '未' }}</td>
              <td>{{ $item->manager ?: '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="15" class="empty-state">該当する依頼がありません。</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <p class="footer-note">一覧は依頼日から過去３か月分を表示</p>
  </div>
</div>
@endsection
