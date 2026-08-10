<?php
// 会員・管理ページ共通のシンプルヘッダー（公開ページ用は partials/header.php）。
// パスはすべてルート絶対（/logo.png 等）なので admin/ 配下からも同じ書き方で使える。
//
// 呼び出し側で設定できる変数:
//   $mh_variant      : 'logo'（ロゴのみ・既定） | 'back'（← 戻るテキストリンク）
//   $mh_href         : リンク先。既定は logo → '/index.php'、back → '/dashboard.php'
//   $mh_label        : back のときの表示テキスト。既定 'ダッシュボード'
//   $mh_actions_html : 右側に置く操作ボタン等の生HTML（呼び出し側の責任でエスケープ）
$mh_variant      = $mh_variant ?? 'logo';
$mh_href         = $mh_href ?? ($mh_variant === 'back' ? '/dashboard.php' : '/index.php');
$mh_label        = $mh_label ?? 'ダッシュボード';
$mh_actions_html = $mh_actions_html ?? '';
?>
<header class="header">
    <div class="header-inner">
        <?php if ($mh_variant === 'back'): ?>
            <a href="<?php echo htmlspecialchars($mh_href); ?>" class="logo" style="font-size: 1rem; font-weight: 500; display: flex; align-items: center;">
                <i class="fas fa-chevron-left" style="margin-right: 8px; font-size: 0.8rem;"></i> <?php echo htmlspecialchars($mh_label); ?>
            </a>
        <?php else: ?>
            <a href="<?php echo htmlspecialchars($mh_href); ?>" class="logo">
                <img src="/logo.png" alt="WHABITAT" height="50">
            </a>
        <?php endif; ?>
        <?php echo $mh_actions_html; ?>
    </div>
</header>
<?php
// 使い回し時に前ページの設定が残らないようリセット
unset($mh_variant, $mh_href, $mh_label, $mh_actions_html);
?>
