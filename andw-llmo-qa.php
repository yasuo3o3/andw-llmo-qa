<?php
/**
 * Plugin Name:       andW llmo-qa（汎用質問・即答・解説）
 * Description:       /qa/ 配下に「質問＋即答＋解説」構造とFAQ構造化データを自動出力。CPT/カテゴリ/タグ/CSV一括投入/即答ブロック/関連リンク自動出力/タグ機能を同梱。
 * Version:           0.07
 * Author:            Netservice
 * Author URI:        https://netservice.jp/
 * License:           GPLv2 or later
 * Text Domain:       andw-llmo-qa
 */

if (!defined('ABSPATH')) exit;

class Andw_Llmo_QA_Plugin {
    const CPT = 'qa';
    const TAX = 'qa_category';
    const TAX_TAG = 'qa_tag';
    const META_SHORT = '_andw_llmoqa_short_answer';

    // v0.05: 新メタフィールド（回答Y字化）
    const META_ANSWER_DISPLAY = 'andw_llmoqa_answer_display';    // 表示用（Gutenberg blocks）
    const META_ANSWER_SCHEMA = 'andw_llmoqa_answer_schema';      // スキーマ用プレーン
    const META_SCHEMA_MANUAL = 'andw_llmoqa_schema_manual';      // 手動/自動フラグ（bool）
    const META_SCHEMA_ENABLED = 'andw_llmoqa_schema_enabled';    // 投稿単位出力ON/OFF

    // 既存設定キー
    const OPT_REL_ON  = 'andw_llmoqa_rel_on';
    const OPT_REL_NUM = 'andw_llmoqa_rel_num';
    const OPT_TAG_DISPLAY = 'andw_llmoqa_tag_display';
    const OPT_TAG_HIGHLIGHT = 'andw_llmoqa_tag_highlight';

    // v0.05: 新設定キー
    const OPT_USE_RICH_ON_ARCHIVE = 'andw_llmoqa_use_rich_on_archive';        // 一覧リッチ表示
    const OPT_STOP_SCHEMA_ON_FORBIDDEN = 'andw_llmoqa_stop_schema_on_forbidden'; // 禁止ブロック検出時停止
    const OPT_DISABLED = 'andw_llmoqa_disabled';                              // Kill Switch

    // v0.06: AI要約設定キー
    const OPT_OPENAI_API_KEY = 'andw_llmoqa_openai_api_key';                  // OpenAI APIキー
    const OPT_ENABLE_AI_SUMMARY = 'andw_llmoqa_enable_ai_summary';            // AI要約有効/無効
    const OPT_AI_MODEL = 'andw_llmoqa_ai_model';                              // AIモデル名
    const OPT_AI_TIMEOUT = 'andw_llmoqa_ai_timeout';                          // API タイムアウト

    // v0.06: JSON-LD拡張設定キー
    const OPT_SCHEMA_AUTHOR_TYPE = 'andw_llmoqa_schema_author_type';          // Author タイプ (Organization/Person)
    const OPT_SCHEMA_AUTHOR_NAME = 'andw_llmoqa_schema_author_name';          // Author 名前

    // CSV インポート制限（将来的にフィルタで変更可能にする予定）
    const CSV_MAX_SIZE = 10485760;  // 10MB
    const CSV_MAX_ROWS = 5000;      // 5000行

    public function __construct() {
        // Kill Switch 早期チェック
        if ($this->is_disabled()) {
            return;
        }

        add_action('init', [$this, 'register_cpt_tax']);
        add_action('init', [$this, 'register_assets']);
        add_action('init', [$this, 'register_meta_fields']);
        
        // v0.05: メタパネル読み込み
        if (is_admin()) {
            require_once plugin_dir_path(__FILE__) . 'includes/admin/meta.php';
        }
        
        // v0.05: WP-CLI コマンド読み込み
        if (defined('WP_CLI') && WP_CLI) {
            require_once plugin_dir_path(__FILE__) . 'includes/migrate-cli.php';
        }

        // v0.06: OpenAI API クラス読み込み
        require_once plugin_dir_path(__FILE__) . 'includes/openai-api.php';

        // エディタ用メタ（REST公開）: ブロックから双方向バインド
        register_post_meta(self::CPT, self::META_SHORT, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => function($allowed, $meta_key, $object_id) {
                return current_user_can('edit_post', $object_id);
            }
        ]);

        // 旧メタボックスは残す（ブロック未使用でも編集できるように）
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post_' . self::CPT, [$this, 'save_metabox']);
        
        // チェックボックス型タグメタボックス
        add_action('add_meta_boxes', [$this, 'add_tags_checkbox_metabox']);
        add_action('save_post_' . self::CPT, [$this, 'save_tags_checkbox_metabox']);

        // v0.05: 新保存ロジック（Answer Container & スキーマ処理）
        add_action('save_post_' . self::CPT, [$this, 'save_answer_container_logic'], 20);

        add_filter('manage_edit-' . self::CPT . '_columns', [$this, 'admin_columns']);
        add_action('manage_' . self::CPT . '_posts_custom_column', [$this, 'admin_column_content'], 10, 2);

        add_action('wp_head', [$this, 'print_faq_schema']);
        add_filter('template_include', [$this, 'inject_templates']);

        add_shortcode('andw_llmo_qa_list', [$this, 'sc_list']);
        add_shortcode('andw_llmo_qa', [$this, 'sc_single']);

        // 管理：CSVインポート & 設定
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // v0.05: 管理画面通知
        add_action('admin_notices', [$this, 'show_forbidden_block_admin_notice']);

        // ブロック登録（ビルド不要）
        add_action('init', [$this, 'register_blocks']);

        // コンテンツ末尾に関連リンク自動挿入（設定ON時）
        add_filter('the_content', [$this, 'append_related_links']);
        
        // タグ語ハイライト機能
        add_filter('the_content', [$this, 'highlight_tag_words'], 20);

        register_activation_hook(__FILE__, [__CLASS__, 'on_activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'on_deactivate']);
    }

    /** CPT・Taxonomy */
    public function register_cpt_tax() {
        register_post_type(self::CPT, [
            'labels' => [
                'name'          => 'Q&A',
                'singular_name' => 'Q&A',
                'add_new'       => '新規Q&Aを追加',
                'add_new_item'  => 'Q&Aを追加',
                'edit_item'     => 'Q&Aを編集',
                'new_item'      => '新規Q&A',
                'view_item'     => 'Q&Aを表示',
                'search_items'  => 'Q&Aを検索',
            ],
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-editor-help',
            'supports' => ['title', 'editor', 'author', 'thumbnail', 'revisions'],
            'rewrite' => ['slug' => 'qa', 'with_front' => false],
            'show_in_rest' => true,
        ]);

        register_taxonomy(self::TAX, [self::CPT], [
            'labels' => [
                'name'          => 'Q&Aカテゴリ',
                'singular_name' => 'Q&Aカテゴリ',
                'search_items'  => 'カテゴリ検索',
                'all_items'     => 'すべてのカテゴリ',
                'edit_item'     => 'カテゴリ編集',
                'update_item'   => 'カテゴリ更新',
                'add_new_item'  => 'カテゴリ追加',
                'new_item_name' => '新規カテゴリ名',
            ],
            'hierarchical' => true,
            'public' => true,
            'rewrite' => ['slug' => 'qa', 'hierarchical' => true, 'with_front' => false],
            'show_in_rest' => true,
        ]);

        register_taxonomy(self::TAX_TAG, [self::CPT], [
            'labels' => [
                'name'                       => 'Q&Aタグ',
                'singular_name'              => 'Q&Aタグ',
                'search_items'               => 'タグ検索',
                'popular_items'              => 'よく使われるタグ',
                'all_items'                  => 'すべてのタグ',
                'parent_item'                => null,
                'parent_item_colon'          => null,
                'edit_item'                  => 'タグ編集',
                'update_item'                => 'タグ更新',
                'add_new_item'               => 'タグ追加',
                'new_item_name'              => '新規タグ名',
                'separate_items_with_commas' => 'タグをカンマで区切ってください',
                'add_or_remove_items'        => 'タグを追加・削除',
                'choose_from_most_used'      => 'よく使われるタグから選択',
            ],
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => false,
            'query_var' => true,
            'rewrite' => ['slug' => 'qa-tag', 'with_front' => false],
            'show_in_rest' => true,
            'meta_box_cb' => 'post_tags_meta_box',
        ]);
    }

    /** CSS/管理CSS（対象画面限定） */
    public function register_assets() {
        wp_register_style('andw-llmo-qa-style', plugins_url('assets/style.css', __FILE__), [], '0.02');
        
        // フロントエンド：Q&A関連ページでのみ読み込み
        if (!is_admin() && (is_singular(self::CPT) || is_post_type_archive(self::CPT) || is_tax([self::TAX, self::TAX_TAG]))) {
            wp_enqueue_style('andw-llmo-qa-style');
        }

        wp_register_style('andw-llmo-qa-admin', plugins_url('assets/admin.css', __FILE__), [], '0.02');
        if (is_admin()) wp_enqueue_style('andw-llmo-qa-admin');
    }

    /** 旧メタボックス（保険） */
    public function add_metabox() {
        add_meta_box('andw_llmoqa_short_answer', '即答（50〜100文字推奨）', function($post) {
            $val = get_post_meta($post->ID, self::META_SHORT, true);
            echo '<textarea name="andw_llmoqa_short_answer" style="width:100%;height:80px;" placeholder="例）6〜8日で20万〜35万円程度、航空券・宿泊・食事込み。">'.esc_textarea($val).'</textarea>';
            echo '<p style="margin-top:6px;color:#666;">※エディタでは「即答（andW）」ブロックからも編集できます。</p>';
            wp_nonce_field('andw_llmoqa_short_answer_nonce', 'andw_llmoqa_short_answer_nonce_field');
        }, self::CPT, 'normal', 'high');
    }
    public function save_metabox($post_id) {
        if (!isset($_POST['andw_llmoqa_short_answer_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['andw_llmoqa_short_answer_nonce_field'] ?? '')), 'andw_llmoqa_short_answer_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $val = isset($_POST['andw_llmoqa_short_answer']) ? wp_kses_post(wp_unslash($_POST['andw_llmoqa_short_answer'])) : '';
        update_post_meta($post_id, self::META_SHORT, $val);
    }

    /** 管理カラム */
    public function admin_columns($cols) {
        $new = [];
        foreach ($cols as $k => $v) {
            $new[$k] = $v;
            if ($k === 'title') $new['andw_llmoqa_short'] = '即答';
        }
        return $new;
    }
    public function admin_column_content($col, $post_id) {
        if ($col === 'andw_llmoqa_short') {
            $s = get_post_meta($post_id, self::META_SHORT, true);
            echo esc_html(mb_strimwidth(wp_strip_all_tags($s), 0, 60, '…', 'UTF-8'));
        }
    }

    /** v0.06: 言語コード正規化ヘルパー */
    private function normalize_language_code($lang_code) {
        if (empty($lang_code)) {
            return 'ja'; // デフォルトは日本語
        }
        
        // WordPressの言語コードを正規化（ja_JP → ja, en_US → en-US等）
        $lang_code = str_replace('_', '-', $lang_code);
        
        // xx-YYの場合はそのまま、xxの場合もそのまま
        if (preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $lang_code)) {
            // 複雑な地域コードは親言語に簡素化（ja-JP → ja等）
            $main_parts = ['ja-JP' => 'ja', 'en-US' => 'en', 'zh-CN' => 'zh', 'ko-KR' => 'ko'];
            return isset($main_parts[$lang_code]) ? $main_parts[$lang_code] : strtolower(substr($lang_code, 0, 2));
        }
        
        // 形式不正の場合は最初の2文字を抽出
        return strtolower(substr($lang_code, 0, 2));
    }

    /** v0.06: スキーマテキスト正規化とサニタイゼーション */
    private function sanitize_schema_text($text, $max_length = 1000) {
        if (empty($text)) {
            return '';
        }
        
        // HTMLタグ除去と改行正規化
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        
        // 長さ制限
        if (mb_strlen($text, 'UTF-8') > $max_length) {
            $text = mb_substr($text, 0, $max_length - 3, 'UTF-8') . '...';
        }
        
        return sanitize_text_field($text);
    }

    /** v0.06: 二重出力検知 */
    private function should_skip_schema_output() {
        // 他プラグインが既にQAPage/FAQを出力している可能性をチェック（簡易）
        static $schema_output_checked = false;
        
        if ($schema_output_checked) {
            return true; // 既に出力済み
        }
        
        // Search Console フレンドリーなデバッグログ（WP_DEBUG時のみ）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            global $post;
            $debug_info = sprintf(
                '[andW-QA Schema] 投稿ID:%d, スキーマ有効:%s, Kill Switch:%s',
                $post ? $post->ID : 0,
                get_post_meta($post->ID ?? 0, self::META_SCHEMA_ENABLED, true) ? 'true' : 'false',
                $this->is_disabled() ? 'ON' : 'OFF'
            );
        }
        
        $schema_output_checked = true;
        return false;
    }

    /** v0.06: JSON-LD（拡張QAPage） */
    public function print_faq_schema() {
        // Kill Switch チェック
        if ($this->is_disabled()) {
            return;
        }
        
        // 二重出力検知
        if ($this->should_skip_schema_output()) {
            return;
        }

        if (is_singular(self::CPT)) {
            // 単一Q&A：拡張QAPage形式
            global $post;
            
            // 投稿単位のスキーマ出力設定をチェック
            $schema_enabled = (bool)get_post_meta($post->ID, self::META_SCHEMA_ENABLED, true);
            if (!$schema_enabled) {
                return;
            }

            // スキーマ用テキストを取得（優先順位：schema > short）
            $answer_text = get_post_meta($post->ID, self::META_ANSWER_SCHEMA, true);
            if (empty($answer_text)) {
                $answer_text = trim(get_post_meta($post->ID, self::META_SHORT, true)); // 後方互換性
            }
            
            if (empty($answer_text)) {
                return;
            }

            // 基本情報の取得
            $canonical_url = function_exists('wp_get_canonical_url') 
                ? esc_url_raw(wp_get_canonical_url($post->ID))
                : esc_url_raw(get_permalink($post->ID));
            
            $language = $this->normalize_language_code(get_bloginfo('language'));
            $question_title = $this->sanitize_schema_text(get_the_title($post), 120);
            $sanitized_answer = $this->sanitize_schema_text($answer_text, 1000);
            
            // Author設定取得
            $author_type = get_option(self::OPT_SCHEMA_AUTHOR_TYPE, 'Organization');
            $author_name = get_option(self::OPT_SCHEMA_AUTHOR_NAME, '');
            if (empty($author_name)) {
                $author_name = get_bloginfo('name');
            }
            $author_name = $this->sanitize_schema_text($author_name, 100);
            
            // 日時情報
            $date_published = gmdate('c', strtotime($post->post_date_gmt . ' UTC'));
            $date_modified = null;
            if (strtotime($post->post_modified_gmt) > strtotime($post->post_date_gmt)) {
                $date_modified = gmdate('c', strtotime($post->post_modified_gmt . ' UTC'));
            }

            // 拡張QAPageスキーマ構築
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'QAPage',
                '@id' => $canonical_url . '#qapage',
                'inLanguage' => $language,
                'mainEntityOfPage' => $canonical_url,
                'mainEntity' => [
                    '@type' => 'Question',
                    '@id' => $canonical_url . '#question',
                    'name' => $question_title,
                    'answerCount' => 1,
                    'datePublished' => $date_published,
                    'inLanguage' => $language,
                    'mainEntityOfPage' => $canonical_url,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'url' => $canonical_url . '#answer',
                        'author' => [
                            '@type' => $author_type,
                            'name' => $author_name
                        ],
                        'datePublished' => $date_published,
                        'inLanguage' => $language,
                        'text' => $sanitized_answer
                    ]
                ]
            ];
            
            // dateModified がある場合のみ追加
            if ($date_modified) {
                $schema['mainEntity']['acceptedAnswer']['dateModified'] = $date_modified;
            }
            
            // JSON エンコードとバリデーション
            $json_output = wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json_output === false) {
                // JSON エンコード失敗時の管理者通知（1回のみ）
                if (current_user_can('manage_options') && !get_transient('andw_llmoqa_schema_json_error_notice')) {
                    set_transient('andw_llmoqa_schema_json_error_notice', 1, 300); // 5分間キャッシュ
                }
                return;
            }
            
            echo "\n<script type=\"application/ld+json\">" . esc_js($json_output) . "</script>\n";
        }

        // v0.05: 一覧ページではJSON-LDを出力しない
        // （集約は混在リスク高のため、単一投稿でのみ出力）
    }

    /** テンプレート注入 */
    public function inject_templates($template) {
        if (is_singular(self::CPT)) {
            $t = locate_template(['single-qa.php', 'single-' . self::CPT . '.php']);
            if (!$t) $template = plugin_dir_path(__FILE__) . 'templates/single-qa.php';
        } elseif (is_post_type_archive(self::CPT) || is_tax(self::TAX) || is_tax(self::TAX_TAG)) {
            if (is_tax(self::TAX_TAG)) {
                $t = locate_template(['taxonomy-qa_tag.php', 'archive-qa.php', 'archive-' . self::CPT . '.php']);
                if (!$t) $template = plugin_dir_path(__FILE__) . 'templates/taxonomy-qa_tag.php';
            } else {
                $t = locate_template(['archive-qa.php', 'archive-' . self::CPT . '.php']);
                if (!$t) $template = plugin_dir_path(__FILE__) . 'templates/archive-qa.php';
            }
        }
        return $template;
    }

    /** 一覧ショートコード */
    public function sc_list($atts) {
        $atts = shortcode_atts([
            'category' => '',
            'tags'     => '',
            'limit'    => '20',
        ], $atts, 'andw_llmo_qa_list');

        $args = [
            'post_type' => self::CPT,
            'posts_per_page' => (int)$atts['limit'],
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        $tax_queries = [];
        if (!empty($atts['category'])) {
            $tax_queries[] = [
                'taxonomy' => self::TAX,
                'field' => 'slug',
                'terms' => array_map('trim', explode(',', $atts['category'])),
            ];
        }
        if (!empty($atts['tags'])) {
            $tax_queries[] = [
                'taxonomy' => self::TAX_TAG,
                'field' => 'slug',
                'terms' => array_map('trim', explode(',', $atts['tags'])),
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
        if (!$q->have_posts()) return '<div class="andw_llmoqa-empty">Q&Aはまだありません。</div>';

        ob_start();
        echo '<div class="andw_llmoqa-list">';
        while ($q->have_posts()) { $q->the_post();
            // v0.06: フォールバック要約使用
            $display_content = $this->generate_fallback_summary(get_the_ID()); ?>
            <article class="andw_llmoqa-item">
                <h3 class="andw_llmoqa-q"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <?php if ($display_content): ?><div class="andw_llmoqa-a"><?php echo esc_html($display_content); ?></div><?php endif; ?>
            </article>
        <?php }
        echo '</div>';
        wp_reset_postdata();
        return ob_get_clean();
    }

    /** 単体ショートコード */
    public function sc_single($atts) {
        $atts = shortcode_atts(['id' => ''], $atts, 'andw_llmo_qa');
        $post_id = (int)$atts['id'];
        if (!$post_id) return '';
        $p = get_post($post_id);
        if (!$p || $p->post_type !== self::CPT) return '';
        $short = get_post_meta($post_id, self::META_SHORT, true);
        ob_start(); ?>
        <article class="andw_llmoqa-single">
            <h2 class="andw_llmoqa-title"><?php echo esc_html(get_the_title($post_id)); ?></h2>
            <?php if ($short): ?><div class="andw_llmoqa-short"><?php echo wp_kses_post(wpautop($short)); ?></div><?php endif; ?>
            <div class="andw_llmoqa-content"><?php echo wp_kses_post(apply_filters('the_content', $p->post_content)); ?></div>
        </article>
        <?php
        return ob_get_clean();
    }

    /** 管理メニュー（CSV/設定） */
    public function admin_menu() {
        add_submenu_page(
            'edit.php?post_type=' . self::CPT,
            'CSVインポート & 設定',
            'CSVインポート & 設定',
            'manage_options',
            'andw_llmoqa-import',
            [$this, 'render_import_settings_page']
        );
    }

    /** 設定登録 */
    public function register_settings() {
        // AI要約設定グループ
        register_setting('andw_llmoqa_group_ai', self::OPT_OPENAI_API_KEY, [
            'type' => 'string', 
            'default' => '', 
            'sanitize_callback' => [$this, 'sanitize_api_key']
        ]);
        register_setting('andw_llmoqa_group_ai', self::OPT_ENABLE_AI_SUMMARY, [
            'type' => 'boolean', 
            'default' => false,
            'sanitize_callback' => [$this, 'sanitize_checkbox']
        ]);
        register_setting('andw_llmoqa_group_ai', self::OPT_AI_MODEL, [
            'type' => 'string', 
            'default' => 'gpt-4o-mini',
            'sanitize_callback' => [$this, 'sanitize_ai_model']
        ]);
        register_setting('andw_llmoqa_group_ai', self::OPT_AI_TIMEOUT, [
            'type' => 'integer', 
            'default' => 12,
            'sanitize_callback' => [$this, 'sanitize_ai_timeout']
        ]);

        // 基本設定グループ
        register_setting('andw_llmoqa_group_basic', self::OPT_REL_ON, [
            'type' => 'boolean', 
            'default' => true,
            'sanitize_callback' => [$this, 'sanitize_checkbox']
        ]);
        register_setting('andw_llmoqa_group_basic', self::OPT_REL_NUM, [
            'type' => 'integer', 
            'default' => 6,
            'sanitize_callback' => [$this, 'sanitize_rel_num']
        ]);
        register_setting('andw_llmoqa_group_basic', self::OPT_TAG_DISPLAY, [
            'type' => 'boolean', 
            'default' => true,
            'sanitize_callback' => [$this, 'sanitize_checkbox']
        ]);
        register_setting('andw_llmoqa_group_basic', self::OPT_TAG_HIGHLIGHT, [
            'type' => 'boolean', 
            'default' => false,
            'sanitize_callback' => [$this, 'sanitize_checkbox']
        ]);
        register_setting('andw_llmoqa_group_basic', self::OPT_USE_RICH_ON_ARCHIVE, [
            'type' => 'boolean', 
            'default' => false,
            'sanitize_callback' => [$this, 'sanitize_checkbox']
        ]);
        register_setting('andw_llmoqa_group_basic', self::OPT_STOP_SCHEMA_ON_FORBIDDEN, [
            'type' => 'boolean', 
            'default' => true,
            'sanitize_callback' => [$this, 'sanitize_checkbox']
        ]);
        register_setting('andw_llmoqa_group_basic', self::OPT_SCHEMA_AUTHOR_TYPE, [
            'type' => 'string', 
            'default' => 'Organization',
            'sanitize_callback' => [$this, 'sanitize_author_type']
        ]);
        register_setting('andw_llmoqa_group_basic', self::OPT_SCHEMA_AUTHOR_NAME, [
            'type' => 'string', 
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        // autoload=no で初期設定（パフォーマンス最適化）
        $this->ensure_option_exists(self::OPT_OPENAI_API_KEY, '');
        $this->ensure_option_exists(self::OPT_ENABLE_AI_SUMMARY, false);
        $this->ensure_option_exists(self::OPT_AI_MODEL, 'gpt-4o-mini');
        $this->ensure_option_exists(self::OPT_AI_TIMEOUT, 12);
        $this->ensure_option_exists(self::OPT_SCHEMA_AUTHOR_TYPE, 'Organization');
        $this->ensure_option_exists(self::OPT_SCHEMA_AUTHOR_NAME, '');
    }

    /** オプションが存在しない場合のみ作成 */
    private function ensure_option_exists($option_name, $default_value) {
        if (false === get_option($option_name)) {
            add_option($option_name, $default_value, '', 'no');
        }
    }

    /** 管理画面スクリプト読み込み */
    public function enqueue_admin_scripts($hook_suffix) {
        // andW-QA の設定ページでのみ読み込み
        if ($hook_suffix !== 'qa_page_andw_llmoqa-import') {
            return;
        }
        
        wp_enqueue_script(
            'andw_llmoqa-admin-settings',
            plugins_url('assets/admin-settings.js', __FILE__),
            ['jquery'],
            '0.06',
            true
        );
        
        // 翻訳文字列を渡す
        wp_localize_script('andw_llmoqa-admin-settings', 'andw_llmoqaAdmin', [
            'confirmClearApiKey' => __('APIキーを削除してもよろしいですか？', 'andw-llmo-qa')
        ]);
    }

    /** 画面レンダリング */
    public function render_import_settings_page() {
        require_once plugin_dir_path(__FILE__) . 'admin/import-page.php';
    }

    /** ブロック登録 */
    public function register_blocks() {
        register_block_type(__DIR__ . '/blocks/short-answer');    // 即答
        register_block_type(__DIR__ . '/blocks/qa-list');         // 単一カテゴリ一覧
        register_block_type(__DIR__ . '/blocks/qa-index');        // 複数カテゴリインデックス
        register_block_type(__DIR__ . '/blocks/answer-container'); // v0.05: 回答コンテナ
    }

    /** 関連リンク自動挿入：同カテゴリ上位N件 */
    public function append_related_links($content) {
        if (!is_singular(self::CPT) || !in_the_loop() || !is_main_query()) return $content;
        $on  = (bool)get_option(self::OPT_REL_ON, true);
        $num = (int)get_option(self::OPT_REL_NUM, 6);
        if (!$on || $num < 1) return $content;

        $term_ids = wp_get_post_terms(get_the_ID(), self::TAX, ['fields' => 'ids']);
        $args = [
            'post_type' => self::CPT,
            'posts_per_page' => $num,
            // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
            'post__not_in' => [get_the_ID()],
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'fields' => 'ids',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];
        if ($term_ids) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $args['tax_query'] = [[
                'taxonomy' => self::TAX,
                'field' => 'term_id',
                'terms' => $term_ids,
                'operator' => 'IN',
                'include_children' => false,
            ]];
        }
        $q = new WP_Query($args);
        if (!$q->have_posts()) return $content;

        ob_start();
        echo '<aside class="andw_llmoqa-related"><h3>関連Q&A</h3><ul>';
        while ($q->have_posts()) { $q->the_post();
            echo '<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></li>';
        }
        echo '</ul></aside>';
        wp_reset_postdata();

        return $content . ob_get_clean();
    }

    /** チェックボックス型タグメタボックス */
    public function add_tags_checkbox_metabox() {
        add_meta_box(
            'andw_llmoqa_tags_checkbox', 
            'タグ（チェックボックス）',
            [$this, 'render_tags_checkbox_metabox'],
            self::CPT,
            'side',
            'low'
        );
    }

    public function render_tags_checkbox_metabox($post) {
        wp_nonce_field('andw_llmoqa_tags_checkbox_nonce', 'andw_llmoqa_tags_checkbox_nonce_field');
        
        $current_tags = wp_get_post_terms($post->ID, self::TAX_TAG, ['fields' => 'ids']);
        $all_tags = get_terms([
            'taxonomy' => self::TAX_TAG,
            'hide_empty' => false,
            'number' => 50,
            'orderby' => 'count',
            'order' => 'DESC'
        ]);

        if (empty($all_tags)) {
            echo '<p>まだタグが登録されていません。</p>';
            return;
        }

        echo '<div class="andw_llmoqa-tags-checkbox">';
        foreach ($all_tags as $tag) {
            $checked = in_array($tag->term_id, $current_tags);
            echo '<label style="display:block;margin:4px 0;">';
            echo '<input type="checkbox" name="andw_llmoqa_tag_ids[]" value="' . esc_attr($tag->term_id) . '" ' . checked($checked, true, false) . '>';
            echo ' ' . esc_html($tag->name) . ' (' . absint($tag->count) . ')';
            echo '</label>';
        }
        echo '</div>';
    }

    public function save_tags_checkbox_metabox($post_id) {
        if (!isset($_POST['andw_llmoqa_tags_checkbox_nonce_field']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['andw_llmoqa_tags_checkbox_nonce_field'] ?? '')), 'andw_llmoqa_tags_checkbox_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // 標準タグUIからの入力も取得
        $tag_input = isset($_POST['tax_input'][self::TAX_TAG]) ? sanitize_text_field(wp_unslash($_POST['tax_input'][self::TAX_TAG])) : '';
        $input_tags = [];
        if (!empty($tag_input)) {
            $input_tags = array_map('trim', explode(',', $tag_input));
        }

        // チェックボックスからの選択
        $checkbox_tag_ids = isset($_POST['andw_llmoqa_tag_ids']) ? array_map('intval', $_POST['andw_llmoqa_tag_ids']) : [];
        
        // 両方をマージ
        $all_tag_terms = [];
        
        // 入力されたタグを処理（新規作成も含む）
        foreach ($input_tags as $tag_name) {
            if (empty($tag_name)) continue;
            $term = term_exists($tag_name, self::TAX_TAG);
            if (!$term) {
                $term = wp_insert_term($tag_name, self::TAX_TAG, [
                    'slug' => sanitize_title($tag_name)
                ]);
            }
            if (!is_wp_error($term)) {
                $all_tag_terms[] = is_array($term) ? $term['term_id'] : $term;
            }
        }
        
        // チェックボックスの選択をマージ
        $all_tag_terms = array_merge($all_tag_terms, $checkbox_tag_ids);
        $all_tag_terms = array_unique($all_tag_terms);
        
        // タグを設定
        wp_set_object_terms($post_id, $all_tag_terms, self::TAX_TAG, false);
    }

    /** タグ語ハイライト機能 */
    public function highlight_tag_words($content) {
        if (!is_singular(self::CPT) || !in_the_loop() || !is_main_query()) return $content;
        
        $highlight_enabled = (bool)get_option(self::OPT_TAG_HIGHLIGHT, false);
        if (!$highlight_enabled) return $content;

        $post_tags = wp_get_post_terms(get_the_ID(), self::TAX_TAG, ['fields' => 'names']);
        if (empty($post_tags)) return $content;

        // HTMLを壊さないよう、テキストノードのみを対象とする
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = true;
        
        // エラー管理をtry-finallyで確実に行う
        libxml_use_internal_errors(true);
        $original_errors = libxml_get_errors();
        
        try {
            // LIBXML_NONET を追加してネットワークアクセスを禁止
            $html_content = '<meta charset="utf-8">' . $content;
            $success = $dom->loadHTML($html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
            
            // libxml エラーチェック
            $errors = libxml_get_errors();
            if (!$success || !empty($errors)) {
                return $content; // エラーがあれば元のコンテンツを返す
            }
        } catch (Exception $e) {
            return $content; // 例外発生時は元のコンテンツを返す
        } finally {
            libxml_clear_errors();
            // 元のエラー状態を復元
            foreach ($original_errors as $error) {
                libxml_use_internal_errors(true);
            }
        }
        
        // 除外タグ
        $exclude_tags = ['code', 'pre', 'a', 'script', 'style'];
        
        $xpath = new DOMXPath($dom);
        $text_nodes = $xpath->query('//text()[not(ancestor::' . implode(' or ancestor::', $exclude_tags) . ')]');
        
        foreach ($text_nodes as $node) {
            $text = $node->nodeValue;
            $modified = false;
            
            foreach ($post_tags as $tag) {
                if (empty($tag)) continue;
                $pattern = '/(' . preg_quote($tag, '/') . ')/ui';
                if (preg_match($pattern, $text)) {
                    $text = preg_replace($pattern, '<mark class="tagged">$1</mark>', $text);
                    $modified = true;
                }
            }
            
            if ($modified) {
                $fragment = $dom->createDocumentFragment();
                $fragment->appendXML($text);
                $node->parentNode->replaceChild($fragment, $node);
            }
        }
        
        $result = $dom->saveHTML();
        
        // 追加したmeta charsetタグを削除（最初のもののみ）
        $result = preg_replace('/<meta charset="utf-8">/i', '', $result, 1);
        
        return $result;
    }

    /** Kill Switch チェック */
    private function is_disabled() {
        return (bool)get_option(self::OPT_DISABLED, false);
    }

    /** v0.05: 新メタフィールド登録 */
    public function register_meta_fields() {
        // 表示用リッチコンテンツ（Gutenberg blocks serialized）
        register_post_meta(self::CPT, self::META_ANSWER_DISPLAY, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => function($allowed, $meta_key, $object_id) {
                return current_user_can('edit_post', $object_id);
            }
        ]);

        // スキーマ用プレーンテキスト
        register_post_meta(self::CPT, self::META_ANSWER_SCHEMA, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => function($allowed, $meta_key, $object_id) {
                return current_user_can('edit_post', $object_id);
            }
        ]);

        // 手動/自動切り替えフラグ
        register_post_meta(self::CPT, self::META_SCHEMA_MANUAL, [
            'type' => 'boolean',
            'single' => true,
            'default' => false,
            'show_in_rest' => true,
            'auth_callback' => function($allowed, $meta_key, $object_id) {
                return current_user_can('edit_post', $object_id);
            }
        ]);

        // 投稿単位のスキーマ出力ON/OFF
        register_post_meta(self::CPT, self::META_SCHEMA_ENABLED, [
            'type' => 'boolean',
            'single' => true,
            'default' => true,
            'show_in_rest' => true,
            'auth_callback' => function($allowed, $meta_key, $object_id) {
                return current_user_can('edit_post', $object_id);
            }
        ]);
    }

    /** v0.05: 禁止ブロック検出 */
    public function has_forbidden_blocks($post_content) {
        $forbidden_blocks = [
            'core/video',
            'core/embed',
            'core/html',
            'core/file',
            'core/audio',
            'core/gallery',
            'core/media-text',
            'core/cover',
            'core/freeform',
            // 主要埋め込み系
            'core-embed/youtube',
            'core-embed/twitter',
            'core-embed/facebook',
            'core-embed/instagram',
            'core-embed/vimeo',
            'core-embed/soundcloud',
            'core-embed/spotify',
            'core-embed/flickr',
            'core-embed/animoto',
            'core-embed/cloudup',
            'core-embed/crowdsignal',
            'core-embed/dailymotion',
            'core-embed/hulu',
            'core-embed/imgur',
            'core-embed/issuu',
            'core-embed/kickstarter',
            'core-embed/meetup-com',
            'core-embed/mixcloud',
            'core-embed/photobucket',
            'core-embed/polldaddy',
            'core-embed/reddit',
            'core-embed/reverbnation',
            'core-embed/screencast',
            'core-embed/scribd',
            'core-embed/slideshare',
            'core-embed/smugmug',
            'core-embed/speaker-deck',
            'core-embed/ted',
            'core-embed/tumblr',
            'core-embed/videopress',
            'core-embed/wordpress-tv',
            'core-embed/amazon-kindle'
        ];

        if (empty($post_content)) {
            return false;
        }

        $blocks = parse_blocks($post_content);
        return $this->scan_blocks_for_forbidden($blocks, $forbidden_blocks);
    }

    /** 再帰的にブロックスキャン */
    private function scan_blocks_for_forbidden($blocks, $forbidden_blocks) {
        foreach ($blocks as $block) {
            if (in_array($block['blockName'], $forbidden_blocks, true)) {
                return true;
            }
            // 子ブロック（InnerBlocks）もチェック
            if (!empty($block['innerBlocks'])) {
                if ($this->scan_blocks_for_forbidden($block['innerBlocks'], $forbidden_blocks)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** v0.05: リッチコンテンツからプレーンテキスト生成 */
    public function generate_plain_from_rich($rich_content) {
        if (empty($rich_content)) {
            return '';
        }

        // ブロックコンテンツをHTMLに変換
        $html = do_blocks($rich_content);

        // 許可する最小限のHTMLタグ
        $allowed_tags = [
            'p' => [],
            'h3' => [],
            'h4' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'blockquote' => [],
            'a' => ['href' => []]
        ];

        // HTMLをサニタイズ
        $clean_html = wp_kses($html, $allowed_tags);

        // HTMLタグを除去してプレーンテキストに
        $plain_text = wp_strip_all_tags($clean_html);

        // 改行を統一し、連続する空白を圧縮
        $plain_text = preg_replace('/\r\n|\r|\n/', "\n", $plain_text);
        $plain_text = preg_replace('/\s+/u', ' ', $plain_text);
        $plain_text = trim($plain_text);

        // 1000文字に制限
        if (mb_strlen($plain_text, 'UTF-8') > 1000) {
            $plain_text = mb_substr($plain_text, 0, 1000, 'UTF-8');
        }

        return $plain_text;
    }

    /** v0.06: 描画時フォールバック要約生成 */
    public function generate_fallback_summary($post_id) {
        // メタフィールドに既に要約があれば使用
        $existing_schema = get_post_meta($post_id, self::META_ANSWER_SCHEMA, true);
        if (!empty($existing_schema)) {
            return $existing_schema;
        }

        // ローカル要約生成
        $post = get_post($post_id);
        if (!$post || $post->post_type !== self::CPT) {
            return '';
        }

        // Answer Container または投稿本文からローカル要約
        $answer_display = get_post_meta($post_id, self::META_ANSWER_DISPLAY, true);
        $source_content = !empty($answer_display) ? $answer_display : $post->post_content;
        
        if (empty($source_content)) {
            // 既存の即答フィールド（後方互換性）
            $short_answer = get_post_meta($post_id, self::META_SHORT, true);
            return !empty($short_answer) ? $short_answer : '';
        }

        // プレーンテキスト生成
        $plain_text = $this->generate_plain_from_rich($source_content);
        
        // 160文字で要約（一覧表示用）
        if (mb_strlen($plain_text, 'UTF-8') > 160) {
            $plain_text = mb_substr($plain_text, 0, 160, 'UTF-8') . '…';
        }

        return $plain_text;
    }

    /** v0.06: スキーマ要約生成（メタ空時はAI優先） */
    public function generate_schema_with_fallback($content, $post_id) {
        if (empty($content)) {
            return '';
        }

        // ローカル要約を生成
        $local_summary = $this->generate_plain_from_rich($content);
        
        // AI要約を試す（メタが空なら常に実行）
        $ai_summary = $this->try_ai_summary($content);
        if (!empty($ai_summary)) {
            return $ai_summary;
        }
        
        // AI失敗時はローカル要約にフォールバック
        return $local_summary;
    }

    /** v0.06: AI要約の試行 */
    private function try_ai_summary($content) {
        // AI要約機能が無効の場合はスキップ
        if (!(bool)get_option(self::OPT_ENABLE_AI_SUMMARY, false)) {
            return '';
        }

        // APIキーが設定されていない場合はスキップ
        $api_key = get_option(self::OPT_OPENAI_API_KEY, '');
        if (empty($api_key)) {
            return '';
        }

        try {
            $openai_api = new Andw_Llmo_QA_OpenAI_API();
            $result = $openai_api->summarize_text($content);
            
            if ($result['success']) {
                return $result['summary'];
            } else {
                return '';
            }
        } catch (Exception $e) {
            // 例外処理
            return '';
        }
    }

    /** v0.06: AI要約生成（CLI用パブリックメソッド） */
    public function generate_ai_summary($content, $post_id = 0) {
        return $this->try_ai_summary($content);
    }

    /** チェックボックス用サニタイゼーション */
    public function sanitize_checkbox($value) {
        return empty($value) ? 0 : 1;
    }

    /** AI モデル用サニタイゼーション */
    public function sanitize_ai_model($model) {
        $allowed_models = ['gpt-4o-mini', 'gpt-4o', 'gpt-3.5-turbo'];
        $model = sanitize_text_field($model);
        
        if (!in_array($model, $allowed_models, true)) {
            return 'gpt-4o-mini'; // デフォルト値
        }
        
        return $model;
    }

    /** AI タイムアウト用サニタイゼーション */
    public function sanitize_ai_timeout($timeout) {
        $timeout = (int) $timeout;
        
        // 5-60秒の範囲にクランプ、範囲外または0の場合は12秒
        if ($timeout < 5 || $timeout > 60) {
            if ($timeout !== 0) { // 0以外の範囲外値の場合はエラー表示
                add_settings_error(
                    'andw_llmoqa_ai_timeout',
                    'invalid_timeout',
                    __('タイムアウト値は5〜60秒の範囲で入力してください（未入力は12秒）。', 'andw-llmo-qa'),
                    'error'
                );
            }
            return 12;
        }
        
        return $timeout;
    }

    /** 関連QA件数用サニタイゼーション */
    public function sanitize_rel_num($num) {
        $num = (int) $num;
        
        // 関連QAが無効の場合は検証をスキップ
        $rel_enabled = !empty(get_option(self::OPT_REL_ON));
        if (!$rel_enabled) {
            return max(1, $num); // 最小値1を保持するが検証はしない
        }
        
        // 有効時は1-20の範囲でクランプ
        if ($num < 1) {
            add_settings_error(
                'andw_llmoqa_rel_num',
                'invalid_rel_num',
                __('関連QA件数は1件以上で入力してください。', 'andw-llmo-qa'),
                'error'
            );
            return 1;
        }
        
        return min(20, max(1, $num));
    }

    /** Author タイプ用サニタイゼーション */
    public function sanitize_author_type($type) {
        $allowed_types = ['Organization', 'Person'];
        $type = sanitize_text_field($type);
        
        if (!in_array($type, $allowed_types, true)) {
            return 'Organization';
        }
        
        return $type;
    }

    /** v0.06: APIキーサニタイゼーション */
    public function sanitize_api_key($api_key) {
        $api_key = trim($api_key);
        
        // 空の場合は既存値を保持（変更しない）
        if (empty($api_key)) {
            return get_option(self::OPT_OPENAI_API_KEY, '');
        }
        
        // OpenAI APIキーの基本フォーマットチェック
        if (!preg_match('/^sk-[a-zA-Z0-9-_]{32,}$/', $api_key)) {
            add_settings_error(
                'andw_llmoqa_openai_api_key',
                'invalid_api_key',
                __('OpenAI APIキーの形式が正しくありません。sk- で始まる形式を使用してください。', 'andw-llmo-qa'),
                'error'
            );
            return get_option(self::OPT_OPENAI_API_KEY, ''); // 既存値を保持
        }
        
        return $api_key;
    }

    /** v0.06: APIキーマスク表示（末尾4桁のみ）*/
    public function mask_api_key($api_key) {
        if (empty($api_key)) {
            return __('未設定', 'andw-llmo-qa');
        }
        
        $key_length = mb_strlen($api_key, 'UTF-8');
        if ($key_length < 8) {
            return str_repeat('•', $key_length);
        }
        
        // 末尾4桁のみ表示、先頭は●でマスク
        $visible_chars = 4;
        $masked_length = max(0, $key_length - $visible_chars);
        $masked_part = str_repeat('•', min(20, $masked_length)); // 最大20文字まで
        $visible_part = mb_substr($api_key, -$visible_chars, null, 'UTF-8');
        
        return $masked_part . $visible_part;
    }

    /** v0.05: Answer Container 保存ロジック */
    public function save_answer_container_logic($post_id) {
        // 基本チェック
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (get_post_type($post_id) !== self::CPT) return;

        // Kill Switch チェック
        if ($this->is_disabled()) return;

        $post = get_post($post_id);
        if (!$post) return;

        // 現在の設定値を取得
        $schema_manual = (bool)get_post_meta($post_id, self::META_SCHEMA_MANUAL, true);
        $schema_enabled = get_post_meta($post_id, self::META_SCHEMA_ENABLED, true);
        
        // 初回保存時のデフォルト設定
        if ($schema_enabled === '') {
            update_post_meta($post_id, self::META_SCHEMA_ENABLED, true);
            $schema_enabled = true;
        }

        // Answer Containerブロックを検索・抽出
        $answer_display_content = $this->extract_answer_container_content($post->post_content);
        
        if ($answer_display_content) {
            // Answer Containerが見つかった場合
            update_post_meta($post_id, self::META_ANSWER_DISPLAY, $answer_display_content);
            
            // 自動モード & メタが空の場合のみ要約生成
            if (!$schema_manual) {
                $existing_schema = get_post_meta($post_id, self::META_ANSWER_SCHEMA, true);
                if (empty($existing_schema)) {
                    $schema_text = $this->generate_schema_with_fallback($answer_display_content, $post_id);
                    update_post_meta($post_id, self::META_ANSWER_SCHEMA, $schema_text);
                }
            }
        } else {
            // Answer Containerが見つからない場合は投稿本文全体から生成
            if (!$schema_manual && !empty($post->post_content)) {
                $existing_schema = get_post_meta($post_id, self::META_ANSWER_SCHEMA, true);
                if (empty($existing_schema)) {
                    $schema_text = $this->generate_schema_with_fallback($post->post_content, $post_id);
                    update_post_meta($post_id, self::META_ANSWER_SCHEMA, $schema_text);
                }
            }
        }

        // 禁止ブロック検出処理
        $stop_on_forbidden = (bool)get_option(self::OPT_STOP_SCHEMA_ON_FORBIDDEN, true);
        if ($stop_on_forbidden && $this->has_forbidden_blocks($post->post_content)) {
            // 禁止ブロックが検出された場合、スキーマ出力を自動停止
            update_post_meta($post_id, self::META_SCHEMA_ENABLED, false);
            
            // 管理者通知をセット（次回管理画面表示時に表示）
            set_transient('andw_llmoqa_forbidden_notice_' . get_current_user_id(), [
                'post_id' => $post_id,
                'post_title' => $post->post_title
            ], 300); // 5分間有効
        }
    }

    /** Answer Container ブロックコンテンツを抽出 */
    private function extract_answer_container_content($post_content) {
        if (empty($post_content)) {
            return '';
        }

        $blocks = parse_blocks($post_content);
        return $this->find_answer_container_in_blocks($blocks);
    }

    /** 再帰的にAnswer Containerブロックを検索 */
    private function find_answer_container_in_blocks($blocks) {
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'andw/llmo-answer-container') {
                // Answer Containerブロックが見つかった
                return serialize_blocks($block['innerBlocks'] ?? []);
            }
            
            // 子ブロックも検索
            if (!empty($block['innerBlocks'])) {
                $result = $this->find_answer_container_in_blocks($block['innerBlocks']);
                if ($result) {
                    return $result;
                }
            }
        }
        
        return '';
    }

    /** 禁止ブロック検出時の管理画面通知 */
    public function show_forbidden_block_admin_notice() {
        $notice_data = get_transient('andw_llmoqa_forbidden_notice_' . get_current_user_id());
        if ($notice_data) {
            delete_transient('andw_llmoqa_forbidden_notice_' . get_current_user_id());
            
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__('andW Q&A:', 'andw-llmo-qa') . '</strong> ';
            echo sprintf(
                /* translators: %s は投稿タイトル */
                esc_html__('「%s」で禁止ブロックが検出されました。スキーマ出力が自動停止されました。', 'andw-llmo-qa'),
                esc_html($notice_data['post_title'])
            );
            echo '</p></div>';
        }
    }

    /** 有効化 */
    public static function on_activate() {
        if (is_multisite() && is_network_admin()) {
            // ネットワーク有効化：全サイトで実行
            $sites = get_sites(['number' => 0]);
            foreach ($sites as $site) {
                switch_to_blog($site->blog_id);
                self::activate_single_site();
                restore_current_blog();
            }
        } else {
            // 単一サイト有効化
            if (is_multisite() && !current_user_can('manage_options')) {
                return; // マルチサイトではサイト管理者権限が必要
            }
            self::activate_single_site();
        }
    }

    /** 無効化 */
    public static function on_deactivate() {
        if (is_multisite() && (is_network_admin() || (defined('WP_CLI') && WP_CLI))) {
            // ネットワーク無効化：全サイトで実行
            $site_ids = get_sites(['number' => 0, 'fields' => 'ids']);
            foreach ($site_ids as $blog_id) {
                switch_to_blog($blog_id);
                try {
                    self::deactivate_single_site();
                } finally {
                    restore_current_blog();
                }
            }
        } else {
            self::deactivate_single_site();
        }
    }

    /** 単一サイト有効化処理 */
    private static function activate_single_site() {
        $self = new self();
        $self->register_cpt_tax();
        
        // v0.05: 新設定のデフォルト値設定（autoload: no）
        if (false === get_option(self::OPT_USE_RICH_ON_ARCHIVE)) {
            add_option(self::OPT_USE_RICH_ON_ARCHIVE, false, '', 'no');
        }
        if (false === get_option(self::OPT_STOP_SCHEMA_ON_FORBIDDEN)) {
            add_option(self::OPT_STOP_SCHEMA_ON_FORBIDDEN, true, '', 'no');
        }
        if (false === get_option(self::OPT_DISABLED)) {
            add_option(self::OPT_DISABLED, false, '', 'no');
        }

        // v0.06: AI要約設定のデフォルト値設定（autoload: no）
        if (false === get_option(self::OPT_OPENAI_API_KEY)) {
            add_option(self::OPT_OPENAI_API_KEY, '', '', 'no');
        }
        if (false === get_option(self::OPT_ENABLE_AI_SUMMARY)) {
            add_option(self::OPT_ENABLE_AI_SUMMARY, false, '', 'no');
        }
        if (false === get_option(self::OPT_AI_MODEL)) {
            add_option(self::OPT_AI_MODEL, 'gpt-4o-mini', '', 'no');
        }
        if (false === get_option(self::OPT_AI_TIMEOUT)) {
            add_option(self::OPT_AI_TIMEOUT, 12, '', 'no');
        }

        flush_rewrite_rules();
    }

    /** 単一サイト無効化処理（必要最小の後始末のみ） */
    private static function deactivate_single_site(): void {
        // 例：登録していれば Cron を解除（フック名は後で差し込む）
        // if (wp_next_scheduled('andw_llmoqa_cron')) {
        //     wp_clear_scheduled_hook('andw_llmoqa_cron');
        // }

        // CPT/タクソノミーのリライトルール掃除
        flush_rewrite_rules();
    }
}
new Andw_Llmo_QA_Plugin();
