# ADR 001: 部署ポータルは employee リバースプロキシ経由

## 状態

採用

## 背景

複数部署の社内サイトを社員に提供する。単一ドメイン・既存ログインセッションの再利用が必要。

## 決定

- 各部署ポータルは非公開 Cloud Run にデプロイ
- ブラウザは `employee.careearth.net/{proxy_path}` のみアクセス
- employee が HTTP プロキシ + URL/Cookie 書き換えを行う

## 結果

- メリット: 単一入口、部署タブ権限と統合、Cloud Run IAM で upstream を保護
- デメリット: プロキシ層の複雑さ（不動産 SSO 等）、HTML 書き換えの保守

## 関連

- `DepartmentPortalProxyController`
- [architecture.md](../architecture.md)
