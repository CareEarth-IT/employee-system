<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>移動中…</title>
</head>
<body>
    <p style="font-family: sans-serif; padding: 1.5rem;">
        移動しています…
        <a href="{{ $url }}">自動で移動しない場合はこちら</a>
    </p>
    <script>
        location.replace(@json($url));
    </script>
</body>
</html>
