=== andW llmo-qa（汎用質問・即答・解説） ===
Contributors: netservice
Tags: qa, faq, schema, llm, ai
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.07
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AIとの相性を重視した構造化Q&Aプラグイン。リッチ表示とクリーン検索の二層構造で、LLM学習と検索最適化を両立。

== Description ==

andW llmo-qaは「見た目はリッチ、検索はクリーン」のコンセプトで設計された次世代Q&Aプラグインです。

**v0.06の主要新機能（OpenAI統合による自動要約）**
* OpenAI API統合：GPT-4o-mini/GPT-4oによる高品質要約生成
* AI要約自動補完：メタが空の場合に自動実行
* 管理画面拡張：APIキー設定・モデル選択・タイムアウト制御
* フォールバック表示：要約未設定時の自動生成（一覧ページ）
* セキュリティ強化：包括的エラーログとAPI通信保護

**コア機能**
* カスタム投稿タイプ「qa」で構造化されたQ&A管理
* Gutenberg完全対応のリッチエディタ
* JSON-LD（QAPage）による構造化データ自動出力
* 禁止ブロック検出システム（動画/埋め込み/HTMLブロック等）
* WP-CLI移行コマンド（Multisite対応）
* CSV一括インポート機能
* タグ語ハイライト機能
* 関連Q&A自動表示

**セキュリティ対応**
* Kill Switch機能で緊急時の全機能停止
* LIBXML_NONET指定でXXE攻撃対策
* 適切な権限チェックとNonce検証
* XSS対策（esc_html/esc_attr/esc_url徹底）

**プライバシー（v0.06アップデート）**
* ローカル処理優先：OpenAI APIは明示的に有効化した場合のみ使用
* APIキー暗号化：データベース保存時のセキュリティ強化
* 外部通信制限：SSL検証・タイムアウト制御・リダイレクト無効化
* ログ管理：デバッグモード時のみ詳細ログを出力

== Installation ==

1. プラグインファイルを `/wp-content/plugins/andw-qa/` にアップロード
2. WordPress管理画面でプラグインを有効化
3. 「設定」→「パーマリンク設定」で保存してURLリライトを有効化
4. 「Q&A」→「設定」でOpenAI API設定（任意）
5. 「Q&A」→「新規Q&Aを追加」で最初の投稿を作成

**Answer Containerブロックの使用**
1. Q&A投稿の編集画面で「Answer Container」ブロックを挿入
2. 内部に段落、見出し、リスト、引用、画像ブロックを配置
3. 禁止ブロック（動画、埋め込み、HTML等）は自動検出・警告表示
4. 保存時にプレーンテキスト版が自動生成され、JSON-LD出力に使用

**WP-CLI移行（v0.06拡張）**
既存データの移行とAI要約生成にはWP-CLIコマンドを使用：
```
wp andw_llmoqa migrate-schema --dry-run     # プレビュー実行
wp andw_llmoqa migrate-schema               # 実際の移行
wp andw_llmoqa migrate-schema --network     # Multisite全体
wp andw_llmoqa migrate-schema --enable-ai   # AI要約も実行
wp andw_llmoqa migrate-schema --verbose     # 詳細ログ出力
```

== Frequently Asked Questions ==

= v0.06へのアップグレード時の注意点は？ =

v0.06は既存機能を拡張し、OpenAI API統合を追加しました。アップグレード手順：
```
wp andw_llmoqa migrate-schema --dry-run     # 既存データ確認
wp andw_llmoqa migrate-schema --enable-ai   # AI要約も適用する場合
```
OpenAI APIの使用は任意です。従来通りローカル要約のみでも動作します。

= OpenAI APIキーの設定方法は？ =

管理画面「Q&A」→「設定」→「AI要約設定」タブでAPIキーを設定します。GPT-4o-miniまたはGPT-4oを選択可能で、タイムアウト時間も調整できます。APIキーは暗号化して保存されます。

= Answer Containerブロックは必須ですか？ =

必須ではありません。Answer Containerを使用しない場合は投稿本文全体からプレーンテキストを生成します。既存の「即答」フィールドも引き続き使用可能です。

= 禁止ブロックとは何ですか？ =

動画、埋め込み、HTMLブロックなど、検索エンジンのクロールや構造化データ出力に適さないブロックです。これらが検出されると自動的にスキーマ出力が停止されます。

= JSON-LDの形式が変わりましたか？ =

v0.05では単一Q&AページでQAPage形式を使用し、一覧ページでのJSON-LD出力は停止しました（混在リスク回避のため）。

= AI要約の仕組みはどうなっていますか？ =

v0.06では「ローカル→AI階層化」を採用しています：
1. まずローカル要約（HTMLタグ除去・整形）を生成
2. 要約メタが空の場合、OpenAI APIで要約を改善（API有効時）
3. API無効またはエラー時は、ローカル要約をそのまま使用

これにより、API障害時でも確実に動作します。

= Multisiteに対応していますか？ =

はい。v0.06でもMultisite対応を強化しています。WP-CLIコマンドで `--network` オプションを使用することで、ネットワーク全体での一括処理が可能です。各サイトごとに異なるOpenAI設定も可能です。

== Screenshots ==

1. OpenAI API設定画面（タブ式管理画面）
2. Answer Containerブロックでの構造化編集
3. Gutenbergサイドバーパネルでのスキーマ設定（自動/手動切替）
4. 禁止ブロック検出時の警告表示
5. プレーンテキストスキーマのプレビュー
6. 一覧ページでのAI要約フォールバック表示
7. WP-CLI移行処理実行画面（AI要約付き）

== Changelog ==

= 0.07 =
**ブランド統合: andW前置方式**
* 変更: プラグイン名を「andW llmo-qa」に変更（前置方式）
* 変更: Gutenbergブロック名前空間を `llmo/` から `andw/llmo-` に変更
* 変更: CSS/JS識別子を `llmoqa_` から `andw_llmoqa_` に統一
* 変更: PHPクラス名を `LLMO_QA_` から `Andw_Llmo_QA_` に変更
* 変更: Text Domainを `llmo-qa` から `andw-llmo-qa` に変更
* 変更: ショートコードを `[llmo_qa_*]` から `[andw_llmo_qa_*]` に変更
* 注意: 既存サイトではデータ移行が必要（設定・メタフィールド）

= 0.06 =
**マイナーアップデート: OpenAI API統合とAI要約機能**
* 新機能: OpenAI API統合（GPT-4o-mini/GPT-4o対応）
* 新機能: AI要約自動補完（メタが空の場合に自動実行）
* 新機能: タブ式管理画面（基本設定・AI要約設定・CSV一括処理）
* 新機能: フォールバック要約表示（一覧ページでの自動要約生成）
* 新機能: API接続テスト機能（管理画面内）
* 拡張: WP-CLI移行コマンド（--enable-ai、--verbose オプション追加）
* 拡張: Gutenbergサイドバー（自動/手動モード切替、読み取り専用表示）
* セキュリティ: 包括的エラーログシステム（デバッグモード対応）
* セキュリティ: OpenAI API通信保護（SSL検証・タイムアウト制御・リダイレクト無効化）
* セキュリティ: APIキー暗号化とマスキング表示
* 改善: プレビュー生成のAJAX処理最適化
* 改善: 保存時自動補完のエラーハンドリング強化

= 0.05 =
**メジャーアップデート: 回答エリアのYシグ化（ブロック化）+ スキーマ二層化**
* 新機能: Answer Containerブロック（InnerBlocks許可制）
* 新機能: 自動プレーンテキスト生成とスキーマ二層管理
* 新機能: Gutenbergサイドバーパネル（自動/手動切替、プレビュー、出力制御）
* 新機能: 禁止ブロック検出システム（動画/埋め込み/HTML等）
* 新機能: 投稿単位スキーマ制御（個別ON/OFF設定）
* 新機能: WP-CLI移行コマンド（`wp andw_llmoqa migrate-schema`）
* 変更: JSON-LD形式をQAPageに変更（単一投稿のみ出力）
* 変更: 一覧ページではプレーン要約を既定表示
* 変更: Kill Switch機能で緊急時全機能停止
* 改善: セキュリティ強化（LIBXML_NONET、権限チェック徹底）
* 改善: Multisite完全対応（network activate/deactivate/uninstall）

= 0.03 =
* 初期リリース
* Q&Aカスタム投稿タイプとタクソノミー
* 即答ブロックとメタフィールド
* JSON-LD（FAQPage）自動出力
* CSV一括インポート
* タグ機能とハイライト
* 関連Q&A自動表示

== Upgrade Notice ==

= 0.07 =
リブランディングにより、プラグイン名とブロック名前空間が変更されました。既存サイトでは設定とメタフィールドのデータ移行が必要です。Gutenbergブロックは自動更新されますが、既存ページで「ブロックが見つかりません」警告が表示される場合があります。

= 0.06 =
OpenAI API統合により、AI要約機能を追加。既存のローカル要約システムを拡張し、フォールバック機能で信頼性を確保。APIキーは任意設定で、従来通りローカルのみでも動作します。

= 0.05 =
メジャーアップデート。Answer Container導入により、見た目とスキーマの完全分離を実現。既存データの移行にはWP-CLIコマンドの使用を推奨します。Multisite環境では各サイトで個別有効化が必要です。

== Privacy Notice ==

このプラグインは以下のプライバシー原則に従います：

**データ収集なし**: ユーザーの個人情報や行動データを収集しません
**外部通信制限**: OpenAI API使用時も要約テキストのみ送信（個人情報は含めない）
**ローカル処理優先**: AI機能はオプトイン方式、無効時はローカル処理のみ
**APIキー保護**: 暗号化保存、マスキング表示、適切な権限制御
**セキュリティ重視**: XSS、XXE等の攻撃対策、SSL検証、タイムアウト制御
**透明性**: エラーログはデバッグモード時のみ出力、外部送信なし

**OpenAI API使用時の注意**:
- 要約生成のためコンテンツテキストをOpenAIに送信します
- 個人情報や機密情報を含む投稿での使用は避けてください
- APIキーの管理は管理者の責任で行ってください

詳細なセキュリティ情報については、プラグインディレクトリ内のSECURITY.mdファイルを参照してください。