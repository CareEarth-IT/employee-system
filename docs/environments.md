# 環境一覧（ローカル vs 本番）

コードに存在する機能と、**本番で有効な設定**を分けて記載します。

## 基本

| 項目 | ローカル（XAMPP） | 本番（Cloud Run） |
|------|-------------------|-------------------|
| URL | `http://employee.local` 等 | https://employee.careearth.net |
| `APP_ENV` | `local` | `production` |
| DB | SQLite / ローカル MySQL | Cloud SQL `employee` / `ceemployee` |
| セッション | file / database | database |
| メール | `log` または Mailpit | Gmail SMTP（`deploy-gmail-mail-prod`） |

## 入口・認証

| 項目 | ローカル | 本番 |
|------|----------|------|
| トップ `/` | 準備中ページへリダイレクト | 同左 |
| ログイン | `/login` | `/login` |
| Sateraito SSO | コードあり、`PORTAL_REQUIRE_SATERAITO_ENTRY=false` なら未使用 | **未使用**（通常ログイン） |
| 強制パスワード変更 | 有効 | 有効 |

## 部署ポータル

| 項目 | ローカル | 本番 |
|------|----------|------|
| 特定技能 | `SPECIFIED_SKILLS_PORTAL_INTERNAL_URL` 設定時のみ | Cloud Run `specified-skills-portal` |
| 不動産 | `REAL_ESTATE_PORTAL_INTERNAL_URL` | Cloud Run `real-estate-portal` |
| Identity Token | 通常オフ（`APP_ENV=local`） | オン（各 `*_USE_IDENTITY_TOKEN`） |
| 不動産 Proxy Secret | 任意 | `EMPLOYEE_PORTAL_PROXY_SECRET` + `--no-invoker-iam-check` 構成可 |
| 不動産 SSO handoff | コードあり | 本番ポリシーに依存（Identity Token 利用時も初回 GET で handoff あり）|

## 同梱アプリ

| アプリ | ローカル | 本番 |
|--------|----------|------|
| finance-hr | `/apps/finance-hr` | 同 path（SSO secret 要設定） |
| WordPress | 任意 | `/wordpress`（GCS メディア） |

## メール

| 方式 | 用途 | 本番 |
|------|------|------|
| `smtp` + Gmail | 標準 | **使用中** |
| `log` / Mailpit | ローカル確認 | ローカルのみ |

## GCP リソース（本番）

| リソース | 名前 |
|----------|------|
| プロジェクト | `ce-gr-employee-info-2606st` |
| リージョン | `asia-northeast1` |
| Cloud Run | `employee`, `specified-skills-portal`, `real-estate-portal` |
| Cloud SQL | インスタンス `employee` |
| DB | `ceemployee`, `specific_skills`, `real_estate`, `finance_hr` |
| GCS | SQL バックアップ等 `ce-gr-employee-info-2606st-sql-backups` |

## 環境変数の参照元

- テンプレート: `.env.example`
- 本番反映: `deploy/deploy-common.ps1` の `Get-CloudRunEnvVars` および各 deploy スクリプト

## ローカルでポータルを試す

1.  sibling リポジトリを `htdocs/specific_skills`, `htdocs/real-estate` に配置
2. `.env` に `SPECIFIED_SKILLS_PORTAL_INTERNAL_URL` / `REAL_ESTATE_PORTAL_INTERNAL_URL` をローカル URL で設定
3. `USE_IDENTITY_TOKEN=false`（Proxy Secret またはローカル直アクセス）
4. employee にログイン後、ダッシュボードのリンクまたは `/specified-skills-portal` へ
