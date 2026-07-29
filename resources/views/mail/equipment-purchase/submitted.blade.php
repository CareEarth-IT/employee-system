<x-mail::message>
# 備品購入申請を受け付けました

{{ $application->user->displayName() }} 様

備品購入の申請を受け付けました。承認が出るまでお待ちください。

**申請タイプ:** {{ $application->typeLabel() }}  
**購入商品名:** {{ $application->product_name }}  
**価格（税込）:** {{ number_format($application->price_including_tax) }} 円  
**申請日:** {{ $application->application_date->format('Y/m/d') }}

<x-mail::button :url="$detailUrl">
申請内容を確認する
</x-mail::button>

CE-Group 社員専用
</x-mail::message>
