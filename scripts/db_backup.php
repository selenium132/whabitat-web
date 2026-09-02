<?php
/**
 * DBバックアップスクリプト（CLI専用）
 *
 * 全テーブルの CREATE TABLE + INSERT 文を生成し、gzip圧縮して
 * ../db_backups/ に保存する。14世代を超えた古いファイルは自動削除。
 *
 * XServerでの設定: サーバーパネル > Cron設定 で毎日1回
 *   /usr/bin/php /home/＜サーバーID＞/whabitathome.com/public_html/scripts/db_backup.php
 * を登録する（PHPのパスはパネルの案内に従う）。
 *
 * サーバー外への退避（任意・推奨）:
 *   .env に BACKUP_DRIVE_USER_ID=auto（または管理者の users.id）を設定すると、その管理者が
 *   「名簿シート出力」で連携済みの Google アカウントの Drive（drive.file スコープ）に
 *   フォルダ「WHABITAT DB Backups」を作り、同じ gzip を毎回アップロードして 14 世代を保つ。
 *   サーバー障害・誤操作で public_html ごと消えても DB を復元できるようにするのが目的。
 *   Drive 側の失敗はローカル保存を妨げない（STDERR に出して終了コード 2）。
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit; // Webからは実行不可
}

require_once __DIR__ . '/../config.php';

$backup_dir = __DIR__ . '/../db_backups/';
$keep = 14; // 保持世代数

if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0700, true);
}
// 万一公開領域に置かれた場合に備えWebアクセスを遮断
$ht = $backup_dir . '.htaccess';
if (!file_exists($ht)) {
    file_put_contents($ht, "Require all denied\n");
}

$pdo = getDB();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$lines = [];
$lines[] = '-- WHABITAT DB backup ' . date('Y-m-d H:i:s');
$lines[] = 'SET NAMES utf8mb4;';
$lines[] = 'SET FOREIGN_KEY_CHECKS=0;';

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    $quoted = '`' . str_replace('`', '``', $table) . '`';

    $create = $pdo->query("SHOW CREATE TABLE $quoted")->fetch(PDO::FETCH_ASSOC);
    $lines[] = '';
    $lines[] = "DROP TABLE IF EXISTS $quoted;";
    $lines[] = ($create['Create Table'] ?? '') . ';';

    $stmt = $pdo->query("SELECT * FROM $quoted");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols = '`' . implode('`, `', array_map(fn($c) => str_replace('`', '``', $c), array_keys($row))) . '`';
        $vals = implode(', ', array_map(function ($v) use ($pdo) {
            if ($v === null) return 'NULL';
            return $pdo->quote((string)$v);
        }, array_values($row)));
        $lines[] = "INSERT INTO $quoted ($cols) VALUES ($vals);";
    }
}

$lines[] = '';
$lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

$sql = implode("\n", $lines);
$file = $backup_dir . 'backup_' . date('Ymd_His') . '.sql.gz';

if (file_put_contents($file, gzencode($sql, 9)) === false) {
    fwrite(STDERR, "バックアップの書き込みに失敗: $file\n");
    exit(1);
}

// ローテーション（古い順に削除して $keep 世代だけ残す）
$files = glob($backup_dir . 'backup_*.sql.gz');
sort($files);
while (count($files) > $keep) {
    @unlink(array_shift($files));
}

echo "OK: " . basename($file) . " (" . round(filesize($file) / 1024) . "KB, " . count($tables) . " tables)\n";

// =====================================================================
// Google Drive への退避（BACKUP_DRIVE_USER_ID が設定されているときだけ）
// =====================================================================
$drive_setting = trim((string)($env['BACKUP_DRIVE_USER_ID'] ?? ''));
$drive_uid = (int)$drive_setting;
if ($drive_setting !== '') {
    try {
        require_once __DIR__ . '/../google_user_sheets.php';
        // "auto" のときは Google 連携済み（名簿シート出力を一度でも使った）管理者を自動選択する
        if ($drive_uid <= 0 && strtolower($drive_setting) === 'auto') {
            $connected = array_map('intval', array_keys(array_filter(gus_read_tokens(), fn($r) => !empty($r['refresh_token']))));
            if ($connected) {
                $in = implode(',', array_fill(0, count($connected), '?'));
                $q = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND id IN ($in) ORDER BY id LIMIT 1");
                $q->execute($connected);
                $drive_uid = (int)$q->fetchColumn();
            }
            if ($drive_uid <= 0) {
                throw new Exception("Google 連携済みの管理者がいません（管理者が一度「名簿をシートに出力」を実行すると連携されます）");
            }
        }
        $rec = gus_get_record($drive_uid);
        if (!$rec || empty($rec['refresh_token'])) {
            throw new Exception("users.id={$drive_uid} は Google 未連携です（名簿シート出力を一度実行して連携してください）");
        }
        $token = gus_access_token($rec['refresh_token']);
        $folderId = backupDriveFolderId($token, 'WHABITAT DB Backups');
        $fileId = backupDriveUpload($token, $folderId, basename($file), file_get_contents($file));
        $removed = backupDrivePrune($token, $folderId, $keep);
        echo "Drive: uploaded " . basename($file) . " → users.id={$drive_uid} の Google Drive「WHABITAT DB Backups」" . ($removed ? "（古い {$removed} 件を削除）" : '') . "\n";
    } catch (Exception $e) {
        fwrite(STDERR, "Drive 退避に失敗（ローカル保存は完了）: " . $e->getMessage() . "\n");
        exit(2);
    }
}

// フォルダを名前で探し、無ければ作る（drive.file スコープではこのアプリが作ったものだけが見える）
function backupDriveFolderId($token, $name) {
    $q = "name = '" . addslashes($name) . "' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
    list($http, $d) = gus_api($token, 'GET', 'https://www.googleapis.com/drive/v3/files?spaces=drive&fields=files(id)&q=' . rawurlencode($q));
    if ($http === 200 && !empty($d['files'][0]['id'])) return $d['files'][0]['id'];
    list($http, $d, $raw) = gus_api($token, 'POST', 'https://www.googleapis.com/drive/v3/files', [
        'name' => $name, 'mimeType' => 'application/vnd.google-apps.folder',
    ]);
    if ($http !== 200 || empty($d['id'])) throw new Exception('フォルダ作成に失敗: HTTP ' . $http . ' ' . substr((string)$raw, 0, 200));
    return $d['id'];
}

// multipart/related でアップロード（メタデータ + gzip 本体）
function backupDriveUpload($token, $folderId, $name, $bytes) {
    $boundary = 'whb_' . bin2hex(random_bytes(8));
    $meta = json_encode(['name' => $name, 'parents' => [$folderId]]);
    $body = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{$meta}\r\n"
          . "--{$boundary}\r\nContent-Type: application/gzip\r\n\r\n{$bytes}\r\n--{$boundary}--";
    $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: multipart/related; boundary=' . $boundary,
            'Content-Length: ' . strlen($body),
        ],
        CURLOPT_POSTFIELDS => $body,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $d = json_decode((string)$resp, true);
    if ($http !== 200 || empty($d['id'])) throw new Exception('アップロードに失敗: HTTP ' . $http . ' ' . ($err ?: substr((string)$resp, 0, 200)));
    return $d['id'];
}

// フォルダ内の backup_*.sql.gz を新しい順に $keep 件だけ残して削除
function backupDrivePrune($token, $folderId, $keep) {
    $q = "'" . $folderId . "' in parents and trashed = false and name contains 'backup_'";
    list($http, $d) = gus_api($token, 'GET', 'https://www.googleapis.com/drive/v3/files?spaces=drive&pageSize=200&orderBy=createdTime%20desc&fields=files(id,name)&q=' . rawurlencode($q));
    if ($http !== 200 || empty($d['files'])) return 0;
    $removed = 0;
    foreach (array_slice($d['files'], $keep) as $f) {
        list($h) = gus_api($token, 'DELETE', 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($f['id']));
        if ($h === 204) $removed++;
    }
    return $removed;
}
