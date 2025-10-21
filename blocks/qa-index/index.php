<?php
if (!defined('ABSPATH')) exit;

register_block_type(__DIR__, [
  'render_callback' => function($atts) {
      $a = wp_parse_args($atts, [
        'categories'   => '',
        'tags'         => '',
        'perCategory'  => 6,
        'columns'      => 2,
        'showShort'    => true,
        'showMoreLink' => true,
      ]);
      $cat_slugs = array_filter(array_map('trim', explode(',', $a['categories'])));
      $tag_slugs = array_filter(array_map('trim', explode(',', $a['tags'])));
      
      if (!$cat_slugs && !$tag_slugs) return '<div class="llmoqa-empty">カテゴリまたはタグが指定されていません。</div>';

      ob_start();
      
      // カテゴリセクション
      foreach ($cat_slugs as $slug) {
        $term = get_term_by('slug', $slug, Andw_Llmo_QA_Plugin::TAX);
        if (!$term) continue;

        $tax_queries = [
          [
            'taxonomy' => Andw_Llmo_QA_Plugin::TAX,
            'field'    => 'slug',
            'terms'    => [$slug],
          ]
        ];
        
        // タグも指定されている場合はAND条件
        if (!empty($tag_slugs)) {
          $tax_queries[] = [
            'taxonomy' => Andw_Llmo_QA_Plugin::TAX_TAG,
            'field'    => 'slug',
            'terms'    => $tag_slugs,
          ];
          $tax_queries = array_merge(['relation' => 'AND'], $tax_queries);
        }

        $q = new WP_Query([
          'post_type'      => Andw_Llmo_QA_Plugin::CPT,
          'posts_per_page' => (int)$a['perCategory'],
          'orderby'        => 'date',
          'order'          => 'DESC',
          // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
          'tax_query'      => $tax_queries,
          'no_found_rows'  => true,
          'update_post_meta_cache' => false,
          'update_post_term_cache' => false,
        ]);
        if (!$q->have_posts()) continue;

        $cols = max(1, min(6, (int)$a['columns']));
        echo '<section class="llmoqa-section">';
        echo '<header class="llmoqa-section__head"><h2>'.esc_html($term->name).'</h2>';
        if ($a['showMoreLink']) {
          echo ' <a class="llmoqa-more" href="'.esc_url(get_term_link($term)).'">もっと見る</a>';
        }
        echo '</header>';

        echo '<div class="llmoqa-list llmoqa-cols-'.esc_attr(absint($cols)).'">';
        while ($q->have_posts()) { $q->the_post();
          $short = get_post_meta(get_the_ID(), Andw_Llmo_QA_Plugin::META_SHORT, true); ?>
          <article class="llmoqa-item">
            <h3 class="llmoqa-q"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            <?php if ($a['showShort'] && $short): ?>
              <div class="llmoqa-a"><?php echo wp_kses_post($short); ?></div>
            <?php endif; ?>
          </article>
        <?php }
        echo '</div></section>';
        wp_reset_postdata();
      }
      
      // タグセクション（カテゴリが指定されていない場合のみ）
      if (empty($cat_slugs)) {
        foreach ($tag_slugs as $slug) {
          $term = get_term_by('slug', $slug, Andw_Llmo_QA_Plugin::TAX_TAG);
          if (!$term) continue;

          $q = new WP_Query([
            'post_type'      => Andw_Llmo_QA_Plugin::CPT,
            'posts_per_page' => (int)$a['perCategory'],
            'orderby'        => 'date',
            'order'          => 'DESC',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            'tax_query'      => [[
              'taxonomy' => Andw_Llmo_QA_Plugin::TAX_TAG,
              'field'    => 'slug',
              'terms'    => [$slug],
              'operator' => 'IN',
            ]],
            'no_found_rows'  => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
          ]);
          if (!$q->have_posts()) continue;

          $cols = max(1, min(6, (int)$a['columns']));
          echo '<section class="llmoqa-section">';
          echo '<header class="llmoqa-section__head"><h2>'.esc_html($term->name).' タグ</h2>';
          if ($a['showMoreLink']) {
            echo ' <a class="llmoqa-more" href="'.esc_url(get_term_link($term)).'">もっと見る</a>';
          }
          echo '</header>';

          echo '<div class="llmoqa-list llmoqa-cols-'.esc_attr(absint($cols)).'">';
          while ($q->have_posts()) { $q->the_post();
            $short = get_post_meta(get_the_ID(), Andw_Llmo_QA_Plugin::META_SHORT, true); ?>
            <article class="llmoqa-item">
              <h3 class="llmoqa-q"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
              <?php if ($a['showShort'] && $short): ?>
                <div class="llmoqa-a"><?php echo wp_kses_post($short); ?></div>
              <?php endif; ?>
            </article>
          <?php }
          echo '</div></section>';
          wp_reset_postdata();
        }
      }
      return ob_get_clean();
  }
]);

add_action('init', function() {
  wp_register_script(
    'andw-llmo-qa-index-editor',
    plugins_url('index.js', __FILE__),
    ['wp-blocks','wp-element','wp-components','wp-i18n','wp-block-editor'],
    '0.02',
    true
  );
});
