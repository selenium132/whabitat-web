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
