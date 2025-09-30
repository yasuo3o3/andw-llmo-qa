<?php
/**
 * WP-CLI: andW Q&A スキーマ移行コマンド
 * 
 * 使用例:
 * wp andw_llmoqa migrate-schema --dry-run
 * wp andw_llmoqa migrate-schema --network
 */

if (!defined('ABSPATH')) exit;

// WP-CLI が利用可能かチェック
if (!class_exists('WP_CLI')) {
    return;
}

class Andw_Llmo_QA_CLI_Command {

    /**
     * エラーログ用プロパティ
     */
    private $error_log = [];
    
    /**
     * 既存投稿から新スキーマ用プレーンテキストを生成
     * 
     * ## OPTIONS
     * 
     * [--dry-run]
     * : 実際の更新を行わず、処理内容のみ表示
     * 
     * [--network]
     * : Multisiteの全サイトで実行
     * 
     * [--force]
     * : 既にスキーマデータが存在する投稿も強制的に更新
     * 
     * [--enable-ai]
     * : AI要約機能も使用する（APIキー設定必要）
     * 
     * [--verbose]
     * : 詳細ログを出力
     * 
     * ## EXAMPLES
     * 
     *     wp andw_llmoqa migrate-schema
     *     wp andw_llmoqa migrate-schema --dry-run
     *     wp andw_llmoqa migrate-schema --network --dry-run
     *     wp andw_llmoqa migrate-schema --enable-ai --verbose
     * 
     * @when after_wp_load
     */
    public function migrate_schema($args, $assoc_args) {
        // Kill Switch チェック
        if (get_option(Andw_Llmo_QA_Plugin::OPT_DISABLED, false)) {
            WP_CLI::error('Kill Switch が有効のため、移行処理を実行できません。');
            return;
        }
        $dry_run = isset($assoc_args['dry-run']);
        $network = isset($assoc_args['network']);
        $force = isset($assoc_args['force']);
        $enable_ai = isset($assoc_args['enable-ai']);
        $verbose = isset($assoc_args['verbose']);
        
        // AI機能チェック
        if ($enable_ai) {
            $api_key = get_option(Andw_Llmo_QA_Plugin::OPT_OPENAI_API_KEY, '');
            $ai_enabled = get_option(Andw_Llmo_QA_Plugin::OPT_ENABLE_AI_SUMMARY, false);
            
            if (empty($api_key) || !$ai_enabled) {
                WP_CLI::warning('AI要約が有効化されていないか、APIキーが未設定です。ローカル要約のみ実行します。');
                $enable_ai = false;
            }
        }
        
        WP_CLI::log('=== andW Q&A スキーマ移行処理開始 ===');
        WP_CLI::log('オプション: ' . ($dry_run ? 'ドライラン ' : '') . 
                   ($network ? 'ネットワーク全体 ' : '') .
                   ($force ? '強制更新 ' : '') .
                   ($enable_ai ? 'AI要約対応 ' : '') .
                   ($verbose ? '詳細ログ' : ''));

        if ($network && is_multisite()) {
            $this->migrate_network($dry_run, $force, $enable_ai, $verbose);
        } else {
            $this->migrate_single_site($dry_run, $force, $enable_ai, $verbose);
        }
        
        // エラーログがある場合は出力
        if (!empty($this->error_log) && $verbose) {
            WP_CLI::log('=== エラー詳細 ===');
            foreach ($this->error_log as $error) {
                WP_CLI::warning($error);
            }
        }
        
        WP_CLI::success('移行処理が完了しました。');
    }

    /**
     * ネットワーク全体での移行処理
     */
    private function migrate_network($dry_run, $force, $enable_ai = false, $verbose = false) {
        $sites = get_sites(['number' => 0]);
        $site_count = count($sites);
        
        WP_CLI::log("Multisite検出: {$site_count}個のサイトで処理を実行します");
        
        $total_processed = 0;
        $total_updated = 0;
        $total_errors = 0;
        
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            $site_url = get_site_url();
            WP_CLI::log("サイト処理中: {$site_url}");
            
            $result = $this->migrate_single_site($dry_run, $force, $enable_ai, $verbose);
            
            $total_processed += $result['processed'];
            $total_updated += $result['updated'];
            $total_errors += $result['errors'];
            
            restore_current_blog();
        }
        
        WP_CLI::log('=== ネットワーク全体の結果 ===');
        WP_CLI::log("処理対象投稿: {$total_processed}件");
        WP_CLI::log("更新完了: {$total_updated}件");
        WP_CLI::log("エラー: {$total_errors}件");
    }

    /**
     * 単一サイトでの移行処理
     */
    private function migrate_single_site($dry_run, $force, $enable_ai = false, $verbose = false) {
        // Kill Switch チェック
        if (get_option(Andw_Llmo_QA_Plugin::OPT_DISABLED, false)) {
            WP_CLI::warning('Kill Switch が有効のため、処理をスキップします');
            return ['processed' => 0, 'updated' => 0, 'errors' => 0];
        }

        // Q&A投稿を取得
        $posts = get_posts([
            'post_type' => Andw_Llmo_QA_Plugin::CPT,
            'post_status' => ['publish', 'draft'],
            'numberposts' => -1,
            'orderby' => 'ID',
            'order' => 'ASC'
        ]);

        if (empty($posts)) {
            WP_CLI::log('Q&A投稿が見つかりませんでした');
            return ['processed' => 0, 'updated' => 0, 'errors' => 0];
        }

        $processed = 0;
        $updated = 0;
        $errors = 0;
        $total = count($posts);
        
        WP_CLI::log("{$total}件のQ&A投稿を処理します");
        
        $progress = \WP_CLI\Utils\make_progress_bar('移行処理中', $total);

        foreach ($posts as $post) {
            $processed++;
            
            try {
                $result = $this->migrate_single_post($post, $dry_run, $force, $enable_ai, $verbose);
                if ($result) {
                    $updated++;
                }
            } catch (Exception $e) {
                $errors++;
                $error_msg = "投稿ID {$post->ID}: " . $e->getMessage();
                WP_CLI::warning($error_msg);
                $this->error_log[] = $error_msg;
                
                // 詳細ログでスタックトレースも記録
                if ($verbose) {
                    $this->error_log[] = "スタックトレース: " . $e->getTraceAsString();
                }
            }
            
            $progress->tick();
        }
        
        $progress->finish();
        
        WP_CLI::log('=== 処理結果 ===');
        WP_CLI::log("処理対象投稿: {$processed}件");
        WP_CLI::log("更新完了: {$updated}件");
        WP_CLI::log("エラー: {$errors}件");
        
        return ['processed' => $processed, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * 単一投稿の移行処理
     */
    private function migrate_single_post($post, $dry_run, $force, $enable_ai = false, $verbose = false) {
        // 既存のスキーマデータをチェック
        $existing_schema = get_post_meta($post->ID, Andw_Llmo_QA_Plugin::META_ANSWER_SCHEMA, true);
        
        if (!$force && !empty($existing_schema)) {
            // 強制更新フラグがなく、既にスキーマデータが存在する場合はスキップ
            return false;
        }

        $plugin = new Andw_Llmo_QA_Plugin();
        
        // Answer Containerの内容を抽出
        $answer_display = '';
        if (!empty($post->post_content)) {
            $blocks = parse_blocks($post->post_content);
            $answer_display = $this->find_answer_container_content($blocks);
        }
        
        // プレーンテキスト生成のソースを決定
        $source_content = '';
        if (!empty($answer_display)) {
            $source_content = $answer_display;
        } elseif (!empty($post->post_content)) {
            $source_content = $post->post_content;
        } else {
            // 既存の即答フィールドを使用（後方互換性）
            $short_answer = get_post_meta($post->ID, Andw_Llmo_QA_Plugin::META_SHORT, true);
            if (!empty($short_answer)) {
                $source_content = $short_answer;
            }
        }

        if (empty($source_content)) {
            throw new Exception('変換可能なコンテンツが見つかりません');
        }

        // プレーンテキスト生成
        $plain_text = $plugin->generate_plain_from_rich($source_content);
        
        if (empty($plain_text)) {
            throw new Exception('プレーンテキストの生成に失敗しました');
        }
        
        // v0.06: AI要約を試行（有効な場合）
        if ($enable_ai) {
            try {
                $ai_summary = $plugin->generate_ai_summary($plain_text, $post->ID);
                if (!empty($ai_summary)) {
                    $plain_text = $ai_summary;
                    if ($verbose) {
                        WP_CLI::log("投稿ID {$post->ID}: AI要約を適用しました");
                    }
                }
            } catch (Exception $e) {
                if ($verbose) {
                    $this->error_log[] = "投稿ID {$post->ID} AI要約失敗: " . $e->getMessage();
                }
                // AI要約失敗時はローカル要約を継続使用
            }
        }

        if ($dry_run) {
            WP_CLI::log("投稿ID {$post->ID}: " . mb_substr($plain_text, 0, 50, 'UTF-8') . '...');
            return true;
        }

        // メタデータを更新
        update_post_meta($post->ID, Andw_Llmo_QA_Plugin::META_ANSWER_SCHEMA, $plain_text);
        
        // Answer Containerが見つかった場合はそれも保存
        if (!empty($answer_display)) {
            update_post_meta($post->ID, Andw_Llmo_QA_Plugin::META_ANSWER_DISPLAY, $answer_display);
        }

        // スキーマ出力をデフォルト有効に設定
        if (get_post_meta($post->ID, Andw_Llmo_QA_Plugin::META_SCHEMA_ENABLED, true) === '') {
            update_post_meta($post->ID, Andw_Llmo_QA_Plugin::META_SCHEMA_ENABLED, true);
        }

        return true;
    }

    /**
     * Answer Containerブロックを再帰的に検索
     */
    private function find_answer_container_content($blocks) {
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'andw/llmo-answer-container' && !empty($block['innerBlocks'])) {
                return serialize_blocks($block['innerBlocks']);
            }
            
            // 子ブロックも検索
            if (!empty($block['innerBlocks'])) {
                $result = $this->find_answer_container_content($block['innerBlocks']);
                if ($result) {
                    return $result;
                }
            }
        }
        
        return '';
    }

    /**
     * 空のスキーマメタフィールドにAI要約を一括補完
     * 
     * ## OPTIONS
     * 
     * [--ai]
     * : AI要約を使用します。
     * 
     * [--missing-only]
     * : 要約メタが空の投稿のみ処理します。
     * 
     * [--dry-run]
     * : 実際の更新は行わず、プレビューのみを表示します。
     * 
     * [--network]
     * : マルチサイトの全サイトで実行します。
     * 
     * [--limit=<num>]
     * : 処理する投稿数を制限します。
     * 
     * ## EXAMPLES
     * 
     *   # AI要約で欠損分を一括補完（ドライラン）
     *   wp andw_llmoqa fill-summary --ai --missing-only --dry-run
     * 
     *   # ネットワーク全体で実行
     *   wp andw_llmoqa fill-summary --ai --missing-only --network
     */
    public function fill_summary($args, $assoc_args) {
        $use_ai = isset($assoc_args['ai']);
        $missing_only = isset($assoc_args['missing-only']);
        $dry_run = isset($assoc_args['dry-run']);
        $network = isset($assoc_args['network']);
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 0;

        if ($network && is_multisite()) {
            $this->fill_summary_network($use_ai, $missing_only, $dry_run, $limit);
            return;
        }

        $this->fill_summary_single_site($use_ai, $missing_only, $dry_run, $limit);
    }

    /**
     * 単一サイトでの要約補完
     */
    private function fill_summary_single_site($use_ai, $missing_only, $dry_run, $limit) {
        $plugin = new Andw_Llmo_QA_Plugin();
        
        // クエリ条件
        $query_args = [
            'post_type' => Andw_Llmo_QA_Plugin::CPT,
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'fields' => 'ids',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        if ($missing_only) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $query_args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => Andw_Llmo_QA_Plugin::META_ANSWER_SCHEMA,
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => Andw_Llmo_QA_Plugin::META_ANSWER_SCHEMA,
                    'value' => '',
                    'compare' => '='
                ]
            ];
        }

        $posts = get_posts($query_args);
        
        if (empty($posts)) {
            WP_CLI::success('処理対象の投稿が見つかりませんでした。');
            return;
        }

        WP_CLI::log(sprintf('処理対象: %d 件の投稿', count($posts)));
        
        if ($dry_run) {
            WP_CLI::log('-- ドライラン実行中 --');
        }

        $processed = 0;
        $ai_success = 0;
        $errors = [];

        foreach ($posts as $post) {
            try {
                $existing_schema = get_post_meta($post->ID, Andw_Llmo_QA_Plugin::META_ANSWER_SCHEMA, true);
                
                if ($missing_only && !empty($existing_schema)) {
                    continue; // 既に要約があるのでスキップ
                }

                // Answer Container または本文からコンテンツを取得
                $answer_display = get_post_meta($post->ID, Andw_Llmo_QA_Plugin::META_ANSWER_DISPLAY, true);
                $source_content = !empty($answer_display) ? $answer_display : $post->post_content;
                
                if (empty($source_content)) {
                    WP_CLI::log("投稿ID {$post->ID}: コンテンツが空のためスキップ");
                    continue;
                }

                // 要約生成
                if ($use_ai) {
                    $schema_text = $plugin->generate_schema_with_fallback($source_content, $post->ID);
                    if (strpos($schema_text, 'AI:') === 0) { // AI生成の場合
                        $ai_success++;
                    }
                } else {
                    $schema_text = $plugin->generate_plain_from_rich($source_content);
                }

                if (!empty($schema_text)) {
                    if (!$dry_run) {
                        update_post_meta($post->ID, Andw_Llmo_QA_Plugin::META_ANSWER_SCHEMA, $schema_text);
                    }
                    
                    WP_CLI::log(sprintf(
                        '投稿ID %d: %s要約生成完了 (%d文字)',
                        $post->ID,
                        $use_ai ? 'AI' : 'ローカル',
                        mb_strlen($schema_text, 'UTF-8')
                    ));
                    
                    $processed++;
                } else {
                    $errors[] = "投稿ID {$post->ID}: 要約生成に失敗";
                }
                
            } catch (Exception $e) {
                $errors[] = "投稿ID {$post->ID}: " . $e->getMessage();
            }
        }

        // 結果報告
        WP_CLI::success(sprintf(
            '処理完了: %d件更新 (%sAI成功: %d件)',
            $processed,
            $use_ai ? '' : 'ローカル要約 ',
            $use_ai ? $ai_success : 0
        ));

        if (!empty($errors)) {
            WP_CLI::warning('エラー発生:');
            foreach ($errors as $error) {
                WP_CLI::log('  - ' . $error);
            }
        }
    }

    /**
     * ネットワーク全体での要約補完
     */
    private function fill_summary_network($use_ai, $missing_only, $dry_run, $limit) {
        if (!is_multisite()) {
            WP_CLI::error('マルチサイト環境ではありません。');
            return;
        }

        $sites = get_sites(['number' => 0]);
        WP_CLI::log(sprintf('ネットワーク全体: %d サイトで実行', count($sites)));

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            WP_CLI::log(sprintf('サイト ID %d (%s) を処理中...', $site->blog_id, get_bloginfo('name')));
            
            try {
                $this->fill_summary_single_site($use_ai, $missing_only, $dry_run, $limit);
            } catch (Exception $e) {
                WP_CLI::warning(sprintf('サイト ID %d でエラー: %s', $site->blog_id, $e->getMessage()));
            } finally {
                restore_current_blog();
            }
        }

        WP_CLI::success('ネットワーク全体の処理が完了しました。');
    }
}

// WP-CLI コマンドを登録
WP_CLI::add_command('andw_llmoqa', 'Andw_Llmo_QA_CLI_Command');