# CLAUDE.md — WHABITAT Web 開発ルール

このファイルは、このリポジトリで作業するときに**毎回守るルール**をまとめたもの。
変更前に必ず目を通すこと。

## ⚠️ 最重要：このリポジトリは GitHub で **公開（public）** されている

- **機密情報を絶対にコミットしない。** コミット前に必ず `git status` と差分を確認する。
- 機密はすべて `.env`（gitignore済）で管理。**コード内に値を直書きしない**（`define()` 経由の定数参照のみ）。
- 追跡禁止（すべて `.gitignore` 済み）:
  - `.env` / `.env.*`（公開してよいのは `.env.example` のみ）
  - `service-account.json` / `*.bak`
  - `private/`（Google OAuth のリフレッシュトークン保存先）
  - `admin/members_sheet_id.txt`
  - `SECURITY_REVIEW.md`（脆弱性詳細を含む内部記録）
- 新しい機密ファイルを足すときは **先に `.gitignore` に追加してから** 作成する。

## デプロイ

- `main` への push で GitHub Actions が Xserver へ **FTP 自動デプロイ**（本番＝ https://whabitathome.com ）。push＝即本番反映。
- FTP の「Sync files」が timeout したら **`gh run rerun --failed`**（よく落ちる）。
- 本番 `.env` は GitHub Secret `ENV_FILE_BASE64`、`service-account.json` は `GOOGLE_SERVICE_ACCOUNT_JSON_BASE64` から生成。**`.env` のキーを増やしたら GitHub Secret も更新**する。
- デプロイ後、PHP opcache の反映に最大1分ほどかかる（古い挙動が残ったら少し待つ）。
- 変更PHPは本番に出す前に必ず `php -l` で構文確認。
- 大きめの変更は作業ブランチ→main マージ。小さな修正は直接 main でも可だが push=即デプロイを意識。

## セキュリティ規約（必ず守る）

- DBアクセスは必ず PDO + プリペアドステートメント（文字列連結禁止）。
- 状態変更を伴う POST は `validateCsrfToken()` を通す。
- ユーザー入力・DB由来データの出力は `htmlspecialchars()`。HTML属性は二重引用符で囲む。
- ページ/操作は `requireLogin()` ＋ role/admin チェックで保護。**更新対象IDで認可を再判定**して IDOR を防ぐ。
- リダイレクトは自サイト内（相対パス or 同一ホスト）のみ許可（オープンリダイレクト対策。`login.php` 参照）。
- Google Sheets 書き込みは `valueInputOption=RAW`、CSV出力は先頭 `= + - @` を無害化（数式インジェクション対策）。
- アップロードは `getimagesize()` でマジックバイト検証＋保存先ディレクトリで PHP 実行禁止。
- エラー詳細は画面に出さず `error_log`（`config.php` で `display_errors=0`）。

## アーキテクチャ要点

- PHP + MySQL（フレームワークなし）。会員ログインは LINE OAuth（`callback.php`、state は常時検証）。
- 共通処理は `config.php`：`getDB()` / CSRF / `requireLogin()` / `requireGoogleDriveConnection()` / `isInAppBrowser()`。
- 会員名簿の管理は `admin/members.php`（検索・フィルター・ソート・全項目表示・CSV出力）でサイト内完結。
- 名簿の Google スプレッドシート出力は **「各自アカウント方式」**：
  `requireGoogleDriveConnection()` → `google_oauth_callback.php`（リフレッシュトークンを `private/` に保存）→ `google_user_sheets.php` が本人の OAuth トークンで **本人の Drive に**シートを作成/更新（本人が所有者＝編集可）。
  Google Cloud（プロジェクト whabitat-web）で **Google Sheets API + Drive API を有効化**、同意画面に `drive.file` スコープ、テストモード＋テストユーザー運用が前提。
  ※サービスアカウントは Drive 容量0でシートを作成/所有/他人共有が一切できない（実APIで確認済み）。anyone 共有は禁止。
- DBスキーマはリポジトリに無い（本番DB管理。`scripts/db_backup.php` が `SHOW CREATE TABLE` でダンプ）。

## プロジェクト固有の決まり

- 画像は `images/{tiles,gv,jv,domestic,common}/`。`logo.png` と `ogp.jpg` のみルート維持（favicon・OGP絶対URL/SNSキャッシュのため）。
- デザインはミニマル・モノトーン。`member.css` を `style.css` の**後に**読み込んで色だけ上書きする（原色・グラデーションは持ち込まない）。
- 「代（grade）」は `'18th'` 形式で、本人が登録時に選択。卒業予定年（admission_year）は廃止し、「今の学年」は代から計算する。
- トップの人数は「在籍中の最新3代」をデータ駆動で集計（卒業生は自動除外、年度跨ぎでも急減しない）。
- プロフィールはメールアドレス必須（`config.php` のプロフィール完成チェックにも含める）。

## 検証

- 表示確認は Playwright(channel:chrome) か macOS の headless Chrome でスクショ。
- ローカルに MySQL が無いと PHP 全体は描画不可（`config.php` が DB 必須）。UI は実CSSを読む静的ハーネス＋スクショで確認する。
