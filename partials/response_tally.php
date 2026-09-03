<?php
// 公開設定された質問の集計表示。form_responses.php から include する。
// 期待する変数: $q_tally, $form_schema, $can_view_list
// 選択式は「選択肢ごとの人数バー」、記述式は回答の一覧。
// $can_view_list が false のときは名前を保持していないため、自動的に人数だけになる。
if (empty($q_tally)) return;
?>
<?php foreach ($q_tally as $idx => $t): ?>
    <?php if ($t['answered'] === 0) continue; ?>
    <div class="p-card">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #eee;">
            <i class="fas fa-chart-simple" style="color: #888;" aria-hidden="true"></i>
            <span style="font-weight: 600; color: var(--text-color);"><?php echo htmlspecialchars($form_schema[$idx]['title'] ?? ('Q' . ($idx + 1))); ?></span>
            <span style="margin-left: auto; font-size: .78rem; color: #888;"><?php echo (int)$t['answered']; ?>人が回答</span>
        </div>

        <?php if ($t['type'] === 'paragraph'): ?>
            <?php foreach ($t['texts'] as $v): ?>
                <div class="custom-ans-block"><?php echo nl2br(htmlspecialchars($v)); ?></div>
            <?php endforeach; ?>
        <?php else: ?>
            <?php
            $max = 0;
            foreach ($t['options'] as $o) { if ($o['count'] > $max) $max = $o['count']; }
            ?>
            <div class="tally-list">
                <?php foreach ($t['options'] as $label => $o): ?>
                    <div class="tally-row<?php echo ($max > 0 && $o['count'] === $max) ? ' is-top' : ''; ?>">
                        <div class="tally-head">
                            <span class="tally-label"><?php echo htmlspecialchars($label); ?></span>
                            <span class="tally-count"><?php echo (int)$o['count']; ?>人</span>
                        </div>
                        <div class="tally-bar"><span style="width: <?php echo $max > 0 ? round($o['count'] / $max * 100) : 0; ?>%;"></span></div>
                        <?php if (!empty($o['names'])): ?>
                            <div class="tally-names">
                                <?php foreach ($o['names'] as $n): ?>
                                    <span class="tally-name"><?php echo htmlspecialchars($n); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$can_view_list): ?>
            <p style="margin: 14px 0 0; font-size: .8rem; color: #888;">この質問は「公開」設定です。回答者名は伏せて共有しています。</p>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
