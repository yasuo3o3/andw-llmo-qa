<?php
if (!defined('ABSPATH')) exit;

register_block_type(__DIR__, [
  'render_callback' => function($atts) {
      $defaults = [
        'category'  => '',
        'tags'      => '',
        'limit'     => 12,
        'columns'   => 2,
        'showShort' => true,
        'showTitle' => true,
      ];
      $a = wp_parse_args($atts, $defaults);

      $args = [
        'post_type'      => Andw_Llmo_QA_Plugin::CPT,
        'posts_per_page' => (int)$a['limit'],
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
      ];
      
      $tax_queries = [];
      if (!empty($a['category'])) {
        $tax_queries[] = [
          'taxonomy' => Andw_Llmo_QA_Plugin::TAX,
          'field'    => 'slug',
          'terms'    => array_map('trim', explode(',', $a['category'])),
          'operator' => 'IN',
        ];
      }
      if (!empty($a['tags'])) {
        $tax_queries[] = [
          'taxonomy' => Andw_Llmo_QA_Plugin::TAX_TAG,
          'field'    => 'slug',
          'terms'    => array_map('trim', explode(',', $a['tags'])),
          'operator' => 'IN',
        ];
      }
      if (count($tax_queries) > 1) {
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
        $args['tax_query'] = array_merge(['relation' => 'AND'], $tax_queries);
      } elseif (count($tax_queries) === 1) {
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
        $args['tax_query'] = $tax_queries;
      }

      $q = new WP_Query($args);
      if (!$q->have_posts()) return '<div class="llmoqa-empty">Q&Aはまだありません。</div>';

      ob_start();
      $cols = max(1, min(6, (int)$a['columns']));
      echo '<div class="llmoqa-list llmoqa-cols-'.esc_attr(absint($cols)).'">';
      while ($q->have_posts()) { $q->the_post();
        $short = get_post_meta(get_the_ID(), Andw_Llmo_QA_Plugin::META_SHORT, true); ?>
        <article class="llmoqa-item">
          <?php if ($a['showTitle']): ?>
            <h3 class="llmoqa-q"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
          <?php endif; ?>
          <?php if ($a['showShort'] && $short): ?>
            <div class="llmoqa-a"><?php echo wp_kses_post($short); ?></div>
          <?php endif; ?>
        </article>
      <?php }
      echo '</div>';
      wp_reset_postdata();
      return ob_get_clean();
  }
]);

add_action('init', function() {
  wp_register_script(
    'llmo-qa-list-editor',
    plugins_url('index.js', __FILE__),
    ['wp-blocks','wp-element','wp-components','wp-i18n','wp-block-editor'],
    '0.02',
    true
  );
});
