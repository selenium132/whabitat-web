<?php
require_once 'config.php';

// History: DBからJVチームを取得（初回は旧ハードコード内容を自動シード）
$pdo = getDB();
ensureActivityTeamsTable($pdo);
$stmt = $pdo->prepare("SELECT * FROM activity_teams WHERE type = 'jv' ORDER BY year_label ASC, sort_order, id");
$stmt->execute();
$jv_years = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $team) {
    $jv_years[$team['year_label']][] = $team;
}
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JV (Japan Village) | WHABITAT</title>
    <meta name="description" content="JV (Japan Village) は、WHABITAT の国内派遣型ボランティアです。日本全国の地域へ夏休みの1週間、チームで派遣され、農作業・環境整備・文化交流などに取り組みます。">
    <link rel="canonical" href="https://whabitathome.com/activity_jv.php">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="WHABITAT">
    <meta property="og:title" content="JV (Japan Village) | WHABITAT">
    <meta property="og:description" content="日本全国の地域へ、夏休みの1週間、国内派遣型ボランティア。農作業・環境整備・文化交流などに取り組みます。">
    <meta property="og:url" content="https://whabitathome.com/activity_jv.php">
    <meta property="og:image" content="https://whabitathome.com/images/jv/jv.jpg?v=<?php echo @filemtime(__DIR__ . '/images/jv/jv.jpg') ?: '1'; ?>">
    <meta property="og:locale" content="ja_JP">
    <meta name="twitter:card" content="summary_large_image">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: '1'; ?>">
    <link rel="stylesheet" href="landing.css?v=<?php echo @filemtime(__DIR__ . '/landing.css') ?: '1'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* =========================================================
           JV ページ固有スタイル — landing.css のミニマル/モノトーンに準拠
           （色は写真のみ。装飾は罫線と余白で見せる）
           ========================================================= */

        /* ヒーロー：軽い暗幕＋白文字で可読性を確保した帯状のビジュアル */
        .jv-hero {
            position: relative;
            margin-top: 72px;
            min-height: clamp(320px, 46vh, 460px);
            display: flex;
            align-items: flex-end;
            overflow: hidden;
            background: var(--lp-paper-2);
        }
        .jv-hero-bg {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
        }
        .jv-hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(18,20,22,.18) 0%, rgba(18,20,22,.30) 50%, rgba(18,20,22,.66) 100%);
        }
        .jv-hero-inner {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: var(--lp-max);
            margin: 0 auto;
            padding: 0 1.5rem 2.8rem;
            color: #fff;
        }
        .jv-hero-eyebrow {
            font-family: 'Montserrat', sans-serif;
            font-size: .7rem;
            font-weight: 600;
            letter-spacing: .26em;
            text-transform: uppercase;
            opacity: .9;
            margin: 0 0 .7rem;
        }
        .jv-hero-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: clamp(1.9rem, 5vw, 2.9rem);
            letter-spacing: .04em;
            margin: 0;
            line-height: 1.15;
        }
        .jv-hero-sub {
            margin: .8rem 0 0;
            font-size: .9rem;
            font-weight: 400;
            letter-spacing: .04em;
            opacity: .92;
        }

        /* パンくず */
        .jv-breadcrumb {
            max-width: var(--lp-max);
            margin: 1.6rem auto 0;
            padding: 0 1.5rem;
            font-size: .78rem;
            letter-spacing: .03em;
            color: var(--lp-muted);
        }
        .jv-breadcrumb a { color: var(--lp-muted); text-decoration: none; transition: color .25s; }
        .jv-breadcrumb a:hover { color: var(--lp-ink); }
        .jv-breadcrumb span { margin: 0 .5em; opacity: .6; }

        /* セクション見出し（landing.css の .section-title に補助のリード文を添える） */
        .jv-section { padding: 5.5rem 0; }
        .jv-section.bg-light { background: var(--lp-paper-2); }
        .jv-lead {
            text-align: center;
            max-width: 40em;
            margin: -2.4rem auto 3.2rem;
            font-size: .98rem;
            line-height: 1.95;
            color: var(--lp-muted);
        }

        /* イントロのリード文（大きめ・上品なタイポ） */
        .jv-intro { text-align: center; max-width: 46em; margin: 0 auto; }
        .jv-intro p {
            font-size: clamp(1.02rem, 2vw, 1.22rem);
            line-height: 2.1;
            font-weight: 400;
            color: var(--lp-ink);
            margin: 0;
        }

        /* 罫線カード共通（3本柱 / 活動内容 / メリット） */
        .jv-grid {
            display: grid;
            gap: 1.4rem;
            max-width: 1000px;
            margin: 0 auto;
        }
        .jv-grid--3 { grid-template-columns: repeat(3, 1fr); }
        .jv-card {
            position: relative;
            background: var(--lp-paper);
            border: 1px solid var(--lp-line);
            border-radius: var(--lp-radius);
            padding: 2.2rem 1.9rem;
            transition: border-color .35s var(--lp-ease), transform .35s var(--lp-ease);
        }
        .jv-card:hover { border-color: var(--lp-ink); transform: translateY(-4px); }
        .jv-card .jv-card-label {
            display: block;
            font-family: 'Montserrat', sans-serif;
            font-size: .64rem;
            font-weight: 600;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: var(--lp-muted);
            margin-bottom: 1rem;
        }
        .jv-card h3 {
            font-size: 1.12rem;
            font-weight: 600;
            color: var(--lp-ink);
            margin: 0 0 .7rem;
            letter-spacing: .01em;
        }
        .jv-card p {
            font-size: .92rem;
            line-height: 1.85;
            color: var(--lp-muted);
            margin: 0;
        }
        .jv-card ul {
            list-style: none;
            margin: 1.1rem 0 0;
            padding: 1rem 0 0;
            border-top: 1px solid var(--lp-line);
        }
        .jv-card ul li {
            position: relative;
            padding-left: 1.1em;
            font-size: .88rem;
            line-height: 1.75;
            color: var(--lp-ink);
        }
        .jv-card ul li + li { margin-top: .35rem; }
        .jv-card ul li::before {
            content: "";
            position: absolute;
            left: 0;
            top: .72em;
            width: 5px;
            height: 1px;
            background: var(--lp-ink);
            opacity: .55;
        }

        /* メリット：番号を大きく、罫線基調 */
        .jv-merit-num {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--lp-ink);
            opacity: .22;
            margin-bottom: .8rem;
            display: block;
        }

        /* 流れ（タイムライン）：横一列・墨の点と細い連結線 */
        .jv-flow {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            max-width: 980px;
            margin: 0 auto;
            position: relative;
        }
        .jv-flow::before {
            content: "";
            position: absolute;
            top: 5px;
            left: 8%;
            right: 8%;
            height: 1px;
            background: var(--lp-line);
            z-index: 0;
        }
        .jv-flow-step {
            position: relative;
            z-index: 1;
            flex: 1;
            text-align: center;
        }
        .jv-flow-point {
            width: 11px;
            height: 11px;
            border-radius: 50%;
            background: var(--lp-paper);
            border: 1px solid var(--lp-ink);
            margin: 0 auto 1.2rem;
        }
        .jv-flow-step h4 {
            font-size: .95rem;
            font-weight: 600;
            color: var(--lp-ink);
            margin: 0 0 .4rem;
        }
        .jv-flow-step p {
            font-size: .8rem;
            line-height: 1.6;
            color: var(--lp-muted);
            margin: 0;
        }

        .team-add-btn {
            display: inline-flex; align-items: center; gap: .45rem;
            font-family: 'Montserrat', sans-serif;
            font-size: .72rem; font-weight: 600; letter-spacing: .08em;
            color: var(--lp-ink); text-decoration: none;
            border: 1px solid var(--lp-line); border-radius: 999px;
            padding: .4rem 1rem; transition: border-color .3s, background .3s;
        }
        .team-add-btn:hover { border-color: var(--lp-ink); background: rgba(26,26,26,.04); }
        .jv-history-noimg {
            width: 100%; height: 100%; min-height: 150px;
            display: flex; align-items: center; justify-content: center;
            background: var(--lp-paper-2);
            color: var(--lp-muted); font-size: 1.6rem;
        }

        /* History：チームカード（写真はカラーのまま、罫線でまとめる） */
        .jv-history-year {
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--lp-ink);
            text-align: center;
            margin: 0 0 2.4rem;
        }
        .jv-history-year::after {
            content: "";
            display: block;
            width: 26px;
            height: 1px;
            margin: .9rem auto 0;
            background: var(--lp-ink);
            opacity: .28;
        }
        .jv-history-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 1.4rem;
            max-width: var(--lp-max);
            margin: 0 auto;
        }
        .jv-history-card {
            display: block;
            text-decoration: none;
            color: inherit;
            background: var(--lp-paper);
            border: 1px solid var(--lp-line);
            border-radius: var(--lp-radius);
            overflow: hidden;
            transition: border-color .35s var(--lp-ease), transform .35s var(--lp-ease);
        }
        .jv-history-card:hover { border-color: var(--lp-ink); transform: translateY(-4px); }
        .jv-history-thumb {
            padding: 1.4rem 1.4rem 0;
        }
        .jv-history-thumb img {
            width: 100%;
            aspect-ratio: 1/1;
            object-fit: contain;
            border-radius: 50%;
            border: 1px solid var(--lp-line);
            background: #fff;
        }
        .jv-history-info {
            padding: 1.1rem 1.2rem 1.4rem;
            text-align: center;
        }
        .jv-history-region {
            display: block;
            font-family: 'Montserrat', sans-serif;
            font-size: .62rem;
            font-weight: 600;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: var(--lp-muted);
        }
        .jv-history-place {
            display: block;
            font-size: .76rem;
            color: var(--lp-muted);
            margin-top: .25rem;
        }
        .jv-history-team {
            font-size: .98rem;
            font-weight: 600;
            color: var(--lp-ink);
            margin: .55rem 0 0;
            line-height: 1.4;
        }

        /* 末尾の戻りリンク */
        .jv-back {
            text-align: center;
            padding-bottom: 1rem;
        }

        @media (max-width: 820px) {
            .jv-grid--3 { grid-template-columns: 1fr; }
            .jv-flow { flex-wrap: wrap; gap: 2rem 1rem; }
            .jv-flow::before { display: none; }
            .jv-flow-step { flex: 1 1 40%; }
        }
        @media (max-width: 768px) {
            .jv-section { padding: 4rem 0; }
            .jv-lead { margin-top: -1.6rem; }
        }
    </style>
</head>
<body>
    <?php include 'partials/header.php'; ?>

    <main>
        <!-- Hero -->
        <section class="page-hero">
            <div class="page-hero-bg" style="background-image: url('images/jv/jv.jpg?v=<?php echo @filemtime(__DIR__ . '/images/jv/jv.jpg') ?: '1'; ?>');"></div>
            <div class="page-hero-overlay"></div>
            <div class="page-hero-inner">
                <p class="page-hero-eyebrow">Domestic Volunteer</p>
                <h1 class="page-hero-title">JV (Japan Village)</h1>
                <p class="page-hero-sub">日本全国の地域へ、夏休みの1週間。国内派遣型ボランティア。</p>
            </div>
        </section>

        <nav class="page-crumb" aria-label="パンくず">
            <a href="index.php">Home</a><span>/</span><a href="index.php#activities">Activities</a><span>/</span>JV
        </nav>

        <!-- Intro -->
        <section class="jv-section">
            <div class="container">
                <div class="jv-intro fade-in">
                    <p>
                        コロナ禍で海外への渡航が困難になった際、<br>
                        「GVに代わる大きなイベントを」という想いから生まれた、国内派遣型ボランティア。<br>
                        日本全国の豊かな自然と温かい人々に出会う、夏休みの1週間。
                    </p>
                </div>
            </div>
        </section>

        <!-- Overview -->
        <section class="jv-section bg-light">
            <div class="container">
                <h2 class="section-title fade-in"><span>JVとは？</span></h2>
                <div class="jv-grid jv-grid--3 stagger-children">
                    <div class="jv-card fade-in">
                        <span class="jv-card-label">Team</span>
                        <h3>チーム構成</h3>
                        <p>各チームは1〜3年生のメンバー8〜11人前後で構成。学年を超えた深い絆が生まれます。</p>
                    </div>
                    <div class="jv-card fade-in">
                        <span class="jv-card-label">Location</span>
                        <h3>派遣先</h3>
                        <p>北は青森から南は九州まで。普段は訪れることのない、日本の原風景が残る地域へ。</p>
                    </div>
                    <div class="jv-card fade-in">
                        <span class="jv-card-label">Timing</span>
                        <h3>派遣時期</h3>
                        <p>夏休みの1週間。日常を離れ、地域の方々と共に汗を流し、語り合う特別な時間です。</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Activities -->
        <section class="jv-section">
            <div class="container">
                <h2 class="section-title fade-in"><span>活動内容</span></h2>
                <p class="jv-lead fade-in">派遣先によって内容は多岐にわたりますが、主に3つの柱があります。</p>
                <div class="jv-grid jv-grid--3 stagger-children">
                    <div class="jv-card fade-in">
                        <span class="jv-card-label">01 / Farming</span>
                        <h3>農作業</h3>
                        <p>地域の名産品に関わる作業を通じて、農家さんの想いや苦労を肌で感じます。</p>
                        <ul>
                            <li>らっきょう（畑の整備・根付け）</li>
                            <li>すだち・きくらげ（収穫）</li>
                            <li>赤かぶ（作付け）など</li>
                        </ul>
                    </div>
                    <div class="jv-card fade-in">
                        <span class="jv-card-label">02 / Environment</span>
                        <h3>環境整備</h3>
                        <p>過疎化が進む地域での保全活動。地域の美しい景観や生活環境を守ります。</p>
                        <ul>
                            <li>森林保全（間伐作業）</li>
                            <li>空き家対策（掃除・遺品整理）</li>
                            <li>設備点検・地域清掃など</li>
                        </ul>
                    </div>
                    <div class="jv-card fade-in">
                        <span class="jv-card-label">03 / Exchange</span>
                        <h3>動物・文化交流</h3>
                        <p>作業だけでなく、その土地ならではの文化や生き物との触れ合いも大切にしています。</p>
                        <ul>
                            <li>動物のお世話（牛・アルパカ・ヤギ）</li>
                            <li>伝統文化体験（合掌造り・伝統楽器）</li>
                            <li>地域学習（資料館訪問・里山保全）</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Merits -->
        <section class="jv-section bg-light">
            <div class="container">
                <h2 class="section-title fade-in"><span>JVに参加するメリット</span></h2>
                <div class="jv-grid jv-grid--3 stagger-children">
                    <div class="jv-card fade-in">
                        <span class="jv-merit-num">01</span>
                        <h3>参加のしやすさ</h3>
                        <p>海外（GV）に比べてハードルが低く、費用も抑えられるため、ボランティア初心者でも安心して参加できます。GVへのステップアップとしても最適です。</p>
                    </div>
                    <div class="jv-card fade-in">
                        <span class="jv-merit-num">02</span>
                        <h3>豊かな環境と出会い</h3>
                        <p>普段行けない自然豊かな地域で、地元の方々やメンバーと濃い時間を過ごします。デジタルデトックスにもなり、心身ともにリフレッシュできます。</p>
                    </div>
                    <div class="jv-card fade-in">
                        <span class="jv-merit-num">03</span>
                        <h3>自己成長と生活</h3>
                        <p>規則正しい生活と美味しいご飯。集団行動を通じて自分自身を見つめ直し、当たり前の生活のありがたさを再確認する機会になります。</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Flow -->
        <section class="jv-section">
            <div class="container">
                <h2 class="section-title fade-in"><span>JVの流れ</span></h2>
                <div class="jv-flow fade-in">
                    <div class="jv-flow-step">
                        <div class="jv-flow-point"></div>
                        <h4>チーム結成</h4>
                        <p>メンバー決定。</p>
                    </div>
                    <div class="jv-flow-step">
                        <div class="jv-flow-point"></div>
                        <h4>事前MTG</h4>
                        <p>事前学習・レクリエーション。</p>
                    </div>
                    <div class="jv-flow-step">
                        <div class="jv-flow-point"></div>
                        <h4>派遣</h4>
                        <p>夏休みの1週間。<br>現地での活動。</p>
                    </div>
                    <div class="jv-flow-step">
                        <div class="jv-flow-point"></div>
                        <h4>事後MTG</h4>
                        <p>振り返り・レクリエーション。</p>
                    </div>
                    <div class="jv-flow-step">
                        <div class="jv-flow-point"></div>
                        <h4>報告会</h4>
                        <p>活動の集大成。<br>学びの共有。</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- History -->
        <section class="jv-section bg-light">
            <div class="container">
                <h2 class="section-title fade-in"><span>WHABITAT JV History</span></h2>
                <p class="jv-lead fade-in">これまでに派遣されたチームの記録です。</p>

                <?php if ($is_admin): ?>
                <p style="text-align: center; margin: -1.6rem 0 2.6rem;">
                    <a href="admin/teams.php?type=jv" class="team-add-btn"><i class="fas fa-plus"></i> チームを追加・編集</a>
                </p>
                <?php endif; ?>

                <?php if (empty($jv_years)): ?>
                    <p class="jv-lead">チームの記録はまだありません。</p>
                <?php else: ?>
                    <?php foreach ($jv_years as $year => $teams): ?>
                    <div class="jv-history-group fade-in">
                        <h3 class="jv-history-year"><?php echo htmlspecialchars($year); ?></h3>
                        <div class="jv-history-grid">
                            <?php foreach ($teams as $team): ?>
                            <<?php echo $team['instagram_url'] ? 'a href="' . htmlspecialchars($team['instagram_url']) . '" target="_blank" rel="noopener"' : 'div'; ?> class="jv-history-card">
                                <div class="jv-history-thumb">
                                    <?php if ($team['image_path']): ?>
                                        <img src="<?php echo htmlspecialchars($team['image_path']); ?>" alt="<?php echo htmlspecialchars($team['team_name']); ?>" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <div class="jv-history-noimg"><i class="fas fa-users"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="jv-history-info">
                                    <?php if ($team['tag1']): ?><span class="jv-history-region"><?php echo htmlspecialchars($team['tag1']); ?></span><?php endif; ?>
                                    <?php if ($team['tag2']): ?><span class="jv-history-place"><?php echo htmlspecialchars($team['tag2']); ?></span><?php endif; ?>
                                    <h4 class="jv-history-team"><?php echo htmlspecialchars($team['team_name']); ?></h4>
                                </div>
                            </<?php echo $team['instagram_url'] ? 'a' : 'div'; ?>>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="jv-back" style="margin-top: 3.5rem;">
                    <a href="index.php#activities" class="btn-secondary"><i class="fas fa-arrow-left"></i> 活動一覧へ戻る</a>
                </div>
            </div>
        </section>
    </main>

    <?php include 'partials/footer.php'; ?>

    <script>
    // Intersection Observer for fade-in animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));

    // Header scroll effect
    const header = document.querySelector('.header');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    }, { passive: true });
    </script>
</body>
</html>
