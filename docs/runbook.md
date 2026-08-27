# 運用 Runbook

障害切り分けと日常運用の手順です。

## 502 / 503（部署ポータル）

### 症状: 「認証に失敗しました」（502）

- **原因候補:** Identity Token 取得失敗（Cloud Run 外で実行、SA 権限不足）
- **確認:** Cloud Run ログ（employee サービス）で `Department portal identity token failed`
- **対処:** employee サービスアカウントに upstream への `run.invoker` があるか確認。不動産は `deploy\grant-realestate-invoker.cmd` または Proxy Secret 構成

### 症状: 「接続先 URL の設定を確認」（502）

- **原因:** upstream URL 誤り、Cloud Run 停止、ネットワーク
- **確認:** `.env` / Cloud Run env の `*_PORTAL_INTERNAL_URL`
- **対処:** `gcloud run services describe specified-skills-portal --region=asia-northeast1`

### 症状: 403 / 503（upstream denied）

- **特定技能:** IAM invoker 未付与 → `deploy\grant-specific-skills-app-prod.cmd`
- **不動産 + Proxy Secret:** `--no-invoker-iam-check` と `EMPLOYEE_PORTAL_PROXY_SECRET` の一致 → `deploy\setup-realestate-proxy.cmd`

### 症状: プロキシ 403（employee 側）

- ユーザーの所属がタブ keywords にマッチしない
- `DashboardTab` / 所属履歴を確認

## デプロイ順序（ポータル更新時）

1. ポータルイメージをデプロイ（`deploy-specified-skills-portal.cmd` / `deploy-realestate-portal.cmd`）
2. 必要なら DB migrate Job
3. employee の `*_PORTAL_INTERNAL_URL` が最新 URL を指しているか確認

## ログ

```powershell
gcloud logging read "resource.type=cloud_run_revision AND resource.labels.service_name=employee" --limit=50 --project=ce-gr-employee-info-2606st
```

## 設定整合性

```powershell
php artisan department-portals:check
```

## バックアップ

```powershell
deploy\backup-cloudsql.cmd
```

GCS: `gs://ce-gr-employee-info-2606st-sql-backups/`

## 関連

- [deploy/README.md](../deploy/README.md)
- [architecture.md](architecture.md)
