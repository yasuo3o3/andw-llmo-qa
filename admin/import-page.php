<?php if (!defined('ABSPATH')) exit;
$action_url = admin_url('edit.php?post_type=' . Andw_Llmo_QA_Plugin::CPT . '&page=andw_llmoqa-import');

// v0.06: タブ切替対応
$allowed_tabs = ['import', 'ai-summary', 'settings'];
$current_tab = sanitize_key(wp_unslash($_GET['tab'] ?? ''));
if (!in_array($current_tab, $allowed_tabs, true)) {
    $current_tab = 'import';
}
$tabs = [
    'import' => __('CSVインポート', 'andw-llmo-qa'),
    'ai-summary' => __('要約（AI）', 'andw-llmo-qa'),
    'settings' => __('基本設定', 'andw-llmo-qa')
];
?>
<div class="wrap andw_llmoqa-admin">
  <h1>CSVインポート & 設定</h1>
  
  <!-- タブナビゲーション -->
  <nav class="nav-tab-wrapper">
    <?php foreach ($tabs as $tab_key => $tab_label): ?>
      <a href="<?php echo esc_url(add_query_arg('tab', $tab_key, $action_url)); ?>" 
         class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
        <?php echo esc_html($tab_label); ?>
      </a>
    <?php endforeach; ?>
  </nav>

<?php if ($current_tab === 'ai-summary'): ?>
  <!-- AI要約設定タブ -->
  <div class="card">
    <h2><?php esc_html_e('要約（AI）設定', 'andw-llmo-qa'); ?></h2>
    <p class="description">
      <?php esc_html_e('要約メタフィールドが空のときに自動要約を生成します。要約が空のときのみAPIを使用し、送信前にHTMLはテキスト化されます。', 'andw-llmo-qa'); ?>
    </p>
    
    <form method="post" action="options.php">
      <?php 
      settings_fields('andw_llmoqa_group_ai');
      
      // v0.06: AI要約設定値を取得（保存処理はSettings APIに委譲済み）
      $api_key = get_option(Andw_Llmo_QA_Plugin::OPT_OPENAI_API_KEY, '');
      $enable_ai = (bool)get_option(Andw_Llmo_QA_Plugin::OPT_ENABLE_AI_SUMMARY, false);
      $ai_model = get_option(Andw_Llmo_QA_Plugin::OPT_AI_MODEL, 'gpt-4o-mini');
      $ai_timeout = (int)get_option(Andw_Llmo_QA_Plugin::OPT_AI_TIMEOUT, 12);
      // 0の場合はデフォルト値を使用
      if ($ai_timeout === 0) {
          $ai_timeout = 12;
      }
      
      $plugin = new Andw_Llmo_QA_Plugin();
      ?>
      
      <table class="form-table">
        <tr>
          <th scope="row">
            <label for="andw_llmoqa_openai_api_key"><?php esc_html_e('OpenAI APIキー', 'andw-llmo-qa'); ?></label>
          </th>
          <td>
            <div class="andw_llmoqa-key-row">
              <input type="password" 
                     id="andw_llmoqa_openai_api_key" 
                     name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_OPENAI_API_KEY); ?>"
                     value="" 
                     class="andw_llmoqa-input-key" 
                     placeholder="<?php echo !empty($api_key) ? esc_attr__('新しいAPIキーを入力（変更しない場合は空欄）', 'andw-llmo-qa') : 'sk-...'; ?>" />
              <button type="button" 
                      id="clear-api-key" 
                      class="button button-secondary"
                      data-confirm="<?php esc_attr_e('APIキーを削除してもよろしいですか？', 'andw-llmo-qa'); ?>">
                <?php esc_html_e('APIキーを削除', 'andw-llmo-qa'); ?>
              </button>
            </div>
            
            <div class="andw_llmoqa-key-status" aria-label="<?php esc_attr_e('現在設定済みキー（マスク表示）', 'andw-llmo-qa'); ?>">
              <code><?php echo esc_html($plugin->mask_api_key($api_key)); ?></code>
            </div>
            
            <p class="description">
              <?php esc_html_e('OpenAI のAPIキーを入力してください。形式: sk-で始まる文字列', 'andw-llmo-qa'); ?>
            </p>
          </td>
        </tr>
        
        <tr>
          <th scope="row"><?php esc_html_e('AI要約機能', 'andw-llmo-qa'); ?></th>
          <td>
            <fieldset>
              <input type="hidden" name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_ENABLE_AI_SUMMARY); ?>" value="0">
              <label>
                <input type="checkbox" 
                       name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_ENABLE_AI_SUMMARY); ?>" 
                       value="1" 
                       <?php checked($enable_ai); ?> />
                <?php esc_html_e('AI要約を有効にする', 'andw-llmo-qa'); ?>
              </label>
              <p class="description">
                <?php esc_html_e('要約メタフィールドが空の場合にOpenAI APIを使用して要約を生成します', 'andw-llmo-qa'); ?>
              </p>
            </fieldset>
          </td>
        </tr>
        
        <tr>
          <th scope="row">
            <label for="andw_llmoqa_ai_model"><?php esc_html_e('使用AIモデル', 'andw-llmo-qa'); ?></label>
          </th>
          <td>
            <select id="andw_llmoqa_ai_model" name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_AI_MODEL); ?>">
              <option value="gpt-4o-mini" <?php selected($ai_model, 'gpt-4o-mini'); ?>>gpt-4o-mini (推奨)</option>
              <option value="gpt-4o" <?php selected($ai_model, 'gpt-4o'); ?>>gpt-4o</option>
              <option value="gpt-3.5-turbo" <?php selected($ai_model, 'gpt-3.5-turbo'); ?>>gpt-3.5-turbo</option>
            </select>
            <p class="description">
              <?php esc_html_e('gpt-4o-mini は低コストで高品質な要約が可能です（推奨）', 'andw-llmo-qa'); ?>
            </p>
          </td>
        </tr>
        
        <tr>
          <th scope="row">
            <label for="andw_llmoqa_ai_timeout"><?php esc_html_e('API タイムアウト', 'andw-llmo-qa'); ?></label>
          </th>
          <td>
            <input type="number" 
                   id="andw_llmoqa_ai_timeout" 
                   name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_AI_TIMEOUT); ?>"
                   value="<?php echo esc_attr($ai_timeout); ?>" 
                   min="5" 
                   max="60" 
                   step="1" /> 秒
            <p class="description">
              <?php esc_html_e('API リクエストのタイムアウト時間（5-60秒、既定: 12秒）', 'andw-llmo-qa'); ?>
            </p>
          </td>
        </tr>
      </table>
      
      <?php submit_button(__('設定を保存', 'andw-llmo-qa')); ?>
    </form>

    <?php if (!empty($api_key) && $enable_ai): ?>
    <div class="card" style="margin-top: 20px;">
      <h3><?php esc_html_e('API 接続テスト', 'andw-llmo-qa'); ?></h3>
      <p class="description">
        <?php esc_html_e('OpenAI APIへの接続が正常に動作するかテストします。', 'andw-llmo-qa'); ?>
      </p>
      
      <form method="post" action="">
        <?php wp_nonce_field('andw_llmoqa_test_api', 'andw_llmoqa_test_api_nonce'); ?>
        <input type="hidden" name="action" value="test_api" />
        <button type="submit" class="button button-secondary" id="test-api-button">
          <?php esc_html_e('API 接続テスト実行', 'andw-llmo-qa'); ?>
        </button>
      </form>

      <?php
      // API テスト処理
      if (isset($_SERVER['REQUEST_METHOD']) && 'POST' === $_SERVER['REQUEST_METHOD'] && 
          isset($_POST['action']) && $_POST['action'] === 'test_api') {
          $nonce = sanitize_text_field(wp_unslash($_POST['andw_llmoqa_test_api_nonce'] ?? ''));
          if (!wp_verify_nonce($nonce, 'andw_llmoqa_test_api')) {
              wp_die(esc_html__('Security check failed.', 'andw-llmo-qa'));
          }
          
          if (current_user_can('manage_options')) {
              try {
                  $openai_api = new Andw_Llmo_QA_OpenAI_API();
                  $test_result = $openai_api->test_api_key();
                  
                  if ($test_result['success']) {
                      echo '<div class="notice notice-success inline">';
                      echo '<p><strong>' . esc_html__('成功', 'andw-llmo-qa') . ':</strong> ';
                      echo esc_html__('API接続が正常に動作しています。', 'andw-llmo-qa');
                      echo '<br>' . esc_html__('テスト要約', 'andw-llmo-qa') . ': ' . esc_html(mb_substr($test_result['summary'], 0, 100, 'UTF-8')) . '...</p>';
                      echo '</div>';
                  } else {
                      echo '<div class="notice notice-error inline">';
                      echo '<p><strong>' . esc_html__('エラー', 'andw-llmo-qa') . ':</strong> ';
                      echo esc_html($test_result['error']);
                      echo '</p></div>';
                  }
              } catch (Exception $e) {
                  echo '<div class="notice notice-error inline">';
                  echo '<p><strong>' . esc_html__('例外エラー', 'andw-llmo-qa') . ':</strong> ';
                  echo esc_html($e->getMessage());
                  echo '</p></div>';
              }
          }
      }
      ?>
    </div>
    <?php endif; ?>
    
  </div>

<?php elseif ($current_tab === 'settings'): ?>
  <!-- 基本設定タブ -->
  <div class="card">
    <h2><?php esc_html_e('基本設定', 'andw-llmo-qa'); ?></h2>
    
    <form method="post" action="options.php">
      <?php 
      settings_fields('andw_llmoqa_group_basic');
      
      $rel_on = (bool)get_option(Andw_Llmo_QA_Plugin::OPT_REL_ON, true);
      $rel_num = (int)get_option(Andw_Llmo_QA_Plugin::OPT_REL_NUM, 6);
      // 0の場合はデフォルト値を使用
      if ($rel_num === 0) {
          $rel_num = 6;
      }
      $tag_display = (bool)get_option(Andw_Llmo_QA_Plugin::OPT_TAG_DISPLAY, true);
      $tag_highlight = (bool)get_option(Andw_Llmo_QA_Plugin::OPT_TAG_HIGHLIGHT, false);
      $use_rich_on_archive = (bool)get_option(Andw_Llmo_QA_Plugin::OPT_USE_RICH_ON_ARCHIVE, false);
      $stop_schema_on_forbidden = (bool)get_option(Andw_Llmo_QA_Plugin::OPT_STOP_SCHEMA_ON_FORBIDDEN, true);
      
      // v0.06: JSON-LD拡張設定
      $schema_author_type = get_option(Andw_Llmo_QA_Plugin::OPT_SCHEMA_AUTHOR_TYPE, 'Organization');
      $schema_author_name = get_option(Andw_Llmo_QA_Plugin::OPT_SCHEMA_AUTHOR_NAME, '');
      ?>
      
      <table class="form-table">
        <tr>
          <th scope="row"><?php esc_html_e('関連Q&A自動表示', 'andw-llmo-qa'); ?></th>
          <td>
            <fieldset>
              <input type="hidden" name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_REL_ON); ?>" value="0">
              <label>
                <input type="checkbox" 
                       id="andw_llmoqa_rel_on"
                       name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_REL_ON); ?>" 
                       value="1" 
                       <?php checked($rel_on); ?> />
                <?php esc_html_e('単体Q&A記事に関連記事を自動表示', 'andw-llmo-qa'); ?>
              </label>
              <br>
              <label>
                <?php esc_html_e('表示件数', 'andw-llmo-qa'); ?>:
                <input type="number" 
                       id="andw_llmoqa_rel_num"
                       name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_REL_NUM); ?>"
                       value="<?php echo esc_attr($rel_num); ?>" 
                       min="1" 
                       max="20" 
                       step="1" 
                       style="width: 60px;"
                       <?php echo $rel_on ? '' : 'disabled'; ?> /> <?php esc_html_e('件', 'andw-llmo-qa'); ?>
              </label>
            </fieldset>
          </td>
        </tr>
        
        <tr>
          <th scope="row"><?php esc_html_e('タグ機能', 'andw-llmo-qa'); ?></th>
          <td>
            <fieldset>
              <input type="hidden" name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_TAG_DISPLAY); ?>" value="0">
              <label>
                <input type="checkbox" 
                       name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_TAG_DISPLAY); ?>" 
                       value="1" 
                       <?php checked($tag_display); ?> />
                <?php esc_html_e('タグチップを表示', 'andw-llmo-qa'); ?>
              </label>
              <br>
              <input type="hidden" name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_TAG_HIGHLIGHT); ?>" value="0">
              <label>
                <input type="checkbox" 
                       name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_TAG_HIGHLIGHT); ?>" 
                       value="1" 
                       <?php checked($tag_highlight); ?> />
                <?php esc_html_e('本文中のタグ語をハイライト', 'andw-llmo-qa'); ?>
              </label>
            </fieldset>
          </td>
        </tr>
        
        <tr>
          <th scope="row"><?php esc_html_e('表示設定', 'andw-llmo-qa'); ?></th>
          <td>
            <fieldset>
              <input type="hidden" name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_USE_RICH_ON_ARCHIVE); ?>" value="0">
              <label>
                <input type="checkbox" 
                       name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_USE_RICH_ON_ARCHIVE); ?>" 
                       value="1" 
                       <?php checked($use_rich_on_archive); ?> />
                <?php esc_html_e('一覧ページでリッチ表示を使用', 'andw-llmo-qa'); ?>
              </label>
              <p class="description">
                <?php esc_html_e('OFF時はプレーン要約を表示（推奨）', 'andw-llmo-qa'); ?>
              </p>
            </fieldset>
          </td>
        </tr>
        
        <tr>
          <th scope="row"><?php esc_html_e('スキーマ制御', 'andw-llmo-qa'); ?></th>
          <td>
            <fieldset>
              <input type="hidden" name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_STOP_SCHEMA_ON_FORBIDDEN); ?>" value="0">
              <label>
                <input type="checkbox" 
                       name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_STOP_SCHEMA_ON_FORBIDDEN); ?>" 
                       value="1" 
                       <?php checked($stop_schema_on_forbidden); ?> />
                <?php esc_html_e('禁止ブロック検出時にスキーマ出力を自動停止', 'andw-llmo-qa'); ?>
              </label>
              <p class="description">
                <?php esc_html_e('動画・埋め込み・HTMLブロック等が検出された場合の自動制御', 'andw-llmo-qa'); ?>
              </p>
            </fieldset>
          </td>
        </tr>
        
        <tr>
          <th scope="row"><?php esc_html_e('JSON-LD Author設定', 'andw-llmo-qa'); ?></th>
          <td>
            <fieldset>
              <p class="description">
                <?php esc_html_e('構造化データ（Schema.org）のAuthor情報を設定します。空の場合はサイト名を使用。', 'andw-llmo-qa'); ?>
              </p>
              
              <label>
                <?php esc_html_e('Author タイプ', 'andw-llmo-qa'); ?>:
                <select name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_SCHEMA_AUTHOR_TYPE); ?>">
                  <option value="Organization" <?php selected($schema_author_type, 'Organization'); ?>>
                    <?php esc_html_e('Organization（組織）', 'andw-llmo-qa'); ?>
                  </option>
                  <option value="Person" <?php selected($schema_author_type, 'Person'); ?>>
                    <?php esc_html_e('Person（個人）', 'andw-llmo-qa'); ?>
                  </option>
                </select>
              </label>
              <br><br>
              
              <label>
                <?php esc_html_e('Author 名前', 'andw-llmo-qa'); ?>:
                <input type="text" 
                       name="<?php echo esc_attr(Andw_Llmo_QA_Plugin::OPT_SCHEMA_AUTHOR_NAME); ?>"
                       value="<?php echo esc_attr($schema_author_name); ?>" 
                       class="regular-text" 
                       placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>" />
              </label>
              <p class="description">
                <?php esc_html_e('空の場合は自動的にサイト名を使用します。', 'andw-llmo-qa'); ?>
                <?php
                /* translators: %s はサイト名 */
                echo sprintf(esc_html__('現在のサイト名: %s', 'andw-llmo-qa'), '<strong>' . esc_html(get_bloginfo('name')) . '</strong>'); ?>
              </p>
            </fieldset>
          </td>
        </tr>
      </table>
      
      <?php submit_button(__('設定を保存', 'andw-llmo-qa')); ?>
    </form>
  </div>

<?php else: ?>
  <!-- CSVインポートタブ（既定） -->
  <div class="card">
    <h2><?php esc_html_e('CSV一括投入', 'andw-llmo-qa'); ?></h2>
    <p>UTF-8推奨（SJISの場合は自動変換を試みます）。ヘッダー行は必須。</p>
    <p><strong>カラム仕様：</strong></p>
    <ul>
      <li><code>title</code>（必須）：質問文（投稿タイトル）</li>
      <li><code>short_answer</code>（必須）：即答（50〜100文字目安）</li>
      <li><code>content</code>（必須）：解説（HTML可）</li>
      <li><code>categories</code>（任意）：カテゴリスラッグをカンマ区切り（例：mongolia,reform）</li>
      <li><code>tags</code>（任意）：タグ名をカンマ区切り（例：初心者,費用,安全）</li>
      <li><code>slug</code>（任意）：投稿スラッグ</li>
      <li><code>status</code>（任意）：publish / draft（既定：publish）</li>
    </ul>

    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($action_url); ?>">
      <?php 
      wp_nonce_field('andw_llmoqa_import_csv', 'andw_llmoqa_import_csv_nonce'); 
      wp_nonce_field('andw_llmoqa_import');
      ?>
      <div class="field">
        <input type="file" name="andw_llmoqa_csv" accept=".csv" required />
      </div>
      <div class="field">
        <label><input type="checkbox" name="andw_llmoqa_dry_run" value="1" checked> ドライラン（作成せず検証のみ）</label>
      </div>
      <p class="submit"><button class="button button-primary">CSVを処理</button></p>
    </form>

    <?php
    // 処理
    if (isset($_SERVER['REQUEST_METHOD']) && 'POST' === $_SERVER['REQUEST_METHOD']) {
        // 厳密な権限チェックとnonce検証
        if (!current_user_can('manage_options')) { 
            echo '<div class="error"><p>' . esc_html('権限不足') . '</p></div>'; 
            return;
        }
        
        $nonce = sanitize_text_field(wp_unslash($_POST['andw_llmoqa_import_csv_nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'andw_llmoqa_import_csv')) {
            echo '<div class="error"><p>' . esc_html('セキュリティエラー：不正な要求です') . '</p></div>';
            return;
        }
        
        // admin_referrer も確認
        check_admin_referer('andw_llmoqa_import');

        if (isset($_FILES['andw_llmoqa_csv']) && !empty($_FILES['andw_llmoqa_csv']['tmp_name'])) {
            // 事前アップロード検証
            $tmp_name = sanitize_text_field(wp_unslash($_FILES['andw_llmoqa_csv']['tmp_name']));
            if (!is_uploaded_file($tmp_name)) {
                echo '<div class="error"><p>' . esc_html('不正なファイルアップロードです') . '</p></div>';
            } else {
                // wp_handle_upload()による安全なアップロード処理
                $upload_overrides = array(
                    'test_form' => false,
                    'mimes' => array('csv' => 'text/csv')
                );

                $uploaded = wp_handle_upload($_FILES['andw_llmoqa_csv'], $upload_overrides);

                if (isset($uploaded['error'])) {
                    wp_die(esc_html($uploaded['error']));
                }

                $tmp = $uploaded['file'];
                $filename = basename($uploaded['file']);

                // ファイルサイズチェック
                if (filesize($tmp) > Andw_Llmo_QA_Plugin::CSV_MAX_SIZE) {
                    echo '<div class="error"><p>' . esc_html(sprintf('ファイルサイズが上限（%s）を超えています', size_format(Andw_Llmo_QA_Plugin::CSV_MAX_SIZE))) . '</p></div>';
                    wp_delete_file($tmp);
                } else {
                // 追加のMIME検証
                $file_check = wp_check_filetype_and_ext($tmp, $filename);
                $allowed_types = ['text/csv', 'application/csv', 'text/plain'];

                if (!in_array($file_check['type'], $allowed_types, true)) {
                    echo '<div class="error"><p>' . esc_html('CSVファイルのみアップロード可能です') . '</p></div>';
                    wp_delete_file($tmp);
                } else {
                    $data = file_get_contents($tmp);
                    if ($data === false) {
                        echo '<div class="error"><p>ファイル読込エラー：ファイルが破損している可能性があります</p></div>';
                        wp_delete_file($tmp);
                    } else {

            // 文字コード変換（SJIS→UTF-8想定）
            $enc = mb_detect_encoding($data, ['UTF-8','SJIS','EUC-JP','JIS','ISO-2022-JP'], true);
            if ($enc && $enc !== 'UTF-8') {
                $converted = mb_convert_encoding($data, 'UTF-8', $enc);
                if ($converted !== false) {
                    $data = $converted;
                } else {
                    echo '<div class="error"><p>' . esc_html('文字コード変換エラー：' . $enc . 'からUTF-8への変換に失敗しました') . '</p></div>';
                    wp_delete_file($tmp);
                }
            }

            // BOM除去
            if (substr($data, 0, 3) === "\xEF\xBB\xBF") {
                $data = substr($data, 3);
            }

            // SplFileObject でCSVパース
            $temp_file = wp_tempnam();
            file_put_contents($temp_file, $data);
            
            $file = new SplFileObject($temp_file);
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
            
            $rows = [];
            $row_count = 0;
            $empty_rows = 0;
            $malformed_rows = 0;
            
            foreach ($file as $line_num => $row) {
                if ($row_count >= Andw_Llmo_QA_Plugin::CSV_MAX_ROWS) {
                    echo '<div class="notice notice-warning"><p>' . esc_html(sprintf('行数上限（%d行）に達したため、処理を打ち切りました', Andw_Llmo_QA_Plugin::CSV_MAX_ROWS)) . '</p></div>';
                    break;
                }
                
                if ($row === null || $row === [null] || $row === false) {
                    if ($row === false) {
                        $malformed_rows++;
                    } else {
                        $empty_rows++;
                    }
                    continue;
                }
                
                $rows[] = $row;
                $row_count++;
            }
            
            wp_delete_file($temp_file);
            
            if ($empty_rows > 0) {
                echo '<div class="notice notice-info"><p>' . esc_html(sprintf('空行 %d 行をスキップしました', $empty_rows)) . '</p></div>';
            }

            if ($malformed_rows > 0) {
                echo '<div class="notice notice-warning"><p>' . esc_html(sprintf('不正な形式の行 %d 行をスキップしました', $malformed_rows)) . '</p></div>';
            }

            if (count($rows) < 1) {
                echo '<div class="error"><p>' . esc_html('データが見つかりません（ヘッダー行＋最低1行のデータが必要です）') . '</p></div>';
                wp_delete_file($tmp);
            } else {

            $headers = array_shift($rows);
            $map = array_flip($headers);

            $required = ['title','short_answer','content'];
            $missing = [];
            foreach ($required as $r) {
                if (!isset($map[$r])) { $missing[] = $r; }
            }
            if (!empty($missing)) {
                echo '<div class="error"><p>' . esc_html('必須カラムが不足：' . implode(', ', $missing)) . '</p></div>';
                wp_delete_file($tmp);
            } else {

            // ヘッダー列数を記録（データ行の列数と比較用）
            $expected_columns = count($headers);

            $dry = !empty($_POST['andw_llmoqa_dry_run']);
            $created = 0; $errors = 0;

            echo '<h3>結果</h3><ol>';
            foreach ($rows as $i => $cols) {
                // 列数チェック
                if (count($cols) !== $expected_columns) {
                    $errors++; 
                    echo '<li>' . esc_html(sprintf('行%d：列数不正（期待%d列、実際%d列）', $i+2, $expected_columns, count($cols))) . '</li>'; 
                    continue;
                }

                $title  = trim($cols[$map['title']] ?? '');
                $short  = trim($cols[$map['short_answer']] ?? '');
                $content= ($cols[$map['content']] ?? '');
                $cats   = isset($map['categories']) ? trim($cols[$map['categories']]) : '';
                $tags   = isset($map['tags']) ? trim($cols[$map['tags']]) : '';
                $slug   = isset($map['slug']) ? sanitize_title($cols[$map['slug']]) : '';
                $status = isset($map['status']) ? strtolower(trim($cols[$map['status']])) : 'publish';
                if (!in_array($status, ['publish','draft'], true)) $status = 'publish';

                if ($title === '' || $short === '' || $content === '') {
                    $errors++; 
                    echo '<li>' . esc_html(sprintf('行%d：必須項目不足', $i+2)) . '</li>'; 
                    continue;
                }

                // ドライラン表示
                if ($dry) {
                    echo '<li>行'.esc_html(intval($i+2)).'：OK（ドライラン） title="'.esc_html($title).'" cats="'.esc_html($cats).'" tags="'.esc_html($tags).'"</li>';
                    continue;
                }

                // 作成
                $postarr = [
                    'post_type' => Andw_Llmo_QA_Plugin::CPT,
                    'post_title'=> $title,
                    'post_content' => $content,
                    'post_status'  => $status,
                ];
                if ($slug) $postarr['post_name'] = $slug;

                $post_id = wp_insert_post($postarr, true);
                if (is_wp_error($post_id)) {
                    $errors++; echo '<li>行'.esc_html(intval($i+2)).'：エラー '.esc_html($post_id->get_error_message()).'</li>';
                    continue;
                }

                update_post_meta($post_id, Andw_Llmo_QA_Plugin::META_SHORT, wp_kses_post($short));

                if ($cats !== '') {
                    $slugs = array_map('trim', explode(',', $cats));
                    foreach ($slugs as $s) {
                        if (!term_exists($s, Andw_Llmo_QA_Plugin::TAX)) {
                            wp_insert_term($s, Andw_Llmo_QA_Plugin::TAX, ['slug'=>$s, 'description'=>'']);
                        }
                    }
                    wp_set_object_terms($post_id, $slugs, Andw_Llmo_QA_Plugin::TAX, false);
                }

                if ($tags !== '') {
                    $tag_names = array_map('trim', explode(',', $tags));
                    $tag_terms = [];
                    foreach ($tag_names as $tag_name) {
                        if (empty($tag_name)) continue;
                        $term = term_exists($tag_name, Andw_Llmo_QA_Plugin::TAX_TAG);
                        if (!$term) {
                            $term = wp_insert_term($tag_name, Andw_Llmo_QA_Plugin::TAX_TAG, [
                                'slug' => sanitize_title($tag_name),
                                'description' => ''
                            ]);
                        }
                        if (!is_wp_error($term)) {
                            $tag_terms[] = is_array($term) ? $term['term_id'] : $term;
                        }
                    }
                    if (!empty($tag_terms)) {
                        wp_set_object_terms($post_id, $tag_terms, Andw_Llmo_QA_Plugin::TAX_TAG, false);
                    }
                }

                $created++;
                echo '<li>行'.esc_html(intval($i+2)).'：作成 post_id='.esc_html(intval($post_id)).'</li>';
            }
            echo '</ol>';
            echo '<p><strong>作成：'.esc_html(intval($created)).' / エラー：'.esc_html(intval($errors)).'</strong></p>';

            // 処理完了時に元のCSVファイルを削除（成功・失敗・ドライラン問わず）
            wp_delete_file($tmp);
            } // end else for required columns check
            } // end else for data rows check
                    } // end else for file_get_contents check
                } // end else for MIME type check
                } // end else for file size check
            } // end else for is_uploaded_file check
        } // end if for $_FILES check
    }
    ?>
  </div>

  <div class="card">
    <h2><?php esc_html_e('CSVサンプル', 'andw-llmo-qa'); ?></h2>
<code>title,short_answer,content,categories,tags,slug,status
"モンゴル乗馬は初心者でも参加できる？","初心者歓迎ツアーが多く、初日に基本レクチャーを受けてから草原へ出ます。","&lt;p&gt;多くのツアーで安全指導と基礎練習を用意…&lt;/p&gt;","mongolia","初心者,安全,乗馬","beginner-ok","publish"
"キッチンリフォームの費用相場はいくら？","規模により20〜150万円程度。間取り変更や設備グレードで変動します。","&lt;p&gt;…&lt;/p&gt;","reform","費用,相場,キッチン","kitchen-cost","draft"
</code>
  </div>

<?php endif; ?>

</div>
