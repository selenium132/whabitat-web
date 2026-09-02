<?php
// ===========================================
// Load Environment Variables from .env
// ===========================================
$env_path = __DIR__ . '/.env';
if (!file_exists($env_path)) {
    die('ERROR: .env file not found. System cannot continue without environment configuration.');
}
$env = parse_ini_file($env_path);
if ($env === false) {
    die('ERROR: Failed to parse .env file.');
}

// Database Configuration
define('DB_HOST', $env['DB_HOST'] ?? 'localhost');
define('DB_NAME', $env['DB_NAME'] ?? '');
define('DB_USER', $env['DB_USER'] ?? '');
define('DB_PASS', $env['DB_PASS'] ?? '');

// Circle Secret Code (for registration)
define('CIRCLE_SECRET', $env['CIRCLE_SECRET'] ?? '');    // 承認待ち画面の合言葉（approval_pending.php）
// ADMIN_SECRET は参照箇所が無いデッドコードだったため撤去（管理者昇格は admin/members.php の権限変更のみ）

// Available Grades (Generations) - dynamically calculated
// Base: fiscal year 2026 -> newest gen = 20th
$current_fy = ((int)date('n') >= 4) ? (int)date('Y') : (int)date('Y') - 1;
$newest_gen = 20 + ($current_fy - 2026);
define('AVAILABLE_GRADES', array_map(function($g) { return $g . 'th'; }, range($newest_gen - 3, $newest_gen + 1)));

// LINE Login Configuration
define('LINE_CHANNEL_ID', $env['LINE_CHANNEL_ID'] ?? '');
define('LINE_CHANNEL_SECRET', $env['LINE_CHANNEL_SECRET'] ?? '');
define('LINE_CALLBACK_URL', $env['LINE_CALLBACK_URL'] ?? '');

// LINE Messaging API (Bot) Configuration
define('LINE_BOT_ACCESS_TOKEN', $env['LINE_BOT_ACCESS_TOKEN'] ?? '');
define('LINE_BOT_CHANNEL_SECRET', $env['LINE_BOT_CHANNEL_SECRET'] ?? '');

// reCAPTCHA v2 Configuration
define('RECAPTCHA_SITE_KEY', $env['RECAPTCHA_SITE_KEY'] ?? '');
define('RECAPTCHA_SECRET_KEY', $env['RECAPTCHA_SECRET_KEY'] ?? '');

// Google OAuth (スプシ出力時に押した本人のGoogleアカウントを特定し、そのアカウントだけに共有するため)
define('GOOGLE_OAUTH_CLIENT_ID', $env['GOOGLE_OAUTH_CLIENT_ID'] ?? '');
define('GOOGLE_OAUTH_CLIENT_SECRET', $env['GOOGLE_OAUTH_CLIENT_SECRET'] ?? '');
define('GOOGLE_OAUTH_REDIRECT_URI', $env['GOOGLE_OAUTH_REDIRECT_URI'] ?? '');

// Google Apps Script WebアプリURL（イベント出欠シートの新規作成用）。
// URLを知っていれば誰でもPOSTできる疑似シークレットのため、コードに直書きせず .env で管理する。
define('APPS_SCRIPT_URL', $env['APPS_SCRIPT_URL'] ?? '');

// Security: Session Hardening
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_secure', 1); // Enable for HTTPS
// Lax で十分: OAuthのリダイレクト遷移はトップレベルGETのため Lax でも Cookie は送出される
// （login.php の line_state Cookie が同じ前提で既に本番稼働しており実証済み）。
// None にすると全クロスサイトリクエストでCookieが送出されCSRF面が不要に広がるため避ける。
ini_set('session.cookie_samesite', 'Lax');

// Security: レスポンスヘッダ
// .htaccess の mod_headers は本番(Xserver)で無効で、実際には一切付与されていなかった(実測)。
// PHP から直接送ることで環境に依存せず確実に付与する。CLI(cron スクリプト)では何もしない。
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()");
    // クリックジャッキング対策(X-Frame-Options の後継)。インラインJS/CSS が多いため script/style の制限は掛けない。
    header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; form-action 'self' https://accounts.google.com https://access.line.me");
    // HSTS: 180日。サブドメイン・preload は運用影響が読めないため付けない。
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); // nginx リバースプロキシ配下(Xserver)も考慮
    if ($is_https) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

// Security: Disable Error Display in Production
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start Session
session_start();

// Database Connection Function
// リクエスト内では同じ接続を使い回す（従来は呼ぶたびに新規接続を張っていた。
// requireLogin / isEventAdmin / auditLog などから1ページで十数回呼ばれるため接続コストが支配的だった）。
// 名前付きロック(GET_LOCK)は同一接続内で取得・解放しているので単一接続でも問題ない。
function getDB() {
    static $shared = null;
    if ($shared instanceof PDO) return $shared;
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $shared = $pdo;
        return $pdo;
    } catch (PDOException $e) {
        error_log("DB connection failed: " . $e->getMessage());
        die("システムエラーが発生しました。時間をおいて再度お試しください。");
    }
}

// Helper: スキーマの自己修復(CREATE TABLE IF NOT EXISTS / ALTER)を「初回だけ」実行する。
// 従来は毎リクエスト(部室タブは20秒ポーリングごと)にDDLが走っていた。成功したら private/schema/ に
// マーカーを置き、以後はファイル存在チェックだけで済ませる。DDLを変えたら $key の末尾バージョンを上げる。
// 手動でテーブルを消した等で再実行したい場合はマーカーファイルを削除する。
function ensureSchemaOnce($key, callable $fn) {
    $dir  = __DIR__ . '/private/schema';
    $mark = $dir . '/' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $key);
    if (file_exists($mark)) return true;
    try {
        $fn();
    } catch (Exception $e) {
        error_log("ensureSchemaOnce[{$key}] failed: " . $e->getMessage());
        return false;
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
        $ht = __DIR__ . '/private/.htaccess';
        if (!file_exists($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    }
    @file_put_contents($mark, date('c'));
    return true;
}

// Helper: events.is_archived カラム（従来 dashboard/past_events/form_archive の3箇所に同じ ALTER 試行が重複していた）
function ensureEventsArchivedColumn(PDO $pdo) {
    ensureSchemaOnce('events_is_archived_v1', function () use ($pdo) {
        try { $pdo->query("SELECT is_archived FROM events LIMIT 1"); }
        catch (PDOException $e) { $pdo->exec("ALTER TABLE events ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0"); }
    });
}

// Helper: mtg_history テーブル（従来 activity_mtg.php と admin/mtg_history.php に同一DDLが重複していた）
function ensureMtgHistoryTable(PDO $pdo) {
    ensureSchemaOnce('mtg_history_v1', function () use ($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS mtg_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_date DATE NOT NULL,
            title VARCHAR(255) NOT NULL,
            subtitle VARCHAR(255) DEFAULT NULL,
            description TEXT,
            image_path VARCHAR(255) DEFAULT NULL,
            year_group INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    });
}

// Helper: 出欠/回答ステータスの表示ラベル（従来 form_view.php と form_responses.php に別実装が重複し挙動が乖離していた）
function getStatusLabel($status, $isSurvey = false) {
    if ($status === 'join')    return $isSurvey ? '回答済' : '参加';
    if ($status === 'decline') return '不参加';
    if ($status === 'maybe')   return '未定';
    return (string)$status;
}

// Helper: users.email カラムが無い既存DBに自動でカラムを追加する（既にあれば何もしない）。
// email を使うページ(プロフィール登録・メンバー管理)の冒頭で呼ぶ。
function ensureUsersEmailColumn(PDO $pdo) {
    ensureSchemaOnce('users_email_v1', function () use ($pdo) {
        try { $pdo->query("SELECT email FROM users LIMIT 1"); }
        catch (PDOException $e) { $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL DEFAULT NULL"); }
    });
}

// Helper: events.show_participants カラムが無い既存DBに自動で追加する（無ければ作る）。
// 参加者/回答者リストの公開可否を保持する。NULL = 未設定（種別ごとの既定で解釈）。
// カラムを利用できるかどうかを返す（false のとき呼び出し側はこの列を使わずに保存する）。
function ensureEventShowParticipantsColumn(PDO $pdo) {
    try {
        $pdo->query("SELECT show_participants FROM events LIMIT 1");
        return true;
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE events ADD COLUMN show_participants TINYINT(1) NULL DEFAULT NULL");
            return true;
        } catch (PDOException $e2) {
            error_log('ensureEventShowParticipantsColumn failed: ' . $e2->getMessage());
            return false;
        }
    }
}

// Helper: 参加者/回答者リストを一般会員に見せてよいか。
// 種別ごとの既定は「出欠確認=公開 / アンケート=非公開」。カラム未設定(NULL)の既存データもこの既定に従う。
// 管理者・作成者・イベント管理者は常に閲覧可のため、この関数の判定対象外（呼び出し側で OR する）。
function isParticipantsVisible(array $event) {
    $flag = $event['show_participants'] ?? null;
    if ($flag === null || $flag === '') {
        return (($event['type'] ?? 'event') !== 'survey');
    }
    return (int)$flag === 1;
}

// Helper: 監査ログ用テーブルを自動作成（無ければ作る。ensureUsersEmailColumn と同じ流儀）
function ensureAuditLogTable(PDO $pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NULL,
            admin_name VARCHAR(255) NULL,
            action VARCHAR(64) NOT NULL,
            target_id INT NULL,
            target_name VARCHAR(255) NULL,
            detail TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
        error_log('ensureAuditLogTable failed: ' . $e->getMessage());
    }
}

// Helper: 管理者操作を監査ログに記録する。
// 失敗しても呼び出し元の操作（承認・削除等）を絶対に止めないよう、全体を try/catch で握りつぶす。
function auditLog($action, $targetId = null, $targetName = null, $detail = null) {
    try {
        $pdo = getDB();
        ensureAuditLogTable($pdo);
        $adminId = $_SESSION['user_id'] ?? null;
        $adminName = $_SESSION['name'] ?? '';
        if ($adminName === '' && $adminId) {
            $s = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $s->execute([$adminId]);
            $adminName = $s->fetchColumn() ?: '';
        }
        $stmt = $pdo->prepare("INSERT INTO audit_log (admin_id, admin_name, action, target_id, target_name, detail) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $adminId,
            $adminName !== '' ? $adminName : null,
            (string)$action,
            $targetId !== null ? (int)$targetId : null,
            $targetName,
            $detail,
        ]);
    } catch (Exception $e) {
        error_log('auditLog failed: ' . $e->getMessage());
    }
}

// Helper: Generate CSRF Token
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Helper: Validate CSRF Token
function validateCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        header('Content-Type: text/html; charset=utf-8');
        die('セッションの有効期限が切れました。前のページに戻り、再読み込みしてからもう一度お試しください。');
    }
}

// Helper: DBに保存された画像パスの解決。旧データはファイル名のみで保存されている場合があり、
// その実体は images/common/ に置かれている（例: mtg_history の初期データ）。
function resolveUploadImagePath($path) {
    if ($path && strpos($path, '/') === false) {
        return 'images/common/' . $path;
    }
    return $path;
}

// Helper: GV/JV チーム（History）テーブルの用意。
// 初回はページにハードコードされていた実績データを自動シードする（以後は admin/teams.php で管理）。
function ensureActivityTeamsTable($pdo) {
    ensureSchemaOnce('activity_teams_v1', function () use ($pdo) { ensureActivityTeamsTableNow($pdo); });
}
function ensureActivityTeamsTableNow($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('gv','jv') NOT NULL,
        year_label VARCHAR(20) NOT NULL,
        team_name VARCHAR(100) NOT NULL,
        tag1 VARCHAR(50) DEFAULT NULL,
        tag2 VARCHAR(50) DEFAULT NULL,
        instagram_url VARCHAR(255) DEFAULT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_type_year (type, year_label)
    )");

    // チーム⇔会員の紐付け（管理画面・名簿用。対外公開ページには出さない）
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_team_members (
        team_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (team_id, user_id),
        INDEX idx_user (user_id)
    )");

    $count = (int)$pdo->query("SELECT COUNT(*) FROM activity_teams")->fetchColumn();
    if ($count > 0) return;

    // 旧ハードコード内容の移行シード。GV: tag1=Season / tag2=Country、JV: tag1=Region / tag2=地名
    $seed = [
        ['gv', '2020', 'さかんとGV',    'Spring', 'India',       'https://www.instagram.com/sakanto_gv',              'images/gv/gv_sakanto.jpg'],
        ['gv', '2020', 'ぱるぱるGV',    'Spring', 'Vietnam',     'https://www.instagram.com/habitat_paruparu_gv',     'images/gv/gv_paruparu.jpg'],
        ['gv', '2023', 'たんたんぐGV',  'Summer', 'Indonesia',   'https://www.instagram.com/tantangood_whabitat2023', 'images/gv/gv_tantangood.jpg'],
        ['gv', '2024', 'ゆぷるむGV',    'Spring', 'Cambodia',    'https://www.instagram.com/yupurumu_whabitat',       'images/gv/gv_yupurumu.jpg'],
        ['gv', '2024', 'マカランGV',    'Spring', 'Philippines', 'https://www.instagram.com/magkarawn_gv',            'images/gv/gv_magkarawn.jpg'],
        ['gv', '2024', 'すかいるGV',    'Summer', 'Cambodia',    'https://www.instagram.com/sukairu.gv_whabitat',     'images/gv/gv_sukairu.jpg'],
        ['gv', '2025', 'ばんがるGV',    'Spring', 'Nepal',       'https://www.instagram.com/bangalgv',                'images/gv/gv_bangal.jpg'],
        ['gv', '2025', 'わばるまGV',    'Spring', 'Indonesia',   'https://www.instagram.com/wabarumahgv',             'images/gv/gv_wabarumah.jpg'],
        ['gv', '2025', 'ダンガンGV',    'Spring', 'Vietnam',     'https://www.instagram.com/dangan_gv',               'images/gv/gv_dangan.jpg'],
        ['gv', '2025', 'エルメラGV',    'Summer', 'Indonesia',   'https://www.instagram.com/erumela_gv',              'images/gv/gv_erumela.jpg'],
        ['jv', '2025 Summer', 'みさらーちJV',   'Tokushima', '大井',   'https://www.instagram.com/oi.jv2025',        'images/jv/jv_misarachi.jpg'],
        ['jv', '2025 Summer', 'てやのっぺJV',   'Nagano',    '立屋',   'https://www.instagram.com/teyanope_jv',      'images/jv/jv_teyanoppe.jpg'],
        ['jv', '2025 Summer', 'ぺぺPON！JV',    'Tochigi',   '益子',   'https://www.instagram.com/pepepon_jv',       'images/jv/jv_pepepon.jpg'],
        ['jv', '2025 Summer', 'じゃっぱーれ団', 'Aomori',    '白神',   'https://www.instagram.com/pepepon_jv',       'images/jv/jv_japparedan.jpg'],
        ['jv', '2025 Summer', 'めんけぽっこJV', 'Akita',     '仙北',   'https://www.instagram.com/jyapparedan_jv',   'images/jv/jv_menkepokko.jpg'],
        ['jv', '2025 Summer', 'ふくでっぽらJV', 'Fukushima', '田人',   'https://www.instagram.com/fukudeppo',        'images/jv/jv_fukudeppora.jpg'],
        ['jv', '2025 Summer', 'かまきゅらんJV', 'Nagano',    '真木',   'https://www.instagram.com/kamaqran_jv2025',  'images/jv/jv_kamaquran.jpg'],
        ['jv', '2025 Summer', 'ぎゃばみっちゃJV', 'Fukuoka', '黒木',   'https://www.instagram.com/kamaqran_jv2025',  'images/jv/jv_gyabamiccha.jpg'],
        ['jv', '2025 Summer', 'このれい48JV',   'Mie',       '赤目',   'https://www.instagram.com/konorei48jv2025',  'images/jv/jv_konorei48.jpg'],
        ['jv', '2025 Summer', 'なちゃJV',       'Toyama',    '五箇山', 'https://www.instagram.com/konorei48jv2025',  'images/jv/jv_nacha.jpg'],
        ['jv', '2025 Summer', 'つむぐるりんJV', 'Niigata',   '山古志', 'https://www.instagram.com/tsumugururin_jv',  'images/jv/jv_tsumugururin.png'],
        ['jv', '2025 Summer', 'りもちゅんJV',   'Shiga',     '高島',   'https://www.instagram.com/rimochunnn_jv',    'images/jv/jv_rimochun.jpg'],
    ];
    $stmt = $pdo->prepare("INSERT INTO activity_teams (type, year_label, team_name, tag1, tag2, instagram_url, image_path, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($seed as $i => $row) {
        $stmt->execute([$row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $i]);
    }
}

// Helper: 会員ID => 所属チーム名リスト（新しい年度順）。名簿表示・CSV/シート出力用
function getTeamNamesByUser(PDO $pdo) {
    try {
        $sql = "SELECT tm.user_id, t.team_name FROM activity_team_members tm
                JOIN activity_teams t ON t.id = tm.team_id
                ORDER BY t.year_label DESC, FIELD(t.tag1, 'Spring', 'Summer'), t.sort_order, t.id";
        $map = [];
        foreach ($pdo->query($sql) as $row) {
            $map[(int)$row['user_id']][] = $row['team_name'];
        }
        return $map;
    } catch (Exception $e) {
        return []; // テーブル未作成でも名簿表示を止めない
    }
}

// Helper: プロフィール必須項目が全て埋まっているか（requireLogin と callback.php で共用）
function isProfileComplete(array $user) {
    return !(
        empty($user['name']) ||
        empty($user['name_kana']) ||
        empty($user['email']) ||
        empty($user['gender']) ||
        empty($user['zipcode']) ||
        empty($user['address']) ||
        empty($user['phone']) ||
        empty($user['birthdate']) ||
        empty($user['grade']) ||
        empty($user['student_id'])
    );
}

// Helper: Check Login & Approval
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        // Use JavaScript redirect to capture the full URL including hash (anchor)
        // Fallback to PHP session storage if JS fails (or for API calls) can happen in login.php via 'next' param
        
        // ルート絶対パスにする。相対 'login.php' だと /admin/ 配下から呼ばれた際に
        // /admin/login.php を参照して404になるため（login.php はサイトルートにある）。
        $login_url = '/login.php';
        
        // Output simple HTML with JS redirect
        // We pass the current full URL as 'next' parameter to login.php
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
        echo '<script>window.location.href = "' . $login_url . '?next=" + encodeURIComponent(window.location.href);</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $login_url . '"></noscript>';
        echo '</body></html>';
        exit;
    }

    // Security: Re-validate user status from DB on every request
    // This prevents banned/deleted users from staying logged in via session
    try {
        $pdo = getDB();
        ensureUsersEmailColumn($pdo); // emailカラムが無い既存DBでもauth checkが落ちないように先に確保
        $stmt = $pdo->prepare("SELECT id, is_approved, name, role, name_kana, gender, zipcode, address, phone, birthdate, grade, email, student_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // User deleted
            header("Location: /logout.php");
            exit;
        }

        // Sync Session with DB
        $_SESSION['is_approved'] = $user['is_approved'];
        $_SESSION['role'] = $user['role']; // Also sync role in case of promotion/demotion

        $profile_incomplete = !isProfileComplete($user);

    } catch (PDOException $e) {
        die("Database Error during auth check.");
    }
    
    $current_page = basename($_SERVER['PHP_SELF']);
    $allowed_unapproved = ['approval_pending.php', 'logout.php'];
    $allowed_incomplete = ['register_profile.php', 'approval_pending.php', 'logout.php'];

    // Check Approval Status
    if (empty($_SESSION['is_approved']) && !in_array($current_page, $allowed_unapproved)) {
        header("Location: /approval_pending.php");
        exit;
    }

    // Check Profile Completion (Force existing & new users to complete their profile)
    if (!empty($_SESSION['is_approved']) && $profile_incomplete && !in_array($current_page, $allowed_incomplete)) {
        header("Location: /register_profile.php");
        exit;
    }
}

// Helper: LINE Messaging API への POST（push/multicast 共用）。失敗しても呼び出し元を止めない。
function lineBotApiPost($endpoint, array $payload) {
    if (!defined('LINE_BOT_ACCESS_TOKEN') || LINE_BOT_ACCESS_TOKEN === '') return false;
    $ch = curl_init('https://api.line.me/v2/bot/' . $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . LINE_BOT_ACCESS_TOKEN,
    ]);
    $result = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($result === false || $status >= 300) {
        error_log("lineBotApiPost {$endpoint} failed (HTTP {$status}): " . ($result === false ? curl_error($ch) : $result));
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    return true;
}

// Helper: 特定の会員1人へ LINE push 通知（LINE未連携なら何もしない）
function linePushToUser($lineUserId, $text) {
    if (empty($lineUserId)) return false;
    return lineBotApiPost('message/push', [
        'to' => $lineUserId,
        'messages' => [['type' => 'text', 'text' => $text]],
    ]);
}

// Helper: 管理者全員へ LINE push 通知（問い合わせ・目安箱の新着通知用）。
// 通知失敗が元の操作（問い合わせ送信等）を絶対に止めないよう、全体を try/catch で握りつぶす。
function linePushToAdmins($text) {
    try {
        $pdo = getDB();
        $ids = $pdo->query("SELECT line_user_id FROM users WHERE role = 'admin' AND line_user_id IS NOT NULL AND line_user_id != ''")
                   ->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) return;
        // multicast は1回で最大500宛先（管理者数なら1回で十分）
        lineBotApiPost('message/multicast', [
            'to' => array_values(array_slice($ids, 0, 500)),
            'messages' => [['type' => 'text', 'text' => $text]],
        ]);
    } catch (Exception $e) {
        error_log('linePushToAdmins failed: ' . $e->getMessage());
    }
}

// Helper: 管理者専用ページの保護（requireLogin + role チェック）。
// admin/ 配下の各ページ冒頭で必ず呼ぶこと。JSON APIは各自 403 応答を実装する（calendar_api.php 参照）。
function requireAdmin() {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header("Location: /dashboard.php");
        exit;
    }
}

// Helper: Check if user is Event Admin (Global Admin, Creator, OR Assigned Event Admin)
function isEventAdmin($event_id) {
    $event_id = (int)$event_id;
    // 1. Global Admin is always allowed
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }

    // 不正/空のIDは以降のDB判定不要
    if ($event_id <= 0) return false;

    // 同一リクエスト内の再判定はメモ化（一覧ページのループから event ごとに呼ばれるため）
    static $memo = [];
    if (array_key_exists($event_id, $memo)) return $memo[$event_id];
    $memo[$event_id] = isEventAdminQuery($event_id);
    return $memo[$event_id];
}

function isEventAdminQuery($event_id) {

    if (isset($_SESSION['user_id'])) {
        try {
            $pdo = getDB();
            
            // 2. Check if user is the creator of the event
            $stmt_creator = $pdo->prepare("SELECT COUNT(*) FROM events WHERE id = ? AND created_by = ?");
            $stmt_creator->execute([$event_id, $_SESSION['user_id']]);
            if ($stmt_creator->fetchColumn() > 0) return true;
            
            // 3. Check if user is assigned as admin for this event
            $stmt_admin = $pdo->prepare("SELECT COUNT(*) FROM event_admins WHERE event_id = ? AND user_id = ?");
            $stmt_admin->execute([$event_id, $_SESSION['user_id']]);
            if ($stmt_admin->fetchColumn() > 0) return true;
        } catch (PDOException $e) {
            // Table might not exist yet, ignore
        }
    }

    return false;
}


// Helper: アプリ内ブラウザ（LINE/Facebook/Instagram等の埋め込みWebView）判定。
// これらの中ではGoogleのOAuthが "disallowed_useragent" でブロックされるため検知する。
function isInAppBrowser() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '') return false;
    if (preg_match('/Line\//i', $ua)) return true;                 // LINE
    if (strpos($ua, 'FBAN') !== false || strpos($ua, 'FBAV') !== false) return true; // Facebook/Messenger
    if (strpos($ua, 'Instagram') !== false) return true;           // Instagram
    if (preg_match('/; wv\)/', $ua)) return true;                  // Android WebView
    return false;
}

// Helper: 本人のGoogle Drive連携を要求（各自アカウントで名簿シートを作るため）。
// 連携済み（リフレッシュトークン保存済み）ならそのレコードを返す。
// 未連携なら Google OAuth（drive.file + オフライン）を開始し、認証後 $return_uri へ戻る。
function requireGoogleDriveConnection($return_uri) {
    require_once __DIR__ . '/google_user_sheets.php';
    $rec = gus_get_record($_SESSION['user_id'] ?? 0);
    if ($rec && !empty($rec['refresh_token'])) {
        return $rec;
    }
    if (GOOGLE_OAUTH_CLIENT_ID === '' || GOOGLE_OAUTH_REDIRECT_URI === '') {
        die('Google連携が未設定です（.env の GOOGLE_OAUTH_* を確認してください）。');
    }
    // アプリ内ブラウザではGoogle認証がブロックされる(disallowed_useragent)ため、
    // 外部ブラウザ(Safari/Chrome)で開き直すよう案内する。LINEは openExternalBrowser=1 で外部起動。
    if (isInAppBrowser()) {
        $cur = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'whabitathome.com') . ($_SERVER['REQUEST_URI'] ?? '/');
        $ext = $cur . (strpos($cur, '?') !== false ? '&' : '?') . 'openExternalBrowser=1';
        $ext_h = htmlspecialchars($ext, ENT_QUOTES);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="ja"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>ブラウザで開いてください</title>'
           . '<style>body{font-family:"Noto Sans JP",sans-serif;background:#faf9f6;color:#2a2a2a;margin:0;padding:2rem 1.2rem;line-height:1.8}'
           . '.box{max-width:480px;margin:1.5rem auto;background:#fff;border:1px solid #e6e2d9;border-radius:12px;padding:1.6rem}'
           . 'h1{font-size:1.15rem;margin:0 0 1rem}a.btn{display:block;text-align:center;background:#1a1a1a;color:#fff;text-decoration:none;padding:0.9rem;border-radius:8px;font-weight:600;margin:1.2rem 0}'
           . '.muted{color:#8d877c;font-size:.86rem}</style></head><body><div class="box">'
           . '<h1>ブラウザで開いてください</h1>'
           . '<p>Googleの仕様により、LINEなどの<strong>アプリ内ブラウザではGoogleログインができません</strong>（エラー: disallowed_useragent）。Safari / Chrome で開くと出力できます。</p>'
           . '<a class="btn" href="' . $ext_h . '">ブラウザで開いて続ける</a>'
           . '<p class="muted">ボタンで開けない場合は、画面右上のメニューから「ブラウザで開く」(Safari/Chrome) を選び、もう一度「シートに出力」を押してください。PCのブラウザでもOKです。</p>'
           . '</div></body></html>';
        exit;
    }
    $_SESSION['google_oauth_return'] = $return_uri;
    $state = bin2hex(random_bytes(32));
    $_SESSION['google_oauth_state'] = $state;
    $params = http_build_query([
        'client_id'     => GOOGLE_OAUTH_CLIENT_ID,
        'redirect_uri'  => GOOGLE_OAUTH_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email https://www.googleapis.com/auth/drive.file',
        'state'         => $state,
        'access_type'   => 'offline',
        'prompt'        => 'consent',
    ]);
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

// Helper: Get DB Connection
?>
