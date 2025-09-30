<?php
if (!defined('ABSPATH')) exit;

register_block_type(__DIR__, [
  'render_callback' => function($atts, $content, $block) {
      // 公開側の出力（単純にメタ値を表示）
      if (!is_singular(Andw_Llmo_QA_Plugin::CPT)) return '';
      $val = get_post_meta(get_the_ID(), Andw_Llmo_QA_Plugin::META_SHORT, true);
      if (!$val) return '';
      return '<div class="llmoqa-short">' . wpautop(wp_kses_post($val)) . '</div>';
  }
]);

// エディタ用スクリプト登録（ビルド不要）
add_action('init', function() {
    wp_register_script(
        'llmo-short-answer-editor',
        plugins_url('index.js', __FILE__),
        ['wp-blocks','wp-element','wp-components','wp-data','wp-editor','wp-i18n'],
        '0.02',
        true
    );
});
