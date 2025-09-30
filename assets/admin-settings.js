/**
 * andW-QA 管理画面設定用JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // 関連QA自動表示のON/OFF連動
        const relOnCheckbox = $('#andwqa_rel_on');
        const relNumInput = $('#andwqa_rel_num');
        
        if (relOnCheckbox.length && relNumInput.length) {
            // 初期状態設定
            toggleRelNumInput();
            
            // チェックボックス変更時
            relOnCheckbox.on('change', toggleRelNumInput);
            
            function toggleRelNumInput() {
                const isEnabled = relOnCheckbox.is(':checked');
                
                relNumInput.prop('disabled', !isEnabled);
                
                if (isEnabled) {
                    relNumInput.attr('min', '1');
                    relNumInput.removeClass('disabled-input');
                } else {
                    relNumInput.removeAttr('min');
                    relNumInput.addClass('disabled-input');
                }
            }
        }
        
        // APIキー削除ボタン
        const clearApiKeyBtn = $('#clear-api-key');
        const apiKeyInput = $('#andwqa_openai_api_key');
        
        if (clearApiKeyBtn.length && apiKeyInput.length) {
            clearApiKeyBtn.on('click', function() {
                const confirmMessage = $(this).data('confirm') || 'APIキーを削除してもよろしいですか？';
                
                if (confirm(confirmMessage)) {
                    apiKeyInput.val('');
                    apiKeyInput.focus();
                }
            });
        }
    });

})(jQuery);