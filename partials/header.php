<?php
// 公開ページ共通ヘッダー（+ハンバーガーメニューのJS）
// 呼び出し側で設定できる変数:
//   $nav_home : トップページは '' (ページ内アンカー)。それ以外は省略で 'index.php'
//   $nav_blog : Blogリンク先。省略時はホームのBlogセクション（blog系ページは 'blog.php' を指定）
$nav_home = $nav_home ?? 'index.php';
$nav_blog = $nav_blog ?? $nav_home . '#blog';
?>
<header class="header">
    <div class="header-inner">
        <a href="<?php echo $nav_home === '' ? '#' : 'index.php'; ?>" class="logo">
            <img src="logo.png" alt="WHABITAT" height="50">
        </a>
        <button class="menu-toggle" aria-label="Toggle Menu" aria-expanded="false" aria-controls="nav-list">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav>
            <ul class="nav-list" id="nav-list">
                <li><a href="<?php echo $nav_home; ?>#about" class="nav-link">About</a></li>
                <li><a href="<?php echo $nav_home; ?>#activities" class="nav-link">Activities</a></li>
                <li><a href="<?php echo $nav_blog; ?>" class="nav-link">Blog</a></li>
                <li><a href="<?php echo $nav_home; ?>#join" class="nav-link">Join</a></li>
                <li><a href="<?php echo $nav_home; ?>#contact" class="nav-link">Contact</a></li>
                <li>
                    <a href="https://x.com/whabitat?s=21" target="_blank" class="social-icon"><i class="fab fa-x-twitter"></i></a>
                    <a href="https://www.instagram.com/whabinsta" target="_blank" class="social-icon"><i class="fab fa-instagram"></i></a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="dashboard.php" class="btn-login"><i class="fas fa-user"></i> MY PAGE</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="btn-login"><i class="fas fa-lock"></i> MEMBER LOGIN</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<script>
    document.querySelector('.menu-toggle').addEventListener('click', function () {
        this.classList.toggle('active');
        const isOpen = document.querySelector('.nav-list').classList.toggle('nav-open');
        this.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    // Close menu when a link is clicked
    document.querySelectorAll('.nav-link, .btn-login').forEach(link => {
        link.addEventListener('click', () => {
            document.querySelector('.menu-toggle').classList.remove('active');
            document.querySelector('.menu-toggle').setAttribute('aria-expanded', 'false');
            document.querySelector('.nav-list').classList.remove('nav-open');
        });
    });
</script>
