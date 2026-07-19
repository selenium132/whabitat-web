<?php
/**
 * ワンタイム: GV/JVチームのメンバー紐付け一括投入（実行後にリポジトリから削除する）
 *
 * 保護: POST + ADMIN_SECRET の hash_equals 照合のみ実行可（GET/不一致は404）。
 * 冪等: 既存の紐付けは INSERT IGNORE 相当でスキップ。チーム作成も存在チェック付き。
 */
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ADMIN_SECRET === '' || !hash_equals(ADMIN_SECRET, $_POST['secret'] ?? '')) {
    http_response_code(404);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
ensureActivityTeamsTable($pdo);

// --- 新規チーム（2026春のGV）。既存チェック付き ---
$new_teams = [
    ['gv', '2026', 'プカルナGV', 'Spring', ''],
    ['gv', '2026', 'AZRIDGV',   'Spring', ''],
];
$created = [];
foreach ($new_teams as $t) {
    $chk = $pdo->prepare("SELECT id FROM activity_teams WHERE type = ? AND year_label = ? AND team_name = ?");
    $chk->execute([$t[0], $t[1], $t[2]]);
    if (!$chk->fetchColumn()) {
        $ins = $pdo->prepare("INSERT INTO activity_teams (type, year_label, team_name, tag1, tag2) VALUES (?, ?, ?, ?, ?)");
        $ins->execute($t);
        $created[] = $t[2] . '（' . $t[1] . ' ' . $t[3] . '）';
    }
}

// --- 投入データ: [チーム名, 名前, ふりがな, 学籍番号] ---
// 「つむぐるりんGV」は既存チーム名「つむぐるりんJV」として扱う
$data = [
    ['プカルナGV', '竹内　伯夫', 'たけうち　のりお', '1X24A097'],
    ['プカルナGV', '宮本　峻輔', 'みやもと　しゅんすけ', '1B240762'],
    ['プカルナGV', '立澤　智樹', 'たつざわ　ともき', '1B240473'],
    ['プカルナGV', '関根　有唯', 'せきね　ゆい', '1U240461'],
    ['プカルナGV', '三田　怜奈', 'みた　れいな', '1E25A065'],
    ['プカルナGV', '冨岡　彩由', 'とみおか　あゆ', '1E25F083'],
    ['プカルナGV', '前田　結衣', 'まえだ　ゆい', '1J25F165'],
    ['プカルナGV', '川尻　悠夏', 'かわじり　ゆな', '1T250271'],
    ['プカルナGV', '平井　日菜', 'ひらい　ひな', '1F250689'],
    ['プカルナGV', '武塙　彩佳', 'たけはな　あやか', '1T250535'],
    ['プカルナGV', '齊藤　成央', 'さいとう　なお', '1J25F081'],
    ['プカルナGV', '駒崎　誠幸', 'こまさき　せいこう', '1F250315'],
    ['AZRIDGV', '井上　璃美', 'いのうえ　りみ', '1H240072'],
    ['AZRIDGV', '北山　風香', 'きたやま　ふうか', '1E24F044'],
    ['AZRIDGV', '大坂　春奈', 'おおさか　はるな', '1F240143'],
    ['AZRIDGV', '大橋　りん', 'おおはし　りん', '1B240163'],
    ['AZRIDGV', '安藤　千栞', 'あんどう　ちひろ', '1H240032'],
    ['AZRIDGV', '砂田　琉璃子', 'すなだ　るりこ', '1A242271'],
    ['AZRIDGV', '髙橋　寛大', 'たかはし　ひろまさ', '1U240481'],
    ['AZRIDGV', '島田　夏向', 'しまだ　かなた', '1U250377'],
    ['AZRIDGV', '木村　陽光', 'きむら　ようこう', '1F250264'],
    ['AZRIDGV', '松本　莉子', 'まつもと　りこ', '1X25D072'],
    ['AZRIDGV', '斉藤　琴美', 'さいとう　ことみ', '1F250330'],
    ['エルメラGV', '三須　亮汰', 'みす　りょうた', '1U240724'],
    ['エルメラGV', '嶋田　愛', 'しまだ　あい', '1T240405'],
    ['エルメラGV', '藤原　悠記', 'ふじわら　ゆうき', '1X24C110'],
    ['りもちゅんJV', '本間　もも', 'ほんま　もも', '1A241303'],
    ['りもちゅんJV', '久保園　莉奈', 'くぼぞの　りな', '1M241083'],
    ['りもちゅんJV', '五十嵐　日音', 'いからし　はるね', '1M241053'],
    ['りもちゅんJV', '永井　龍人', 'ながい　りゅうと', '1F250573'],
    ['ふくでっぽらJV', '伊藤　秀治', 'いとう　しゅうじ', '1H240060'],
    ['ふくでっぽらJV', '河野　美希', 'こうの　みき', '1H240230'],
    ['ふくでっぽらJV', '岡田　衿奈', 'おかだ　えりな', '1X25D020'],
    ['ぎゃばみっちゃJV', '渡邊　杏花', 'わたなべ　きょうか', '1H240730'],
    ['ぎゃばみっちゃJV', '住田　耕助', 'すみだ　こうすけ', '1E24A040'],
    ['ぎゃばみっちゃJV', '林　小暖里', 'はやし　このり', '1F250673'],
    ['ぎゃばみっちゃJV', '井口　愛尋', 'いぐち　まひろ', '1T250043'],
    ['ぎゃばみっちゃJV', '伊藤　真道', 'いとう　しんどう', '1T250085'],
    ['ぎゃばみっちゃJV', '田中　鈴乃', 'たなか　しんどう', '1E25H107'],
    ['ぎゃばみっちゃJV', '高山　花帆', 'たかやま　まほ', '1T250515'],
    ['めんけぽっこJV', '傅　明羽', 'ふう　めいわ', '1U240645'],
    ['めんけぽっこJV', '草川　清佳', 'くさかわ　さやか', '1H240213'],
    ['めんけぽっこJV', '小島　花緒里', 'こじま　かおり', '1B250329'],
    ['めんけぽっこJV', '山下　実果', 'やました　みか', '1T250880'],
    ['めんけぽっこJV', '岸田　とあ', 'きしだ　とあ', '1F250249'],
    ['めんけぽっこJV', '齊藤　海心', 'さいとう　かいしん', '1B250355'],
    ['てやのっぺJV', '平澤　由望', 'ひらさわ　ゆの', '1E24N075'],
    ['てやのっぺJV', '新徳　絢音', 'しんとく　あやね', '1F250406'],
    ['なちゃJV', '稲森　ももこ', 'いなもり　ももこ', '1F240084'],
    ['みさらーちJV', '木村　美月', 'きむら　みつき', '1T240267'],
    ['みさらーちJV', '古田　晃基', 'ふるた　こうき', '1Y25F134'],
    ['みさらーちJV', '尾崎　花音', 'おざき　かのん', '1M250310'],
    ['つむぐるりんJV', '松澤　結衣', 'まつざわ　ゆい', '1T240770'],
    ['つむぐるりんJV', '笹川　美空', 'ささかわ　みそら', '1H250307'],
    ['つむぐるりんJV', '飯嶋　元康', 'いいじま　もとやす', '1T250036'],
    ['かまきゅらんJV', '中其　香乃', 'なかその　かの', '1H240461'],
    ['かまきゅらんJV', '那須　華子', 'なす　はなこ', '1B250607'],
    ['じゃっぱーれ団', '久一　優奈', 'ひさいち　ゆうな', '1W251072'],
    ['じゃっぱーれ団', '緒方　晶', 'おがた　ひかり', '1M241138'],
    ['このれい48JV', '平野　杏奈', 'ひらの　あんな', '1A252571'],
    ['このれい48JV', '萩原　怜士', 'はぎわら　れいじ', '1T250686'],
    ['このれい48JV', '和氣　菜々子', 'わけ　ななこ', '1E25F118'],
    ['このれい48JV', '信清　航', 'のぶきよ　わたる', ''],
];

// チーム名 → id（同名は最新年度を採用）
$teams = [];
foreach ($pdo->query("SELECT id, team_name FROM activity_teams ORDER BY year_label ASC") as $t) {
    $teams[$t['team_name']] = (int)$t['id'];
}

// 会員の照合インデックス（学籍番号は大文字化、名前/かなは空白除去）
$norm = function ($s) { return str_replace(["　", ' '], '', (string)$s); };
$by_sid = [];
$by_name = [];
$by_kana = [];
foreach ($pdo->query("SELECT id, name, name_kana, student_id FROM users") as $u) {
    if (!empty($u['student_id'])) $by_sid[strtoupper(trim($u['student_id']))] = (int)$u['id'];
    if (!empty($u['name'])) $by_name[$norm($u['name'])][] = (int)$u['id'];
    if (!empty($u['name_kana'])) $by_kana[$norm($u['name_kana'])][] = (int)$u['id'];
}

$linked = 0; $skipped = 0; $unmatched = []; $team_missing = [];
$ins = $pdo->prepare("INSERT IGNORE INTO activity_team_members (team_id, user_id) VALUES (?, ?)");
foreach ($data as $row) {
    list($team_name, $name, $kana, $sid) = $row;
    if (!isset($teams[$team_name])) { $team_missing[$team_name] = true; continue; }
    $uid = null;
    if ($sid !== '' && isset($by_sid[strtoupper($sid)])) {
        $uid = $by_sid[strtoupper($sid)];
    } elseif (isset($by_name[$norm($name)]) && count($by_name[$norm($name)]) === 1) {
        $uid = $by_name[$norm($name)][0];
    } elseif (isset($by_kana[$norm($kana)]) && count($by_kana[$norm($kana)]) === 1) {
        $uid = $by_kana[$norm($kana)][0];
    }
    if ($uid === null) { $unmatched[] = $team_name . ' / ' . $name . '（' . ($sid ?: '学籍番号なし') . '）'; continue; }
    $ins->execute([$teams[$team_name], $uid]);
    if ($ins->rowCount() > 0) $linked++; else $skipped++;
}

echo json_encode([
    'created_teams' => $created,
    'linked' => $linked,
    'already_linked_skipped' => $skipped,
    'unmatched_members' => $unmatched,
    'missing_teams' => array_keys($team_missing),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
