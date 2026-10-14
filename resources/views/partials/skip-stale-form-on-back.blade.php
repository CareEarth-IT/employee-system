{{-- 保存/削除後にブラウザ戻るで、変更前のフォームがキャッシュ表示されるのを防ぐ --}}
@php
    use App\Support\UserRouteHelper;
    $skipStaleFormFallbackUrl = $skipStaleFormFallbackUrl
        ?? UserRouteHelper::route($user, 'profile.edit', 'users.profile.edit');
@endphp
<script>
    (() => {
        const fallbackUrl = @json($skipStaleFormFallbackUrl);
        const storageKey = 'employee_skip_stale_form';

        @if (session('skip_stale_form'))
            sessionStorage.setItem(storageKey, '1');
        @endif

        window.addEventListener('pageshow', (event) => {
            const nav = performance.getEntriesByType('navigation')[0];
            const fromHistory = event.persisted || nav?.type === 'back_forward';
            if (! fromHistory) {
                return;
            }

            if (sessionStorage.getItem(storageKey) === '1') {
                sessionStorage.removeItem(storageKey);
                window.location.replace(fallbackUrl);
                return;
            }

            // bfcache の古い入力より、サーバー上の最新を優先
            if (event.persisted) {
                window.location.reload();
            }
        });
    })();
</script>
