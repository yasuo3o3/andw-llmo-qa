<?php
if (!defined('ABSPATH')) exit;

// Kill Switch チェック
if (get_option(Andw_Llmo_QA_Plugin::OPT_DISABLED, false)) {
    get_header();
    echo '<main class="site-main container"><p>' . esc_html__('このコンテンツは現在利用できません。', 'andw-llmo-qa') . '</p></main>';
    get_footer();
    exit;
}

get_header();

// v0.05: 一覧表示設定
$use_rich_on_archive = (bool)get_option(Andw_Llmo_QA_Plugin::OPT_USE_RICH_ON_ARCHIVE, false);
?>

<main id="primary" class="site-main container">
  <header class="page-header">
    <h1 class="page-title">
      <?php
      if (is_tax('qa_category')) {
        single_term_title();
      } else {
        echo 'Q&A';
      }
      ?>
    </h1>
    <?php if (term_description()) echo '<div class="archive-description">'.term_description().'</div>'; ?>
  </header>

  <?php if (have_posts()): ?>
    <div class="andw_llmoqa-list<?php echo $use_rich_on_archive ? ' andw_llmoqa-list-rich' : ' andw_llmoqa-list-plain'; ?>">
      <?php while (have_posts()): the_post();
        // v0.06: フォールバック要約表示
        $plugin = new Andw_Llmo_QA_Plugin();
        
        // 表示する回答コンテンツを決定
        $display_content = '';
        if ($use_rich_on_archive) {
          // リッチ表示モード（設定で有効化時）
          $answer_display = get_post_meta(get_the_ID(), Andw_Llmo_QA_Plugin::META_ANSWER_DISPLAY, true);
          if (!empty($answer_display)) {
            $display_content = do_blocks($answer_display);
          } else {
            // フォールバック要約を使用
            $display_content = esc_html($plugin->generate_fallback_summary(get_the_ID()));
          }
        } else {
          // プレーン要約表示（既定）- フォールバック対応
          $display_content = esc_html($plugin->generate_fallback_summary(get_the_ID()));
        }
        ?>
        <article <?php post_class('andw_llmoqa-item'); ?>>
          <h3 class="andw_llmoqa-q"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
          <?php if ($display_content): ?>
            <div class="andw_llmoqa-a"><?php echo esc_html($display_content); ?></div>
          <?php endif; ?>
        </article>
      <?php endwhile; ?>
    </div>

    <?php the_posts_pagination(); ?>
  <?php else: ?>
    <p><?php esc_html_e('Q&Aはまだありません。', 'andw-llmo-qa'); ?></p>
  <?php endif; ?>
</main>

<?php get_footer(); ?>
