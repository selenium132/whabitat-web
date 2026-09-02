<?php
// 公開ページ共通フッター（$nav_home は header.php と同じ規約）
$nav_home = $nav_home ?? 'index.php';
?>
<footer class="footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <span class="footer-wordmark">WHABITAT</span>
            <span class="footer-tagline">Habitat for Humanity<br>Waseda University Chapter</span>
        </div>
        <nav class="footer-nav" aria-label="フッターナビゲーション">
            <a href="<?php echo $nav_home; ?>#about">About</a>
            <a href="<?php echo $nav_home; ?>#activities">Activities</a>
            <a href="blog.php">Blog</a>
            <a href="<?php echo $nav_home; ?>#join">Join</a>
            <a href="<?php echo $nav_home; ?>#contact">Contact</a>
        </nav>
        <div class="footer-social">
            <a href="https://x.com/whabitat?s=21" target="_blank" rel="noopener" aria-label="X (Twitter)"><i class="fab fa-x-twitter" aria-hidden="true"></i></a>
            <a href="https://www.instagram.com/whabinsta" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram" aria-hidden="true"></i></a>
        </div>
    </div>
    <div class="container footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> WHABITAT Waseda University Chapter. All Rights Reserved.</p>
    </div>
</footer>

<script>
    // 公開ページ共通: スクロールで現れるフェードイン + ヘッダーのスクロール時スタイル
    // （従来は各ページに同じコードがコピペされていた）
    (function () {
        const targets = document.querySelectorAll('.fade-in, .fade-in-left, .fade-in-right');
        if (targets.length) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
            targets.forEach(el => observer.observe(el));
        }
        const header = document.querySelector('.header');
        if (header) {
            window.addEventListener('scroll', () => {
                header.classList.toggle('scrolled', window.scrollY > 50);
            }, { passive: true });
        }
    })();
</script>
