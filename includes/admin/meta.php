<?php
/**
 * Gutenberg サイドバーパネル（qa投稿タイプ限定）
 * 
 * スキーマ管理UI：自動/手動切替、プレビュー、出力ON/OFF、警告表示
 */

if (!defined('ABSPATH')) exit;

class LLMO_QA_Meta_Panel {
    
    public function __construct() {
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('admin_notices', [$this, 'show_forbidden_block_notice']);
        
        // AJAX エンドポイント（スキーマプレビュー用）
        add_action('wp_ajax_andw_llmoqa_preview_schema', [$this, 'ajax_preview_schema']);
    }

    /** エディタ用アセット読み込み */
    public function enqueue_editor_assets() {
        global $post;
        
        // qa投稿タイプのみ
        if (!$post || $post->post_type !== Andw_Llmo_QA_Plugin::CPT) {
            return;
        }

        wp_enqueue_script(
            'andw_llmoqa-meta-panel',
            plugins_url('assets/meta-panel.js', plugin_dir_path(__FILE__) . '../..'),
            [
                'wp-plugins',
                'wp-edit-post', 
                'wp-element',
                'wp-components',
                'wp-data',
                'wp-i18n',
                'wp-api-fetch'
            ],
            '0.05',
            true
        );

        wp_enqueue_style(
            'andw_llmoqa-meta-panel',
            plugins_url('assets/meta-panel.css', plugin_dir_path(__FILE__) . '../..'),
            [],
            '0.05'
        );

        // 設定データをJavaScriptに渡す
        wp_localize_script('andw_llmoqa-meta-panel', 'andw_llmoqaMeta', [
            'pluginUrl' => plugins_url('', plugin_dir_path(__FILE__) . '../..'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('andw_llmoqa_meta_nonce'),
            'postId' => $post->ID,
            'strings' => [
                'schemaSettings' => __('スキーマ設定', 'andw-llmo-qa'),
                'autoGeneration' => __('自動生成', 'andw-llmo-qa'),
                'manualEdit' => __('手動編集', 'andw-llmo-qa'),
                'schemaPreview' => __('スキーマプレビュー', 'andw-llmo-qa'),
                'schemaOutput' => __('スキーマ出力', 'andw-llmo-qa'),
                'enabled' => __('有効', 'andw-llmo-qa'),
                'disabled' => __('無効', 'andw-llmo-qa'),
                'warningForbiddenBlocks' => __('禁止ブロックが検出されました。スキーマ出力が自動停止されています。', 'andw-llmo-qa'),
                'charactersCount' => __('文字', 'andw-llmo-qa'),
                'previewLoading' => __('プレビュー生成中...', 'andw-llmo-qa'),
                'previewError' => __('プレビューの生成に失敗しました', 'andw-llmo-qa')
            ]
        ]);
    }

    /** 禁止ブロック検出時の警告表示 */
    public function show_forbidden_block_notice() {
        global $post;
        
        if (!$post || $post->post_type !== Andw_Llmo_QA_Plugin::CPT) {
            return;
        }

        // Kill Switch チェック
        if (get_option(Andw_Llmo_QA_Plugin::OPT_DISABLED, false)) {
            return;
        }

        $plugin = new LLMO_QA_Plugin();
        if ($plugin->has_forbidden_blocks($post->post_content)) {
            echo '<div class="notice notice-error andw_llmoqa-forbidden-notice">';
            echo '<p><strong>' . esc_html__('スキーマ自動停止中', 'andw-llmo-qa') . ':</strong> ';
            echo esc_html__('禁止ブロック（動画/埋め込み/HTML等）が検出されました。構造化データの出力が自動で停止されています。', 'andw-llmo-qa');
            echo '</p></div>';
        }
    }

    /** AJAX: スキーマプレビュー生成 */
    public function ajax_preview_schema() {
        // Nonce検証
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'andw_llmoqa_meta_nonce')) {
            wp_die(esc_html__('権限がありません', 'andw-llmo-qa'), 403);
        }

        // 権限チェック
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('権限がありません', 'andw-llmo-qa'), 403);
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        $rich_content = wp_kses_post(wp_unslash($_POST['rich_content'] ?? ''));

        if (!$post_id || get_post_type($post_id) !== Andw_Llmo_QA_Plugin::CPT) {
            wp_send_json_error(__('無効な投稿です', 'andw-llmo-qa'));
        }

        $plugin = new LLMO_QA_Plugin();
        $plain_text = $plugin->generate_plain_from_rich($rich_content);
        
        wp_send_json_success([
            'plain_text' => $plain_text,
            'character_count' => mb_strlen($plain_text, 'UTF-8'),
            'is_truncated' => mb_strlen($plain_text, 'UTF-8') >= 1000
        ]);
    }
}

// 初期化
new LLMO_QA_Meta_Panel();