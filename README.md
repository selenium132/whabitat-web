# WHABITAT Web

早稲田大学のボランティアサークル **WHABITAT**（国際NGO Habitat for Humanity 早稲田支部）の公式サイト兼・会員管理システム。
広報用のランディングページと、LINE ログインによる会員専用機能を 1 つの PHP アプリで提供しています。

🌐 本番: https://whabitathome.com

## 主な機能

**広報（公開ページ）**
- 活動紹介（海外建築ボランティア GV / 国内ボランティア JV / 国内単発 / 定例MTG）
- ブログ、お問い合わせ、OGP・構造化データ（JSON-LD）対応のSEO
- トップの実績数字（在籍人数・活動年数・男女比）を会員DBから**動的に算出**

**会員機能（LINEログイン）**
- プロフィール登録（郵便番号→住所オートフィル付き）
- イベントの出欠登録・カスタムアンケート回答
- 目安箱（意見投稿）

**管理機能（幹部のみ）**
- メンバー管理：検索・フィルター・ソート・全項目表示・CSV出力をサイト内で完結
- 名簿を**各幹部自身の Google アカウント**でスプレッドシート出力（本人所有・最小権限共有）
- ブログ / カレンダー / 定例MTG履歴 / お問い合わせ管理

## 技術スタック

| 領域 | 使用技術 |
|---|---|
| バックエンド | PHP（フレームワークなし）、MySQL（PDO） |
| 認証 | LINE OAuth 2.0（会員ログイン）、Google OAuth 2.0（名簿のスプシ出力） |
| 外部連携 | Google Sheets API、LINE Messaging API、reCAPTCHA v2、zipcloud（住所補完） |
| フロント | 素の HTML / CSS / JavaScript（モノトーンのミニマルデザイン） |
| インフラ / CI | Xserver（共用）、GitHub Actions による FTP 自動デプロイ |

## セキュリティへの取り組み

個人情報（会員の氏名・住所・電話・生年月日など）を扱うため、以下を徹底しています。

- **SQLインジェクション対策**：全DBアクセスを PDO プリペアドステートメント化
- **XSS対策**：出力は `htmlspecialchars`、属性は二重引用符
- **CSRF対策**：状態変更POSTにトークン（`hash_equals` で比較）
- **認可**：全管理操作で `requireLogin` ＋ role チェック、更新対象IDでの認可再判定（IDOR対策）
- **シークレット管理**：機密はすべて環境変数。リポジトリには非追跡で、本番は GitHub Secrets 経由で注入
- **名簿の最小権限共有**：公開（リンク共有）を廃止し、各幹部の Google アカウントへ `drive.file` スコープで限定共有
- **数式インジェクション対策**：CSV/スプレッドシート出力時に数式トリガ文字を無害化
- **アップロード検証**：マジックバイト判定＋アップロードディレクトリでの PHP 実行禁止

## ローカル開発

```bash
# 1. 環境変数を用意
cp .env.example .env   # 各値を設定（DB / LINE / Google OAuth など）

# 2. MySQL に users / events / blogs などのテーブルを用意

# 3. PHP で配信
php -S localhost:8000
```

## デプロイ

`main` ブランチへ push すると、GitHub Actions が Xserver へ FTP デプロイします（`.github/workflows/deploy.yml`）。
本番の `.env` と `service-account.json` は GitHub Secrets から生成されます。

## ディレクトリ構成（抜粋）

```
├── index.php                 トップ（広報LP）
├── config.php                共通設定・DB接続・認証・CSRF
├── login.php / callback.php  LINE OAuth ログイン
├── dashboard.php             会員ダッシュボード
├── register_profile.php      プロフィール登録
├── admin/                    管理画面（members / blog / calendar / messages / mtg_history）
├── google_user_sheets.php    各自アカウントでの名簿スプシ出力
├── images/                   画像アセット（tiles / gv / jv / domestic / common）
└── .github/workflows/        CI（FTPデプロイ）
```
