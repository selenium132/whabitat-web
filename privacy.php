<?php
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
    <title>プライバシーポリシー | WHABITAT</title>
    <meta name="description" content="WHABITAT（早稲田大学 Habitat for Humanity 学生支部）における個人情報の取扱いについて。">
    <link rel="canonical" href="https://whabitathome.com/privacy.php">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="WHABITAT">
    <meta property="og:title" content="プライバシーポリシー | WHABITAT">
    <meta property="og:description" content="WHABITATにおける個人情報の取扱いについて。">
    <meta property="og:url" content="https://whabitathome.com/privacy.php">
    <meta property="og:image" content="https://whabitathome.com/ogp.jpg?v=<?php echo @filemtime(__DIR__ . '/ogp.jpg') ?: '1'; ?>">
    <meta property="og:locale" content="ja_JP">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: '1'; ?>">
    <link rel="stylesheet" href="landing.css?v=<?php echo @filemtime(__DIR__ . '/landing.css') ?: '1'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .policy-main { padding: calc(var(--header-height, 80px) + 4rem) 0 6rem; }
        .policy-wrap { max-width: 760px; margin: 0 auto; padding: 0 1.5rem; }
        .policy-eyebrow {
            font-family: 'Montserrat', sans-serif; font-size: .72rem; font-weight: 600;
            letter-spacing: .22em; text-transform: uppercase; color: var(--lp-muted); margin: 0 0 1rem;
        }
        .policy-title { font-size: clamp(1.6rem, 4vw, 2.1rem); font-weight: 600; color: var(--lp-ink); margin: 0 0 .8rem; line-height: 1.3; }
        .policy-lead { color: var(--lp-muted); line-height: 1.95; margin: 0 0 3rem; font-size: .95rem; }
        .policy-section { border-top: 1px solid var(--lp-line); padding: 2.2rem 0; }
        .policy-section h2 { font-size: 1.05rem; font-weight: 600; color: var(--lp-ink); margin: 0 0 1rem; letter-spacing: .02em; text-align: left; }
        .policy-section p, .policy-section li { color: var(--lp-muted); line-height: 1.95; font-size: .94rem; }
        .policy-section p { margin: 0 0 .9rem; }
        .policy-section ul { margin: 0 0 .9rem; padding-left: 1.4em; list-style: disc; }
        .policy-section li { margin-bottom: .35rem; }
        .policy-section strong { color: var(--lp-ink); font-weight: 600; }
        .policy-meta { border-top: 1px solid var(--lp-line); padding-top: 1.6rem; font-size: .82rem; color: var(--lp-muted); }
        .policy-back { display: inline-flex; align-items: center; gap: .4rem; margin-top: 2.5rem; color: var(--lp-ink); text-decoration: none; font-size: .88rem; }
        .policy-back:hover { text-decoration: underline; text-underline-offset: .2em; }
    </style>
    <script type="application/ld+json">{"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [{"@type": "ListItem", "position": 1, "name": "Home", "item": "https://whabitathome.com/"}, {"@type": "ListItem", "position": 2, "name": "プライバシーポリシー", "item": "https://whabitathome.com/privacy.php"}]}</script>
</head>
<body>
    <?php include 'partials/header.php'; ?>

    <main id="main" class="policy-main">
        <div class="policy-wrap">
            <p class="policy-eyebrow">Privacy Policy</p>
            <h1 class="policy-title">プライバシーポリシー</h1>
            <p class="policy-lead">
                WHABITAT（早稲田大学 Habitat for Humanity 学生支部。以下「当団体」）は、活動に参加する会員および本サイトの利用者の個人情報を、以下の方針にもとづいて取り扱います。
            </p>

            <section class="policy-section">
                <h2>1. 取得する情報</h2>
                <p>当団体は、活動の運営に必要な範囲で次の情報を取得します。</p>
                <ul>
                    <li><strong>会員登録時にご記入いただく情報</strong>: 氏名・ふりがな・メールアドレス・性別・郵便番号・住所・電話番号・生年月日・学籍番号・学部・入会した代、および任意でご記入いただく事項（アレルギー等）</li>
                    <li><strong>LINEログインにより取得する情報</strong>: LINEのユーザー識別子・表示名・プロフィール画像</li>
                    <li><strong>活動にともない生じる情報</strong>: イベントの出欠・アンケートの回答・部室の入退室記録・所属チーム</li>
                    <li><strong>お問い合わせ時の情報</strong>: お名前・メールアドレス・お問い合わせ内容</li>
                    <li><strong>技術的な情報</strong>: ログイン状態を維持するためのCookie（セッション）、および不正アクセス防止のための操作記録</li>
                </ul>
            </section>

            <section class="policy-section">
                <h2>2. 利用目的</h2>
                <ul>
                    <li>会員の管理、および活動に関する連絡</li>
                    <li>イベント・合宿・渡航活動の運営（出欠管理、緊急時の連絡、保険加入手続き等）</li>
                    <li>活動記録の作成、および団体運営のための統計（個人を特定しない形での集計）</li>
                    <li>お問い合わせへの対応</li>
                    <li>本サイトの安全な運用、および不正利用の防止</li>
                </ul>
            </section>

            <section class="policy-section">
                <h2>3. 第三者への提供</h2>
                <p>当団体は、ご本人の同意なく個人情報を第三者に提供しません。ただし、次の場合を除きます。</p>
                <ul>
                    <li>法令にもとづく場合</li>
                    <li>人の生命・身体・財産の保護のために必要で、ご本人の同意を得ることが困難な場合（渡航先での事故・災害時の安全確保など）</li>
                    <li>活動先団体（Habitat for Humanity 各国支部等）への参加者名簿の提出など、活動への参加にあたり必要となる場合。この場合は事前にお知らせします</li>
                </ul>
            </section>

            <section class="policy-section">
                <h2>4. 利用している外部サービス</h2>
                <p>本サイトの運営にあたり、次の外部サービスを利用しています。各サービスにおける情報の取扱いは、各社のプライバシーポリシーに従います。</p>
                <ul>
                    <li><strong>LINE</strong>（LINEヤフー株式会社）: 会員ログイン、およびLINE公式アカウントからの通知</li>
                    <li><strong>Google スプレッドシート / Google ドライブ</strong>（Google LLC）: 名簿・出欠データの整理。管理者本人のGoogleアカウント上に作成し、団体外へは共有しません</li>
                    <li><strong>reCAPTCHA</strong>（Google LLC）: お問い合わせフォームのスパム防止</li>
                    <li><strong>エックスサーバー</strong>（エックスサーバー株式会社）: 本サイトおよびデータベースのホスティング（国内サーバー）</li>
                </ul>
            </section>

            <section class="policy-section">
                <h2>5. 安全管理のための取り組み</h2>
                <ul>
                    <li>通信はすべて暗号化（HTTPS）しています</li>
                    <li>会員の個人情報を閲覧できるのは、団体の運営を担う管理者に限定しています</li>
                    <li>管理者による名簿の操作は記録（監査ログ）を残しています</li>
                    <li>データベースは定期的にバックアップし、アクセスを制限した場所に保管しています</li>
                </ul>
            </section>

            <section class="policy-section">
                <h2>6. 保有期間</h2>
                <p>会員の個人情報は、在籍中および卒業・退会後に団体運営上必要となる期間（卒業生としての連絡、活動実績の記録など）にわたり保有し、その後は削除または個人を特定できない形に加工します。ご本人から削除のお申し出があった場合は、法令上の義務がある場合を除き速やかに対応します。</p>
            </section>

            <section class="policy-section">
                <h2>7. ご本人の権利</h2>
                <p>ご自身の情報の確認・訂正は、ログイン後のマイページからいつでも行えます。開示・削除・利用停止のご請求、またはこのポリシーに関するご質問は、本サイトの<a href="index.php#contact" style="color: var(--lp-ink); text-decoration: underline; text-underline-offset: .2em;">お問い合わせフォーム</a>よりご連絡ください。ご本人確認のうえ、合理的な期間内に対応します。</p>
            </section>

            <section class="policy-section">
                <h2>8. Cookie について</h2>
                <p>本サイトでは、ログイン状態を維持するためのCookie（セッションCookie）と、お問い合わせフォームのスパム防止のための reCAPTCHA によるCookieのみを使用しています。広告配信や行動追跡を目的としたCookieは使用していません。</p>
            </section>

            <section class="policy-section">
                <h2>9. 改定</h2>
                <p>本ポリシーは、法令の改正や活動内容の変更にともない改定することがあります。改定した場合は本ページに掲載し、重要な変更については会員向けにお知らせします。</p>
            </section>

            <div class="policy-meta">
                制定日: 2026年8月28日<br>
                WHABITAT（早稲田大学 Habitat for Humanity 学生支部）
            </div>

            <a href="index.php" class="policy-back"><i class="fas fa-chevron-left"></i> トップに戻る</a>
        </div>
    </main>

    <?php include 'partials/footer.php'; ?>
</body>
</html>
