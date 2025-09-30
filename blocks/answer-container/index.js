import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { select, dispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

registerBlockType('andw/llmo-answer-container', {
    edit: function(props) {
        const { clientId } = props;
        const blockProps = useBlockProps({
            className: 'andwqa-answer-container-editor'
        });

        // 許可されたブロックの設定
        const ALLOWED_BLOCKS = [
            'core/paragraph',
            'core/heading',
            'core/list', 
            'core/quote',
            'core/image'
        ];

        // 禁止ブロック検出時の警告表示
        useEffect(() => {
            const checkForbiddenBlocks = () => {
                const innerBlocks = select('core/block-editor').getBlock(clientId)?.innerBlocks || [];
                const hasForbiddenBlocks = innerBlocks.some(block => 
                    !ALLOWED_BLOCKS.includes(block.name)
                );
                
                if (hasForbiddenBlocks) {
                    dispatch('core/notices').createWarningNotice(
                        __('禁止されたブロックが検出されました。スキーマ出力が自動停止される可能性があります。', 'andw-llmo-qa'),
                        { id: 'andwqa-forbidden-blocks', isDismissible: true }
                    );
                } else {
                    dispatch('core/notices').removeNotice('andwqa-forbidden-blocks');
                }
            };

            const unsubscribe = select('core/block-editor').subscribe(checkForbiddenBlocks);
            return unsubscribe;
        }, [clientId]);

        // テンプレート（初期コンテンツ）
        const TEMPLATE = [
            ['core/paragraph', { 
                placeholder: __('詳細な回答をここに入力してください...', 'andw-llmo-qa') 
            }]
        ];

        return (
            <div {...blockProps}>
                <div className="andwqa-answer-container-label">
                    {__('回答エリア（Answer Container）', 'andw-llmo-qa')}
                </div>
                <InnerBlocks
                    allowedBlocks={ALLOWED_BLOCKS}
                    template={TEMPLATE}
                    templateLock={false}
                />
            </div>
        );
    },

    save: function(props) {
        const blockProps = useBlockProps.save({
            className: 'andwqa-answer-container'
        });

        return (
            <div {...blockProps}>
                <InnerBlocks.Content />
            </div>
        );
    }
});