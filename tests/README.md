# テスト

PHPUnit テストは **仕様の参照** として読めます。ファイル名・メソッド名が期待動作を表します。

## 実行

```powershell
php artisan test
php artisan test --filter=DepartmentPortal
php artisan test tests/Unit/RealEstatePortalSsoHandoffTest.php
```

## ポータル・連携

| テスト | 確認内容 |
|--------|----------|
| `Unit/DepartmentPortalTest` | タブ権限、entry URL、Identity Token 設定 |
| `Unit/SpecifiedSkillsPortalTest` | 特定技能アクセス、経理課 |
| `Unit/RealEstatePortalTest` | 不動産リンク・設定 |
| `Unit/RealEstatePortalProxyPathTest` | プロキシ path 正規化 |
| `Unit/RealEstatePortalSsoHandoffTest` | SSO handoff フロー |
| `Feature/EmployeeDirectoryApiTest` | 内部社員 API |
| `Feature/PortalEntryGateTest` | Sateraito 入場（`@group sateraito`） |

## 社員・所属

| テスト | 確認内容 |
|--------|----------|
| `Unit/UserAffiliationPermissionsTest` | 所属に基づく権限 |
| `Feature/AffiliationCurrentOrgLockTest` | 所属編集 |

## 設定チェック

```powershell
php artisan department-portals:check
```

`DashboardTab` と `department_portals` の整合性。

## 関連

- [docs/architecture.md](../docs/architecture.md)
