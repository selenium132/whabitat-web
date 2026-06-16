<?php
require_once '../config.php';
require_once '../sheet_sync.php';
requireLogin();

// Check Admin Role
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? ''); // CSRF Check

    $target_id = $_POST['user_id'] ?? 0;
    $action = $_POST['action'] ?? '';

    if ($target_id) {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
            $stmt->execute([$target_id]);
        } elseif ($action === 'disapprove') {
            $stmt = $pdo->prepare("UPDATE users SET is_approved = 0 WHERE id = ?");
            $stmt->execute([$target_id]);
        } elseif ($action === 'delete') {
            // Prevent deleting self
            if ($target_id != $_SESSION['user_id']) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$target_id]);
            }
        } elseif ($action === 'update_role') {
            $new_role = $_POST['role'] ?? '';
            if ($new_role === 'member' || $new_role === 'admin') {
                // Prevent removing own admin rights
                if ($target_id != $_SESSION['user_id'] || $new_role === 'admin') {
                    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->execute([$new_role, $target_id]);
                }
            }
        } elseif ($action === 'update_profile') {
            // Admin Update Profile
            $name = $_POST['name'] ?? '';
            $name_kana = $_POST['name_kana'] ?? '';
            $sid = $_POST['student_id'] ?? '';
            $faculty = $_POST['faculty'] ?? '';
            $department = $_POST['department'] ?? '';
            $grade = $_POST['grade'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $zipcode = $_POST['zipcode'] ?? '';
            $address = $_POST['address'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $birthdate = $_POST['birthdate'] ?? '';
            $other_circles = $_POST['other_circles'] ?? '';
            $allergies = $_POST['allergies'] ?? '';
            $notes = $_POST['notes'] ?? '';

            // Calculate admission_year from grade
            $admission_year = '';
            if ($grade) {
                $gen_num = (int)str_replace('th', '', $grade);
                if ($gen_num > 0) {
                    $admission_year = ($gen_num + 2010) . '年';
                }
            }

            if ($name && $grade) {
                // Use Prepared Statements to prevent SQL injection
                $stmt = $pdo->prepare("UPDATE users SET
                    name = ?, name_kana = ?, student_id = ?, grade = ?, faculty = ?,
                    department = ?, admission_year = ?, gender = ?, zipcode = ?, address = ?,
                    phone = ?, birthdate = ?, other_circles = ?, allergies = ?, notes = ?
                    WHERE id = ?");
                $stmt->execute([
                    $name, $name_kana, $sid, $grade, $faculty,
                    $department, $admission_year, $gender, $zipcode, $address,
                    $phone, empty($birthdate) ? null : $birthdate, $other_circles, $allergies, $notes,
                    $target_id
                ]);
            }
        }
    }

    // メンバー情報の変更を名簿スプシに自動反映（連携済みの場合のみ・失敗しても処理継続）
    syncMembersToSheetSafe($pdo);
}

// Fetch All Members
$stmt = $pdo->query("SELECT * FROM users ORDER BY grade ASC, name COLLATE utf8mb4_unicode_ci ASC");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count members by grade (only approved members)
$grade_counts = [];
$total_approved = 0;
$total_all = count($members);
foreach ($members as $m) {
    if ($m['is_approved']) {
        $total_approved++;
        $grade = $m['grade'] ?: '未設定';
        if (!isset($grade_counts[$grade])) {
            $grade_counts[$grade] = 0;
        }
        $grade_counts[$grade]++;
    }
}
ksort($grade_counts);

// フィルタ用の選択肢を実データから抽出
$distinct_grades = [];
$distinct_faculties = [];
foreach ($members as $m) {
    if (!empty($m['grade']))   $distinct_grades[$m['grade']] = true;
    if (!empty($m['faculty'])) $distinct_faculties[$m['faculty']] = true;
}
$distinct_grades = array_keys($distinct_grades);
usort($distinct_grades, function ($a, $b) { return (int)$a - (int)$b; });
$distinct_faculties = array_keys($distinct_faculties);
sort($distinct_faculties);

// 性別の日本語化ヘルパー
if (!function_exists('m_gender_ja')) {
    function m_gender_ja($g) {
        return $g === 'male' ? '男性' : ($g === 'female' ? '女性' : ($g === 'no_answer' ? '回答しない' : ''));
    }
}

// CSV出力用にメンバーデータをJSへ（このページは元々admin限定で同データを表示している）
$members_js = [];
foreach ($members as $m) {
    $members_js[] = [
        'id'             => (int)$m['id'],
        'name'           => $m['name'] ?? '',
        'name_kana'      => $m['name_kana'] ?? '',
        'student_id'     => $m['student_id'] ?? '',
        'grade'          => $m['grade'] ?? '',
        'admission_year' => $m['admission_year'] ?? '',
        'faculty'        => $m['faculty'] ?? '',
        'department'     => $m['department'] ?? '',
        'gender'         => m_gender_ja($m['gender'] ?? ''),
        'birthdate'      => $m['birthdate'] ?? '',
        'zipcode'        => $m['zipcode'] ?? '',
        'address'        => $m['address'] ?? '',
        'phone'          => $m['phone'] ?? '',
        'line_name'      => $m['line_name'] ?? '',
        'email'          => $m['email'] ?? '',
        'other_circles'  => $m['other_circles'] ?? '',
        'allergies'      => $m['allergies'] ?? '',
        'notes'          => $m['notes'] ?? '',
        'status'         => $m['is_approved'] ? '承認済' : '未承認',
        'role'           => $m['role'] === 'admin' ? '管理者' : '一般',
    ];
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="apple-touch-icon" href="../logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メンバー管理 | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ===== ツールバー（検索・フィルター・操作） ===== */
        .members-toolbar { display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center; margin-bottom: 1rem; }
        .members-toolbar .search-wrap { position: relative; flex: 1 1 240px; min-width: 200px; }
        .members-toolbar .search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted, #8d877c); font-size: 0.85rem; }
        .members-toolbar .search-wrap input { width: 100%; padding-left: 34px; }
        .members-toolbar select { width: auto; min-width: 96px; }
        .members-toolbar .tool-spacer { flex: 1 1 auto; }
        .members-count { font-size: 0.85rem; color: var(--text-muted, #8d877c); white-space: nowrap; }
        .btn-mini { padding: 0.45rem 0.9rem; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px; }

        /* ===== テーブル（スプレッドシート風） ===== */
        .table-responsive table { font-size: 0.85rem; line-height: 1.4; }
        .table-responsive th, .table-responsive td { white-space: nowrap; vertical-align: middle; padding: 0.55rem 0.7rem; }
        th.sortable { cursor: pointer; user-select: none; }
        th.sortable .sort-ind { opacity: 0.35; margin-left: 4px; font-size: 0.7rem; }
        th.sortable.sorted-asc .sort-ind, th.sortable.sorted-desc .sort-ind { opacity: 1; }

        /* 1列目（メンバー名）を横スクロール時に固定 */
        .table-responsive th.col-name, .table-responsive td.col-name {
            position: sticky; left: 0; z-index: 2; background: #ffffff;
            box-shadow: 1px 0 0 var(--border-color, #e6e2d9);
        }
        .table-responsive th.col-name { z-index: 3; background: #f2f0ea; }
        .member-id-cell { display: flex; align-items: center; gap: 8px; }
        .member-id-cell img, .member-id-cell .avatar-fallback {
            width: 30px; height: 30px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
        }
        .member-id-cell .avatar-fallback { background: #f2f0ea; display: flex; align-items: center; justify-content: center; color: #bdb8ad; font-size: 0.8rem; }
        .member-id-cell .name-main { font-weight: 600; line-height: 1.2; }
        .member-id-cell .name-kana { font-size: 0.72rem; color: var(--text-muted, #8d877c); line-height: 1.2; }

        .cell-muted { color: var(--text-muted, #8d877c); }
        .cell-clip { max-width: 200px; overflow: hidden; text-overflow: ellipsis; }

        /* 減彩バッジ（モノトーン） */
        .tag { display: inline-block; padding: 2px 9px; border-radius: 11px; font-size: 0.72rem; font-weight: 500; white-space: nowrap; }
        .tag-ok { background: #ecf2ed; color: #3f7d54; }
        .tag-pending { background: #f4eedd; color: #a8762e; }
        .tag-admin { background: #1a1a1a; color: #fff; }
        .tag-member { background: #f2f0ea; color: #6b6b6b; }

        .row-actions { display: flex; gap: 5px; flex-wrap: nowrap; align-items: center; }
        .row-actions .btn-secondary, .row-actions .btn-primary, .row-actions .btn-danger { padding: 0.28rem 0.7rem; font-size: 0.76rem; }
        .row-actions select.form-select { padding: 0.28rem; width: auto; font-size: 0.78rem; }

        .empty-row td { text-align: center; color: var(--text-muted, #8d877c); padding: 2rem; }

        /* コンパクト表示（詳細列を隠す） */
        body.compact .col-detail { display: none; }

        /* ===== 編集モーダル ===== */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(26,26,26,0.45); z-index: 2000;
            display: none; align-items: center; justify-content: center;
        }
        .modal-content {
            background: white; padding: 2rem; border-radius: 8px; width: 90%; max-width: 600px;
            max-height: 90vh; overflow-y: auto;
            box-shadow: 0 10px 40px rgba(26,26,26,0.12); border: 1px solid var(--border-color, #e6e2d9);
        }
        .edit-section { background: #f2f0ea; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .edit-section-title { font-size: 1rem; margin-bottom: 0.8rem; border-bottom: 1px solid var(--border-color, #e6e2d9); padding-bottom: 0.4rem; }

        @media (max-width: 700px) {
            .members-toolbar select { flex: 1 1 calc(50% - 0.3rem); min-width: 0; }
        }
    </style>
    <script>
        function confirmAction(message) { return confirm(message); }

        // Modal Logic
        function openEditModal(userObj) {
            document.getElementById('edit_user_id').value = userObj.id || '';
            document.getElementById('edit_name').value = userObj.name || '';
            document.getElementById('edit_name_kana').value = userObj.name_kana || '';
            document.getElementById('edit_sid').value = userObj.student_id || '';
            document.getElementById('edit_faculty').value = userObj.faculty || '';
            document.getElementById('edit_department').value = userObj.department || '';
            document.getElementById('edit_grade').value = userObj.grade || '';
            document.getElementById('edit_gender').value = userObj.gender || '';
            document.getElementById('edit_zipcode').value = userObj.zipcode || '';
            document.getElementById('edit_address').value = userObj.address || '';
            document.getElementById('edit_phone').value = userObj.phone || '';
            document.getElementById('edit_birthdate').value = userObj.birthdate || '';
            document.getElementById('edit_other_circles').value = userObj.other_circles || '';
            document.getElementById('edit_allergies').value = userObj.allergies || '';
            document.getElementById('edit_notes').value = userObj.notes || '';
            document.getElementById('editModal').style.display = 'flex';
        }
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
    </script>
    <link rel="stylesheet" href="../member.css?v=<?php echo @filemtime(__DIR__ . '/../member.css') ?: '1'; ?>">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="../dashboard.php" class="logo" style="font-size: 1rem; font-weight: 500; display: flex; align-items: center;">
                <i class="fas fa-chevron-left" style="margin-right: 8px; font-size: 0.8rem;"></i> 一覧に戻る
            </a>
        </div>
    </header>

    <main>
        <div class="dashboard-container" style="max-width: 1280px;">

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 10px;">
                <h1 style="margin: 0;">メンバー管理</h1>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" id="csvBtn" class="btn-secondary btn-mini">
                        <i class="fas fa-download"></i> CSV出力
                    </button>
                    <a href="members_export_sheet.php" class="btn-primary btn-mini">
                        <i class="fas fa-file-excel"></i> シートに出力
                    </a>
                </div>
            </div>

            <!-- Member Statistics -->
            <div style="margin-bottom: 1rem; padding: 0.8rem 1rem; background: #f2f0ea; border-radius: 8px; display: flex; flex-wrap: wrap; gap: 0.8rem; align-items: center; font-size: 0.9rem;">
                <span style="font-weight: 600;"><i class="fas fa-users" style="margin-right:5px;"></i><?php echo $total_approved; ?>名</span>
                <span class="cell-muted">|</span>
                <?php foreach ($grade_counts as $grade => $count): ?>
                    <span class="cell-muted"><?php echo htmlspecialchars($grade); ?>: <?php echo $count; ?></span>
                <?php endforeach; ?>
                <?php if ($total_all > $total_approved): ?>
                    <span class="cell-muted">|</span>
                    <span style="color: #a8762e;">未承認: <?php echo $total_all - $total_approved; ?></span>
                <?php endif; ?>
            </div>

            <!-- Toolbar: 検索・フィルター -->
            <div class="members-toolbar">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" class="form-input" placeholder="名前・ふりがな・学籍番号・LINE名・学部・住所・電話で検索">
                </div>
                <select id="filterGrade" class="form-select">
                    <option value="">代（全て）</option>
                    <?php foreach ($distinct_grades as $g): ?>
                        <option value="<?php echo htmlspecialchars($g); ?>"><?php echo htmlspecialchars($g); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterFaculty" class="form-select">
                    <option value="">学部（全て）</option>
                    <?php foreach ($distinct_faculties as $f): ?>
                        <option value="<?php echo htmlspecialchars($f); ?>"><?php echo htmlspecialchars($f); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterGender" class="form-select">
                    <option value="">性別（全て）</option>
                    <option value="male">男性</option>
                    <option value="female">女性</option>
                    <option value="no_answer">回答しない</option>
                </select>
                <select id="filterStatus" class="form-select">
                    <option value="">状態（全て）</option>
                    <option value="1">承認済</option>
                    <option value="0">未承認</option>
                </select>
                <select id="filterRole" class="form-select">
                    <option value="">権限（全て）</option>
                    <option value="admin">管理者</option>
                    <option value="member">一般</option>
                </select>
                <button type="button" id="resetBtn" class="btn-secondary btn-mini"><i class="fas fa-rotate-left"></i> リセット</button>
                <button type="button" id="compactBtn" class="btn-secondary btn-mini"><i class="fas fa-compress"></i> コンパクト</button>
                <span class="tool-spacer"></span>
                <span class="members-count" id="memberCount"></span>
            </div>

            <div class="card" style="padding: 0;">
                <div class="table-responsive">
                    <table id="membersTable">
                        <thead>
                            <tr>
                                <th class="col-name sortable" data-type="text">メンバー<span class="sort-ind"><i class="fas fa-sort"></i></span></th>
                                <th class="sortable" data-type="text">学籍番号<span class="sort-ind"><i class="fas fa-sort"></i></span></th>
                                <th class="sortable" data-type="text">LINE名<span class="sort-ind"><i class="fas fa-sort"></i></span></th>
                                <th class="sortable" data-type="num">代<span class="sort-ind"><i class="fas fa-sort"></i></span></th>
                                <th class="sortable col-detail" data-type="num">卒業予定<span class="sort-ind"><i class="fas fa-sort"></i></span></th>
                                <th class="sortable" data-type="text">学部<span class="sort-ind"><i class="fas fa-sort"></i></span></th>
                                <th class="sortable col-detail" data-type="text">学科<span class="sort-ind"><i class="fas fa-sort"></i></span></th>
                                <th class="sortable" data-type="text">性別<span class="sort-ind"><i class="fas fa-sort"></i></span></th>
                                <th class="sortable col-detail" data-type="date">生年月日<span class="sort-ind"><i class="fas fa-sort"></i></span></th>
                                <th class="col-detail">郵便番号</th>
                                <th class="col-detail">住所</th>
                                <th class="col-detail">電話</th>
                                <th class="col-detail">他サークル</th>
                                <th class="col-detail">アレルギー</th>
                                <th class="col-detail">備考</th>
                                <th class="sortable" data-type="num">状態<span class="sort-ind"><i class="fas fa-sort"></i></span></th>
                                <th class="sortable" data-type="text">権限<span class="sort-ind"><i class="fas fa-sort"></i></span></th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="membersBody">
                            <?php foreach ($members as $m): ?>
                                <?php
                                    $search_blob = mb_strtolower(implode(' ', array_filter([
                                        $m['name'] ?? '', $m['name_kana'] ?? '', $m['student_id'] ?? '',
                                        $m['line_name'] ?? '', $m['faculty'] ?? '', $m['department'] ?? '',
                                        $m['address'] ?? '', $m['phone'] ?? '', $m['other_circles'] ?? '',
                                    ])));
                                    $grade_num = (int)preg_replace('/\D/', '', $m['grade'] ?? '');
                                    $grad_num  = (int)preg_replace('/\D/', '', $m['admission_year'] ?? '');
                                ?>
                                <tr data-id="<?php echo (int)$m['id']; ?>"
                                    data-grade="<?php echo htmlspecialchars($m['grade'] ?? ''); ?>"
                                    data-faculty="<?php echo htmlspecialchars($m['faculty'] ?? ''); ?>"
                                    data-gender="<?php echo htmlspecialchars($m['gender'] ?? ''); ?>"
                                    data-approved="<?php echo $m['is_approved'] ? '1' : '0'; ?>"
                                    data-role="<?php echo htmlspecialchars($m['role'] ?? ''); ?>"
                                    data-search="<?php echo htmlspecialchars($search_blob); ?>">
                                    <td class="col-name">
                                        <div class="member-id-cell">
                                            <?php if (!empty($m['avatar_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($m['avatar_url']); ?>" alt="">
                                            <?php else: ?>
                                                <div class="avatar-fallback"><i class="fas fa-user"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="name-main" data-sort="<?php echo htmlspecialchars($m['name_kana'] ?: $m['name']); ?>"><?php echo htmlspecialchars($m['name']); ?></div>
                                                <div class="name-kana"><?php echo htmlspecialchars($m['name_kana'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($m['student_id'] ?? ''); ?></td>
                                    <td class="cell-muted"><?php echo htmlspecialchars($m['line_name'] ?? ''); ?></td>
                                    <td data-sort="<?php echo $grade_num; ?>"><?php echo htmlspecialchars($m['grade'] ?? ''); ?></td>
                                    <td class="col-detail" data-sort="<?php echo $grad_num; ?>"><?php echo htmlspecialchars($m['admission_year'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($m['faculty'] ?? ''); ?></td>
                                    <td class="col-detail"><?php echo htmlspecialchars($m['department'] ?? ''); ?></td>
                                    <td data-sort="<?php echo htmlspecialchars($m['gender'] ?? ''); ?>"><?php echo htmlspecialchars(m_gender_ja($m['gender'] ?? '')) ?: '<span class="cell-muted">-</span>'; ?></td>
                                    <td class="col-detail"><?php echo htmlspecialchars($m['birthdate'] ?? ''); ?></td>
                                    <td class="col-detail"><?php echo htmlspecialchars($m['zipcode'] ?? ''); ?></td>
                                    <td class="col-detail cell-clip" title="<?php echo htmlspecialchars($m['address'] ?? ''); ?>"><?php echo htmlspecialchars($m['address'] ?? ''); ?></td>
                                    <td class="col-detail"><?php echo htmlspecialchars($m['phone'] ?? ''); ?></td>
                                    <td class="col-detail"><?php echo htmlspecialchars($m['other_circles'] ?? ''); ?></td>
                                    <td class="col-detail cell-clip" title="<?php echo htmlspecialchars($m['allergies'] ?? ''); ?>"><?php echo htmlspecialchars($m['allergies'] ?? ''); ?></td>
                                    <td class="col-detail cell-clip" title="<?php echo htmlspecialchars($m['notes'] ?? ''); ?>"><?php echo htmlspecialchars($m['notes'] ?? ''); ?></td>
                                    <td data-sort="<?php echo $m['is_approved'] ? '1' : '0'; ?>">
                                        <?php if ($m['is_approved']): ?>
                                            <span class="tag tag-ok">承認済</span>
                                        <?php else: ?>
                                            <span class="tag tag-pending">未承認</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-sort="<?php echo $m['role'] === 'admin' ? '0' : '1'; ?>">
                                        <?php if ($m['role'] === 'admin'): ?>
                                            <span class="tag tag-admin">管理者</span>
                                        <?php else: ?>
                                            <span class="tag tag-member">一般</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($m['id'] != $_SESSION['user_id']): ?>
                                            <div class="row-actions">
                                                <?php
                                                    $userJson = json_encode([
                                                        'id' => $m['id'],
                                                        'name' => $m['name'],
                                                        'name_kana' => $m['name_kana'] ?? '',
                                                        'student_id' => $m['student_id'],
                                                        'grade' => $m['grade'],
                                                        'faculty' => $m['faculty'] ?? '',
                                                        'department' => $m['department'] ?? '',
                                                        'admission_year' => $m['admission_year'] ?? '',
                                                        'gender' => $m['gender'] ?? '',
                                                        'zipcode' => $m['zipcode'] ?? '',
                                                        'address' => $m['address'] ?? '',
                                                        'phone' => $m['phone'] ?? '',
                                                        'birthdate' => $m['birthdate'] ?? '',
                                                        'other_circles' => $m['other_circles'] ?? '',
                                                        'allergies' => $m['allergies'] ?? '',
                                                        'notes' => $m['notes'] ?? ''
                                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                                ?>
                                                <button type="button" class="btn-secondary"
                                                    onclick='openEditModal(<?php echo $userJson; ?>)'>編集</button>

                                                <?php if (!$m['is_approved']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn-primary">承認</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirmAction('本当に承認を取り消しますか？');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                                        <input type="hidden" name="action" value="disapprove">
                                                        <button type="submit" class="btn-secondary">取消</button>
                                                    </form>
                                                <?php endif; ?>

                                                <form method="POST" style="display: inline;" onsubmit="return confirmAction('本当にこのユーザーを削除しますか？\nこの操作は取り消せません。');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn-danger">削除</button>
                                                </form>

                                                <?php if ($m['is_approved']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                                        <input type="hidden" name="action" value="update_role">
                                                        <select name="role" class="form-select" onchange="this.form.submit()">
                                                            <option value="member" <?php echo $m['role'] === 'member' ? 'selected' : ''; ?>>一般</option>
                                                            <option value="admin" <?php echo $m['role'] === 'admin' ? 'selected' : ''; ?>>管理者</option>
                                                        </select>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="cell-muted">(自分)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="empty-row" id="emptyRow" style="display: none;">
                                <td colspan="18">条件に一致するメンバーがいません。</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay" onclick="if(event.target === this) closeEditModal()">
        <div class="modal-content">
            <h2 style="margin-bottom: 1.5rem; text-align: center;">メンバー情報を編集</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="user_id" id="edit_user_id">
                <input type="hidden" name="action" value="update_profile">

                <div class="edit-section">
                    <div class="edit-section-title">基本情報</div>
                    <div class="form-group">
                        <label class="form-label">名前</label>
                        <input type="text" name="name" id="edit_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ふりがな</label>
                        <input type="text" name="name_kana" id="edit_name_kana" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">生年月日</label>
                        <input type="date" name="birthdate" id="edit_birthdate" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">性別</label>
                        <select name="gender" id="edit_gender" class="form-select">
                            <option value="">選択してください</option>
                            <option value="male">男性</option>
                            <option value="female">女性</option>
                            <option value="no_answer">回答しない</option>
                        </select>
                    </div>
                </div>

                <div class="edit-section">
                    <div class="edit-section-title">大学情報</div>
                    <div class="form-group">
                        <label class="form-label">代</label>
                        <select name="grade" id="edit_grade" class="form-select" required>
                            <option value="">選択してください</option>
                            <?php
                            $cy = (int)date('Y');
                            $cm = (int)date('n');
                            $fy = ($cm >= 4) ? $cy : $cy - 1;
                            $ng = 20 + ($fy - 2026);
                            $ming = $ng - 3;
                            $maxg = $ng + 1;
                            for ($g = $ming; $g <= $maxg; $g++) {
                                echo '<option value="' . $g . 'th">' . $g . 'th</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">学部</label>
                        <select name="faculty" id="edit_faculty" class="form-select">
                            <option value="">選択してください</option>
                            <?php
                            $waseda_faculties = ['政治経済学部','法学部','教育学部','商学部','社会科学部','国際教養学部','文化構想学部','文学部','基幹理工学部','創造理工学部','先進理工学部','人間科学部','スポーツ科学部'];
                            foreach ($waseda_faculties as $f): ?>
                                <option value="<?php echo htmlspecialchars($f); ?>"><?php echo htmlspecialchars($f); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">学科</label>
                        <input type="text" name="department" id="edit_department" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">学籍番号</label>
                        <input type="text" name="student_id" id="edit_sid" class="form-input">
                    </div>
                </div>

                <div class="edit-section">
                    <div class="edit-section-title">連絡先・その他</div>
                    <div class="form-group">
                        <label class="form-label">郵便番号</label>
                        <div style="position: relative;">
                            <input type="text" name="zipcode" id="edit_zipcode" class="form-input">
                            <span id="edit-zipcode-loading" style="display:none; position:absolute; right:10px; top:50%; transform:translateY(-50%); color:#999; font-size:0.85rem;"><i class="fas fa-spinner fa-spin"></i> 検索中...</span>
                        </div>
                        <p id="edit-zipcode-error" style="font-size:0.8rem; color:#b0453a; margin-top:0.3rem; display:none;"></p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">住所</label>
                        <input type="text" name="address" id="edit_address" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">携帯電話番号</label>
                        <input type="text" name="phone" id="edit_phone" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">他サークル</label>
                        <input type="text" name="other_circles" id="edit_other_circles" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">アレルギー</label>
                        <textarea name="allergies" id="edit_allergies" class="form-input" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">その他</label>
                        <textarea name="notes" id="edit_notes" class="form-input" rows="2"></textarea>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 1.5rem;">
                    <button type="button" class="btn-secondary" onclick="closeEditModal()" style="flex: 1;">キャンセル</button>
                    <button type="submit" class="btn-primary" style="flex: 1;">更新</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const MEMBERS = <?php echo json_encode($members_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;

    // ===== 検索・フィルター・ソート・CSV =====
    (function() {
        const tbody = document.getElementById('membersBody');
        const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.classList.contains('empty-row'));
        const emptyRow = document.getElementById('emptyRow');
        const searchInput = document.getElementById('searchInput');
        const fGrade = document.getElementById('filterGrade');
        const fFaculty = document.getElementById('filterFaculty');
        const fGender = document.getElementById('filterGender');
        const fStatus = document.getElementById('filterStatus');
        const fRole = document.getElementById('filterRole');
        const countEl = document.getElementById('memberCount');

        function applyFilter() {
            const q = (searchInput.value || '').trim().toLowerCase();
            const g = fGrade.value, fac = fFaculty.value, gen = fGender.value, st = fStatus.value, ro = fRole.value;
            let visible = 0;
            rows.forEach(r => {
                let show = true;
                if (q && !(r.dataset.search || '').includes(q)) show = false;
                if (g && r.dataset.grade !== g) show = false;
                if (fac && r.dataset.faculty !== fac) show = false;
                if (gen && r.dataset.gender !== gen) show = false;
                if (st && r.dataset.approved !== st) show = false;
                if (ro && r.dataset.role !== ro) show = false;
                r.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            emptyRow.style.display = visible === 0 ? '' : 'none';
            countEl.textContent = '表示 ' + visible + ' / ' + rows.length + ' 名';
        }

        // ソート
        const table = document.getElementById('membersTable');
        const headers = Array.from(table.querySelectorAll('th'));
        headers.forEach((th, idx) => {
            if (!th.classList.contains('sortable')) return;
            th.addEventListener('click', () => {
                const type = th.dataset.type || 'text';
                const asc = !(th.classList.contains('sorted-asc'));
                headers.forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));
                th.classList.add(asc ? 'sorted-asc' : 'sorted-desc');
                const indEl = th.querySelector('.sort-ind i');
                headers.forEach(h => { const i = h.querySelector('.sort-ind i'); if (i) i.className = 'fas fa-sort'; });
                if (indEl) indEl.className = asc ? 'fas fa-sort-up' : 'fas fa-sort-down';

                const getKey = (row) => {
                    const cell = row.children[idx];
                    if (!cell) return '';
                    const raw = cell.dataset.sort !== undefined ? cell.dataset.sort
                              : (cell.querySelector('[data-sort]') ? cell.querySelector('[data-sort]').dataset.sort : cell.textContent.trim());
                    return raw;
                };
                const sorted = rows.slice().sort((a, b) => {
                    let ka = getKey(a), kb = getKey(b);
                    if (type === 'num') { ka = parseFloat(ka) || 0; kb = parseFloat(kb) || 0; return asc ? ka - kb : kb - ka; }
                    return asc ? String(ka).localeCompare(String(kb), 'ja') : String(kb).localeCompare(String(ka), 'ja');
                });
                sorted.forEach(r => tbody.insertBefore(r, emptyRow));
            });
        });

        // CSV出力（現在の絞り込み結果を対象。Excel/スプシ用にBOM付きUTF-8）
        function visibleIds() {
            return rows.filter(r => r.style.display !== 'none').map(r => parseInt(r.dataset.id, 10));
        }
        document.getElementById('csvBtn').addEventListener('click', () => {
            const ids = visibleIds();
            const byId = {}; MEMBERS.forEach(m => byId[m.id] = m);
            const headerRow = ['ID','名前','ふりがな','学籍番号','代','卒業予定年','学部','学科','性別','生年月日','郵便番号','住所','電話番号','LINE名','メールアドレス','他サークル','アレルギー等','備考','ステータス','権限'];
            const keys = ['id','name','name_kana','student_id','grade','admission_year','faculty','department','gender','birthdate','zipcode','address','phone','line_name','email','other_circles','allergies','notes','status','role'];
            const esc = v => {
                let s = (v === null || v === undefined) ? '' : String(v);
                // CSV数式インジェクション対策: 先頭が = + - @ TAB CR のセルは ' を前置し、
                // Excel/スプレッドシートで開いた際に数式として評価されないようにする（値は壊さず出力時のみ無害化）
                if (/^[=+\-@\t\r]/.test(s)) s = "'" + s;
                return '"' + s.replace(/"/g, '""') + '"';
            };
            const lines = [headerRow.map(esc).join(',')];
            ids.forEach(id => {
                const m = byId[id]; if (!m) return;
                lines.push(keys.map(k => esc(m[k])).join(','));
            });
            const csv = '﻿' + lines.join('\r\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            const d = new Date();
            const stamp = d.getFullYear() + ('0'+(d.getMonth()+1)).slice(-2) + ('0'+d.getDate()).slice(-2);
            a.href = url; a.download = 'whabitat_members_' + stamp + '.csv';
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });

        // リセット・コンパクト
        document.getElementById('resetBtn').addEventListener('click', () => {
            searchInput.value = ''; fGrade.value = ''; fFaculty.value = ''; fGender.value = ''; fStatus.value = ''; fRole.value = '';
            applyFilter();
        });
        document.getElementById('compactBtn').addEventListener('click', function() {
            document.body.classList.toggle('compact');
            const on = document.body.classList.contains('compact');
            this.innerHTML = on ? '<i class="fas fa-expand"></i> 全項目' : '<i class="fas fa-compress"></i> コンパクト';
        });

        [searchInput, fGrade, fFaculty, fGender, fStatus, fRole].forEach(el => {
            el.addEventListener('input', applyFilter);
            el.addEventListener('change', applyFilter);
        });
        applyFilter();
    })();

    // ===== 郵便番号 → 住所 オートフィル（zipcloud JSONP） =====
    (function() {
        const zipcodeInput = document.getElementById('edit_zipcode');
        const addressInput = document.getElementById('edit_address');
        const loadingEl = document.getElementById('edit-zipcode-loading');
        const errorEl = document.getElementById('edit-zipcode-error');
        let debounceTimer = null;

        function lookupZipcode(zipcode) {
            const cleaned = zipcode.replace(/[^0-9]/g, '');
            if (cleaned.length !== 7) return;
            loadingEl.style.display = 'inline';
            errorEl.style.display = 'none';
            const callbackName = '_zipCallback_' + Date.now();
            const script = document.createElement('script');
            script.src = 'https://zipcloud.ibsnet.co.jp/api/get?zipcode=' + cleaned + '&callback=' + callbackName;
            window[callbackName] = function(data) {
                loadingEl.style.display = 'none';
                if (data.status === 200 && data.results && data.results.length > 0) {
                    const result = data.results[0];
                    addressInput.value = result.address1 + result.address2 + result.address3;
                    addressInput.focus();
                    errorEl.style.display = 'none';
                } else {
                    errorEl.textContent = '該当する住所が見つかりませんでした';
                    errorEl.style.display = 'block';
                }
                delete window[callbackName];
                if (script.parentNode) script.parentNode.removeChild(script);
            };
            script.onerror = function() {
                loadingEl.style.display = 'none';
                errorEl.textContent = '住所の検索に失敗しました';
                errorEl.style.display = 'block';
                delete window[callbackName];
                if (script.parentNode) script.parentNode.removeChild(script);
            };
            document.body.appendChild(script);
        }

        zipcodeInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() { lookupZipcode(zipcodeInput.value); }, 500);
        });
    })();
    </script>
</body>
</html>
