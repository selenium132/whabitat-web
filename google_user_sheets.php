<?php
// =====================================================================
// 各管理者が「自分のGoogleアカウント」で名簿シートを生成/更新するヘルパー。
// サービスアカウントは Drive 容量0でシートを作成・所有できないため、
// 本人の OAuth トークン（オフライン/リフレッシュトークン）で本人の Drive に作る。
// 本人が所有者になるので編集自由・共有不要・アクセス拒否が起きない。
// =====================================================================
require_once __DIR__ . '/config.php';

// ---- トークン保存（private/ 配下。.htaccessでWebアクセス拒否＋gitignore） ----
function gus_tokens_path() { return __DIR__ . '/private/google_tokens.json'; }

function gus_ensure_private_dir() {
    $dir = __DIR__ . '/private';
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) { @file_put_contents($ht, "Require all denied\nDeny from all\n"); }
}

function gus_read_tokens() {
    $p = gus_tokens_path();
    if (!file_exists($p)) return [];
    $j = json_decode((string)file_get_contents($p), true);
    return is_array($j) ? $j : [];
}

function gus_write_tokens($data) {
    gus_ensure_private_dir();
    file_put_contents(gus_tokens_path(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    @chmod(gus_tokens_path(), 0600);
}

function gus_get_record($user_id) {
    $t = gus_read_tokens();
    return $t[(string)$user_id] ?? null;
}

function gus_set_record($user_id, $record) {
    $t = gus_read_tokens();
    $t[(string)$user_id] = $record;
    gus_write_tokens($t);
}

// ---- OAuth: refresh_token → access_token ----
function gus_access_token($refresh_token) {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id'     => GOOGLE_OAUTH_CLIENT_ID,
        'client_secret' => GOOGLE_OAUTH_CLIENT_SECRET,
        'refresh_token' => $refresh_token,
        'grant_type'    => 'refresh_token',
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $d = json_decode($resp, true);
    if ($http !== 200 || empty($d['access_token'])) {
        throw new Exception('TOKEN_REFRESH_FAILED: HTTP ' . $http . ' ' . substr((string)$resp, 0, 200));
    }
    return $d['access_token'];
}

// ---- ユーザートークンでの汎用 API 呼び出し ----
function gus_api($accessToken, $method, $url, $body = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, json_decode($resp, true), $resp];
}

// 先頭シート名を取得（存在しなければ false）
function gus_first_sheet_title($accessToken, $id) {
    list($http, $d) = gus_api($accessToken, 'GET',
        'https://sheets.googleapis.com/v4/spreadsheets/' . urlencode($id) . '?fields=sheets.properties.title');
    if ($http === 200 && !empty($d['sheets'][0]['properties']['title'])) {
        return $d['sheets'][0]['properties']['title'];
    }
    return false;
}

// 新規スプレッドシートを本人のDriveに作成 → [id, 先頭シート名]
function gus_create_spreadsheet($accessToken, $title) {
    list($http, $d, $raw) = gus_api($accessToken, 'POST',
        'https://sheets.googleapis.com/v4/spreadsheets', ['properties' => ['title' => $title]]);
    if ($http !== 200 || empty($d['spreadsheetId'])) {
        throw new Exception('CREATE_FAILED: HTTP ' . $http . ' ' . substr((string)$raw, 0, 200));
    }
    $sheetTitle = $d['sheets'][0]['properties']['title'] ?? 'Sheet1';
    return [$d['spreadsheetId'], $sheetTitle];
}

// 名簿の行データ生成（sheet_sync の名簿仕様に合わせる）
function gus_build_members_rows(PDO $pdo) {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY grade ASC, name COLLATE utf8mb4_unicode_ci ASC");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rows = [[
        'ID', '名前', 'ふりがな', '学籍番号', '代', '今の学年', '学部', '学科', '性別',
        '郵便番号', '住所', '電話番号', '生年月日', 'LINE名', 'メールアドレス', '他サークル', 'アレルギー等', '備考(その他)', 'ステータス', '権限'
    ]];
    foreach ($members as $m) {
        $status = $m['is_approved'] ? '承認済' : '未承認';
        $role = ($m['role'] ?? '') === 'admin' ? '管理者' : '一般';
        $g = $m['gender'] ?? '';
        $gender_label = $g === 'male' ? '男性' : ($g === 'female' ? '女性' : ($g === 'no_answer' ? '回答しない' : $g));
        // 今の学年は「代」から算出（卒業予定年は廃止。内部計算のみで非保存・非表示）
        $uni = '-';
        $gen = (int)preg_replace('/\D/', '', $m['grade'] ?? '');
        if ($gen > 0) {
            $gy = $gen + 2010; // 代 → 卒業予定年(内部計算のみ)
            $cy = (int)date('Y'); $cm = (int)date('n');
            $cay = ($cm >= 4) ? $cy : $cy - 1;
            $uy = 4 - ($gy - $cay - 1);
            $uni = $uy < 1 ? '入学前' : ($uy > 4 ? 'OB/OG' : $uy . '年生');
        }
        $rows[] = [
            $m['id'], $m['name'], $m['name_kana'] ?? '', $m['student_id'], $m['grade'],
            $uni, $m['faculty'] ?? '', $m['department'] ?? '', $gender_label,
            $m['zipcode'] ?? '', $m['address'] ?? '', $m['phone'] ?? '', $m['birthdate'] ?? '', $m['line_name'],
            $m['email'] ?? '', $m['other_circles'] ?? '', $m['allergies'] ?? '', $m['notes'] ?? '', $status, $role
        ];
    }
    return $rows;
}

// メイン: 本人のDriveに名簿シートを作成/更新し、URLを返す
function gus_export_members_to_user_sheet(PDO $pdo, $user_id) {
    $rec = gus_get_record($user_id);
    if (!$rec || empty($rec['refresh_token'])) {
        throw new Exception('NOT_CONNECTED');
    }
    $accessToken = gus_access_token($rec['refresh_token']); // 失効時は例外

    $sid = $rec['spreadsheet_id'] ?? '';
    $title = $sid ? gus_first_sheet_title($accessToken, $sid) : false;
    if (!$title) {
        // 未作成 or 本人が削除済み → 作り直し
        list($sid, $title) = gus_create_spreadsheet($accessToken, '[WHABITAT] メンバー名簿');
        $rec['spreadsheet_id'] = $sid;
        gus_set_record($user_id, $rec);
    }

    $rows = gus_build_members_rows($pdo);

    // 古いデータをクリアしてから書き込み（RAW=数式評価されない=CSV/数式インジェクション無効）
    $clearRange = rawurlencode($title . '!A1:Z100000');
    gus_api($accessToken, 'POST',
        'https://sheets.googleapis.com/v4/spreadsheets/' . urlencode($sid) . '/values/' . $clearRange . ':clear',
        new stdClass());
    $writeRange = rawurlencode($title . '!A1');
    list($http, , $raw) = gus_api($accessToken, 'PUT',
        'https://sheets.googleapis.com/v4/spreadsheets/' . urlencode($sid) . '/values/' . $writeRange . '?valueInputOption=RAW',
        ['values' => $rows]);
    if ($http !== 200) {
        throw new Exception('WRITE_FAILED: HTTP ' . $http . ' ' . substr((string)$raw, 0, 200));
    }

    return 'https://docs.google.com/spreadsheets/d/' . $sid . '/edit';
}
