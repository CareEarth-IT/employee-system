# 詳細設計（CE-Group 社員ポータル連携）

本ドキュメントは執筆用アウトラインをコードベースと突合した **詳細設計の正** です。  
Word 版と差分が出た場合は本ファイルを優先し、変更時は両方を更新してください。

---

## 0. スコープと環境

### 0-1 対象

- 社員サイト（employee）
- 接続済み部署ポータル: 特定技能、不動産
- 同梱: finance-hr、WordPress

### 0-2 対象外（本番）

- Sateraito SSO 入場（コードのみ、`PORTAL_REQUIRE_SATERAITO_ENTRY=false`）
- 未接続ポータル: dispatch / food / telecom / beauty（`internal_url` 未設定）

### 0-3 環境表

→ [environments.md](environments.md)

---

## 1. 連携設計（employee プロキシ）

### 1-1 コンポーネント

| 層 | ファイル |
|----|----------|
| 設定 | `config/department_portals.php` |
| タブ・権限 | `app/Support/DashboardTab.php`, `DepartmentPortal.php` |
| ルート | `routes/web.php` |
| プロキシ | `DepartmentPortalProxyController` |
| 上流 HTTP | `Services/DepartmentPortalProxy/DepartmentPortalUpstreamClient` |
| 書き換え | `Services/DepartmentPortalProxy/DepartmentPortalResponseRewriter` |
| 不動産 | `Services/DepartmentPortalProxy/RealEstatePortalProxyHandler` |
| Identity Token | `Services/DepartmentPortalIdentityToken` |

### 1-2 リクエスト処理

1. 認証済みセッション必須（`auth` ミドルウェア）
2. `DepartmentPortal::canAccess($user, $tabKey)`
3. upstream へ HTTP 転送（Identity Token / ヘッダ）
4. レスポンスの URL・Cookie path 書き換え

シーケンス図: [architecture.md](architecture.md)

### 1-3 転送ヘッダ仕様

`DepartmentPortal` 定数参照。upstream 側での検証はポータル実装依存。

### 1-4 エラー

| HTTP | 意味 |
|------|------|
| 403 | employee 側権限なし |
| 502 | upstream 接続 / Token / SSO 失敗 |
| 503 | `internal_url` 未設定、upstream 403（IAM/Secret） |

---

## 2. 社員サイト（employee）

### 2-1 モジュール

| 機能 | 主要クラス |
|------|------------|
| ダッシュボード | `DashboardController`, `DashboardTab` |
| 所属 | `AffiliationController`, `AffiliationHistory` |
| 社員一覧 | `EmployeeController` |
| 備品購入 | `EquipmentPurchaseController` |
| プロキシ | 第 1 章 |
| SSO 入場（未本番） | `PortalEntryGate`, `config/portal_entry.php` |
| 内部 API | `Internal\EmployeeDirectoryController` |

### 2-2 データ

- DB: `ceemployee`
- 所属履歴がタブ表示・権限の根拠

### 2-3 テスト

→ [tests/README.md](../tests/README.md)

---

## 3. 特定技能ポータル

### 3-1 リポジトリ構成

- PHP + MySQL、画面 `screens/`, API `api/`
- 設定: `includes/config.php`（`APP_BASE_PATH`）
- DB: `specific_skills`

### 3-2 認証（現状）

- Cloud Run IAM: employee サービスアカウントのみ invoker
- employee プロキシ経由の Identity Token
- **アプリ内 `X-Employee-Portal-*` ヘッダ検証: 未実装**

### 3-3 画面

応募者 / 面談 / 内定 / 支援管理 / スタッフ管理（`includes/config.php` の `$navItems`）

### 3-4 運用 Job

- `employee-sync-support-csv`: CSV 同期（add-only + promote）
- スクリプト: `deploy/sync-support-csv-prod.cmd`

---

## 4. 不動産ポータル

### 4-1 リポジトリ

- Laravel（`real-estate`）
- DB: `real_estate`
- 設定: `config/employee-portal.php`

### 4-2 認証

| 方式 | 説明 |
|------|------|
| Proxy Secret | `VerifyEmployeePortalProxySecret` |
| SSO handoff | `RealEstatePortalSsoHandoff` → upstream `EmployeePortalSsoService` |
| Cookie | `real_estate_portal_session`, path `/realestate-portal` |

初回 GET で portal session Cookie が無い場合、employee が handoff を実行。

### 4-3 社員ディレクトリ API

employee `GET /internal/portal/employee-directory`（Proxy Secret 必須）

### 4-4 デプロイ

- `deploy/deploy-realestate-portal.cmd`
- Proxy 設定: `deploy/setup-realestate-proxy.cmd`

---

## 5. 運用

### 5-1 Cloud Run サービス

`employee`, `specified-skills-portal`, `real-estate-portal`

### 5-2 デプロイ

→ [deploy/README.md](../deploy/README.md)

### 5-3 障害対応

→ [runbook.md](runbook.md)

### 5-4 ADR（設計判断メモ）

| 決定 | 理由 |
|------|------|
| Reverse Proxy | 単一ドメイン・Cookie・SSO 統合 |
| 不動産のみ SSO handoff | upstream が独自セッションを要求 |
| 特定技能は IAM のみ | シンプルな PHP アプリ、非公開 Cloud Run |
| employee プロジェクトに portal 配置 | クロスプロジェクト IAM 回避 |

---

## 改訂履歴

| 日付 | 内容 |
|------|------|
| 2026-08-27 | 初版（ソース突合、フェーズ 5） |
