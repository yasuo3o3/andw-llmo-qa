<?php
if (!defined('ABSPATH')) exit;
get_header(); ?>

<main id="primary" class="site-main container">
  <header class="page-header">
    <h1 class="page-title">
      <?php
      if (is_tax('qa_tag')) {
        echo 'タグ: ';
        single_term_title();
      } else {
        echo 'Q&A';
      }
      ?>
    </h1>
    <?php if (term_description()) echo '<div class="archive-description">'.term_description().'</div>'; ?>
  </header>

  <?php if (have_posts()): ?>
    <div class="andw_llmoqa-list">
      <?php while (have_posts()): the_post();
        $short = get_post_meta(get_the_ID(), '_andw_llmoqa_short_answer', true); ?>
        <article <?php post_class('andw_llmoqa-item'); ?>>
          <h3 class="andw_llmoqa-q"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
          <?php if ($short): ?><div class="andw_llmoqa-a"><?php echo wp_kses_post($short); ?></div><?php endif; ?>
        </article>
      <?php endwhile; ?>
    </div>

    <?php the_posts_pagination(); ?>
  <?php else: ?>
    <p>このタグに関連するQ&Aはまだありません。</p>
  <?php endif; ?>
</main>

<?php get_footer(); ?>