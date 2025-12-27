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
        
        if ($title && $content) {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO blogs (title, content, thumbnail, author_id, is_published) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $content, $thumbnail ?: null, $_SESSION['user_id'], $is_published])) {
                    $success = '記事を投稿しました！';
                } else {
                    $error = 'エラーが発生しました。';
                }
            } else {
                $blog_id = $_POST['blog_id'] ?? 0;
                $stmt = $pdo->prepare("UPDATE blogs SET title = ?, content = ?, thumbnail = ?, is_published = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$title, $content, $thumbnail ?: null, $is_published, $blog_id])) {
                    $success = '記事を更新しました！';
                } else {
                    $error = 'エラーが発生しました。';
                }
            }
        } else {
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
$stmt = $pdo->query("SELECT b.*, u.name as author_name FROM blogs b LEFT JOIN users u ON b.author_id = u.id ORDER BY b.created_at DESC");
$blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ブログ管理 | WHABITAT</title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="apple-touch-icon" href="../logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .editor-container {
            background: #fff;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .editor-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        .editor-header h2 {
            font-size: 1.3rem;
            margin: 0;
        }
        .editor-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 1rem;
        }
        .editor-tab {
            padding: 8px 16px;
            background: #f0f0f0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .editor-tab.active {
            background: #667eea;
            color: white;
        }
        .rich-editor {
            min-height: 350px;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 1rem;
            font-size: 1rem;
            line-height: 1.8;
            resize: vertical;
            font-family: inherit;
        }
        .rich-editor:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px 10px 0 0;
            border: 1px solid #ddd;
            border-bottom: none;
            margin-bottom: -1px;
        }
        .toolbar button {
            padding: 8px 12px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
        }
        .toolbar button:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .preview-box {
            min-height: 350px;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 1.5rem;
            background: #fafafa;
            line-height: 1.8;
        }
        .blog-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .blog-item {
            background: #fff;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s;
        }
        .blog-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .blog-item-info h3 {
            font-size: 1rem;
            margin-bottom: 0.3rem;
            font-weight: 600;
        }
        .blog-item-info .meta {
            font-size: 0.8rem;
            color: #888;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .blog-item-actions {
            display: flex;
            gap: 8px;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-published {
            background: #d4edda;
            color: #155724;
        }
        .status-draft {
            background: #fff3cd;
            color: #856404;
        }
        .thumbnail-preview {
            margin-top: 10px;
            max-width: 200px;
            border-radius: 8px;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
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
            <a href="../blog.php" style="display: inline-flex; align-items: center; gap: 8px; color: #333; text-decoration: none; font-weight: 500; margin-bottom: 1.5rem;">
                <i class="fas fa-chevron-left"></i> ブログに戻る
            </a>
            
            <?php if ($error): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="editor-container">
                <div class="editor-header">
                    <i class="fas fa-pen-fancy" style="font-size: 1.5rem; color: #667eea;"></i>
                    <h2><?php echo $edit_blog ? '記事を編集' : '新しい記事を書く'; ?></h2>
                </div>
                
                <form method="POST" id="blogForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="<?php echo $edit_blog ? 'update' : 'create'; ?>">
                    <?php if ($edit_blog): ?>
                        <input type="hidden" name="blog_id" value="<?php echo $edit_blog['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600;">タイトル</label>
                        <input type="text" name="title" class="form-input" required placeholder="魅力的なタイトルを入力..." 
                               value="<?php echo htmlspecialchars($edit_blog['title'] ?? ''); ?>"
                               style="font-size: 1.1rem; padding: 14px;">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600;">サムネイル画像URL（任意）</label>
                        <input type="url" name="thumbnail" id="thumbnailInput" class="form-input" 
                               placeholder="https://example.com/image.jpg"
                               value="<?php echo htmlspecialchars($edit_blog['thumbnail'] ?? ''); ?>"
                               onchange="updateThumbnailPreview()">
                        <img id="thumbnailPreview" class="thumbnail-preview" 
                             src="<?php echo htmlspecialchars($edit_blog['thumbnail'] ?? ''); ?>" 
                             style="<?php echo empty($edit_blog['thumbnail']) ? 'display:none;' : ''; ?>"
                             alt="サムネイルプレビュー">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600;">本文</label>
                        <div class="toolbar">
                            <button type="button" onclick="insertFormat('## ', '')"><i class="fas fa-heading"></i> 見出し</button>
                            <button type="button" onclick="insertFormat('**', '**')"><i class="fas fa-bold"></i> 太字</button>
                            <button type="button" onclick="insertFormat('', '\n')"><i class="fas fa-paragraph"></i> 改行</button>
                            <button type="button" onclick="insertFormat('- ', '')"><i class="fas fa-list-ul"></i> リスト</button>
                            <button type="button" onclick="insertFormat('> ', '')"><i class="fas fa-quote-left"></i> 引用</button>
                            <button type="button" onclick="insertFormat('---\n', '')"><i class="fas fa-minus"></i> 区切り</button>
                        </div>
                        <textarea name="content" id="contentEditor" class="rich-editor" required 
                                  placeholder="ここに本文を入力してください...&#10;&#10;改行は「Enter」キーでできます。&#10;見出しは「## テキスト」のように書けます。"><?php echo htmlspecialchars($edit_blog['content'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_published" <?php echo ($edit_blog['is_published'] ?? true) ? 'checked' : ''; ?>>
                            公開する（チェックを外すと下書き保存）
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 1.5rem;">
                        <?php if ($edit_blog): ?>
                            <a href="blog.php" class="btn-secondary" style="flex: 1; text-align: center;">キャンセル</a>
                        <?php endif; ?>
                        <button type="submit" class="btn-primary" style="flex: 2;">
                            <i class="fas fa-paper-plane"></i> <?php echo $edit_blog ? '更新する' : '投稿する'; ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <h2 style="font-size: 1.3rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-list-alt" style="color: #667eea;"></i> 投稿済み記事
            </h2>
            
            <?php if (empty($blogs)): ?>
                <div style="text-align: center; padding: 3rem; color: #888; background: #f8f9fa; border-radius: 12px;">
                    <i class="fas fa-inbox" style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p>まだ記事がありません。上のフォームから最初の記事を書きましょう！</p>
                </div>
            <?php else: ?>
                <div class="blog-list">
                    <?php foreach ($blogs as $blog): ?>
                        <div class="blog-item">
                            <div class="blog-item-info">
                                <h3><?php echo htmlspecialchars($blog['title']); ?></h3>
                                <div class="meta">
                                    <span><i class="far fa-calendar"></i> <?php echo date('Y/m/d H:i', strtotime($blog['created_at'])); ?></span>
                                    <?php if ($blog['author_name']): ?>
                                        <span><i class="far fa-user"></i> <?php echo htmlspecialchars($blog['author_name']); ?></span>
                                    <?php endif; ?>
                                    <span class="status-badge <?php echo $blog['is_published'] ? 'status-published' : 'status-draft'; ?>">
                                        <?php echo $blog['is_published'] ? '公開中' : '下書き'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="blog-item-actions">
                                <a href="../blog_view.php?id=<?php echo $blog['id']; ?>" target="_blank" class="btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                                    <i class="fas fa-eye"></i> 表示
                                </a>
                                <a href="?edit=<?php echo $blog['id']; ?>" class="btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                                    <i class="fas fa-edit"></i> 編集
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('「<?php echo htmlspecialchars($blog['title']); ?>」を削除しますか？');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="blog_id" value="<?php echo $blog['id']; ?>">
                                    <button type="submit" class="btn-danger" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function insertFormat(before, after) {
            const editor = document.getElementById('contentEditor');
            const start = editor.selectionStart;
            const end = editor.selectionEnd;
            const text = editor.value;
            const selectedText = text.substring(start, end);
            
            editor.value = text.substring(0, start) + before + selectedText + after + text.substring(end);
            editor.focus();
            editor.selectionStart = editor.selectionEnd = start + before.length + selectedText.length + after.length;
        }
        
        function updateThumbnailPreview() {
            const input = document.getElementById('thumbnailInput');
            const preview = document.getElementById('thumbnailPreview');
            if (input.value) {
                preview.src = input.value;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
</body>
</html>
