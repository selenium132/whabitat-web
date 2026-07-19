<?php
require_once '../config.php';
requireLogin();

// Only admins can access this page
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();
$error = '';
$success = '';

ensureActivityTeamsTable($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? '');

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $type = ($_POST['type'] ?? '') === 'jv' ? 'jv' : 'gv';
        $year = trim($_POST['year'] ?? '');
        $team_name = trim($_POST['team_name'] ?? '');
        $instagram_url = trim($_POST['instagram_url'] ?? '');

        // GV: 春休み(Spring)/夏休み(Summer)の2シーズン + 渡航国
        // JV: 夏休みのみ（シーズン入力なし）。都道府県 + 地名は自由入力
        if ($type === 'gv') {
            $season = ($_POST['season'] ?? '') === 'Summer' ? 'Summer' : 'Spring';
            $year_label = $year;                       // 年ごとにまとめ、シーズンはカード上のタグで表示
            $tag1 = $season;
            $tag2 = trim($_POST['country'] ?? '');     // 渡航国
        } else {
            $year_label = $year !== '' ? $year . ' Summer' : ''; // JVは夏休みのみ
            $tag1 = trim($_POST['region'] ?? '');      // 都道府県など
            $tag2 = trim($_POST['place'] ?? '');       // 地名
        }

        if ($year !== '' && !preg_match('/^\d{4}$/', $year)) {
            $error = '年度は西暦4桁で入力してください（例: 2026）。';
        }

        // InstagramのURLのみ許可（空は可）
        if (!$error && $instagram_url !== '' && !preg_match('#^https://(www\.)?instagram\.com/#', $instagram_url)) {
            $error = 'Instagram URLは https://www.instagram.com/ で始まるURLを入力してください。';
        }

        // Handle image upload
        $image_path = $_POST['existing_image'] ?? '';
        if (!$error && !empty($_FILES['image']['name']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/teams/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            // PHP実行を二重に禁止（親 uploads/.htaccess に加え専用ディレクトリにも設置）
            $ht = $upload_dir . '.htaccess';
            if (!file_exists($ht)) {
                file_put_contents($ht, "<IfModule mod_php.c>\nphp_flag engine off\n</IfModule>\n<FilesMatch \"(?i)\\.(php|php3|php4|php5|php7|phtml|pht|phar)$\">\nRequire all denied\n</FilesMatch>\n");
            }
            // クライアント申告の拡張子は信用せず、マジックバイトで実形式を判定する
            $imageInfo = getimagesize($_FILES['image']['tmp_name']);
            $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
            if ($imageInfo === false || !isset($allowed[$imageInfo[2]])) {
                $error = '画像ファイル（JPEG / PNG / GIF / WebP）のみアップロードできます。';
            } else {
                $ext = $allowed[$imageInfo[2]];
                $filename = 'team_' . date('Y_m_d_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $target = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    $image_path = 'uploads/teams/' . $filename;
                }
            }
        }

        if ($team_name && $year_label && !$error) {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO activity_teams (type, year_label, team_name, tag1, tag2, instagram_url, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$type, $year_label, $team_name, $tag1, $tag2, $instagram_url, $image_path]);
                $id = (int)$pdo->lastInsertId();
                $success = 'チームを追加しました。';
            } else {
                $stmt = $pdo->prepare("UPDATE activity_teams SET type = ?, year_label = ?, team_name = ?, tag1 = ?, tag2 = ?, instagram_url = ?, image_path = ? WHERE id = ?");
                $stmt->execute([$type, $year_label, $team_name, $tag1, $tag2, $instagram_url, $image_path, $id]);
                $success = 'チームを更新しました。';
            }

            // チームメンバーの紐付けを同期（実在する会員IDのみ受け付ける）
            $posted_ids = array_map('intval', (array)($_POST['member_ids'] ?? []));
            $valid_ids = array_map('intval', $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN));
            $member_ids = array_values(array_intersect(array_unique($posted_ids), $valid_ids));
            $pdo->prepare("DELETE FROM activity_team_members WHERE team_id = ?")->execute([$id]);
            if ($member_ids) {
                $ins = $pdo->prepare("INSERT INTO activity_team_members (team_id, user_id) VALUES (?, ?)");
                foreach ($member_ids as $uid) {
                    $ins->execute([$id, $uid]);
                }
            }
        } elseif (!$error) {
            $error = 'チーム名と年度は必須です。';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM activity_team_members WHERE team_id = ?")->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM activity_teams WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'チームを削除しました。';
    }
}

// Filter tab (gv / jv / all)
$filter = $_GET['type'] ?? 'all';
if (!in_array($filter, ['gv', 'jv', 'all'], true)) $filter = 'all';

if ($filter === 'all') {
    $teams = $pdo->query("SELECT * FROM activity_teams ORDER BY type, year_label DESC, FIELD(tag1, 'Summer', 'Spring'), sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT * FROM activity_teams WHERE type = ? ORDER BY year_label DESC, FIELD(tag1, 'Summer', 'Spring'), sort_order, id");
    $stmt->execute([$filter]);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Edit mode
$edit_team = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM activity_teams WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_team = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 新規追加時の初期タイプ（?type=gv/jv から引き継ぐ）
$default_type = $edit_team['type'] ?? ($filter === 'jv' ? 'jv' : 'gv');

// 編集時のプリフィル（保存形式 → フォーム項目に展開）
$edit_year = '';
$edit_season = 'Spring';
$edit_country = '';
$edit_region = '';
$edit_place = '';
if ($edit_team) {
    if ($edit_team['type'] === 'gv') {
        $edit_year = $edit_team['year_label'];
        $edit_season = ($edit_team['tag1'] === 'Summer') ? 'Summer' : 'Spring';
        $edit_country = $edit_team['tag2'] ?? '';
    } else {
        $edit_year = trim(str_replace('Summer', '', $edit_team['year_label']));
        $edit_region = $edit_team['tag1'] ?? '';
        $edit_place = $edit_team['tag2'] ?? '';
    }
}

// メンバー選択用: 承認済み会員（代の新しい順 → 名前順）
$all_members = $pdo->query("SELECT id, name, name_kana, grade FROM users WHERE is_approved = 1 AND name IS NOT NULL AND name != '' ORDER BY CAST(grade AS UNSIGNED) DESC, name COLLATE utf8mb4_unicode_ci ASC")->fetchAll(PDO::FETCH_ASSOC);

// チームごとの所属メンバー（一覧表示 + 編集時のプリチェック用）
$team_member_map = [];   // team_id => [user_id, ...]
$team_member_names = []; // team_id => [name, ...]
$mrows = $pdo->query("SELECT tm.team_id, tm.user_id, u.name FROM activity_team_members tm JOIN users u ON u.id = tm.user_id ORDER BY u.name COLLATE utf8mb4_unicode_ci ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($mrows as $r) {
    $team_member_map[$r['team_id']][] = (int)$r['user_id'];
    $team_member_names[$r['team_id']][] = $r['name'];
}
$edit_member_ids = $edit_team ? ($team_member_map[$edit_team['id']] ?? []) : [];

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GV/JVチーム管理 | WHABITAT</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo @filemtime(__DIR__ . '/../style.css') ?: '1'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .admin-container { max-width: 900px; margin: 0 auto; padding: 20px; padding-top: 100px; }
        .form-card { background: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; scroll-margin-top: 90px; }
        .form-card.is-editing { border: 1.5px solid var(--primary-color, #1a1a1a) !important; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
        .form-input, .form-select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
        .btn-submit { background: var(--primary-color); color: white; padding: 12px 30px; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; }
        .btn-submit:hover { opacity: 0.9; }
        .btn-cancel { background: #fff; color: #333; padding: 12px 20px; border: 1px solid #ccc; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: .95rem; transition: border-color .2s, background .2s; }
        .btn-cancel:hover { border-color: #1a1a1a; background: #f7f5f0; }
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; }
        .filter-tab { padding: 6px 16px; border: 1px solid #ddd; border-radius: 999px; text-decoration: none; color: #333; font-size: .88rem; }
        .filter-tab.active { background: var(--primary-color); color: white; border-color: var(--primary-color); }
        .type-badge { display: inline-block; padding: 1px 10px; border-radius: 10px; font-size: .72rem; font-weight: 600; letter-spacing: .06em; margin-right: 6px; }
        .type-gv { background: #ecf0f2; color: #51666e; }
        .type-jv { background: #ecf2ed; color: #3f7d54; }
        .entry-list { display: grid; gap: 15px; }
        /* min-width: 0 が無いと、nowrap の行(メンバー名/URL)の幅まで grid トラックが広がり
           モバイルで画面からはみ出す（min-width:auto 対策） */
        .entry-item { background: white; padding: 15px; border-radius: 8px; display: flex; gap: 15px; align-items: center; min-width: 0; }
        .entry-item.is-editing { border-color: var(--primary-color, #1a1a1a) !important; background: #f7f5f0; }
        .editing-chip {
            display: inline-flex; align-items: center; gap: 6px; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            background: var(--primary-color, #1a1a1a); color: #fff;
            font-size: .78rem; font-weight: 600; letter-spacing: .04em;
            padding: 4px 12px; border-radius: 999px; vertical-align: 3px;
        }
        .form-head { display: flex; align-items: center; flex-wrap: wrap; gap: 8px 12px; margin-bottom: 12px; }
        .form-head h3 { margin: 0; }
        .form-actions { display: flex; gap: 10px; margin-top: 20px; }
        .form-actions .btn-submit { flex: 1; }
        .form-actions .btn-cancel { flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; gap: 7px; margin-left: 0; }
        .entry-item img { width: 80px; height: 60px; object-fit: cover; border-radius: 6px; }
        .entry-info { flex: 1; min-width: 0; }
        .entry-title { font-weight: 600; color: #333; }
        .entry-date { font-size: 0.85rem; color: #666; }
        .entry-insta { font-size: .8rem; color: #999; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .entry-actions { display: flex; gap: 8px; }
        .btn-edit { background: #667eea; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.85rem; }
        .btn-delete { background: #dc3545; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; }
        .alert-success { background: #ecf2ed; color: #3f7d54; padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        .alert-error { background: #f6ebe9; color: #b0453a; padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #667eea; text-decoration: none; }
        .field-note { font-size: .8rem; color: #888; margin-top: 4px; }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .member-picker { max-height: 220px; overflow-y: auto; border: 1px solid #ddd; border-radius: 6px; padding: 4px; display: flex; flex-direction: column; }
        .member-pick { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 5px; cursor: pointer; font-size: .92rem; }
        .member-pick:hover { background: #f5f3ee; }
        .member-pick input { accent-color: #1a1a1a; }
        .member-pick-grade { margin-left: auto; font-size: .78rem; color: #999; }
        .entry-members { font-size: .8rem; color: #666; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .entry-members.is-empty { color: #aaa; }
        .admin-container h1 { font-size: 1.6rem; }

        @media (max-width: 640px) {
            .admin-container { padding: 14px; padding-top: 90px; }
            .admin-container h1 { font-size: 1.3rem; margin-bottom: 20px !important; }
            .form-card { padding: 18px; }
            .form-grid-2 { grid-template-columns: 1fr; gap: 0; }
            .entry-item { flex-wrap: wrap; }
            .entry-info { flex: 1 1 calc(100% - 95px); }
            .entry-actions { width: 100%; justify-content: flex-end; }
            .form-actions { flex-direction: column; }
            .form-actions .btn-cancel { width: 100%; box-sizing: border-box; }
            .editing-chip { margin-left: 0; }
        }
    </style>
    <link rel="stylesheet" href="../member.css?v=<?php echo @filemtime(__DIR__ . '/../member.css') ?: '1'; ?>">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="../dashboard.php" class="logo" style="font-size: 1rem;">
                <i class="fas fa-chevron-left"></i> ダッシュボード
            </a>
        </div>
    </header>

    <div class="admin-container">
        <h1 style="margin-bottom: 30px;">GV/JVチーム管理</h1>

        <?php if ($success): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Add/Edit Form -->
        <div class="form-card card<?php echo $edit_team ? ' is-editing' : ''; ?>" id="teamForm">
            <div class="form-head">
                <h3><?php echo $edit_team ? 'チームを編集' : '新しいチームを追加'; ?></h3>
                <?php if ($edit_team): ?>
                    <span class="editing-chip"><i class="fas fa-pen"></i> 「<?php echo htmlspecialchars($edit_team['team_name']); ?>」を編集中</span>
                <?php endif; ?>
            </div>
            <?php if ($edit_team): ?>
                <p class="field-note" style="margin: 0 0 15px;">下の一覧で選んだチームの内容を書き換えています。「編集をやめる」で新規追加に戻ります。</p>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="<?php echo $edit_team ? 'edit' : 'add'; ?>">
                <?php if ($edit_team): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_team['id']; ?>">
                    <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($edit_team['image_path']); ?>">
                <?php endif; ?>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="team-type">種別 *</label>
                        <select name="type" id="team-type" class="form-select" required onchange="toggleTypeFields()">
                            <option value="gv" <?php echo $default_type === 'gv' ? 'selected' : ''; ?>>GV（海外住居建築）</option>
                            <option value="jv" <?php echo $default_type === 'jv' ? 'selected' : ''; ?>>JV（国内派遣・夏休み）</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="team-year">年度 *</label>
                        <input type="text" name="year" id="team-year" class="form-input" required
                               inputmode="numeric" pattern="\d{4}" placeholder="例: <?php echo date('Y'); ?>"
                               value="<?php echo htmlspecialchars($edit_year); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="team-name">チーム名 *</label>
                    <input type="text" name="team_name" id="team-name" class="form-input" required
                           placeholder="例: ばんがるGV / みさらーちJV"
                           value="<?php echo htmlspecialchars($edit_team['team_name'] ?? ''); ?>">
                </div>

                <!-- GV: シーズン（春休み/夏休み）+ 渡航国 -->
                <div class="form-grid-2 gv-only">
                    <div class="form-group">
                        <label class="form-label" for="team-season">シーズン *</label>
                        <select name="season" id="team-season" class="form-select">
                            <option value="Spring" <?php echo $edit_season === 'Spring' ? 'selected' : ''; ?>>Spring（春休み）</option>
                            <option value="Summer" <?php echo $edit_season === 'Summer' ? 'selected' : ''; ?>>Summer（夏休み）</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="team-country">渡航国</label>
                        <input type="text" name="country" id="team-country" class="form-input"
                               placeholder="例: Nepal / Indonesia"
                               value="<?php echo htmlspecialchars($edit_country); ?>">
                    </div>
                </div>

                <!-- JV: 都道府県 + 地名（自由入力） -->
                <div class="form-grid-2 jv-only">
                    <div class="form-group">
                        <label class="form-label" for="team-region">都道府県</label>
                        <input type="text" name="region" id="team-region" class="form-input"
                               placeholder="例: Nagano / Tokushima"
                               value="<?php echo htmlspecialchars($edit_region); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="team-place">地名</label>
                        <input type="text" name="place" id="team-place" class="form-input"
                               placeholder="例: 立屋 / 大井"
                               value="<?php echo htmlspecialchars($edit_place); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">チームメンバー <span class="field-note" style="display:inline;">（<span id="memberSelCount">0</span>名選択中・サイト内の管理用で、公開ページには表示されません）</span></label>
                    <input type="text" id="memberSearch" class="form-input" placeholder="名前・ふりがな・代で絞り込み" style="margin-bottom: 8px;" oninput="filterMemberList()">
                    <div class="member-picker" id="memberPicker">
                        <?php foreach ($all_members as $mem): ?>
                            <label class="member-pick" data-search="<?php echo htmlspecialchars(mb_strtolower(($mem['name'] ?? '') . ' ' . ($mem['name_kana'] ?? '') . ' ' . ($mem['grade'] ?? ''))); ?>">
                                <input type="checkbox" name="member_ids[]" value="<?php echo (int)$mem['id']; ?>"
                                       <?php echo in_array((int)$mem['id'], $edit_member_ids, true) ? 'checked' : ''; ?>
                                       onchange="updateMemberCount()">
                                <span class="member-pick-name"><?php echo htmlspecialchars($mem['name']); ?></span>
                                <span class="member-pick-grade"><?php echo htmlspecialchars($mem['grade'] ?? ''); ?></span>
                            </label>
                        <?php endforeach; ?>
                        <?php if (empty($all_members)): ?>
                            <p class="field-note" style="padding: 8px;">承認済みの会員がいません。</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="team-insta">Instagram URL</label>
                    <input type="url" name="instagram_url" id="team-insta" class="form-input"
                           placeholder="https://www.instagram.com/..."
                           value="<?php echo htmlspecialchars($edit_team['instagram_url'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="team-image">チーム写真<?php echo $edit_team ? ' (変更する場合のみ)' : ''; ?></label>
                    <?php if ($edit_team && $edit_team['image_path']): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="../<?php echo htmlspecialchars($edit_team['image_path']); ?>" alt="現在のチーム写真" style="max-width: 150px; border-radius: 6px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" id="team-image" accept="image/*" class="form-input">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-<?php echo $edit_team ? 'check' : 'plus'; ?>"></i> <?php echo $edit_team ? 'この内容で更新する' : '追加する'; ?>
                    </button>
                    <?php if ($edit_team): ?>
                        <a href="teams.php<?php echo $filter !== 'all' ? '?type=' . $filter : ''; ?>" class="btn-cancel"><i class="fas fa-xmark"></i> 編集をやめる</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Team List -->
        <h3 style="margin-bottom: 15px;">登録済みチーム</h3>
        <div class="filter-tabs">
            <a href="?type=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">すべて</a>
            <a href="?type=gv" class="filter-tab <?php echo $filter === 'gv' ? 'active' : ''; ?>">GV</a>
            <a href="?type=jv" class="filter-tab <?php echo $filter === 'jv' ? 'active' : ''; ?>">JV</a>
        </div>
        <div class="entry-list">
            <?php if (empty($teams)): ?>
                <p style="color: #666;">まだチームがありません。</p>
            <?php else: ?>
                <?php foreach ($teams as $team): ?>
                    <div class="entry-item<?php echo ($edit_team && (int)$edit_team['id'] === (int)$team['id']) ? ' is-editing' : ''; ?>">
                        <?php if ($team['image_path']): ?>
                            <img src="../<?php echo htmlspecialchars($team['image_path']); ?>" alt="<?php echo htmlspecialchars($team['team_name']); ?>">
                        <?php else: ?>
                            <div style="width: 80px; height: 60px; background: #eee; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #999;">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                        <div class="entry-info">
                            <div class="entry-title">
                                <span class="type-badge type-<?php echo $team['type']; ?>"><?php echo strtoupper($team['type']); ?></span>
                                <?php echo htmlspecialchars($team['team_name']); ?>
                            </div>
                            <div class="entry-date">
                                <?php echo htmlspecialchars($team['year_label']); ?>
                                <?php if ($team['tag1']): ?> / <?php echo htmlspecialchars($team['tag1']); ?><?php endif; ?>
                                <?php if ($team['tag2']): ?> / <?php echo htmlspecialchars($team['tag2']); ?><?php endif; ?>
                            </div>
                            <?php if ($team['instagram_url']): ?>
                                <div class="entry-insta"><i class="fab fa-instagram"></i> <?php echo htmlspecialchars($team['instagram_url']); ?></div>
                            <?php endif; ?>
                            <?php $tm_names = $team_member_names[$team['id']] ?? []; ?>
                            <div class="entry-members<?php echo $tm_names ? '' : ' is-empty'; ?>" title="<?php echo htmlspecialchars(implode('、', $tm_names)); ?>">
                                <i class="fas fa-user-group"></i> <?php echo count($tm_names); ?>名<?php echo $tm_names ? '：' . htmlspecialchars(implode('、', $tm_names)) : '（未登録）'; ?>
                            </div>
                        </div>
                        <div class="entry-actions">
                            <a href="?edit=<?php echo $team['id']; ?><?php echo $filter !== 'all' ? '&type=' . $filter : ''; ?>#teamForm" class="btn-edit" aria-label="編集"><i class="fas fa-edit"></i></a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('「<?php echo htmlspecialchars($team['team_name']); ?>」を削除しますか？\nこの操作は取り消せません。');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $team['id']; ?>">
                                <button type="submit" class="btn-delete" aria-label="削除"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="margin-top: 30px;">
            <a href="../activity_gv.php" class="back-link"><i class="fas fa-external-link-alt"></i> GVページを見る</a>
            <a href="../activity_jv.php" class="back-link" style="margin-left: 15px;"><i class="fas fa-external-link-alt"></i> JVページを見る</a>
        </div>
    </div>
    <script>
        function filterMemberList() {
            const q = (document.getElementById('memberSearch').value || '').trim().toLowerCase();
            document.querySelectorAll('#memberPicker .member-pick').forEach(el => {
                el.style.display = (!q || (el.dataset.search || '').includes(q)) ? '' : 'none';
            });
        }
        function updateMemberCount() {
            const n = document.querySelectorAll('#memberPicker input[type="checkbox"]:checked').length;
            document.getElementById('memberSelCount').textContent = n;
        }
        updateMemberCount();

        function toggleTypeFields() {
            const isGv = document.getElementById('team-type').value === 'gv';
            document.querySelectorAll('.gv-only').forEach(el => { el.style.display = isGv ? '' : 'none'; });
            document.querySelectorAll('.jv-only').forEach(el => { el.style.display = isGv ? 'none' : ''; });
            document.getElementById('team-season').required = isGv;
        }
        toggleTypeFields();
    </script>
</body>
</html>
