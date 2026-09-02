<?php
// 公開ページ共通フッター（$nav_home は header.php と同じ規約）
$nav_home = $nav_home ?? 'index.php';
?>
<footer class="footer">
    <div class="container">
        <div class="footer-links">
            <a href="https://x.com/whabitat?s=21" target="_blank">X (Twitter)</a>
            <a href="https://www.instagram.com/whabinsta" target="_blank">Instagram</a>
            <a href="<?php echo $nav_home; ?>#contact">Contact</a>
        </div>
        <p style="margin-top: 2rem; font-size: 0.8rem; color: #ccc;">&copy; <?php echo date('Y'); ?> WHABITAT Waseda University Chapter. All Rights Reserved.</p>
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
