( ({blocks, element, components, i18n, blockEditor}) => {
  const { registerBlockType } = blocks;
  const { createElement: el } = element;
  const { TextControl, ToggleControl, PanelBody, RangeControl } = components;
  const { InspectorControls } = blockEditor || wp.editor;
  const __ = i18n.__;

  registerBlockType('andw/llmo-qa-list', {
    edit: ({ attributes, setAttributes }) => {
      const { category, tags, limit, columns, showShort, showTitle } = attributes;
      return el('div', { className: 'andwqa-list-editor' },
        el(InspectorControls, null,
          el(PanelBody, { title: '表示設定', initialOpen: true },
            el(TextControl, {
              label: 'カテゴリスラッグ（カンマ区切り可）',
              value: category,
              onChange: (v) => setAttributes({ category: v })
            }),
            el(TextControl, {
              label: 'タグスラッグ（カンマ区切り可）',
              value: tags,
              onChange: (v) => setAttributes({ tags: v })
            }),
            el(RangeControl, {
              label: '表示件数',
              min: 1, max: 48, value: limit,
              onChange: (v) => setAttributes({ limit: v })
            }),
            el(RangeControl, {
              label: '列数',
              min: 1, max: 6, value: columns,
              onChange: (v) => setAttributes({ columns: v })
            }),
            el(ToggleControl, {
              label: 'タイトルを表示',
              checked: showTitle,
              onChange: (v) => setAttributes({ showTitle: v })
            }),
            el(ToggleControl, {
              label: '即答（short）を表示',
              checked: showShort,
              onChange: (v) => setAttributes({ showShort: v })
            })
          )
        ),
        el('div', { style: { padding:'8px', border:'1px dashed #cbd5e1', background:'#f8fafc' } },
          el('strong', null, 'プレビュー（概略）'),
          el('div', null, `カテゴリ: ${category || '(指定なし)'} / タグ: ${tags || '(指定なし)'} / 件数: ${limit} / 列数: ${columns}`)
        )
      );
    },
    save: () => null
  });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.i18n, window.wp.blockEditor);
