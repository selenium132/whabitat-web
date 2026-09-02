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
- イベントの出欠登録・カスタムアンケート回答（参加者/回答者リストの公開・非公開はイベント単位で設定）
- 目安箱（意見投稿）、退会・個人情報削除の申請
- LINE 通知: 締切24時間前・当日朝に**主催者へ1通**のダイジェスト（未回答数＋一覧リンク。無料枠200通/月を守るため会員への一斉送信はしない）

**管理機能（幹部のみ）**
- メンバー管理：検索・フィルター・ソート・全項目表示・CSV出力をサイト内で完結
- 名簿を**各幹部自身の Google アカウント**でスプレッドシート出力（本人所有・最小権限共有）
- ブログ / カレンダー / 定例MTG履歴 / GV・JVチーム / お問い合わせ・目安箱・退会申請の管理、監査ログ
- 問い合わせ・目安箱・退会申請の新着は管理者へ LINE push 通知

**法務・信頼**
- プライバシーポリシー（`privacy.php`）を公開し、会員登録・問い合わせフォームから同意導線を設置

## 技術スタック

| 領域 | 使用技術 |
|---|---|
| バックエンド | PHP（フレームワークなし）、MySQL（PDO） |
| 認証 | LINE OAuth 2.0（会員ログイン）、Google OAuth 2.0（名簿のスプシ出力） |
| 外部連携 | Google Sheets API、LINE Messaging API、reCAPTCHA v2、zipcloud（住所補完） |
| フロント | 素の HTML / CSS / JavaScript（モノトーンのミニマルデザイン） |
| インフラ / CI | Xserver（共用）、GitHub Actions（全PHPの `php -l` → FTPS デプロイ、3回まで自動リトライ） |

## セキュリティへの取り組み

個人情報を扱うため、以下を徹底しています。

- **SQLインジェクション対策**：全DBアクセスを PDO プリペアドステートメント化
- **XSS対策**：出力は `htmlspecialchars`、属性は二重引用符
- **CSRF対策**：状態変更POSTにトークン（`hash_equals` で比較）
- **認可**：全管理操作で `requireLogin` ＋ role チェック、更新対象IDでの認可再判定（IDOR対策）
- **シークレット管理**：機密はすべて環境変数。リポジトリには非追跡で、本番は GitHub Secrets 経由で注入
- **名簿の最小権限共有**：公開（リンク共有）を廃止し、各幹部の Google アカウントへ `drive.file` スコープで限定共有
- **数式インジェクション対策**：CSV/スプレッドシート出力時に数式トリガ文字を無害化
- **アップロード検証**：マジックバイト判定＋サイズ上限＋アップロードディレクトリでの PHP 実行禁止
- **セキュリティヘッダ**：HSTS / X-Frame-Options / nosniff / Referrer-Policy / Permissions-Policy / CSP(frame-ancestors) を `config.php` から送出（Xserver では `.htaccess` の `Header` が効かないため）
- **状態変更は POST + CSRF のみ**：シート出力・同期も GET では発火しない

## ローカル開発

```bash
# 1. ローカルDBを一発セットアップ（テーブル構造 + ダミーデータ投入）
#    事前に MySQL を起動しておく（例: brew install mysql && mysql.server start）
./scripts/local_setup.sh

# 2. 環境変数を用意（DB_* はスクリプトの出力に合わせる。LINE/Google等はログイン機能を
#    使わないならダミー値でOK）
cp .env.example .env

# 3. PHP で配信
php -S localhost:8000
```

DBスキーマは本番運用（Xserver）のため、変更のたびに本番サーバー上で
`php scripts/export_schema.php > schema/schema.sql` を実行してコミットする
（詳細は [schema/README.md](schema/README.md)）。`schema/seed.sql` はダミーの
会員・イベント・ブログデータで、実データは一切含まない。

## デプロイ

`main` ブランチへ push すると、GitHub Actions が **全 PHP の構文チェック（`php -l`）** を通した上で Xserver へ FTPS デプロイします（`.github/workflows/deploy.yml`、FTP タイムアウト時は3回まで自動リトライ）。
本番の `.env` と `service-account.json` は GitHub Secrets から生成されます。`.env` にキーを増やしたら Secret も更新すること。

## 定期実行（Xserver の Cron）

| スクリプト | 頻度 | 内容 |
|---|---|---|
| `scripts/db_backup.php` | 毎日 | DB を `db_backups/` に14世代保存。`BACKUP_DRIVE_USER_ID` を設定すると管理者の Google Drive にも退避 |
| `scripts/remind_deadlines.php` | 毎時 | 締切24時間前／当日朝に主催者へ1通のダイジェスト（LINE、当月150通超で自動停止） |

運用・引き継ぎの実務は [docs/OPERATIONS.md](docs/OPERATIONS.md) を参照。

## ディレクトリ構成（抜粋）

```
├── index.php                 トップ（広報LP）
├── config.php                共通設定・DB接続・認証・CSRF
├── login.php / callback.php  LINE OAuth ログイン
├── dashboard.php             会員ダッシュボード
├── register_profile.php      プロフィール登録
├── privacy.php               プライバシーポリシー
├── admin/                    管理画面（members / blog / calendar / messages / mtg_history / teams / audit_log）
├── partials/                 共通ヘッダー・フッター（公開用 header/footer、会員用 member_header）
├── google_user_sheets.php    各自アカウントでの名簿スプシ出力
├── scripts/                  CLI スクリプト（DBバックアップ・リマインド・スキーマ出力）
├── docs/OPERATIONS.md        運用・引き継ぎガイド
├── images/                   画像アセット（tiles / gv / jv / domestic / common / icons）
└── .github/workflows/        CI（php -l → FTPSデプロイ）
```
