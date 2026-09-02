<?php
// 会員向け「個人情報の取り扱いについて」。
// 公開サイトの導線（フッター・sitemap）からは外し、会員登録フォームとダッシュボードからだけ案内する。
require_once 'config.php';
$nav_blog = 'blog.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/icons/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/icons/apple-touch-icon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>個人情報の取り扱いについて | WHABITAT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: '1'; ?>">
    <link rel="stylesheet" href="landing.css?v=<?php echo @filemtime(__DIR__ . '/landing.css') ?: '1'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .policy-main { padding: calc(var(--header-height, 80px) + 4rem) 0 6rem; }
        .policy-wrap { max-width: 720px; margin: 0 auto; padding: 0 1.5rem; }
        .policy-title { font-size: clamp(1.5rem, 4vw, 1.9rem); font-weight: 600; color: var(--lp-ink); margin: 0 0 1rem; line-height: 1.4; }
        .policy-lead { color: var(--lp-muted); line-height: 2; margin: 0 0 2.5rem; font-size: .96rem; }
        .policy-section { border-top: 1px solid var(--lp-line); padding: 2rem 0; }
        .policy-section h2 { font-size: 1.05rem; font-weight: 600; color: var(--lp-ink); margin: 0 0 1rem; text-align: left; }
        .policy-section p, .policy-section li { color: var(--lp-muted); line-height: 1.95; font-size: .94rem; }
        .policy-section p { margin: 0 0 .9rem; }
        .policy-section ul { margin: 0 0 .9rem; padding-left: 1.4em; list-style: disc; }
        .policy-section li { margin-bottom: .5rem; }
        .policy-section strong { color: var(--lp-ink); font-weight: 600; }
        .policy-meta { border-top: 1px solid var(--lp-line); padding-top: 1.4rem; font-size: .82rem; color: var(--lp-muted); line-height: 1.8; }
        .policy-back { display: inline-flex; align-items: center; gap: .4rem; margin-top: 2rem; color: var(--lp-ink); text-decoration: none; font-size: .88rem; }
        .policy-back:hover { text-decoration: underline; text-underline-offset: .2em; }
    </style>
</head>
<body>
    <?php include 'partials/header.php'; ?>

    <main id="main" class="policy-main">
        <div class="policy-wrap">
            <h1 class="policy-title">個人情報の取り扱いについて</h1>
            <p class="policy-lead">
                WHABITAT では、合宿や海外渡航を含む活動を安全に運営するために、会員のみなさんからいくつかの個人情報をお預かりしています。
                何を、何のために預かり、誰が見られるのかをここにまとめました。
            </p>

            <section class="policy-section">
                <h2>お預かりしているものと、その理由</h2>
                <ul>
                    <li><strong>氏名・ふりがな・学部・学籍番号・代</strong> — 会員名簿として。大学への届出や、活動先に提出する参加者名簿に使うことがあります。</li>
                    <li><strong>メールアドレス・電話番号・住所</strong> — 活動の連絡と、合宿や渡航中に何かあったときにご本人やご家族へ連絡するためです。</li>
                    <li><strong>生年月日</strong> — 渡航や保険の手続きに必要なときのためです。</li>
                    <li><strong>アレルギー等（任意）</strong> — 合宿や渡航先での食事・安全のためです。書きたくない場合は空欄で構いません。</li>
                    <li><strong>出欠・アンケートの回答、部室の入退室記録</strong> — 日々の活動を運営するためです。</li>
                    <li><strong>LINE アカウントの識別子・表示名</strong> — ログインと、LINE でのお知らせのためです。</li>
                </ul>
            </section>

            <section class="policy-section">
                <h2>誰が見られるか</h2>
                <ul>
                    <li>名簿の全体を見られるのは、<strong>運営を担当する幹部（管理者権限を持つ人）だけ</strong>です。</li>
                    <li>一般の会員に、他の会員の住所や連絡先が表示されることはありません。出欠確認の参加者名は、主催者の設定によって会員に表示されることがあります。</li>
                    <li><strong>団体の外に渡すことはありません。</strong>例外は、活動に参加するために必要な場合（渡航先の Habitat 現地支部に提出する参加者名簿など。事前にお知らせします）、事故や災害などで緊急に必要な場合、法令で求められた場合です。</li>
                </ul>
            </section>

            <section class="policy-section">
                <h2>どう守っているか</h2>
                <ul>
                    <li>サイトの通信はすべて暗号化（HTTPS）し、データは国内のサーバーに保存して毎日バックアップしています。</li>
                    <li>幹部が名簿を操作した記録が残るようにしています。</li>
                    <li>ログインには LINE、名簿の整理には Google スプレッドシート（幹部本人のアカウント内で、外部には共有しません）、お問い合わせフォームのスパム対策には reCAPTCHA を使っています。</li>
                </ul>
            </section>

            <section class="policy-section">
                <h2>いつまで持つか・やめたいとき</h2>
                <ul>
                    <li>卒業・退会後も、卒業生への連絡や活動の記録のために一定期間は名簿に残ります。</li>
                    <li>自分の情報の確認・変更は、マイページからいつでもできます。</li>
                    <li>退会や情報の削除を希望するときは、マイページの「アカウント」から申請するか、幹部に直接伝えてください。確認のうえ削除します。</li>
                </ul>
            </section>

            <section class="policy-section">
                <h2>この内容を変えるとき</h2>
                <p>活動内容や法律の変更にあわせて見直すことがあります。変えたときはこのページを更新し、大きな変更は会員にお知らせします。気になることがあれば、幹部までいつでも聞いてください。</p>
            </section>

            <div class="policy-meta">
                最終更新: 2026年9月2日<br>
                WHABITAT（Habitat for Humanity 早稲田大学 学生支部）
            </div>

            <a href="dashboard.php" class="policy-back"><i class="fas fa-chevron-left"></i> マイページに戻る</a>
        </div>
    </main>

    <?php include 'partials/footer.php'; ?>
</body>
</html>
