# CE-Group 社員専用サイト（employee）

社員情報・ダッシュボード・部署別社内サイトへの入口を提供する Laravel アプリケーションです。  
本番: https://employee.careearth.net

## 関連リポジトリ

| リポジトリ | 役割 | 本番 path |
|------------|------|-----------|
| **employee**（本リポジトリ） | 社員サイト本体・リバースプロキシ | `/`, `/login`, `/dashboard` |
| [specific_skills](../specific_skills) | 特定技能ステータス管理 | `/specified-skills-portal`（employee 経由） |
| [real-estate](../real-estate) | 不動産社内サイト | `/realestate-portal/home`（employee 経由） |

横断ドキュメントは [`docs/`](docs/) を参照してください。

## ローカル開発（XAMPP）

1. `.env.example` を `.env` にコピーし `php artisan key:generate`
2. SQLite または MySQL を設定（`.env.example` 参照）
3. `php artisan migrate --seed`（必要に応じて）
4. `http://employee.local/login` でログイン（`deploy/setup-employee-local-host.cmd` で hosts 設定可）

```powershell
php artisan serve
# または XAMPP の DocumentRoot を public/ に向ける
```

## 主要機能

- 社員プロフィール・所属履歴・人事詳細 CSV
- ダッシュボード（部署タブ別お知らせ・リンク）
- 備品購入申請・開発依頼・組織図
- 部署別社内サイト（[プロキシ](docs/architecture.md#部署別社内サイトプロキシ)）
- 同梱: WordPress（`/wordpress`）、経理・人事問い合わせ（`/apps/finance-hr`）

## ドキュメント

| ファイル | 内容 |
|----------|------|
| [docs/architecture.md](docs/architecture.md) | 全体構成・プロキシ・認証 |
| [docs/environments.md](docs/environments.md) | ローカル vs 本番 |
| [docs/glossary.md](docs/glossary.md) | 用語・path の対応 |
| [docs/detailed-design.md](docs/detailed-design.md) | **詳細設計書**（本書が正） |
| [docs/runbook.md](docs/runbook.md) | 障害切り分け・運用 |
| [deploy/README.md](deploy/README.md) | デプロイスクリプト一覧 |
| [tests/README.md](tests/README.md) | テストの読み方 |

## 設定の整合性チェック

```powershell
php artisan department-portals:check
```

`DashboardTab` と `config/department_portals.php` のキー不一致を検出します。

## テスト

```powershell
php artisan test
# プロキシ・ポータル関連のみ
php artisan test --filter=DepartmentPortal
```

## 本番（GCP）

- プロジェクト: `ce-gr-employee-info-2606st`
- リージョン: `asia-northeast1`
- Cloud Run サービス: `employee`
- Cloud SQL インスタンス: `employee`（DB: `ceemployee` 他）

デプロイ: `deploy\deploy-employee-cloudbuild.cmd`（詳細は [deploy/README.md](deploy/README.md)）
