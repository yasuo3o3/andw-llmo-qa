<?php
if (!defined('ABSPATH')) exit;

// Kill Switch チェック
if (get_option(Andw_Llmo_QA_Plugin::OPT_DISABLED, false)) {
    get_header();
    echo '<main class="site-main container"><p>' . esc_html__('このコンテンツは現在利用できません。', 'andw-llmo-qa') . '</p></main>';
    get_footer();
    exit;
}

get_header(); ?>

<main id="primary" class="site-main container">
  <?php while (have_posts()) : the_post();
    // v0.05: 新しい表示ロジック
    $answer_display = get_post_meta(get_the_ID(), Andw_Llmo_QA_Plugin::META_ANSWER_DISPLAY, true);
    $short = get_post_meta(get_the_ID(), '_andw_llmoqa_short_answer', true); // 既存互換性維持
    ?>
    <article <?php post_class('andw_llmoqa-single'); ?>>
      <header class="entry-header">
        <h1 class="entry-title"><?php the_title(); ?></h1>
        <?php 
        // タグチップ表示（設定で有効化されている場合）
        if ((bool)get_option(Andw_Llmo_QA_Plugin::OPT_TAG_DISPLAY, true)) {
          $tags = wp_get_post_terms(get_the_ID(), Andw_Llmo_QA_Plugin::TAX_TAG);
          if (!empty($tags) && !is_wp_error($tags)) {
            echo '<div class="andw_llmoqa-tags">';
            foreach ($tags as $tag) {
              echo '<a href="' . esc_url(get_term_link($tag)) . '" class="andw_llmoqa-tag">' . esc_html($tag->name) . '</a>';
            }
            echo '</div>';
          }
        }
        ?>
      </header>

      <?php if ($short): ?>
        <section class="andw_llmoqa-short"><?php echo wp_kses_post(wpautop($short)); ?></section>
      <?php endif; ?>

      <section class="entry-content">
        <?php 
        // v0.05: Answer Container優先表示
        if (!empty($answer_display)) {
          // Answer Containerのリッチコンテンツを表示
          echo wp_kses_post(do_blocks($answer_display));
        } else {
          // 従来通りの投稿本文表示
          the_content();
        }
        ?>
      </section>
    </article>
  <?php endwhile; ?>
</main>

<?php get_footer(); ?>
