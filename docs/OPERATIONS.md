# WHABITAT Web 運用・引き継ぎガイド

代替わりのときに次の運営へ渡すための実務ドキュメント。技術的な開発ルールは `CLAUDE.md`、概要は `README.md` を参照。

## 1. 管理に必要なアカウント一覧（引き継ぎ時に権限を移すもの）

| 何を | どこで | 用途 | 引き継ぎ方法 |
|---|---|---|---|
| GitHub リポジトリ `whabitat-web` | github.com | ソース管理。`main` に push すると自動で本番反映 | 新代表を Collaborator(Admin) に追加 → 旧代表を外す |
| GitHub Secrets | リポジトリ Settings → Secrets | 本番の `.env` / サービスアカウント / FTP 情報 | リポジトリ権限に付随（値は見えない。更新は `base64 -i .env \| gh secret set ENV_FILE_BASE64`） |
| Xserver サーバーパネル | xserver.ne.jp | サーバー・ドメイン・DB・Cron | 契約者情報の変更手続き（ドメイン更新費の支払い元も） |
| LINE Developers | developers.line.biz | LINEログイン（チャネル）と LINE公式アカウント（Messaging API） | プロバイダーに新管理者を追加 |
| Google Cloud プロジェクト `whabitat-web` | console.cloud.google.com | Sheets/Drive API、OAuth 同意画面、サービスアカウント | IAM でオーナーを追加 |
| Google Apps Script（出欠シート作成用） | script.google.com | `.env` の `APPS_SCRIPT_URL` の実体 | デプロイ主のアカウントで共有 or 新アカウントで再デプロイして URL 差し替え |
| reCAPTCHA | google.com/recaptcha/admin | 問い合わせフォームのスパム対策 | サイトオーナーに追加 |

サイト内の**管理者権限（role=admin）**は `admin/members.php` で既存の管理者が付与・剥奪する。代替わり時は「新幹部を admin に → 旧幹部を member に」の順で行うこと（自分自身の admin は外せない安全装置あり）。

## 2. Xserver の Cron 設定（サーバーパネル → Cron設定）

PHP のパスはパネルの案内に従う（例: `/usr/bin/php8.2`）。`＜サーバーID＞` は自分のもの。

| 頻度 | コマンド | 目的 |
|---|---|---|
| 毎日 1回（例 4:00） | `/usr/bin/php /home/＜サーバーID＞/whabitathome.com/public_html/scripts/db_backup.php` | DB バックアップ（14世代）。`.env` に `BACKUP_DRIVE_USER_ID` を設定すると Google Drive にも退避 |
| 毎時（例 毎時5分） | `/usr/bin/php /home/＜サーバーID＞/whabitathome.com/public_html/scripts/remind_deadlines.php` | 締切24時間前と当日朝に**主催者1人へ1通**のダイジェスト（未回答◯名＋一覧リンク）。会員への一斉送信はしない |

- LINE 公式アカウントの無料枠は **月200通**（push/multicast は宛先1人＝1通。会員からのメッセージへの返信は無料）。
  このため**会員全員への一斉 push は実装しない方針**。通知は「管理者への新着通知」「主催者へのダイジェスト」「管理者が個別に押すリマインドボタン」だけに限定している。
  ダイジェストは当月150通を超えると自動停止する。LINE Official Account Manager でメッセージ数を月1回は確認する。
  催促は LINE グループに `form_view.php?id=◯` のリンクを貼る（無料）。

## 3. バックアップと復元

- 保存先: サーバーの `db_backups/backup_YYYYMMDD_HHMMSS.sql.gz`（Web からはアクセス不可）と、設定していれば Drive の「WHABITAT DB Backups」フォルダ。
- 復元: Xserver の phpMyAdmin で対象DBを選び「インポート」→ `.sql.gz` をそのまま指定（`DROP TABLE IF EXISTS` を含む完全復元）。復元前に現状の手動バックアップを取ること。
- 画像（`uploads/`）は DB に含まれない。FTP でまとめてダウンロードして保管する（年1回、卒業前に）。

## 4. 年度の切り替え（毎年3〜4月）

1. `admin/members.php` で新幹部を admin に、旧幹部を member に。
2. 卒業した代のメンバーは名簿の「OB/OG」フィルターで確認できる。個人情報の保有期間ポリシー（`privacy.php` 第6条）に従い、不要になった人は削除する。退会申請は `admin/messages.php` の「退会申請」タブに届く。
3. トップページの人数は「在籍中の最新3代」で自動計算されるので手作業は不要。新入生の「代」は `config.php` の `AVAILABLE_GRADES` が年度から自動計算する。
4. 合言葉（`.env` の `CIRCLE_SECRET`）を新歓に合わせて変えるなら、`.env` 更新 → GitHub Secret 更新 → 何か push（または Actions を再実行）で本番反映。

## 5. よくあるトラブル

| 症状 | 対処 |
|---|---|
| デプロイが `Timeout (control socket)` で失敗 | Xserver の FTPS が不安定なため。ワークフローは3回まで自動リトライする。それでも落ちたら Actions 画面で「Re-run failed jobs」 |
| デプロイ後も古い表示のまま | PHP の opcache 反映に最大1分。ブラウザのハードリロードも試す |
| 「Google連携が未設定です」 | `.env` の `GOOGLE_OAUTH_*` を確認。同意画面が「本番環境」になっているか（テストモードだと7日でトークン失効） |
| 名簿シート出力で「未確認のアプリ」警告 | 仕様（Google の確認申請をしていない）。「詳細」→「（安全ではないページ）に移動」で続行できる |
| LINE通知が届かない | 月200通の上限、または相手がブロック中。`error_log` に `lineBotApiPost ... failed` が出る |
| 会員がログインできない/承認待ちのまま | `admin/members.php` で承認。合言葉を5回間違えると10分ロックされる |
| 出欠シート作成に失敗 | `.env` の `APPS_SCRIPT_URL` が最新のデプロイURLか、Apps Script のアクセス権が「全員」か |

## 6. 個人情報の扱い（最低限）

- 名簿の CSV/スプレッドシート出力は管理者本人の端末・Drive に残る。不要になったら削除する。共有リンクを「リンクを知っている全員」にしない。
- 会員から開示・削除の申し出があったら、`admin/members.php` で対応し、対応した旨を返信する。監査ログ（`admin/audit_log.php`）に記録が残る。
- `privacy.php` を変更したら制定日/改定日を更新する。

## 7. ローカルで動作確認したいとき

`README.md` の「ローカル開発」を参照。MySQL が無くても、`privacy.php` などDBを使わないページは `php -S localhost:8000` で表示できる。全ページの構文チェックは `for f in $(git ls-files '*.php'); do php -l $f; done`（CI でも push のたびに自動実行される）。
