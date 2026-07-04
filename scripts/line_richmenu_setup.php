<?php
/**
 * LINE公式アカウントのリッチメニュー作成スクリプト（CLI専用、実行のたびに作り直す）
 *
 * 「イベント確認」「部室入退出」「利用状況確認」の3ボタンをpostbackイベントとして送る
 * リッチメニューを作成し、全員のデフォルトメニューとして設定する。
 * 「部室入退出」は1ボタンで、今の在室状態を見てサーバー側で入室/退室を自動判定する。
 * line_webhook.phpのhandleRoomPostback()が
 * action=event / action=toggle / action=status を受け取る前提。
 * 実行時に既存の登録済みリッチメニューを全て削除してから作り直すため、
 * 再実行してもLINE Developers側にゴミが残らない。
 *
 * 実行方法（本番サーバー上、SSHまたはXserverのコマンド実行環境で）:
 *   php scripts/line_richmenu_setup.php images/richmenu/room_richmenu.png
 *
 * 画像は2500x843px推奨（3等分でボタンを配置するため）。PNG/JPG対応。
 * 既存の.env（LINE_BOT_ACCESS_TOKEN）をそのまま使うため.env変更は不要。
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit; // Webからは実行不可
}

require_once __DIR__ . '/../config.php';

$imagePath = $argv[1] ?? null;
if (!$imagePath || !file_exists($imagePath)) {
    fwrite(STDERR, "使い方: php scripts/line_richmenu_setup.php <リッチメニュー画像のパス(PNG/JPG, 2500x843推奨)>\n");
    exit(1);
}

if (LINE_BOT_ACCESS_TOKEN === '') {
    fwrite(STDERR, ".env の LINE_BOT_ACCESS_TOKEN が設定されていません。\n");
    exit(1);
}

function lineApiRequest($method, $url, $data = null, $contentType = 'application/json') {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = ['Authorization: Bearer ' . LINE_BOT_ACCESS_TOKEN];
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $headers[] = 'Content-Type: ' . $contentType;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    if ($response === false) {
        fwrite(STDERR, "cURL error: " . curl_error($ch) . "\n");
        exit(1);
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$httpCode, $response];
}

$richMenuDef = [
    'size' => ['width' => 2500, 'height' => 843],
    'selected' => true,
    'name' => 'WHABITAT メニュー',
    'chatBarText' => 'メニュー',
    'areas' => [
        [
            'bounds' => ['x' => 0, 'y' => 0, 'width' => 833, 'height' => 843],
            'action' => ['type' => 'postback', 'data' => 'action=event', 'displayText' => 'イベント確認'],
        ],
        [
            'bounds' => ['x' => 833, 'y' => 0, 'width' => 834, 'height' => 843],
            'action' => ['type' => 'postback', 'data' => 'action=toggle', 'displayText' => '部室入退出'],
        ],
        [
            'bounds' => ['x' => 1667, 'y' => 0, 'width' => 833, 'height' => 843],
            'action' => ['type' => 'postback', 'data' => 'action=status', 'displayText' => '部室利用状況確認'],
        ],
    ],
];

echo "1/4 既存のリッチメニューを整理中...\n";
[$code, $body] = lineApiRequest('GET', 'https://api.line.me/v2/bot/richmenu/list');
$existing = json_decode($body, true)['richmenus'] ?? [];
foreach ($existing as $rm) {
    lineApiRequest('DELETE', 'https://api.line.me/v2/bot/richmenu/' . $rm['richMenuId']);
    echo "  削除: {$rm['richMenuId']} ({$rm['name']})\n";
}

echo "2/4 リッチメニューを作成中...\n";
[$code, $body] = lineApiRequest('POST', 'https://api.line.me/v2/bot/richmenu', json_encode($richMenuDef));
$result = json_decode($body, true);
if ($code !== 200 || empty($result['richMenuId'])) {
    fwrite(STDERR, "リッチメニュー作成に失敗しました (HTTP $code): $body\n");
    exit(1);
}
$richMenuId = $result['richMenuId'];
echo "  richMenuId = $richMenuId\n";

echo "3/4 画像をアップロード中...\n";
$ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
$mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : 'image/png';
$imageData = file_get_contents($imagePath);
[$code, $body] = lineApiRequest('POST', "https://api-data.line.me/v2/bot/richmenu/$richMenuId/content", $imageData, $mime);
if ($code !== 200) {
    fwrite(STDERR, "画像アップロードに失敗しました (HTTP $code): $body\n");
    exit(1);
}

echo "4/4 全員のデフォルトリッチメニューとして設定中...\n";
[$code, $body] = lineApiRequest('POST', "https://api.line.me/v2/bot/user/all/richmenu/$richMenuId");
if ($code !== 200) {
    fwrite(STDERR, "デフォルト設定に失敗しました (HTTP $code): $body\n");
    exit(1);
}

echo "完了しました。richMenuId = $richMenuId\n";
