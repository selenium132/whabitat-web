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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? '');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $thumbnail = $_POST['thumbnail'] ?? '';
        $is_published = isset($_POST['is_published']) ? 1 : 0;
        
        // Handle file upload
        if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024;
            $file = $_FILES['thumbnail_file'];
            
            if (!in_array($file['type'], $allowed_types)) {
                $error = '画像形式はJPEG, PNG, GIF, WebPのみ対応です。';
            } elseif ($file['size'] > $max_size) {
                $error = 'ファイルサイズは5MB以下にしてください。';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'blog_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $upload_dir = '../uploads/blog/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
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
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <label style="font-size: 0.85rem; color: #666; display: block; margin-bottom: 5px;">ファイルをアップロード</label>
                                <input type="file" name="thumbnail_file" accept="image/*" class="form-input" style="padding: 8px;">
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <label style="font-size: 0.85rem; color: #666; display: block; margin-bottom: 5px;">またはURLを入力</label>
                                <input type="url" name="thumbnail" class="form-input" placeholder="https://example.com/image.jpg">
                            </div>
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
    </script>
</body>
</html>
