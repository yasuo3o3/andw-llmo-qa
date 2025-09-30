( function (blocks, element, components, data, editor, i18n) {
  const { registerBlockType } = blocks;
  const { createElement: el } = element;
  const { TextareaControl, PanelBody } = components;
  const { useEntityProp } = data;
  const { InspectorControls } = editor;
  const __ = i18n.__;

  registerBlockType('andw/llmo-short-answer', {
    edit: () => {
      const postType = wp.data.select('core/editor').getCurrentPostType();
      const postId   = wp.data.select('core/editor').getCurrentPostId();

      // 投稿メタを双方向バインド
      const [ meta, setMeta ] = useEntityProp('postType', postType, 'meta', postId);
      const key = '_andwqa_short_answer';
      const value = meta?.[key] || '';

      const onChange = (val) => {
        setMeta( { ...meta, [key]: val } );
      };

      return el('div', { className: 'andwqa-short-editor' },
        el(InspectorControls, null,
          el(PanelBody, { title: '即答（LLMO）', initialOpen: true },
            el(TextareaControl, {
              label: '即答（50〜100文字推奨）',
              help: 'AIに拾わせる短い回答。JSON-LDにも反映されます。',
              value: value,
              onChange: onChange,
              rows: 4
            })
          )
        ),
        el('div', { style: { padding:'12px', border:'1px dashed #cbd5e1', background:'#f8fafc' } },
          el('strong', null, '即答（プレビュー）'),
          el('div', null, value ? value : 'ここに入力するとプレビュー表示されます。')
        )
      );
    },
    save: () => null
  });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.data, window.wp.editor || window.wp.blockEditor, window.wp.i18n);
