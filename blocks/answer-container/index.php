<?php
/**
 * Answer Container ブロック サーバーサイド レンダリング
 */

if (!defined('ABSPATH')) exit;

function llmo_qa_render_answer_container_block($attributes, $content, $block) {
    // Kill Switch チェック
    if (get_option('llmoqa_disabled', false)) {
        return '';
    }

    // InnerBlocks のコンテンツを取得
    $inner_content = '';
    if (!empty($block->inner_blocks)) {
        foreach ($block->inner_blocks as $inner_block) {
            $inner_content .= render_block($inner_block);
        }
    }

    // コンテナでラップして返す
    return sprintf(
        '<div class="llmoqa-answer-container">%s</div>',
        $inner_content
    );
}

// ブロックのレンダーコールバックを登録
register_block_type(__DIR__, [
    'render_callback' => 'llmo_qa_render_answer_container_block'
]);