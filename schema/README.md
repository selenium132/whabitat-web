# schema/

本番DBの構造（テーブル定義のみ・データは含まない）をここにバージョン管理する。

## 更新方法

本番サーバー（Xserver）側で、DBに接続できる環境（SSH等）から実行する。
このリポジトリの開発環境（手元PC）からは `DB_HOST=localhost` のため直接実行できない。

```bash
php scripts/export_schema.php > schema/schema.sql
```

生成された `schema/schema.sql` をコミットする。テーブル構造を変更した
タイミング（カラム追加など）で都度これを実行し、差分をコミットすること。

## ローカル開発での使い方

```bash
mysql -uroot your_local_db < schema/schema.sql
```

でローカルDBにテーブル構造だけ再現できる（データはシードスクリプト等で別途用意する）。
