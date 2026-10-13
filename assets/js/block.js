/**
 * Archive Section block.
 *
 * Written against the globals WordPress already enqueues, so the plugin needs
 * no build step — that is why there is no JSX here.
 *
 * The block stores only a section ID and renders server-side, so the embedded
 * content always reflects the section as it is now rather than a copy frozen at
 * insert time.
 */
( function ( blocks, element, blockEditor, components, data, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var useSelect = data.useSelect;

	blocks.registerBlockType( 'kayce/archive-section', {
		edit: function ( props ) {
			var sectionId = props.attributes.sectionId || 0;

			// Published sections only — a draft would render nothing on the
			// front end, so offering one here would be a trap.
			var sections = useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'postType', 'kcas_section', {
					per_page: 100,
					status: 'publish',
					orderby: 'title',
					order: 'asc',
					_fields: 'id,title'
				} );
			}, [] );

			var options = [ { label: __( '— Select a section —', 'kayce-custom-archive-sections' ), value: 0 } ];

			if ( sections ) {
				sections.forEach( function ( section ) {
					var title = section.title && section.title.rendered
						? section.title.rendered
						: __( '(no title)', 'kayce-custom-archive-sections' );
					options.push( { label: title, value: section.id } );
				} );
			}

			var control = el( components.SelectControl, {
				label: __( 'Section', 'kayce-custom-archive-sections' ),
				value: sectionId,
				options: options,
				onChange: function ( value ) {
					props.setAttributes( { sectionId: parseInt( value, 10 ) || 0 } );
				},
				__nextHasNoMarginBottom: true
			} );

			var body;

			if ( null === sections || undefined === sections ) {
				body = el( components.Spinner, null );
			} else if ( 0 === sections.length ) {
				body = el(
					'p',
					null,
					__( 'No published archive sections yet. Create one under Archive Sections first.', 'kayce-custom-archive-sections' )
				);
			} else {
				body = control;
			}

			return el(
				'div',
				useBlockProps(),
				el(
					components.Placeholder,
					{
						icon: 'layout',
						label: __( 'Archive Section', 'kayce-custom-archive-sections' ),
						instructions: sectionId
							? __( 'This section is rendered when the page loads, so it always shows the current content.', 'kayce-custom-archive-sections' )
							: __( 'Choose a section to embed here.', 'kayce-custom-archive-sections' )
					},
					body
				)
			);
		},

		// Server-rendered: nothing is stored in post content but the attribute.
		save: function () {
			return null;
		}
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.data,
	window.wp.i18n
);
