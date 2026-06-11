<?php
require_once '../config.php';
requireLogin();

// Admin only
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();
$error = '';
$success = '';
$edit_blog = null;

/**
 * アップロードディレクトリのPHP実行禁止 .htaccess を安全な形式で常に上書きする。
 * 注意: 裸の php_flag は PHP-FPM/FastCGI 環境で「Invalid command」となり
 * ディレクトリ全体が500になるため、必ず IfModule で囲む（毎回上書き＝自己修復）。
 */
function ensureUploadHtaccess($upload_dir) {
    $safe = "# Disable PHP execution in this upload directory.\n"
        . "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_php7.c>\n    php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_php5.c>\n    php_flag engine off\n</IfModule>\n\n"
        . "<FilesMatch \"(?i)\\.(php|php3|php4|php5|php7|phtml|pht|phar)$\">\n    Require all denied\n</FilesMatch>\n";
    $path = $upload_dir . '.htaccess';
    if (@file_get_contents($path) !== $safe) {
        @file_put_contents($path, $safe);
    }
}

/**
 * 画像バイナリを長辺1600pxに縮小して再圧縮する（スマホ写真の数MB対策）。
 * - GIFはアニメーションを壊すため無加工
 * - GD未導入・デコード失敗時は元データをそのまま返す
 */
function resizeImageData($data, $imagetype) {
    if ($imagetype === IMAGETYPE_GIF || !function_exists('imagecreatefromstring')) return $data;

    $src = @imagecreatefromstring($data);
    if (!$src) return $data;

    $w = imagesx($src);
    $h = imagesy($src);
    $max = 1600;
    $needResize = max($w, $h) > $max;

    // 小さい画像でも500KB超のJPEGは再圧縮の価値あり
    if (!$needResize && !($imagetype === IMAGETYPE_JPEG && strlen($data) > 500 * 1024)) {
        return $data;
    }

    if ($needResize) {
        $scale = $max / max($w, $h);
        // IMG_BICUBIC はアルファ付き画像で false を返すことがあるため既定補間を使う
        $dst = imagescale($src, (int)round($w * $scale), (int)round($h * $scale));
        if ($dst) $src = $dst;
    }

    ob_start();
    $ok = false;
    switch ($imagetype) {
        case IMAGETYPE_PNG:
            imagesavealpha($src, true);
            $ok = imagepng($src, null, 8);
            break;
        case IMAGETYPE_WEBP:
            $ok = function_exists('imagewebp') ? imagewebp($src, null, 80) : false;
            break;
        default: // JPEG
            $ok = imagejpeg($src, null, 80);
    }
    $out = ob_get_clean();

    if (!$ok || $out === false || $out === '') return $data;
    // 縮小した場合は寸法削減を優先して採用（フラットなPNG等でバイト数が微増しても可）。
    // 再圧縮のみの場合は小さくなった時だけ採用。
    if ($needResize) {
        return strlen($out) < strlen($data) * 1.5 ? $out : $data;
    }
    return strlen($out) < strlen($data) ? $out : $data;
}

/**
 * 本文内の base64 画像（Quill画像ボタン/ドラッグ&ドロップが埋め込む形式）を
 * uploads/blog/ のファイルに変換し、URL参照へ書き換える。
 * 表示側(blog_view.php)は XSS対策で data: スキームを無効化するため、
 * base64のまま保存すると画像が表示されない。
 */
function convertInlineImagesToFiles($content) {
    $upload_dir = __DIR__ . '/../uploads/blog/';
    return preg_replace_callback(
        '/<img([^>]*?)src\s*=\s*(["\'])data:image\/(jpeg|jpg|png|gif|webp);base64,([^"\']+)\2([^>]*)>/i',
        function ($m) use ($upload_dir) {
            $data = base64_decode($m[4], true);
            if ($data === false) return ''; // 壊れたデータは除去

            // 実際の画像形式を検証（宣言された形式は信用しない）
            $allowed_ext = [
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_GIF  => 'gif',
                IMAGETYPE_WEBP => 'webp',
            ];
            $info = @getimagesizefromstring($data);
            $type = $info[2] ?? null;
            if (!$type || !isset($allowed_ext[$type])) return '';

            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            ensureUploadHtaccess($upload_dir);

            $data = resizeImageData($data, $type);
            $filename = 'blog_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed_ext[$type];
            if (@file_put_contents($upload_dir . $filename, $data) === false) return '';

            return '<img' . $m[1] . 'src="uploads/blog/' . $filename . '"' . $m[5] . '>';
        },
        $content
    );
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? '');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $thumbnail = $_POST['thumbnail'] ?? '';
        $is_published = isset($_POST['is_published']) ? 1 : 0;

        // base64埋め込み画像をファイル化（過去記事の再保存でも修復される）
        $content = convertInlineImagesToFiles($content);
        
        // Handle file upload
        if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['thumbnail_file'];
            // Verify the actual image type (do not trust client-declared MIME / filename)
            $allowed_ext = [
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_GIF  => 'gif',
                IMAGETYPE_WEBP => 'webp',
            ];
            $image_info = @getimagesize($file['tmp_name']);
            $detected_type = $image_info[2] ?? null;

            if (!$detected_type || !isset($allowed_ext[$detected_type])) {
                $error = '画像形式はJPEG, PNG, GIF, WebPのみ対応です。';
            } else {
                $ext = $allowed_ext[$detected_type];
                $filename = 'blog_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $upload_dir = '../uploads/blog/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                ensureUploadHtaccess($upload_dir);
                $data = file_get_contents($file['tmp_name']);
                $data = resizeImageData($data, $detected_type);
                if ($data !== false && @file_put_contents($upload_dir . $filename, $data) !== false) {
                    $thumbnail = 'uploads/blog/' . $filename;
                }
            }
        }
        
        if (!$error && $title && $content) {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO blogs (title, content, thumbnail, author_id, is_published) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $content, $thumbnail ?: null, $_SESSION['user_id'], $is_published])) {
                    $success = '記事を投稿しました！';
                } else {
                    $error = 'エラーが発生しました。';
                }
            } else {
                $blog_id = $_POST['blog_id'] ?? 0;
                if (empty($thumbnail)) {
                    $stmt = $pdo->prepare("SELECT thumbnail FROM blogs WHERE id = ?");
                    $stmt->execute([$blog_id]);
                    $existing = $stmt->fetch();
                    $thumbnail = $existing['thumbnail'] ?? '';
                }
                $stmt = $pdo->prepare("UPDATE blogs SET title = ?, content = ?, thumbnail = ?, is_published = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$title, $content, $thumbnail ?: null, $is_published, $blog_id])) {
                    $success = '記事を更新しました！';
                } else {
                    $error = 'エラーが発生しました。';
                }
            }
        } elseif (!$error) {
            $error = 'タイトルと本文を入力してください。';
        }
    } elseif ($action === 'delete') {
        $blog_id = $_POST['blog_id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM blogs WHERE id = ?");
        $stmt->execute([$blog_id]);
        $success = '記事を削除しました。';
    }
}

// Check for edit mode
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM blogs WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_blog = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch existing blogs
$blogs = [];
try {
    $stmt = $pdo->query("SELECT b.*, u.name as author_name FROM blogs b LEFT JOIN users u ON b.author_id = u.id ORDER BY b.created_at DESC");
    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ブログ管理 | WHABITAT</title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Quill Editor -->
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <style>
        .ql-editor {
            min-height: 300px;
            font-size: 1rem;
            line-height: 1.8;
        }
        .ql-container {
            border-radius: 0 0 8px 8px;
            font-family: inherit;
        }
        .ql-toolbar {
            border-radius: 8px 8px 0 0;
            background: #f8f9fa;
        }
        .blog-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        .blog-item:last-child { border-bottom: none; }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-published { background: #d4edda; color: #155724; }
        .status-draft { background: #fff3cd; color: #856404; }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .checkbox-label input { width: 16px; height: 16px; }
    </style>
    <link rel="stylesheet" href="../member.css?v=<?php echo @filemtime(__DIR__ . '/../member.css') ?: '1'; ?>">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="../blog.php" class="logo">
                <img src="../logo.png" alt="WHABITAT" height="50">
            </a>
        </div>
    </header>

    <main>
        <div class="dashboard-container" style="max-width: 900px;">
            <a href="../blog.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-color); text-decoration: none; font-weight: 500; margin-bottom: 1.5rem;">
                <i class="fas fa-chevron-left"></i> ブログに戻る
            </a>
            
            <div class="card" style="text-align: center; margin-bottom: 2rem;">
                <h1 style="font-size: 1.5rem; margin: 0;">
                    <i class="fas fa-newspaper" style="margin-right: 8px;"></i>ブログ管理
                </h1>
            </div>
            
            <?php if ($error): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Editor Card -->
            <div class="card" style="margin-bottom: 2rem;">
                <h2 style="font-size: 1.1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-pen" style="color: var(--primary-color);"></i>
                    <?php echo $edit_blog ? '記事を編集' : '新しい記事を書く'; ?>
                </h2>
                
                <form method="POST" enctype="multipart/form-data" id="blogForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="<?php echo $edit_blog ? 'update' : 'create'; ?>">
                    <input type="hidden" name="content" id="contentHidden">
                    <?php if ($edit_blog): ?>
                        <input type="hidden" name="blog_id" value="<?php echo $edit_blog['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">タイトル</label>
                        <input type="text" name="title" class="form-input" required 
                               placeholder="記事のタイトル"
                               value="<?php echo htmlspecialchars($edit_blog['title'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">サムネイル画像</label>
                        <?php if (!empty($edit_blog['thumbnail'])): ?>
                            <div style="margin-bottom: 10px;">
                                <img src="../<?php echo htmlspecialchars($edit_blog['thumbnail']); ?>" 
                                     style="max-width: 200px; border-radius: 8px;">
                                <p style="font-size: 0.8rem; color: #888; margin-top: 5px;">現在のサムネイル</p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Drag & Drop Area -->
                        <div id="dropZone" style="border: 2px dashed #ddd; border-radius: 8px; padding: 2rem; text-align: center; margin-bottom: 1rem; cursor: pointer; transition: all 0.2s;">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #aaa; margin-bottom: 0.5rem;"></i>
                            <p style="color: #666; margin: 0;">ドラッグ＆ドロップ または クリックして画像を選択</p>
                            <input type="file" name="thumbnail_file" id="fileInput" accept="image/*" style="display: none;">
                        </div>
                        <div id="previewNew" style="display: none; margin-bottom: 1rem;">
                            <img id="previewImg" style="max-width: 200px; border-radius: 8px;">
                            <p style="font-size: 0.8rem; color: #888; margin-top: 5px;">選択した画像</p>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <label style="font-size: 0.85rem; color: #666; display: block; margin-bottom: 5px;">または画像アドレスを入力</label>
                            <input type="text" name="thumbnail" id="thumbnailUrl" class="form-input" placeholder="https://example.com/image.jpg">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">本文</label>
                        <div id="editor"><?php echo $edit_blog['content'] ?? ''; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_published" <?php echo ($edit_blog['is_published'] ?? true) ? 'checked' : ''; ?>>
                            公開する（チェックを外すと下書き保存）
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <?php if ($edit_blog): ?>
                            <a href="blog.php" class="btn-secondary" style="flex: 1; text-align: center;">キャンセル</a>
                        <?php endif; ?>
                        <button type="submit" class="btn-primary" style="flex: 2;">
                            <?php echo $edit_blog ? '更新する' : '投稿する'; ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Blog List -->
            <div class="card">
                <h2 style="font-size: 1.1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-list" style="color: var(--primary-color);"></i>
                    投稿済み記事
                </h2>
                
                <?php if (empty($blogs)): ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-light);">
                        まだ記事がありません
                    </div>
                <?php else: ?>
                    <?php foreach ($blogs as $blog): ?>
                        <div class="blog-item">
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 600; margin-bottom: 4px;"><?php echo htmlspecialchars($blog['title']); ?></div>
                                <div style="font-size: 0.8rem; color: #888; display: flex; gap: 10px; flex-wrap: wrap;">
                                    <span><?php echo date('Y/m/d', strtotime($blog['created_at'])); ?></span>
                                    <span class="status-badge <?php echo $blog['is_published'] ? 'status-published' : 'status-draft'; ?>">
                                        <?php echo $blog['is_published'] ? '公開' : '下書き'; ?>
                                    </span>
                                </div>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <a href="../blog_view.php?id=<?php echo $blog['id']; ?>" target="_blank" 
                                   class="btn-secondary" style="padding: 6px 10px; font-size: 0.75rem;">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?edit=<?php echo $blog['id']; ?>" 
                                   class="btn-secondary" style="padding: 6px 10px; font-size: 0.75rem;">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('削除しますか？');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="blog_id" value="<?php echo $blog['id']; ?>">
                                    <button type="submit" class="btn-danger" style="padding: 6px 10px; font-size: 0.75rem;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
    <script>
        var quill = new Quill('#editor', {
            theme: 'snow',
            placeholder: 'ここに本文を入力...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ 'align': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['blockquote'],
                    ['link', 'image'],
                    ['clean']
                ]
            }
        });
        
        // Before submit, copy editor content to hidden field
        document.getElementById('blogForm').addEventListener('submit', function() {
            document.getElementById('contentHidden').value = quill.root.innerHTML;
        });
        
        // Drag & Drop handlers
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const previewNew = document.getElementById('previewNew');
        const previewImg = document.getElementById('previewImg');
        
        dropZone.addEventListener('click', () => fileInput.click());
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#667eea';
            dropZone.style.background = '#f0f4ff';
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.style.borderColor = '#ddd';
            dropZone.style.background = 'transparent';
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#ddd';
            dropZone.style.background = 'transparent';
            
            const files = e.dataTransfer.files;
            if (files.length > 0 && files[0].type.startsWith('image/')) {
                fileInput.files = files;
                showPreview(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                showPreview(e.target.files[0]);
            }
        });
        
        function showPreview(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
                previewNew.style.display = 'block';
                dropZone.style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
    </script>
</body>
</html>
