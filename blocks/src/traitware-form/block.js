/**
 * BLOCK: traitware
 *
 * Registering a basic block with Gutenberg.
 * Simple block, renders and saves the same content without any interactivity.
 */

//  Import CSS.
import './style.scss';
import './editor.scss';

const { __ } = wp.i18n; // Import __() from wp.i18n
const { registerBlockType } = wp.blocks; // Import registerBlockType() from wp.blocks
const { InspectorControls } = wp.editor;
const { PanelBody, SelectControl } = wp.components;
const el = wp.element.createElement;

const iconEl = el('svg', { width: 20, height: 20 },
	el('path', { d: "M16.6,2.6V0h-4v1.2H0.3v18.6h14.8V9h1.5V6.5h2.7V2.6H16.6z M12.3,14h-9v-1h9V14z M12.3,12h-9v-1h9V12z M12.3,10h-9V9h9V10z M12.3,8h-9V7h9V8z M18,5.2h-2.7v2.6h-1.5V5.2h-2.7V3.9h2.7V1.3h1.5v2.6H18V5.2z" } )
);

/**
 * Register: aa Gutenberg Block.
 *
 * Registers a new block provided a unique name and an object defining its
 * behavior. Once registered, the block is made editor as an option to any
 * editor interface where blocks are implemented.
 *
 * @link https://wordpress.org/gutenberg/handbook/block-api/
 * @param  {string}   name     Block name.
 * @param  {Object}   settings Block settings.
 * @return {?WPBlock}          The block, if it has been successfully
 *                             registered; otherwise `undefined`.
 */
registerBlockType( 'traitware/traitware-form-block', {
	// Block name. Block names must be string that contains a namespace prefix. Example: my-plugin/my-custom-block.
	title: __( 'TraitWare Form' ), // Block title.
	icon: iconEl,
	category: 'common', // Block category — Group blocks together based on common traits E.g. common, formatting, layout widgets, embed.
	keywords: [
		__( 'traitware form' ),
		__( 'traitware' ),
		__( 'form' ),
	],

	/**
	 * The edit function describes the structure of your block in the context of the editor.
	 * This represents what the editor will render when the block is used.
	 *
	 * The "edit" property must be a valid function.
	 *
	 * @link https://wordpress.org/gutenberg/handbook/block-api/block-edit-save/
	 */
	edit: function( props ) {
		const { attributes, setAttributes } = props;

		let options = [];
		for ( const r in traitware_block_obj.forms ) {
			if (!traitware_block_obj.forms.hasOwnProperty(r)) {
				continue;
			}

			options.push( { value: traitware_block_obj.forms[r].id, label: traitware_block_obj.forms[r].title } );
		}

		return [
			<InspectorControls>
				<PanelBody title={ __( "Form" ) }>
					{ options.length > 0 ? (
						<SelectControl
							label={ __("Select the form to use:") }
							value={ attributes.form }
							onChange={ ( form ) => { setAttributes( { form: form } ); } }
							options={ options }
						>
						</SelectControl>
					) : (
						<p>No forms exist</p>
					) }
				</PanelBody>
			</InspectorControls>,
			<div className={ props.className }>
				{ __( 'TraitWare Form' ) }
			</div>
		];
	},

	/**
	 * The save function defines the way in which the different attributes should be combined
	 * into the final markup, which is then serialized by Gutenberg into post_content.
	 *
	 * The "save" property must be specified and must be a valid function.
	 *
	 * @link https://wordpress.org/gutenberg/handbook/block-api/block-edit-save/
	 */
	save: function( props ) {
		return (
			<div className={ props.className }>
				{ __( 'TraitWare Form' ) }
			</div>
		);
	},
} );
