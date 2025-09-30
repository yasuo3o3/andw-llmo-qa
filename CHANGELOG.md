# Changelog

All notable changes to andW llmo-qa plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.07] - 2024-09-30

### ブランド統合
- **Plugin Name**: `andW llmo-qa（汎用質問・即答・解説）`に変更（前置方式）
- **Text Domain**: `llmo-qa` → `andw-llmo-qa`に統一
- **File Names**: `llmo-qa.php` → `andw-llmo-qa.php`
- **PHP Classes**: `LLMO_QA_*` → `Andw_Llmo_QA_*`
- **Identifiers**: `llmoqa_*` → `andw_llmoqa_*`
- **Gutenberg Blocks**: `llmo/*` → `andw/llmo-*`
- **Shortcodes**: `[llmo_qa_*]` → `[andw_llmo_qa_*]`

### 技術的変更
- 既存の`llmo`ブランドを維持しつつ`andw`ブランドを前置
- 後方互換性の考慮：既存設定・メタフィールドの移行が必要
- uninstall.phpでの正しいオプション名対応

## [0.04] - 2024-09-01

### セキュリティ強化
- **CSV Upload**: 実体MIME検査・サイズ上限（2MB）・行数上限（50,000行）を追加
- **File Validation**: `wp_check_filetype_and_ext()` + `finfo` による二重検証を実装
- **CSRF Protection**: `check_admin_referer()` による強化されたnonce検証を追加
- **Permission**: `current_user_can('manage_options')` の厳格な権限チェックを追加

### DOMDocument処理改善
- **Network Safety**: `LIBXML_NONET` フラグでネットワークアクセスを禁止
- **Error Handling**: `libxml_get_errors()` による包括的エラー管理を実装
- **Exception Safety**: try-finally文による確実なリソース管理を追加
- **Fallback**: 処理失敗時の元コンテンツ返却を実装

### CSV処理改善  
- **Parser**: `SplFileObject` + `fgetcsv()` による安全なCSV処理に変更
- **Encoding**: UTF-8統一 + BOM除去機能を追加
- **Validation**: 列数・データ形式の厳密チェックを実装
- **Limits**: ファイルサイズ・行数制限の定数化（`CSV_MAX_SIZE`, `CSV_MAX_ROWS`）

### マルチサイト完全対応
- **Network Activation**: `get_sites()` + `switch_to_blog()` による全サイト処理を実装
- **Individual Sites**: 個別サイト有効化での適切な権限管理を追加
- **Uninstall**: マルチサイト環境での完全なアンインストール処理を実装
- **Data Control**: サイトごとの独立したデータ管理を実装

### 新機能
- **Kill Switch**: `andwqa_disabled` オプションによる緊急停止機能を追加
- **Data Retention**: アンインストール時のデータ保持オプションを追加
- **Testing Framework**: `TESTING.md` による実測・テスト指針を追加
- **Security Documentation**: `SECURITY.md` による包括的セキュリティドキュメントを追加

### ドキュメント更新
- **readme.txt**: WordPress 6.8対応、PHP 8.0要件、Privacyセクション追加
- **Version Sync**: プラグインヘッダーとreadme.txtのバージョン統一
- **Changelog**: 詳細な変更履歴の追加

### 技術改善
- **Error Escaping**: 全エラーメッセージの `esc_html()` 適用
- **Constants**: 設定値の定数化による保守性向上
- **File Handling**: 一時ファイルの適切なクリーンアップを実装

## [0.03] - 2024-08-xx

### 新機能
- **Tag System**: `qa_tag` タクソノミーによる横断タグ機能を追加
- **Tag UI**: チェックボックス型タグ選択メタボックスを実装
- **Block Enhancement**: qa-list・qa-indexブロックでタグ絞り込み対応
- **Tag Archive**: 専用のタグアーカイブテンプレート追加
- **Tag Display**: 単体記事でのタグチップ表示機能
- **Tag Highlight**: 本文中のタグ語を安全にハイライトする機能

### CSV機能拡張
- **Tag Import**: CSVのtagsカラムでタグ一括設定に対応
- **Tag Processing**: 新規タグの自動作成機能

## [0.02] - 2024-xx-xx

### 初期リリース
- **Core Structure**: Q&Aカスタムポストタイプとタクソノミーの基本実装
- **Block Editor**: 3つの専用Gutenbergブロック（即答・一覧・インデックス）
- **CSV Import**: 基本的なCSV一括インポート機能  
- **SEO**: JSON-LD FAQPage構造化データの自動出力
- **Templates**: カスタムテンプレートシステム
- **Shortcodes**: `[llmo_qa_list]` と `[llmo_qa]` ショートコード
- **Related Links**: 同カテゴリ関連記事の自動挿入機能

---

## セキュリティ対応履歴

### v0.04 対応項目
- [x] CSRF 強化（二重nonce + admin_referer）
- [x] ファイルアップロード堅牢化（実体検証・サイズ制限）  
- [x] XML外部実体参照禁止（LIBXML_NONET）
- [x] 権限管理厳格化（manage_options強制）
- [x] エスケープ処理完全実装
- [x] キルスイッチによる緊急停止機能

### 今後の予定
- [ ] i18n対応（__(), _e() 関数適用）
- [ ] REST API権限カスタマイズ
- [ ] ログ機能拡充（必要に応じて）
- [ ] パフォーマンス最適化