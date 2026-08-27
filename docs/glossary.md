# 用語集

第三者がコードと URL を対応づけるための参照表です。

## タブキー ↔ 設定 ↔ URL

| タブキー（DashboardTab） | department_portals キー | プロキシ path | 入口 URL（employee） |
|--------------------------|-------------------------|---------------|----------------------|
| `common` | （なし） | — | `/dashboard?tab=common` |
| `dispatch` | `dispatch` | `/dispatch-portal` | `/dispatch-portal` |
| `specified-skills` | `specified-skills` | `/specified-skills-portal` | `/specified-skills-portal` |
| `real-estate` | `real-estate` | `/realestate-portal` | `/realestate-portal/home` |
| `food` | `food` | `/food-portal` | `/food-portal` |
| `telecom` | `telecom` | `/telecom-portal` | `/telecom-portal` |
| `beauty` | `beauty` | `/beauty-portal` | `/beauty-portal` |
| `company-car` | （なし） | — | 社用車連携のみ |

**注意:** タブキーは `DashboardTab.php` と `department_portals.php` で **同じ文字列** に揃える必要があります。`php artisan department-portals:check` で検証できます。

## path 名称の違い（特定技能）

| 名称 | 値 | 説明 |
|------|-----|------|
| プロキシ path（本番） | `/specified-skills-portal` | employee の URL。ブラウザがアクセスする path |
| APP_BASE_PATH（アプリ内） | `/specific_skills` | specific_skills リポジトリ内リンク生成用（ローカル XAMPP 直アクセス） |
| Cloud Run サービス名 | `specified-skills-portal` | GCP リソース名（ハイフン、portal 付き） |

プロキシ経由では upstream HTML 内 URL が employee 側 path に書き換えられます。

## 認証関連

| 用語 | 説明 |
|------|------|
| Identity Token | Cloud Run 間通信の GCP OIDC トークン。`DepartmentPortalIdentityToken` が取得 |
| Proxy Secret | `EMPLOYEE_PORTAL_PROXY_SECRET`。ヘッダ `X-Employee-Portal-Proxy-Secret` |
| SSO handoff | 不動産専用。employee が upstream の auth API をサーバー側で呼びセッション確立 |
| Invoker IAM | Cloud Run を呼べる GCP 主体。特定技能は employee SA に付与 |

## リポジトリ配置（推奨）

```
htdocs/
  employee/          ← 本リポジトリ
  specific_skills/
  real-estate/
```

deploy スクリプトは上記 sibling 配置を前提としています。
