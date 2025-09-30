<?php
/**
 * andW llmo-qa プラグインアンインストール処理
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * データ削除を実行するかどうかのオプション制御
 * デフォルトはデータ保持（false）
 */
function andw_llmoqa_should_delete_data() {
    return (bool) get_option('andw_llmoqa_delete_data_on_uninstall', false);
}

/**
 * 単一サイトのアンインストール処理
 */
function andw_llmoqa_uninstall_single_site() {
    // オプション削除（常に実行）
    delete_option('andw_llmoqa_rel_on');
    delete_option('andw_llmoqa_rel_num');
    delete_option('andw_llmoqa_tag_display');
    delete_option('andw_llmoqa_tag_highlight');
    delete_option('andw_llmoqa_disabled');
    delete_option('andw_llmoqa_delete_data_on_uninstall');
    
    // v0.05+追加オプション
    delete_option('andw_llmoqa_use_rich_on_archive');
    delete_option('andw_llmoqa_stop_schema_on_forbidden');
    
    // v0.06 AI要約設定
    delete_option('andw_llmoqa_openai_api_key');
    delete_option('andw_llmoqa_enable_ai_summary');
    delete_option('andw_llmoqa_ai_model');
    delete_option('andw_llmoqa_ai_timeout');
    delete_option('andw_llmoqa_schema_author_type');
    delete_option('andw_llmoqa_schema_author_name');

    // データ削除（オプション制御）
    if (andw_llmoqa_should_delete_data()) {
        // 投稿データ削除
        $posts = get_posts([
            'post_type' => 'qa',
            'numberposts' => -1,
            'post_status' => 'any'
        ]);

        foreach ($posts as $post) {
            // メタデータも含めて完全削除
            wp_delete_post($post->ID, true);
        }

        // タクソノミータームの削除
        $terms = get_terms([
            'taxonomy' => ['qa_category', 'qa_tag'],
            'hide_empty' => false
        ]);

        foreach ($terms as $term) {
            wp_delete_term($term->term_id, $term->taxonomy);
        }
    }

    flush_rewrite_rules();
}

// マルチサイト対応のアンインストール
if (is_multisite()) {
    $sites = get_sites(['number' => 0]);
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        andw_llmoqa_uninstall_single_site();
        restore_current_blog();
    }
} else {
    andw_llmoqa_uninstall_single_site();
}