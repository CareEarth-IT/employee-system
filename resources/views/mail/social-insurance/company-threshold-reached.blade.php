<x-mail::message>
# 社会保険あり人数が閾値に達しました

所属会社 **{{ $company }}** の社会保険あり人数が **{{ $count }} 人** となり、閾値（{{ $threshold }}人）に達しました。

<x-mail::button :url="$statsUrl">
社会保険人数を確認する
</x-mail::button>

CE-Group 社員専用
</x-mail::message>
