( function( blocks, element, editor, components, i18n ) {
    var el = element.createElement;
    var InspectorControls = editor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;

    blocks.registerBlockType('wcpdbv/viewer', {
        title: 'PDB Viewer',
        icon: 'visibility',
        category: 'widgets',
        attributes: {
            height:  { type: 'string',  default: '400px' },
            width:   { type: 'string',  default: '100%'  },
            ui:      { type: 'boolean', default: true     },
            nomouse: { type: 'boolean', default: false    },
            style:   { type: 'string',  default: ''       },
            spin:    { type: 'string',  default: ''       }
        },
        edit: function(props){
            var a = props.attributes;
            return [
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Viewer settings', initialOpen: true },
                        el(TextControl, { label:'Height', value:a.height, onChange:function(v){ props.setAttributes({height:v}); } }),
                        el(TextControl, { label:'Width',  value:a.width,  onChange:function(v){ props.setAttributes({width:v}); } }),
                        el(ToggleControl, { label:'Show UI', checked:a.ui, onChange:function(v){ props.setAttributes({ui:v}); } }),
                        el(ToggleControl, { label:'Disable mouse', checked:a.nomouse, onChange:function(v){ props.setAttributes({nomouse:v}); } }),
                        el(TextControl, { label:'Style (JSON)', value:a.style, onChange:function(v){ props.setAttributes({style:v}); } }),
                        el(TextControl, { label:'Spin (e.g. \"true\" or \"y:0.5\")', value:a.spin, onChange:function(v){ props.setAttributes({spin:v}); } })
                    )
                ),
                el('div', { className:'components-placeholder is-large' },
                    el('div', { style:{opacity:0.7} }, 'PDB Viewer will render on the front-end.')
                )
            ];
        },
        save: function(){ return null; } // server-rendered
    });
} )( window.wp.blocks, window.wp.element, window.wp.editor || window.wp.blockEditor, window.wp.components, window.wp.i18n );
