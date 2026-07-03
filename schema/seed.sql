-- ローカル動作確認用のダミーデータ(実在の会員情報は一切含まない)。
-- schema.sql でテーブルを作成した後にこれを流し込むと、最低限の
-- 会員/イベント/アンケート/ブログを持つ状態でサイトを触れるようになる。

-- 承認済み・プロフィール入力済みの管理者
INSERT INTO users (id, line_user_id, line_name, name, name_kana, email, student_id, grade, faculty, department, gender, zipcode, address, phone, birthdate, role, is_approved)
VALUES (1, 'U_dummy_admin', 'かんり たろう', '管理 太郎', 'かんり たろう', 'admin@example.com', 'S001', '20th', '文学部', '哲学科', 'male', '1000001', '東京都千代田区1-1', '09000000000', '2004-04-01', 'admin', 1);

-- 承認済み・プロフィール入力済みの一般会員
INSERT INTO users (id, line_user_id, line_name, name, name_kana, email, student_id, grade, faculty, department, gender, zipcode, address, phone, birthdate, role, is_approved)
VALUES (2, 'U_dummy_member', 'いっぱん はなこ', '一般 花子', 'いっぱん はなこ', 'member@example.com', 'S002', '19th', '法学部', '法律学科', 'female', '1000002', '東京都新宿区2-2', '09011112222', '2003-05-02', 'member', 1);

-- 未承認ユーザー(承認待ち画面の動作確認用)
INSERT INTO users (id, line_user_id, line_name, role, is_approved)
VALUES (3, 'U_dummy_pending', 'まち じろう', 'member', 0);

-- 通常イベント(出欠登録の確認用)
INSERT INTO events (id, title, description, type, event_date, created_by, is_archived)
VALUES (10, 'テスト定例MTG', 'ローカル確認用のダミーイベント', 'event', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 1, 0);

-- アンケート(target_usersは管理者のみ。会員が回答すると自動で追加される)
INSERT INTO events (id, title, description, type, target_users, form_schema, event_date, created_by, is_archived)
VALUES (11, 'テストアンケート', 'ローカル確認用のダミーアンケート', 'survey', '[1]',
  '[{"type":"text","label":"好きな食べ物","required":true}]',
  DATE_ADD(CURDATE(), INTERVAL 7 DAY), 1, 0);

-- 公開ブログ記事
INSERT INTO blogs (id, title, content, author_id, is_published)
VALUES (20, 'テスト記事', '<p>これはローカル確認用のダミー記事です。</p>', 1, 1);
