@extends('layouts.app')

@section('title', '欠勤・遅刻・早退・有休の連絡 - CE-Group 社員専用')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">欠勤・遅刻・早退・有休の連絡</h1>
        <a href="{{ route('dashboard') }}" class="text-sm text-blue-600 hover:underline">Top Page へ</a>
    </div>

    @include('partials.validation-errors-banner', ['class' => 'mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm'])

    <form
        method="POST"
        action="{{ route('attendance-notifications.store') }}"
        class="bg-white border border-slate-300 rounded-lg p-8 space-y-6"
    >
        @csrf

        <div class="grid sm:grid-cols-2 gap-4">
            <x-form.readonly-field label="連絡者名" :value="$user->displayName()" />
            <x-form.readonly-field label="社員ID" :value="$user->employee_id ?? '—'" />
        </div>

        <div>
            <label for="notification_type" class="block text-sm mb-1">連絡種別 <span class="text-red-600">*</span></label>
            <select
                id="notification_type"
                name="notification_type"
                class="w-full rounded border border-slate-300 px-3 py-2"
                data-conditional-trigger
            >
                <option value="">選択してください</option>
                @foreach (App\Models\AttendanceNotification::TYPE_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(old('notification_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @include('partials.field-error', ['field' => 'notification_type'])
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label for="target_date" class="block text-sm mb-1">対象日 <span class="text-red-600">*</span></label>
                <input
                    id="target_date"
                    type="date"
                    name="target_date"
                    value="{{ old('target_date', now()->toDateString()) }}"
                    class="w-full rounded border border-slate-300 px-3 py-2"
                >
                @include('partials.field-error', ['field' => 'target_date'])
            </div>
            <div id="end-date-wrap" class="hidden">
                <label for="end_date" class="block text-sm mb-1">終了日 <span class="text-xs text-slate-500">(複数日の場合)</span></label>
                <input
                    id="end_date"
                    type="date"
                    name="end_date"
                    value="{{ old('end_date') }}"
                    class="w-full rounded border border-slate-300 px-3 py-2"
                >
                @include('partials.field-error', ['field' => 'end_date'])
            </div>
        </div>

        <div id="scheduled-time-wrap" class="hidden">
            <label for="scheduled_time" class="block text-sm mb-1">
                <span id="scheduled-time-label">予定時刻</span> <span class="text-red-600">*</span>
            </label>
            <input
                id="scheduled_time"
                type="time"
                name="scheduled_time"
                value="{{ old('scheduled_time') }}"
                class="w-full max-w-xs rounded border border-slate-300 px-3 py-2"
            >
            @include('partials.field-error', ['field' => 'scheduled_time'])
        </div>

        <div>
            <label for="reason" class="block text-sm mb-1">理由 <span class="text-red-600">*</span></label>
            <textarea
                id="reason"
                name="reason"
                rows="5"
                class="w-full rounded border border-slate-300 px-3 py-2"
                placeholder="連絡理由を入力してください"
            >{{ old('reason') }}</textarea>
            @include('partials.field-error', ['field' => 'reason'])
        </div>

        <div class="flex justify-end pt-4 border-t border-slate-200">
            <button type="submit" class="rounded bg-blue-600 text-white px-10 py-2.5 font-medium hover:bg-blue-700">
                送信
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const typeSelect = document.getElementById('notification_type');
        const endDateWrap = document.getElementById('end-date-wrap');
        const scheduledTimeWrap = document.getElementById('scheduled-time-wrap');
        const scheduledTimeLabel = document.getElementById('scheduled-time-label');

        const updateFields = () => {
            const type = typeSelect.value;
            const showEndDate = type === 'absence' || type === 'paid_leave';
            const showScheduledTime = type === 'late' || type === 'early_leave';

            endDateWrap.classList.toggle('hidden', !showEndDate);
            scheduledTimeWrap.classList.toggle('hidden', !showScheduledTime);

            if (type === 'late') {
                scheduledTimeLabel.textContent = '到着予定時刻';
            } else if (type === 'early_leave') {
                scheduledTimeLabel.textContent = '退勤予定時刻';
            }
        };

        typeSelect.addEventListener('change', updateFields);
        updateFields();
    });
</script>
@endpush
@endsection
