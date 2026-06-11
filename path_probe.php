<?php
// サーバーパス確認用の一時ファイル（確認後すぐ削除する）
if (($_GET['t'] ?? '') !== '55a3e28e42224efc6457685c88ca094a') { http_response_code(404); exit; }
header('Content-Type: text/plain');
echo "DIR: " . __DIR__ . "\n";
echo "PHP: " . PHP_VERSION . "\n";
