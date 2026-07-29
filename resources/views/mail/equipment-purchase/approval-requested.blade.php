<x-mail::message>
# 備品購入申請の承認をお願いします

{{ $approver?->displayName() ?? 'ご担当者' }} 様

{{ $application->user->displayName() }} さんより備品購入の申請がありました。  
内容をご確認のうえ、承認をお願いします。

**申請タイプ:** {{ $application->typeLabel() }}  
**購入商品名:** {{ $application->product_name }}  
**価格（税込）:** {{ number_format($application->price_including_tax) }} 円  
**申請日:** {{ $application->application_date->format('Y/m/d') }}  
**申請者:** {{ $application->user->displayName() }}

<x-mail::button :url="$approveUrl">
承認画面を開く
</x-mail::button>

承認待ち一覧は [こちら]({{ $pendingUrl }}) からも確認できます。

CE-Group 社員専用
</x-mail::message>
