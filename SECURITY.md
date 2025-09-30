# セキュリティポリシー

## サポートされるバージョン

現在セキュリティアップデートを提供しているバージョン：

| バージョン | サポート |
| ------- | -------- |
| 0.05.x   | ✅ |
| 0.03.x   | ❌ |
| < 0.03   | ❌ |

## 脆弱性の報告

セキュリティに関する問題を発見した場合は、以下の手順で報告してください：

### 報告方法
1. **公開issue作成前の事前報告**: 重大な脆弱性の場合は、GitHubの公開issueではなく、まず開発者に直接連絡
2. **報告先**: security@netservice.jp
3. **必要情報**: 
   - 影響するバージョン
   - 再現手順
   - 想定される影響範囲
   - 可能であれば修正提案

### 対応プロセス
- **24時間以内**: 受領確認
- **72時間以内**: 初期評価と重要度判定
- **2週間以内**: 修正版リリース（重要度に応じて調整）

## セキュリティ機能

### 基本セキュリティ対策

#### 入力検証・サニタイゼーション
- **ユーザー入力**: `wp_kses_post()`, `sanitize_title()`, `absint()` による適切なサニタイゼーション
- **メタデータ**: 保存前のデータ検証と型チェック
- **ファイルアップロード**: `is_uploaded_file()` による検証

#### 出力エスケープ
- **HTML出力**: `esc_html()`, `esc_attr()`, `esc_url()` の徹底
- **JavaScript**: `wp_localize_script()` による安全なデータ受け渡し
- **JSON-LD**: `wp_json_encode()` による適切なエンコーディング

#### 権限・認証チェック
- **管理機能**: `current_user_can('manage_options')` による制限
- **編集機能**: `current_user_can('edit_posts')` による制限
- **Nonce検証**: すべてのフォーム送信で `wp_verify_nonce()` 実装

### Answer Container固有のセキュリティ

#### 禁止ブロック検出システム
以下のブロックを禁止ブロックとして検出・制御：

**動画・メディア系**
- `core/video`
- `core/audio`
- `core/gallery`
- `core/media-text`
- `core/cover`

**埋め込み系**
- `core/embed`
- `core-embed/youtube`
- `core-embed/twitter`
- `core-embed/facebook`
- `core-embed/instagram`
- `core-embed/vimeo`
- `core-embed/soundcloud`
- `core-embed/spotify`
- その他すべての埋め込みブロック

**危険性のあるブロック**
- `core/html` (任意HTMLの実行防止)
- `core/file` (ファイルダウンロードリンク制限)
- `core/freeform` (クラシックエディタコンテンツ制限)

#### 自動停止条件
- 禁止ブロックが検出された場合、スキーマ出力を自動停止
- `andwqa_stop_schema_on_forbidden` オプション（既定: true）で制御可能
- 管理者に自動通知（Transient API使用、5分間有効）

### XML External Entity (XXE) 攻撃対策

#### DOMDocument処理における対策
```php
// LIBXML_NONET フラグによる外部エンティティアクセス防止
$dom->loadHTML($html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
```

#### 適用箇所
- タグ語ハイライト機能でのDOM処理
- プレーンテキスト生成時のHTML解析

### Kill Switch機能

#### 緊急時停止システム
- オプション: `andwqa_disabled`
- 効果: プラグイン全機能の即座停止
- 適用範囲: フロントエンド表示、管理機能、API、JSON-LD出力

#### 使用例
```php
// wp-config.php または管理画面から設定
update_option('andwqa_disabled', true);
```

### データベースセキュリティ

#### メタデータ保護
- すべてのメタフィールドに適切なauth_callbackを設定
- REST API経由での不正アクセス防止
- autoloadオプションの適切な管理（`'no'`指定でパフォーマンス向上）

#### SQL Injection対策
- WordPress標準のデータベースAPI (`$wpdb->prepare()`) の使用
- 直接SQL文の回避
- バインドパラメータによる安全な値渡し

## WP-CLI セキュリティ

### 権限制限
- WP-CLIコマンドは管理者権限でのみ実行可能
- `--network` オプションはMultisite管理者のみ

### 安全な処理
- `--dry-run` オプションによる事前確認機能
- エラーハンドリングとロールバック機能
- プログレスバー表示によるプロセス透明性

## Multisite セキュリティ

### サイト分離
- 各サイトで独立したオプション管理
- ネットワーク管理者権限による適切な制御
- サイト間データ漏洩防止

### ネットワーク操作
- `switch_to_blog()` / `restore_current_blog()` の適切な使用
- ネットワーク全体操作時の権限チェック

## 既知の制限事項

### DOM処理の制限
- 非常に大きなHTML（1MB以上）での処理性能低下の可能性
- JavaScript実行コンテンツの除去（意図的な制限）

### ブロック制限
- InnerBlocks内での制限は再帰的に適用
- カスタムブロックは禁止リストに含まれていない場合許可

## セキュリティベストプラクティス

### 管理者向け推奨設定
1. **Kill Switch理解**: 緊急時の停止方法を把握
2. **権限管理**: `edit_posts`権限の適切な付与
3. **定期確認**: 禁止ブロック検出ログの確認
4. **バックアップ**: WP-CLI移行処理前の完全バックアップ

### 開発者向け推奨事項
1. **コード監査**: すべての入出力箇所でのセキュリティチェック
2. **テスト環境**: 本番環境での直接変更の回避
3. **ログ監視**: エラーログとセキュリティログの定期確認

## 更新履歴

### v0.05 セキュリティ改善
- XXE攻撃対策の実装 (LIBXML_NONET)
- 禁止ブロック検出システムの導入
- Kill Switch機能の実装
- Nonce検証の徹底
- 権限チェックの強化

## 連絡先

セキュリティに関する質問や報告：
- Email: security@netservice.jp
- 開発者: Netservice (https://netservice.jp/)

**注意**: セキュリティに関する問題は、可能な限り公開issueではなく直接報告を推奨します。
# andW llmo-qa Security Documentation

## セキュリティ実装方針

本プラグインは WordPress セキュリティベストプラクティスに準拠し、多層防御によるセキュリティを実装しています。

## 入出力経路別セキュリティ管理表

### 1. メタボックス（即答入力）
| 段階 | 実装内容 | 関数・機能 |
|------|----------|-----------|
| **入力** | POST データ | `$_POST['andwqa_short_answer']` |
| **権限チェック** | 投稿編集権限 | `current_user_can('edit_post', $post_id)` |
| **CSRF対策** | nonce検証 | `wp_verify_nonce()` |
| **Sanitize** | HTMLタグ許可 | `wp_kses_post()` |
| **保存** | メタデータ保存 | `update_post_meta()` |
| **出力** | HTMLエスケープ | `wp_kses_post()`, `wpautop()` |

### 2. CSV一括インポート
| 段階 | 実装内容 | 関数・機能 |
|------|----------|-----------|
| **入力** | ファイルアップロード | `$_FILES['andwqa_csv']` |
| **権限チェック** | 管理者権限必須 | `current_user_can('manage_options')` |
| **CSRF対策** | 二重nonce検証 | `wp_verify_nonce()` + `check_admin_referer()` |
| **ファイル検証** | MIME実体検査 | `wp_check_filetype_and_ext()` + `finfo_file()` |
| **サイズ制限** | 2MB上限 | `CSV_MAX_SIZE` 定数 |
| **行数制限** | 50,000行上限 | `CSV_MAX_ROWS` 定数 |
| **パース** | 安全なCSV処理 | `SplFileObject` + `fgetcsv()` |
| **Sanitize** | データクリーンアップ | `wp_kses_post()`, `sanitize_title()` |
| **保存** | WordPress API | `wp_insert_post()`, `wp_set_object_terms()` |
| **出力** | 管理画面エスケープ | `esc_html()`, `esc_attr()` |

### 3. 設定フォーム
| 段階 | 実装内容 | 関数・機能 |
|------|----------|-----------|
| **入力** | 設定値 | `$_POST[option_name]` |
| **権限チェック** | 設定変更権限 | WordPress Settings API |
| **CSRF対策** | 自動nonce | `settings_fields()` |
| **Validate** | 型・範囲検証 | `register_setting()` type指定 |
| **保存** | オプション保存 | `update_option()` |
| **出力** | 値エスケープ | `esc_attr()`, `checked()` |

### 4. REST API（ブロック用）
| 段階 | 実装内容 | 関数・機能 |
|------|----------|-----------|
| **入力** | JSON属性 | ブロックエディタAPI |
| **権限チェック** | 編集権限 | `auth_callback` |
| **Sanitize** | メタ登録時 | `register_post_meta()` |
| **保存** | 自動処理 | WordPress REST API |
| **出力** | JSON安全 | WordPress内蔵処理 |

### 5. ショートコード
| 段階 | 実装内容 | 関数・機能 |
|------|----------|-----------|
| **入力** | ショートコード属性 | `$atts` パラメータ |
| **権限チェック** | なし（公開機能） | - |
| **Sanitize** | 属性クリーンアップ | `shortcode_atts()` |
| **保存** | なし（表示のみ） | - |
| **出力** | HTMLエスケープ | `esc_html()`, `esc_url()`, `wp_kses_post()` |

### 6. フロントエンド表示
| 段階 | 実装内容 | 関数・機能 |
|------|----------|-----------|
| **入力** | データベース値 | `get_post_meta()`, `get_posts()` |
| **権限チェック** | 公開状態確認 | WordPress標準 |
| **処理** | テンプレート処理 | `template_include` フック |
| **出力** | 全データエスケープ | `esc_html()`, `esc_url()`, `wp_kses_post()` |

## セキュリティ機能詳細

### 1. ファイルアップロード保護
```php
// 実体とファイル名の二重MIME検証
$file_check = wp_check_filetype_and_ext($tmp, $filename);
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detected_mime = finfo_file($finfo, $tmp);

// 許可タイプの厳格チェック
$allowed_types = ['text/csv', 'application/csv', 'text/plain'];
if (!in_array($file_check['type'], $allowed_types) || 
    !in_array($detected_mime, $allowed_types)) {
    // 拒否処理
}
```

### 2. DOMDocument堅牢化
```php
// ネットワークアクセス禁止
$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);

// エラー管理とフォールバック
$errors = libxml_get_errors();
if (!$success || !empty($errors)) {
    return $content; // 処理失敗時は元コンテンツを返す
}
```

### 3. キルスイッチ機能
```php
// 構築時点での早期終了
if ((bool)get_option(self::OPT_DISABLED, false)) {
    return; // 全機能を無効化
}
```

## 権限モデル

### WordPress Capabilities マッピング
| 操作 | 必要権限 | チェック関数 |
|------|----------|-------------|
| Q&A作成・編集 | `edit_posts` | `current_user_can('edit_post', $post_id)` |
| CSV インポート | `manage_options` | `current_user_can('manage_options')` |
| プラグイン設定変更 | `manage_options` | WordPress Settings API |
| プラグイン有効化 | `activate_plugins` | WordPress 標準 |
| タグ・カテゴリ管理 | `manage_categories` | WordPress 標準 |

### マルチサイト権限
```php
// ネットワーク管理者のみネットワーク有効化可能
if (is_multisite() && is_network_admin()) {
    // 全サイト処理
}

// 各サイト管理者は個別サイトのみ管理可能
if (is_multisite() && !current_user_can('manage_options')) {
    return; // 権限不足
}
```

## 脆弱性対策

### 1. CSRF (Cross-Site Request Forgery)
- **メタボックス**: `wp_nonce_field()` + `wp_verify_nonce()`
- **CSV アップロード**: 二重nonce + `check_admin_referer()`
- **設定変更**: WordPress Settings API内蔵nonce

### 2. XSS (Cross-Site Scripting)
- **全出力**: `esc_html()`, `esc_attr()`, `esc_url()` 徹底使用
- **リッチコンテンツ**: `wp_kses_post()` でHTMLタグ制御
- **JSON-LD**: `wp_json_encode()` でエスケープ処理

### 3. SQLインジェクション
- **データベースアクセス**: WordPress API（`WP_Query`, `get_posts()` 等）のみ使用
- **生SQL不使用**: `$wpdb->prepare()` が必要な場面なし

### 4. 権限昇格
- **機能別権限チェック**: 操作ごとに適切な `current_user_can()` 実装
- **REST API**: `auth_callback` による権限制御

### 5. ファイルインクルージョン
- **パス制御**: `plugin_dir_path(__FILE__)` 使用
- **ABSPATH チェック**: 全PHPファイルで実装

### 6. XML External Entity (XXE)
- **DOMDocument**: `LIBXML_NONET` フラグでネットワーク禁止
- **外部実体**: 参照を完全に禁止

## セキュリティ設定推奨事項

### 1. サーバー設定
```php
// php.ini 推奨値
file_uploads = On
upload_max_filesize = 2M  // プラグイン制限と一致
max_file_uploads = 1
```

### 2. WordPress設定
```php
// wp-config.php 推奨設定
define('DISALLOW_FILE_EDIT', true);
define('FORCE_SSL_ADMIN', true);
```

### 3. プラグイン固有設定
```php
// キルスイッチの有効化（緊急時）
update_option('andwqa_disabled', true);

// アンインストール時のデータ削除制御
update_option('andwqa_delete_data_on_uninstall', false); // 保持（推奨）
```

## インシデント対応

### 1. セキュリティ問題発見時
1. **キルスイッチ有効化**: 管理画面でプラグイン機能を即座に無効化
2. **ログ確認**: WordPress セキュリティログの確認
3. **影響範囲調査**: データ漏洩・改ざんの有無確認

### 2. 脆弱性報告
セキュリティ脆弱性を発見された方は、以下までご報告ください：
- **連絡先**: security@netservice.jp
- **PGP公開鍵**: [公開鍵URL]（該当する場合）

### 3. 更新手順
1. **テスト環境**: まず開発環境でセキュリティパッチを適用
2. **バックアップ**: 本番環境のデータベース・ファイルをバックアップ
3. **適用**: 本番環境でプラグイン更新
4. **機能確認**: 全機能の正常動作を確認

## 監査・コンプライアンス

### セキュリティチェックリスト
- [ ] OWASP Top 10 対策実装済み
- [ ] WordPress VIP Security 準拠
- [ ] CSRF 対策完全実装
- [ ] XSS 対策完全実装
- [ ] 権限管理適切実装
- [ ] ファイルアップロード制限実装
- [ ] エラーハンドリング適切実装
- [ ] ログ記録機能実装（必要に応じて）

### 外部監査対応
本プラグインは以下の基準での外部セキュリティ監査に対応可能：
- WordPress.org Plugin Review ガイドライン
- OWASP Application Security Verification Standard (ASVS)
- 各種ペネトレーションテスト

---

**最終更新**: 2024年9月
**バージョン**: 0.04
