@if ($errors->any())
    <div @isset($class) class="{{ $class }}" @else class="rounded border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm" @endisset>
        入力内容に誤りがあります。各項目のエラーをご確認ください。
    </div>
@endif
