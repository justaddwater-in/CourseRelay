/**
 * CourseRelay — SCORM Player Gutenberg Block
 * Editor-side JS: content picker + preview.
 */
(function (blocks, element, blockEditor, components, apiFetch) {
    var el          = element.createElement;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody   = components.PanelBody;
    var SelectControl = components.SelectControl;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;
    var Placeholder = components.Placeholder;
    var Spinner     = components.Spinner;
    var useState    = element.useState;
    var useEffect   = element.useEffect;

    blocks.registerBlockType('courserelay/scorm-player', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var contentId   = attributes.contentId;
            var width       = attributes.width;
            var height      = attributes.height;
            var tracking    = attributes.completionTracking;

            var packages    = useState([])[0];
            var setPackages = useState([])[1];
            var loading     = useState(true)[0];
            var setLoading  = useState(true)[1];
            var selected    = useState(null)[0];
            var setSelected = useState(null)[1];

            // Use React hooks properly
            var _state = element.useState([]);
            setPackages = _state[1]; var pkgs = _state[0];

            var _load = element.useState(true);
            setLoading = _load[1]; var isLoading = _load[0];

            var _sel = element.useState(null);
            setSelected = _sel[1]; var selectedPkg = _sel[0];

            element.useEffect(function () {
                apiFetch({ path: '/courserelay/v1/packages?per_page=100' })
                    .then(function (data) {
                        setPackages(data || []);
                        setLoading(false);
                        if (contentId) {
                            var found = (data || []).find(function (p) { return p.id === contentId; });
                            if (found) setSelected(found);
                        }
                    })
                    .catch(function () { setLoading(false); });
            }, []);

            var options = [{ label: '— Select SCORM content —', value: 0 }].concat(
                pkgs.map(function (p) { return { label: p.title, value: p.id }; })
            );

            function onSelectContent(val) {
                var id  = parseInt(val, 10);
                var pkg = pkgs.find(function (p) { return p.id === id; }) || null;
                setAttributes({ contentId: id });
                setSelected(pkg);
            }

            var inspectorControls = el(InspectorControls, null,
                el(PanelBody, { title: 'SCORM Settings', initialOpen: true },
                    el(TextControl, {
                        label: 'Width (e.g. 100% or 800px)',
                        value: width,
                        onChange: function (v) { setAttributes({ width: v }); }
                    }),
                    el(TextControl, {
                        label: 'Height (e.g. 600px)',
                        value: height,
                        onChange: function (v) { setAttributes({ height: v }); }
                    }),
                    el(ToggleControl, {
                        label: 'Completion Tracking',
                        checked: tracking,
                        onChange: function (v) { setAttributes({ completionTracking: v }); }
                    })
                )
            );

            var preview = el('div', blockProps,
                inspectorControls,
                isLoading
                    ? el(Placeholder, { label: 'SCORM Content' }, el(Spinner))
                    : el('div', { style: { padding: '16px', background: '#f0f0f1', border: '1px solid #c3c4c7', borderRadius: '4px' } },
                        el(SelectControl, {
                            label: 'Select SCORM Package',
                            value: contentId,
                            options: options,
                            onChange: onSelectContent
                        }),
                        selectedPkg
                            ? el('div', { style: { marginTop: '8px', padding: '12px', background: '#fff', borderRadius: '4px' } },
                                el('strong', null, '📦 ' + selectedPkg.title),
                                el('br'),
                                el('small', { style: { color: '#666' } },
                                    'Version: ' + (selectedPkg.version || 'SCORM 1.2') +
                                    ' | Mastery: ' + (selectedPkg.mastery_score || 80) + '%'
                                )
                              )
                            : el('p', { style: { color: '#666', marginTop: '8px' } }, 'Select a SCORM package above.')
                      )
            );

            return preview;
        },

        save: function () {
            // Server-side rendered — return null
            return null;
        }
    });

}(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.apiFetch
));
