( ({blocks, element, components, i18n, blockEditor}) => {
  const { registerBlockType } = blocks;
  const { createElement: el } = element;
  const { TextControl, ToggleControl, PanelBody, RangeControl } = components;
  const { InspectorControls } = blockEditor || wp.editor;
  const __ = i18n.__;

  registerBlockType('andw/llmo-qa-index', {
    edit: ({ attributes, setAttributes }) => {
      const { categories, tags, perCategory, columns, showShort, showMoreLink } = attributes;
      return el('div', { className: 'andwqa-index-editor' },
        el(InspectorControls, null,
          el(PanelBody, { title: '表示設定', initialOpen: true },
            el(TextControl, {
              label: 'カテゴリスラッグ（カンマ区切り）',
              value: categories,
              onChange: (v) => setAttributes({ categories: v })
            }),
            el(TextControl, {
              label: 'タグスラッグ（カンマ区切り）',
              value: tags,
              onChange: (v) => setAttributes({ tags: v })
            }),
            el(RangeControl, {
              label: 'カテゴリごとの件数',
              min: 1, max: 24, value: perCategory,
              onChange: (v) => setAttributes({ perCategory: v })
            }),
            el(RangeControl, {
              label: '列数',
              min: 1, max: 6, value: columns,
              onChange: (v) => setAttributes({ columns: v })
            }),
            el(ToggleControl, {
              label: '即答（short）を表示',
              checked: showShort,
              onChange: (v) => setAttributes({ showShort: v })
            }),
            el(ToggleControl, {
              label: '「もっと見る」リンクを表示',
              checked: showMoreLink,
              onChange: (v) => setAttributes({ showMoreLink: v })
            })
          )
        ),
        el('div', { style: { padding:'8px', border:'1px dashed #cbd5e1', background:'#f8fafc' } },
          el('strong', null, 'プレビュー（概略）'),
          el('div', null, `カテゴリ: ${categories || '(未指定)'} / タグ: ${tags || '(未指定)'} / 1カテゴリの件数: ${perCategory} / 列数: ${columns}`)
        )
      );
    },
    save: () => null
  });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.i18n, window.wp.blockEditor);
