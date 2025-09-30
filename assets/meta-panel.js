import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, ToggleControl, TextareaControl, Card, CardBody, Spinner } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const AndwQAMetaPanel = () => {
    const { editPost } = useDispatch('core/editor');
    
    // メタデータの取得
    const { 
        schemaManual, 
        schemaEnabled, 
        answerSchema, 
        answerDisplay,
        postContent 
    } = useSelect((select) => {
        const { getEditedPostAttribute } = select('core/editor');
        return {
            schemaManual: getEditedPostAttribute('meta')?.andwqa_schema_manual || false,
            schemaEnabled: getEditedPostAttribute('meta')?.andwqa_schema_enabled !== false,
            answerSchema: getEditedPostAttribute('meta')?.andwqa_answer_schema || '',
            answerDisplay: getEditedPostAttribute('meta')?.andwqa_answer_display || '',
            postContent: getEditedPostAttribute('content') || ''
        };
    });

    const [previewText, setPreviewText] = useState('');
    const [previewLoading, setPreviewLoading] = useState(false);
    const [hasForbiddenBlocks, setHasForbiddenBlocks] = useState(false);

    // 禁止ブロック検出
    const checkForbiddenBlocks = useCallback((content) => {
        const forbiddenBlocks = [
            'core/video',
            'core/embed', 
            'core/html',
            'core/file',
            'core/audio',
            'core/gallery',
            'core/media-text',
            'core/cover',
            'core/freeform'
        ];
        
        const blocks = wp.blocks.parse(content);
        const hasForbidden = scanBlocksForForbidden(blocks, forbiddenBlocks);
        setHasForbiddenBlocks(hasForbidden);
        
        return hasForbidden;
    }, []);

    const scanBlocksForForbidden = (blocks, forbiddenBlocks) => {
        for (const block of blocks) {
            if (forbiddenBlocks.includes(block.name)) {
                return true;
            }
            if (block.innerBlocks && block.innerBlocks.length > 0) {
                if (scanBlocksForForbidden(block.innerBlocks, forbiddenBlocks)) {
                    return true;
                }
            }
        }
        return false;
    };

    // スキーマプレビュー生成
    const generatePreview = useCallback(async () => {
        if (!answerDisplay && !postContent) {
            setPreviewText('');
            return;
        }

        setPreviewLoading(true);
        
        try {
            const formData = new FormData();
            formData.append('action', 'andwqa_preview_schema');
            formData.append('nonce', andwqaMeta.nonce);
            formData.append('post_id', andwqaMeta.postId);
            formData.append('rich_content', answerDisplay || postContent);

            const response = await fetch(andwqaMeta.ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                setPreviewText(data.data.plain_text);
            } else {
                setPreviewText(andwqaMeta.strings.previewError);
            }
        } catch (error) {
            console.error('Preview generation error:', error);
            setPreviewText(andwqaMeta.strings.previewError);
        } finally {
            setPreviewLoading(false);
        }
    }, [answerDisplay, postContent]);

    // コンテンツ変更時の処理
    useEffect(() => {
        checkForbiddenBlocks(postContent);
        
        if (!schemaManual) {
            generatePreview();
        }
    }, [postContent, answerDisplay, schemaManual, checkForbiddenBlocks, generatePreview]);

    // メタデータ更新関数
    const updateMeta = (key, value) => {
        editPost({
            meta: {
                ...useSelect(select => select('core/editor').getEditedPostAttribute('meta')),
                [key]: value
            }
        });
    };

    return (
        <>
            <PluginSidebarMoreMenuItem target="andwqa-meta-panel" icon="admin-generic">
                {andwqaMeta.strings.schemaSettings}
            </PluginSidebarMoreMenuItem>
            
            <PluginSidebar
                name="andwqa-meta-panel"
                title={andwqaMeta.strings.schemaSettings}
                className="andwqa-meta-sidebar"
            >
                {/* 禁止ブロック警告 */}
                {hasForbiddenBlocks && (
                    <div className="andwqa-warning-banner">
                        <p>{andwqaMeta.strings.warningForbiddenBlocks}</p>
                    </div>
                )}

                <PanelBody title={andwqaMeta.strings.schemaSettings} initialOpen={true}>
                    {/* 自動/手動切替 */}
                    <ToggleControl
                        label={schemaManual ? andwqaMeta.strings.manualEdit : andwqaMeta.strings.autoGeneration}
                        checked={schemaManual}
                        onChange={(value) => updateMeta('andwqa_schema_manual', value)}
                        help={schemaManual 
                            ? __('手動モード：下のテキストエリアで直接編集', 'andw-llmo-qa')
                            : __('自動モード：リッチコンテンツから自動生成', 'andw-llmo-qa')
                        }
                    />

                    {/* 手動編集エリア */}
                    {schemaManual ? (
                        <TextareaControl
                            label={__('検索用要約（スキーマ）', 'andw-llmo-qa')}
                            value={answerSchema}
                            onChange={(value) => updateMeta('andwqa_answer_schema', value)}
                            rows={6}
                            help={__('1000文字以内でスキーマ出力用のプレーンテキストを入力してください', 'andw-llmo-qa')}
                        />
                    ) : (
                        <TextareaControl
                            label={__('検索用要約（スキーマ）- 自動生成', 'andw-llmo-qa')}
                            value={answerSchema}
                            readOnly={true}
                            rows={6}
                            help={__('自動モード：保存時にローカル要約→AI要約の順で自動生成されます', 'andw-llmo-qa')}
                            className="readonly-textarea"
                        />
                    )}

                    {/* スキーマプレビュー */}
                    <div className="andwqa-preview-section">
                        <h4>{andwqaMeta.strings.schemaPreview}</h4>
                        <Card>
                            <CardBody>
                                {previewLoading ? (
                                    <div className="andwqa-preview-loading">
                                        <Spinner />
                                        <span>{andwqaMeta.strings.previewLoading}</span>
                                    </div>
                                ) : (
                                    <>
                                        <div className="andwqa-preview-text">
                                            {(schemaManual ? answerSchema : previewText) || __('プレビューテキストがありません', 'andw-llmo-qa')}
                                        </div>
                                        <div className="andwqa-character-count">
                                            {(schemaManual ? answerSchema : previewText).length} {andwqaMeta.strings.charactersCount}
                                        </div>
                                    </>
                                )}
                            </CardBody>
                        </Card>
                    </div>

                    {/* スキーマ出力ON/OFF */}
                    <ToggleControl
                        label={andwqaMeta.strings.schemaOutput}
                        checked={schemaEnabled}
                        onChange={(value) => updateMeta('andwqa_schema_enabled', value)}
                        help={schemaEnabled 
                            ? __('この投稿のJSON-LD構造化データを出力します', 'andw-llmo-qa')
                            : __('この投稿のJSON-LD構造化データを出力しません', 'andw-llmo-qa')
                        }
                    />
                </PanelBody>
            </PluginSidebar>
        </>
    );
};

registerPlugin('andwqa-meta-panel', {
    render: AndwQAMetaPanel
});