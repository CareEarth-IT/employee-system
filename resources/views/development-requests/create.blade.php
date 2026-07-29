@extends(!empty($embed) ? 'layouts.embed' : 'layouts.app')

@section('title', '開発依頼フォーム - CE-Group 社員専用')

@push('styles')
<style>
  .dev-req-page {
    font-family: 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif;
    font-size: 14px;
    color: #333;
    padding: {{ !empty($embed) ? '8px 4px 20px' : '0' }};
    background: {{ !empty($embed) ? '#f5f5f5' : 'transparent' }};
  }

  .dev-req-page .form-wrapper {
    max-width: 680px;
    margin: 0 auto;
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 24px 28px 32px;
  }

  .dev-req-page .form-header {
    border-bottom: 2px solid #1a56a0;
    padding-bottom: 10px;
    margin-bottom: 18px;
  }

  .dev-req-page .form-header h1 {
    font-size: 18px;
    font-weight: bold;
    color: #1a56a0;
  }

  .dev-req-page .form-notice {
    background: #fff8e1;
    border-left: 4px solid #f59e0b;
    padding: 8px 12px;
    font-size: 12px;
    color: #555;
    margin-bottom: 20px;
    line-height: 1.6;
  }

  .dev-req-page .form-row {
    display: flex;
    align-items: flex-start;
    margin-bottom: 14px;
    gap: 8px;
  }

  .dev-req-page .form-row > label,
  .dev-req-page .form-row > .form-label {
    min-width: 160px;
    font-size: 13px;
    padding-top: 7px;
    color: #333;
    flex-shrink: 0;
  }

  .dev-req-page .required-mark {
    color: #c00;
    margin-left: 2px;
  }

  .dev-req-page .form-control {
    flex: 1;
    padding: 6px 8px;
    border: 1px solid #bbb;
    border-radius: 3px;
    font-size: 13px;
    font-family: inherit;
    color: #333;
    background: #fff;
    width: 100%;
  }

  .dev-req-page .form-control:focus {
    outline: none;
    border-color: #1a56a0;
    box-shadow: 0 0 0 2px rgba(26,86,160,0.15);
  }

  .dev-req-page .form-control.readonly {
    background: #f5f5f5;
    color: #555;
    cursor: default;
  }

  .dev-req-page .form-control.readonly:focus {
    border-color: #bbb;
    box-shadow: none;
  }

  .dev-req-page .auto-fill-note {
    font-size: 11px;
    color: #888;
    margin-top: 4px;
  }

  .dev-req-page textarea.form-control {
    resize: vertical;
    min-height: 80px;
  }

  .dev-req-page .radio-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding-top: 6px;
  }

  .dev-req-page .radio-group label {
    min-width: unset;
    padding-top: 0;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-size: 13px;
    color: #333;
  }

  .dev-req-page .radio-group input[type="radio"] {
    accent-color: #1a56a0;
    width: 14px;
    height: 14px;
    cursor: pointer;
    flex-shrink: 0;
  }

  .dev-req-page .divider {
    border: none;
    border-top: 1px solid #e0e0e0;
    margin: 16px 0;
  }

  .dev-req-page .submit-row {
    display: flex;
    justify-content: flex-end;
    margin-top: 20px;
  }

  .dev-req-page .btn-submit {
    background: #1a56a0;
    color: #fff;
    border: none;
    padding: 9px 36px;
    font-size: 14px;
    font-family: inherit;
    border-radius: 3px;
    cursor: pointer;
    letter-spacing: 1px;
  }

  .dev-req-page .btn-submit:hover { background: #154a8a; }
  .dev-req-page .btn-submit:active { background: #0f3a6e; }
  .dev-req-page .btn-submit:disabled { background: #aaa; cursor: not-allowed; }

  .dev-req-page .footer-note {
    font-size: 11px;
    color: #777;
    margin-top: 20px;
    border-top: 1px solid #eee;
    padding-top: 10px;
    line-height: 1.7;
  }

  .dev-req-page .alert-box {
    padding: 10px 14px;
    border-radius: 3px;
    margin-bottom: 16px;
    font-size: 13px;
  }

  .dev-req-page .alert-success {
    background: #e8f5e9;
    border: 1px solid #81c784;
    color: #2e7d32;
  }

  .dev-req-page .alert-error {
    background: #ffebee;
    border: 1px solid #e57373;
    color: #c62828;
  }

  .dev-req-page .top-nav-wrap {
    width: 100%;
    text-align: center;
    margin-bottom: 16px;
  }

  .dev-req-page .top-nav {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .dev-req-page .top-nav a {
    text-decoration: none;
    font-size: 13px;
    padding: 8px 16px;
    border-radius: 4px;
    border: 1px solid #bbb;
    background: #fff;
    color: #333;
  }

  .dev-req-page .top-nav a.active {
    background: #1a56a0;
    border-color: #1a56a0;
    color: #fff;
  }

  @media (max-width: 640px) {
    .dev-req-page .form-row {
      flex-direction: column;
      gap: 4px;
    }
    .dev-req-page .form-row > label,
    .dev-req-page .form-row > .form-label {
      min-width: 0;
      padding-top: 0;
    }
  }
</style>
@endpush

@section('content')
@php
    $embedQuery = !empty($embed) ? ['embed' => 1] : [];
@endphp
<div class="dev-req-page">
  @if (empty($embed))
  <div class="top-nav-wrap">
    <nav class="top-nav">
      <a href="{{ route('development-requests.create', $embedQuery) }}" class="active">新規依頼</a>
      <a href="{{ route('development-requests.index', $embedQuery) }}">開発依頼内容一覧</a>
    </nav>
  </div>
  @endif

  <div class="form-wrapper">
    <div class="form-header">
      <h1>開発依頼フォーム</h1>
    </div>

    <div class="form-notice">
      開発依頼はスケジュールを組み、業務に影響するため、急な複数対応が出来ません。<br>
      依頼内容によっては、1か月ほどかかる場合があります。
    </div>

    @if (session('success'))
      <div class="alert-box alert-success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
      <div class="alert-box alert-error">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
      <div class="alert-box alert-error">
        @foreach ($errors->all() as $error)
          <div>{{ $error }}</div>
        @endforeach
      </div>
    @endif

    @if ($requesterNumber === '')
      <div class="alert-box alert-error">
        社員番号（依頼者番号）が登録されていません。情報システム部に社員IDの登録を依頼してください。
      </div>
    @endif

    <form method="POST" action="{{ route('development-requests.store') }}" id="mainForm" data-submitting-label="送信中...">
      @csrf
      @if (!empty($embed))
        <input type="hidden" name="embed" value="1">
      @endif

      <div class="form-row">
        <label>依頼者名<span class="required-mark">*</span></label>
        <div style="flex:1;">
          <input type="text" class="form-control readonly" value="{{ $requesterName }}" readonly>
          <div class="auto-fill-note">ログイン情報から自動取得</div>
        </div>
      </div>

      <div class="form-row">
        <label>依頼者部署</label>
        <div style="flex:1;">
          <input type="text" class="form-control readonly" value="{{ $requesterDepartment !== '' ? $requesterDepartment : '' }}" readonly>
          <div class="auto-fill-note">現在の所属から自動取得</div>
        </div>
      </div>

      <div class="form-row">
        <label>依頼者番号<span class="required-mark">*</span></label>
        <div style="flex:1;">
          <input type="text" class="form-control readonly" value="{{ $requesterNumber !== '' ? $requesterNumber : '' }}" readonly>
          <div class="auto-fill-note">プロフィールの社員IDから自動取得</div>
        </div>
      </div>

      <hr class="divider">

      <div class="form-row">
        <label for="request_date">依頼日<span class="required-mark">*</span></label>
        <input
          id="request_date"
          type="date"
          name="request_date"
          class="form-control"
          value="{{ old('request_date', now()->toDateString()) }}"
          required
        >
      </div>

      <div class="form-row">
        <span class="form-label">依頼内容について<span class="required-mark">*</span></span>
        <div style="flex:1;">
          <div class="radio-group">
            @foreach (\App\Models\DevelopmentRequest::CONTENT_TYPE_FORM_LABELS as $value => $label)
              <label>
                <input
                  type="radio"
                  name="content_type"
                  value="{{ $value }}"
                  @checked(old('content_type') === $value)
                  required
                >
                {{ $label }}
              </label>
            @endforeach
          </div>
        </div>
      </div>

      <hr class="divider">

      <div class="form-row">
        <label for="title">依頼内容タイトル<span class="required-mark">*</span></label>
        <input
          id="title"
          type="text"
          name="title"
          class="form-control"
          maxlength="30"
          value="{{ old('title') }}"
          placeholder="例：〇〇のスケジュール変更"
          required
        >
      </div>

      <div class="form-row">
        <label for="purpose">目的 (改善内容)<span class="required-mark">*</span></label>
        <textarea
          id="purpose"
          name="purpose"
          class="form-control"
          rows="3"
          placeholder="目的や改善内容を入力してください"
          required
        >{{ old('purpose') }}</textarea>
      </div>

      <div class="form-row">
        <label for="detail">依頼内容詳しく<span class="required-mark">*</span></label>
        <textarea
          id="detail"
          name="detail"
          class="form-control"
          rows="4"
          placeholder="詳細な内容を入力してください"
          required
        >{{ old('detail') }}</textarea>
      </div>

      <div class="submit-row">
        <button type="submit" class="btn-submit" id="submitBtn" @disabled($requesterNumber === '')>
          送信
        </button>
      </div>
    </form>

    <div class="footer-note">
      ※ 新規開発内容は内容をお伺いしたうえで、管理者は変更することもある
    </div>
  </div>
</div>
@endsection
