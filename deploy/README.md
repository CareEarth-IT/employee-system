# デプロイスクリプト

GCP プロジェクト `ce-gr-employee-info-2606st` / リージョン `asia-northeast1` 向けです。  
`.cmd` は PowerShell ラッパー、実体は同名 `.ps1` です。

## 初回・基盤

| スクリプト | 用途 |
|------------|------|
| `bootstrap-gcp.cmd` | GCP プロジェクト初期設定 |
| `setup-cloudsql.cmd` | Cloud SQL インスタンス |
| `setup-gcs-bucket.cmd` | GCS バケット |
| `local-setup.cmd` | ローカル開発環境 |
| `setup-employee-local-host.cmd` | hosts に employee.local |

## 社員サイト（employee）

| スクリプト | 用途 |
|------------|------|
| `deploy-employee-cloudbuild.cmd` | 本番 employee Cloud Run デプロイ |
| `deploy-only.cmd` | イメージのみ再デプロイ |
| `docker-deploy.cmd` | ローカル Docker ビルド |
| `map-domain.cmd` | カスタムドメイン mapping |
| `deploy-gmail-mail-prod.cmd` | 本番 Gmail SMTP 設定 |

## 部署ポータル

| スクリプト | 用途 |
|------------|------|
| `deploy-specified-skills-portal.cmd` | 特定技能 Cloud Run + DB |
| `deploy-specified-skills-portal-image-only.ps1` | イメージのみ |
| `grant-specific-skills-app-prod.cmd` | invoker / DB 権限 |
| `deploy-realestate-portal.cmd` | 不動産 Cloud Run + DB |
| `setup-realestate-proxy.cmd` | Proxy Secret + employee env |
| `grant-realestate-invoker.cmd` | IAM invoker（Secret 未使用時） |
| `grant-real-estate-app-prod.cmd` | アプリ DB ユーザー権限 |
| `migrate-real-estate-prod.cmd` | 不動産 DB migrate Job |
| `migrate-specific-skills-prod.cmd` | 特定技能 schema Job |
| `setup-portal-databases-prod.cmd` | ポータル DB 作成 |

## データ同期・運用 Job

| スクリプト | 用途 |
|------------|------|
| `sync-support-csv-prod.cmd` | 支援管理 CSV → specific_skills |
| `import-employees-prod.cmd` | 社員 CSV インポート |
| `import-specific-skills-prod.cmd` | 特定技能データ import |
| `import-real-estate-prod.cmd` | 不動産データ import |
| `sync-hr-detail-prod.cmd` | 人事詳細同期 |
| `sync-affiliation-*-prod.cmd` | 所属関連同期 |
| `backup-cloudsql.cmd` | Cloud SQL → GCS |

## finance-hr

| スクリプト | 用途 |
|------------|------|
| `setup-finance-hr-db-prod.cmd` | finance_hr DB セットアップ |
| `test-finance-hr-db-prod.ps1` | DB 接続テスト |

## メール（ローカル検証）

| スクリプト | 用途 |
|------------|------|
| `mailpit.cmd` | ローカル Mailpit |
| `deploy-gmail-mail-prod.cmd` | 本番 Gmail SMTP 設定 |

## 標準デプロイ手順（ポータル更新）

1. sibling リポジトリで変更をコミット
2. `deploy-specified-skills-portal.cmd` または `deploy-realestate-portal.cmd`
3. 必要なら migrate / sync Job
4. employee の env URL が正しいことを確認
5. ブラウザで `/specified-skills-portal` または `/realestate-portal/home` を確認

## 共通設定

`deploy-common.ps1` の `Get-DeployConfig`:

- Service: `employee`
- Cloud SQL: `ce-gr-employee-info-2606st:asia-northeast1:employee`
- DB: `ceemployee`

## 関連

- [docs/architecture.md](../docs/architecture.md)
- [docs/runbook.md](../docs/runbook.md)
