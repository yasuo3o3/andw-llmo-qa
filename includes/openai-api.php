<?php
/**
 * OpenAI API 通信クラス
 * 
 * セキュリティ重視の要約生成専用API クライント
 */

if (!defined('ABSPATH')) exit;

class Andw_Llmo_QA_OpenAI_API {
    
    private $api_key;
    private $timeout;
    private $model;
    private $debug_mode;
    
    const API_BASE_URL = 'https://api.openai.com/v1/chat/completions';
    const USER_AGENT = 'andw-llmo-qa/0.06';
    const MAX_INPUT_LENGTH = 2000; // 入力テキストの上限
    
    // v0.06: エラーログのタイプ定数
    const LOG_ERROR = 'error';
    const LOG_WARNING = 'warning';
    const LOG_INFO = 'info';
    
    public function __construct() {
        $this->api_key = get_option(Andw_Llmo_QA_Plugin::OPT_OPENAI_API_KEY, '');
        $this->timeout = (int)get_option(Andw_Llmo_QA_Plugin::OPT_AI_TIMEOUT, 12);
        $this->model = get_option(Andw_Llmo_QA_Plugin::OPT_AI_MODEL, 'gpt-4o-mini');
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
    }
    
    /**
     * v0.06: エラーログ出力
     */
    private function log($level, $message, $context = []) {
        if (!$this->debug_mode && $level === self::LOG_INFO) {
            return; // デバッグモードでない場合はINFOログをスキップ
        }
        
        $log_entry = sprintf(
            '[andW-QA OpenAI] [%s] %s',
            strtoupper($level),
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= ' Context: ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        do_action('andw_llmoqa_debug_log', $log_entry);
        
        // WordPressログにも記録
        if (function_exists('wp_debug_log')) {
            wp_debug_log($log_entry);
        }
    }
    
    /**
     * テキストを要約
     * 
     * @param string $text 要約対象テキスト
     * @return array ['success' => bool, 'summary' => string, 'error' => string]
     */
    public function summarize_text($text) {
        $this->log(self::LOG_INFO, 'AI要約リクエスト開始', [
            'text_length' => mb_strlen($text, 'UTF-8'),
            'model' => $this->model
        ]);
        
        // Kill Switch チェック
        if (get_option(Andw_Llmo_QA_Plugin::OPT_DISABLED, false)) {
            $this->log(self::LOG_WARNING, 'Kill Switch有効のため処理中止');
            return [
                'success' => false,
                'summary' => '',
                'error' => __('プラグインが無効化されています', 'andw-llmo-qa')
            ];
        }
        
        // AI要約有効チェック
        if (!(bool)get_option(Andw_Llmo_QA_Plugin::OPT_ENABLE_AI_SUMMARY, false)) {
            $this->log(self::LOG_WARNING, 'AI要約機能が無効');
            return [
                'success' => false,
                'summary' => '',
                'error' => __('AI要約機能が無効です', 'andw-llmo-qa')
            ];
        }
        
        // APIキー確認
        if (empty($this->api_key)) {
            $this->log(self::LOG_ERROR, 'APIキー未設定');
            return [
                'success' => false,
                'summary' => '',
                'error' => __('OpenAI APIキーが設定されていません', 'andw-llmo-qa')
            ];
        }
        
        // 入力テキスト検証・加工
        $processed_text = $this->prepare_input_text($text);
        if (empty($processed_text)) {
            $this->log(self::LOG_ERROR, '前処理後のテキストが空');
            return [
                'success' => false,
                'summary' => '',
                'error' => __('要約対象のテキストが空です', 'andw-llmo-qa')
            ];
        }
        
        // API リクエスト実行
        return $this->make_api_request($processed_text);
    }
    
    /**
     * 入力テキストの前処理
     * 
     * @param string $text 
     * @return string
     */
    private function prepare_input_text($text) {
        if (empty($text)) {
            return '';
        }
        
        // HTMLタグを除去（既にプレーンテキスト化済みの場合もあるが念のため）
        $text = wp_strip_all_tags($text);
        
        // 改行を統一・連続空白を圧縮
        $text = preg_replace('/\r\n|\r|\n/', "\n", $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        
        // 文字数制限（API送信量制限）
        if (mb_strlen($text, 'UTF-8') > self::MAX_INPUT_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_INPUT_LENGTH, 'UTF-8');
        }
        
        return $text;
    }
    
    /**
     * OpenAI API にリクエストを送信
     * 
     * @param string $text
     * @return array
     */
    private function make_api_request($text) {
        // プロンプト作成
        $prompt = sprintf(
            "以下の本文を日本語で120〜160字に要約してください。プレーンテキストのみで、URL・記号装飾は含めないでください。\n---\n%s",
            $text
        );
        
        // リクエストボディ
        $body = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.2,
            'max_tokens' => 220, // 日本語で160字程度を想定
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        ];
        
        // HTTPリクエスト設定
        $args = [
            'method' => 'POST',
            'timeout' => $this->timeout,
            'redirection' => 0, // リダイレクトを無効化（セキュリティ）
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'User-Agent' => self::USER_AGENT
            ],
            'body' => wp_json_encode($body),
            'cookies' => [], // Cookieを送信しない
            'sslverify' => true // SSL証明書の検証を有効
        ];
        
        $this->log(self::LOG_INFO, 'OpenAI API リクエスト送信', [
            'model' => $this->model,
            'timeout' => $this->timeout,
            'url' => self::API_BASE_URL
        ]);
        
        // API リクエスト実行
        $response = wp_remote_post(self::API_BASE_URL, $args);
        
        // エラーハンドリング
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log(self::LOG_ERROR, 'HTTP リクエストエラー', [
                'error' => $error_message,
                'code' => $response->get_error_code()
            ]);
            
            return [
                'success' => false,
                'summary' => '',
                'error' => sprintf(
                    /* translators: %s はエラーメッセージ */
                    __('API通信エラー: %s', 'andw-llmo-qa'),
                    $response->get_error_message()
                )
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // HTTPステータスコード確認
        if ($response_code !== 200) {
            $error_message = $this->extract_error_message($response_body);
            $this->log(self::LOG_ERROR, 'OpenAI API エラーレスポンス', [
                'status_code' => $response_code,
                'error' => $error_message
            ]);
            
            return [
                'success' => false,
                'summary' => '',
                'error' => sprintf(
                    /* translators: %1$d はHTTPステータスコード、%2$s はエラーメッセージ */
                    __('APIエラー (HTTP %1$d): %2$s', 'andw-llmo-qa'),
                    $response_code,
                    $error_message
                )
            ];
        }
        
        // レスポンス解析
        return $this->parse_api_response($response_body);
    }
    
    /**
     * API レスポンスを解析
     * 
     * @param string $response_body
     * @return array
     */
    private function parse_api_response($response_body) {
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log(self::LOG_ERROR, 'JSON解析エラー', [
                'json_error' => json_last_error_msg(),
                'response_length' => strlen($response_body)
            ]);
            return [
                'success' => false,
                'summary' => '',
                'error' => __('API レスポンスの JSON 解析に失敗しました', 'andw-llmo-qa')
            ];
        }
        
        // レスポンス構造確認
        if (!isset($data['choices'][0]['message']['content'])) {
            $this->log(self::LOG_ERROR, 'APIレスポンス構造異常', [
                'response_structure' => array_keys($data ?? []),
                'choices_exist' => isset($data['choices'])
            ]);
            return [
                'success' => false,
                'summary' => '',
                'error' => __('API レスポンスの形式が不正です', 'andw-llmo-qa')
            ];
        }
        
        $summary = trim($data['choices'][0]['message']['content']);
        
        // 要約テキスト検証
        if (empty($summary)) {
            $this->log(self::LOG_ERROR, 'AIが空の要約を返却');
            return [
                'success' => false,
                'summary' => '',
                'error' => __('AI が空の要約を返しました', 'andw-llmo-qa')
            ];
        }
        
        // 不適切な内容のフィルタリング（基本的なチェック）
        if ($this->contains_inappropriate_content($summary)) {
            $this->log(self::LOG_WARNING, '不適切コンテンツを検出', [
                'summary_length' => mb_strlen($summary, 'UTF-8')
            ]);
            return [
                'success' => false,
                'summary' => '',
                'error' => __('生成された要約に不適切な内容が含まれています', 'andw-llmo-qa')
            ];
        }
        
        // 文字数調整（念のため）
        $original_length = mb_strlen($summary, 'UTF-8');
        if ($original_length > 200) {
            $summary = mb_substr($summary, 0, 200, 'UTF-8') . '...';
            $this->log(self::LOG_INFO, '要約を200文字に切り詰め', [
                'original_length' => $original_length,
                'final_length' => mb_strlen($summary, 'UTF-8')
            ]);
        }
        
        $this->log(self::LOG_INFO, 'AI要約生成成功', [
            'summary_length' => mb_strlen($summary, 'UTF-8')
        ]);
        
        return [
            'success' => true,
            'summary' => $summary,
            'error' => ''
        ];
    }
    
    /**
     * エラーメッセージを抽出
     * 
     * @param string $response_body
     * @return string
     */
    private function extract_error_message($response_body) {
        $data = json_decode($response_body, true);
        
        if (isset($data['error']['message'])) {
            return sanitize_text_field($data['error']['message']);
        }
        
        return __('不明なエラーが発生しました', 'andw-llmo-qa');
    }
    
    /**
     * 不適切なコンテンツチェック
     * 
     * @param string $text
     * @return bool
     */
    private function contains_inappropriate_content($text) {
        // 基本的なパターンチェック
        $forbidden_patterns = [
            '/<script/i',
            '/javascript:/i',
            '/onclick=/i',
            '/onerror=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/data:text\/html/i'
        ];
        
        foreach ($forbidden_patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * API キーの有効性をテスト
     * 
     * @return array
     */
    public function test_api_key() {
        return $this->summarize_text('これはテスト用のサンプルテキストです。');
    }
}