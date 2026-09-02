@php
    use App\Support\NationalityOptions;

    $selected = NationalityOptions::toDisplayName($selected ?? '') ?? ($selected ?? '');
    $inputId = $inputId ?? 'nationality';
    $inputName = $inputName ?? 'nationality';
    $required = $required ?? false;
@endphp

<select id="{{ $inputId }}" name="{{ $inputName }}" @required($required) class="w-full rounded border border-slate-300 px-3 py-2 bg-white">
    <option value="">選択してください</option>
    @foreach (NationalityOptions::names() as $nationalityName)
        <option value="{{ $nationalityName }}" @selected($selected === $nationalityName)>{{ $nationalityName }}</option>
    @endforeach
</select>
