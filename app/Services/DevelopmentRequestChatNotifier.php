<?php

namespace App\Services;

use App\Models\DevelopmentRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DevelopmentRequestChatNotifier
{
    public function notifySubmitted(DevelopmentRequest $request): void
    {
        $webhookUrl = trim((string) config('development_requests.chat_webhook_url', ''));
        if ($webhookUrl === '' || str_contains($webhookUrl, 'XXXXXX')) {
            return;
        }

        $detailUrl = route('development-requests.show', $request);
        $contentTypeLabel = $request->contentTypeLabel();
        $manager = $request->manager ?: DevelopmentRequest::resolveManager($request->content_type);

        $text = implode("\n", [
            '【開発依頼】新しい依頼が届きました',
            '',
            '■ 依頼者名: '.$request->requester_name,
            '■ 依頼者部署: '.($request->requester_department !== null && $request->requester_department !== ''
                ? $request->requester_department
                : '（未入力）'),
            '■ 依頼者メール: '.$request->requester_email,
            '■ 依頼日: '.$request->request_date->format('Y-m-d'),
            '■ 依頼内容について: '.$contentTypeLabel.'（管理者: '.$manager.'）',
            '■ 依頼内容タイトル: '.$request->title,
            '■ 目的 (改善内容):',
            (string) $request->purpose,
            '■ 依頼内容詳しく:',
            (string) $request->detail,
            '',
            '▶ 詳細: '.$detailUrl,
        ]);

        try {
            $response = Http::timeout(15)
                ->asJson()
                ->post($webhookUrl, ['text' => $text]);

            if (! $response->successful()) {
                Log::warning('開発依頼の Chat 通知に失敗しました。', [
                    'request_number' => $request->request_number,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('開発依頼の Chat 通知で例外が発生しました。', [
                'request_number' => $request->request_number,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
