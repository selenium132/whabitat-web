<?php
/**
 * DBスキーマ書き出しスクリプト（CLI専用）
 *
 * 全テーブルの CREATE TABLE 文だけを出力する（データは一切含まない）。
 * db_backup.php と違い、出力先はリポジトリで追跡する schema/schema.sql。
 * 手元での動作確認・将来のマイグレーション把握のため、スキーマ変更時に
 * 都度これを実行して schema/schema.sql をコミットする運用を想定。
 *
 * 実行方法（本番サーバー上、SSHまたはXserverのコマンド実行環境で）:
 *   php scripts/export_schema.php > schema/schema.sql
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit; // Webからは実行不可
}

require_once __DIR__ . '/../config.php';

$pdo = getDB();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$lines = [];
$lines[] = '-- WHABITAT DB schema (structure only, no data) — generated ' . date('Y-m-d H:i:s');
$lines[] = '-- scripts/export_schema.php で生成。スキーマ変更時に再実行してコミットする。';
$lines[] = 'SET NAMES utf8mb4;';
$lines[] = 'SET FOREIGN_KEY_CHECKS=0;';

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($tables);

foreach ($tables as $table) {
    $quoted = '`' . str_replace('`', '``', $table) . '`';
    $create = $pdo->query("SHOW CREATE TABLE $quoted")->fetch(PDO::FETCH_ASSOC);
    $lines[] = '';
    $lines[] = "DROP TABLE IF EXISTS $quoted;";
    $lines[] = ($create['Create Table'] ?? '') . ';';
}

$lines[] = '';
$lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

echo implode("\n", $lines) . "\n";
