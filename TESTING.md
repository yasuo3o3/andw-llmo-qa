# テスト手順書

## v0.05 新機能テスト計画

### 必須テストケース

#### 1. Answer Container ブロックテスト

**許可ブロックのテスト**
- [ ] 段落ブロック（core/paragraph）の挿入・編集
- [ ] 見出しブロック（core/heading）のH3/H4レベル制限
- [ ] リストブロック（core/list）の順序付き・順序なし
- [ ] 引用ブロック（core/quote）の挿入・スタイル
- [ ] 画像ブロック（core/image）のアップロード・表示

**禁止ブロックのテスト**
- [ ] 動画ブロック（core/video）挿入時の警告表示
- [ ] 埋め込みブロック（core/embed）の自動ブロック
- [ ] HTMLブロック（core/html）の挿入拒否
- [ ] ファイルブロック（core/file）の制限確認
- [ ] YouTube埋め込み（core-embed/youtube）の検出

**期待される動作**
- 禁止ブロック挿入時に赤色警告バナー表示
- スキーマ出力が自動停止される
- 管理画面に通知メッセージ表示（5分間）

#### 2. Gutenbergサイドバーパネルテスト

**自動/手動切替**
- [ ] 初期状態：自動モード（手動フラグ=false）
- [ ] 手動モード切替時にテキストエリア表示
- [ ] 自動モード復帰時にテキストエリア非表示
- [ ] 切替時のメタデータ正常保存

**スキーマプレビュー**
- [ ] 自動モード：コンテンツ変更時のリアルタイム更新
- [ ] 手動モード：テキストエリア内容の即座反映
- [ ] 文字数カウンタの正確性（1000文字制限）
- [ ] プレビューローディング表示

**出力制御**
- [ ] スキーマ出力ON/OFF切替の正常動作
- [ ] 投稿単位制御の独立性確認
- [ ] Kill Switchとの併用動作

#### 3. 保存時ロジックテスト

**Answer Container検出**
- [ ] Answer Containerあり：内容のメタデータ保存
- [ ] Answer Containerなし：投稿本文全体からプレーン生成
- [ ] 複数Answer Container：最初のもので処理
- [ ] 空Answer Container：エラーハンドリング

**プレーンテキスト生成**
- [ ] HTMLタグの適切な除去
- [ ] 改行・空白の正規化
- [ ] 1000文字制限の確実な適用
- [ ] 日本語文字の正確なカウント

**禁止ブロック検出時処理**
- [ ] 検出時の自動スキーマ停止
- [ ] Transient通知の正常セット
- [ ] 管理者通知の表示（次回管理画面アクセス時）

#### 4. 表示仕様テスト

**単一Q&Aページ（single-qa.php）**
- [ ] Answer Containerリッチコンテンツの表示
- [ ] 既存投稿本文との後方互換性
- [ ] Kill Switch有効時の停止画面
- [ ] タグチップ表示（設定有効時）

**一覧ページ（archive-qa.php）**
- [ ] 既定：プレーン要約表示（160文字+省略記号）
- [ ] リッチ表示設定：Answer Container内容表示
- [ ] 既存即答フィールドとの互換性
- [ ] 空コンテンツ時のフォールバック

#### 5. JSON-LD出力テスト

**QAPage形式出力**
- [ ] 単一投稿でのQAPage形式確認
- [ ] スキーマ用プレーンテキストの使用
- [ ] 投稿単位制御の反映（OFF時は非出力）
- [ ] Kill Switch時の完全停止

**出力内容検証**
- [ ] JSON構文の正確性
- [ ] schema.orgスキーマ準拠
- [ ] エスケープ処理の適切性
- [ ] 文字エンコーディング（UTF-8）

**非出力条件**
- [ ] 一覧ページでは出力しない
- [ ] スキーマテキストが空の場合
- [ ] 投稿単位制御がOFFの場合

#### 6. WP-CLI移行テスト

**基本機能**
- [ ] `wp andwqa migrate-schema --dry-run` でプレビュー実行
- [ ] `wp andwqa migrate-schema` で実際の移行
- [ ] 進捗バーの正常表示
- [ ] 処理結果サマリの表示

**Multisite対応**
- [ ] `--network` オプションでの全サイト処理
- [ ] サイト切替の正常動作
- [ ] 各サイトの独立処理
- [ ] 集計結果の正確性

**エラーハンドリング**
- [ ] Kill Switch有効時のスキップ動作
- [ ] 処理対象投稿がない場合
- [ ] 不正データに対する例外処理
- [ ] 権限不足時のエラー表示

## 環境別テストマトリックス

### 対象環境
- **WordPress**: 6.0, 6.5, 6.8
- **PHP**: 8.0, 8.1, 8.2
- **環境**: シングルサイト、Multisite

### ブラウザ互換性
- [ ] Chrome（最新版）
- [ ] Firefox（最新版）
- [ ] Safari（最新版）
- [ ] Edge（最新版）

### デバイス対応
- [ ] デスクトップ（1920x1080）
- [ ] タブレット（1024x768）
- [ ] スマートフォン（375x667）

## セキュリティテスト

### XSS対策テスト
- [ ] スクリプトタグを含むコンテンツの適切なエスケープ
- [ ] メタデータ出力時のHTMLエスケープ
- [ ] JSON-LD出力時の文字エンコーディング

### XXE攻撃対策テスト
- [ ] LIBXML_NONET設定の確認
- [ ] 外部エンティティ参照の拒否
- [ ] DOM処理時の安全性確認

### 権限テスト
- [ ] 編集者権限での適切な制限
- [ ] 購読者権限でのアクセス拒否
- [ ] 未ログインユーザーでの管理機能無効化

## パフォーマンステスト

### 大量データ処理
- [ ] 1000件以上のQ&A投稿での一覧表示速度
- [ ] 長文コンテンツ（10,000文字）での処理時間
- [ ] WP-CLI移行コマンドでの大量データ処理

### メモリ使用量
- [ ] プレーンテキスト生成時のメモリ消費
- [ ] DOM処理時のリソース使用量
- [ ] Multisite環境での並列処理負荷

## 回帰テスト（既存機能）

### 既存ブロック動作確認
- [ ] 即答（LLMO）ブロックの正常動作
- [ ] Q&A一覧ブロックの表示
- [ ] Q&Aインデックスブロックの機能

### 既存管理機能
- [ ] CSVインポート機能の継続動作
- [ ] 関連Q&A自動表示
- [ ] タグ語ハイライト機能
- [ ] チェックボックス型タグメタボックス

## 自動テストスクリプト

### PHPUnit テスト項目
```php
// 基本機能テスト例
class LLMO_QA_Test extends WP_UnitTestCase {
    public function test_plain_text_generation()
    public function test_forbidden_block_detection()
    public function test_schema_output()
    public function test_meta_field_validation()
}
```

### JavaScript テスト項目
```javascript
// Gutenbergブロックテスト例
describe('Answer Container Block', () => {
    test('allows permitted blocks')
    test('blocks forbidden blocks')
    test('shows warning on forbidden block detection')
})
```

## 本番環境デプロイ前チェックリスト

### コード品質
- [ ] PHPCS（WordPress Coding Standards）エラー0件
- [ ] ESLint警告への適切な対応
- [ ] セキュリティスキャン実行

### 設定確認
- [ ] Kill Switchの動作確認
- [ ] 新設定項目のデフォルト値確認
- [ ] autoloadオプションの適切な設定

### バックアップ
- [ ] データベース完全バックアップ
- [ ] ファイルシステムバックアップ
- [ ] WP-CLI移行テスト（別環境）

### ドキュメント
- [ ] README.md更新確認
- [ ] SECURITY.md追記確認
- [ ] Changelog記録完了

## トラブルシューティング項目

### 一般的な問題
1. **Answer Containerが表示されない**
   - ブロックエディタのリフレッシュ
   - キャッシュプラグインの無効化確認

2. **スキーマ出力されない**
   - Kill Switch状態確認
   - 投稿単位制御の確認
   - プレーンテキスト生成状況確認

3. **WP-CLI移行エラー**
   - PHP実行時間制限の確認
   - メモリ制限の確認
   - ファイル権限の確認

### ログ確認箇所
- `wp-content/debug.log`（WP_DEBUG有効時）
- サーバーエラーログ
- ブラウザコンソールエラー
- ネットワークタブ（Ajax通信エラー）

## 受入れ基準（Definition of Done）

すべてのテストケースが以下の基準を満たすこと：

1. **機能要件**
   - [ ] Answer Container内でリッチ編集が可能
   - [ ] 禁止ブロック挿入時に即座に警告表示
   - [ ] プレーンテキストが自動生成される

2. **表示要件**
   - [ ] 単一ページ＝リッチ表示
   - [ ] 一覧ページ＝プレーン要約表示（既定）

3. **スキーマ要件**
   - [ ] プレーンテキストのみをJSON-LDに使用
   - [ ] Kill Switch/停止条件で完全に出力停止

4. **性能要件**
   - [ ] WP-CLI移行が件数ログ付きで成功
   - [ ] ページロード時間が既存比150%以内

5. **品質要件**
   - [ ] PHPCS（WPCS）エラー0件
   - [ ] セキュリティスキャン通過
   - [ ] 複数ブラウザで正常動作

## テスト報告書テンプレート

```
## テスト実行結果
実行日: YYYY-MM-DD
実行者: [名前]
環境: WordPress x.x, PHP x.x

### 結果サマリー
- 実行テストケース: XX件
- 成功: XX件  
- 失敗: XX件
- 保留: XX件

### 失敗項目詳細
[項目名] - [エラー内容] - [対応方針]

### 備考
[特記事項があれば記載]
```
# andW llmo-qa プラグイン テスト指針

## 実測値記録欄

### autoload総量測定
```sql
-- MySQL/MariaDB で実行
SELECT SUM(LENGTH(option_value)) AS autoload_bytes 
FROM wp_options 
WHERE autoload='yes';
```

**記録欄:**
- プラグイン有効化前の autoload 総量: ________ bytes
- プラグイン有効化後の autoload 総量: ________ bytes  
- 増加分: ________ bytes

### WP-CLI での測定
```bash
# autoload オプション一覧
wp option list --autoload=yes --format=table

# 特定プラグインのオプション確認
wp option list --search="andwqa_*" --format=table

# データベースサイズ確認
wp db size --human-readable

# プラグインが追加したオプションの確認
wp option get andwqa_rel_on
wp option get andwqa_rel_num
wp option get andwqa_tag_display
wp option get andwqa_tag_highlight
wp option get andwqa_disabled
```

### パフォーマンス実測

#### 管理画面での追加クエリ数
```bash
# Query Monitorプラグインを使用して測定
# または以下のコードを functions.php に追加して測定
```

**記録欄:**
- 管理画面（Q&A一覧）での総クエリ数: ________ queries
- うちプラグイン追加分: ________ queries
- 管理画面（Q&A編集）での総クエリ数: ________ queries
- うちプラグイン追加分: ________ queries

#### フロントエンド実測
**記録欄:**
- Q&A一覧ページ（/qa/）の総クエリ数: ________ queries
- Q&A単体ページでの総クエリ数: ________ queries
- 関連リンク機能有効時の追加クエリ数: ________ queries

#### アセットサイズ実測
```bash
# プラグインディレクトリで実行
find . -name "*.css" -exec wc -c {} +
find . -name "*.js" -exec wc -c {} +
```

**記録欄:**
- CSS合計サイズ: ________ bytes (________ KB)
- JS合計サイズ: ________ bytes (________ KB)
- 管理画面で読み込まれるアセット数: ________ ファイル
- フロントエンドで読み込まれるアセット数: ________ ファイル

## 機能テスト項目

### 基本機能テスト
- [ ] プラグイン有効化 → Q&Aメニュー表示
- [ ] Q&A新規作成 → 即答メタボックス表示・保存
- [ ] カテゴリ・タグ設定 → 正常に保存・表示
- [ ] フロント表示（/qa/）→ 一覧正常表示
- [ ] 単体表示（/qa/記事スラッグ/）→ 即答・本文正常表示

### ブロック機能テスト
- [ ] 即答ブロック → エディタで正常編集・保存
- [ ] Q&A一覧ブロック → 属性設定・プレビュー正常
- [ ] Q&Aインデックスブロック → 複数カテゴリ正常表示

### CSV インポート機能テスト
- [ ] 正常CSVファイル → ドライラン成功
- [ ] 正常CSVファイル → 実際のインポート成功
- [ ] 不正ファイル（非CSV）→ 適切なエラー表示
- [ ] サイズ上限超過ファイル → 適切なエラー表示
- [ ] 必須カラム不足CSV → 適切なエラー表示

### セキュリティテスト
- [ ] 非管理者でCSVアップロード → 権限エラー
- [ ] nonce なしでCSVアップロード → セキュリティエラー  
- [ ] 不正なファイルタイプアップロード → 拒否
- [ ] XSS を含むCSVデータ → 適切にサニタイズされて保存

### マルチサイトテスト（該当環境のみ）
- [ ] ネットワーク有効化 → 全サイトで機能動作
- [ ] 個別サイト有効化 → そのサイトのみ機能動作
- [ ] ネットワーク無効化 → 全サイトで機能停止

### キルスイッチテスト
- [ ] キルスイッチ有効化 → フロント機能完全停止
- [ ] キルスイッチ有効化 → 管理機能完全停止（設定画面以外）
- [ ] キルスイッチ無効化 → 全機能正常復帰

### アンインストールテスト
- [ ] アンインストール → オプション完全削除
- [ ] アンインストール（データ保持）→ Q&A投稿残存
- [ ] アンインストール（データ削除）→ Q&A投稿完全削除
- [ ] アンインストール後 → rewrite rules クリア確認

## パフォーマンス予算

| 項目 | 予算 | 実測値 | 合格 |
|------|------|--------|------|
| autoload 増加分 | < 2KB | _____ bytes | [ ] |
| CSS 合計サイズ | < 10KB | _____ bytes | [ ] |
| JS 合計サイズ | < 15KB | _____ bytes | [ ] |
| 管理画面追加クエリ | < 5 queries | _____ queries | [ ] |
| フロント追加クエリ | < 3 queries | _____ queries | [ ] |

## 実測実行例

```bash
# 1. プラグイン有効化前の状態測定
wp option list --autoload=yes --format=count
wp db query "SELECT SUM(LENGTH(option_value)) AS autoload_bytes FROM wp_options WHERE autoload='yes';" --skip-column-names

# 2. プラグイン有効化

# 3. プラグイン有効化後の状態測定  
wp option list --autoload=yes --format=count
wp db query "SELECT SUM(LENGTH(option_value)) AS autoload_bytes FROM wp_options WHERE autoload='yes';" --skip-column-names

# 4. 増加分計算・記録
```

## 注意事項

- 実測は本番環境に近い状態（データ数・サーバースペック）で実施
- Query Monitor プラグインを併用してクエリ詳細を確認
- 複数回測定して平均値を記録
- パフォーマンス予算を超過した場合は対策を検討・実装
