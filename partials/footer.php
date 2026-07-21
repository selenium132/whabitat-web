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
