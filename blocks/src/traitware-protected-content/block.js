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
const { InspectorControls, InnerBlocks } = wp.editor;
const { PanelBody, CheckboxControl, BaseControl } = wp.components;
const el = wp.element.createElement;

const iconEl = el('svg', { width: 20, height: 20 },
	el('path', { d: "M16.6,5.4V2.6h-4.1v2.8H12V4.9C11.9,2.2,9.7,0,7,0C5.7,0,4.4,0.5,3.5,1.4C2.6,2.3,2,3.5,2,4.9v4.2H1.6 C0.7,9.1,0,9.8,0,10.6v7.8c0,0.9,0.7,1.6,1.6,1.7h10.9c0.9,0,1.6-0.7,1.6-1.6v-6.1h2.5V9.6h2.8V5.4H16.6z M9.4,9.1h-5 c0,0,0-0.1,0-0.1V5.3c0.1-1.4,1.1-2.6,2.5-2.6c0.7,0,1.3,0.3,1.8,0.7c0.4,0.5,0.7,1.1,0.7,1.9V9.1z M18,8.2h-2.8V11h-1.5V8.2H11 V6.8h2.8V4h1.5v2.8H18V8.2z" } )
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
registerBlockType( 'traitware/traitware-protected-content-block', {
	// Block name. Block names must be string that contains a namespace prefix. Example: my-plugin/my-custom-block.
	title: __( 'TraitWare Protected Content' ), // Block title.
	icon: iconEl,
	category: 'common', // Block category — Group blocks together based on common traits E.g. common, formatting, layout widgets, embed.
	keywords: [
		__( 'traitware protected content' ),
		__( 'traitware' ),
		__( 'protected content' ),
	],
	attributes: {
		roles: {
			type: 'string',
			default: ''
		}
	},

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

		const roles = attributes.roles.length ? JSON.parse(attributes.roles) : [];

		let checkboxes = [];
		for ( const r in traitware_block_obj.roles ) {
			if ( ! traitware_block_obj.roles.hasOwnProperty( r ) ) {
				continue;
			}

			const isChecked = roles.includes(r);

			checkboxes.push(
				<CheckboxControl
					label={ traitware_block_obj.roles[r] }
					checked={ isChecked }
					onChange={ ( newIsChecked ) => {
						const wasChecked = roles.includes(r);
						let checkedRoles = roles;

						if ( newIsChecked && ! wasChecked ) {
							checkedRoles.push(r);
						} else if ( ! newIsChecked && wasChecked ) {
							checkedRoles.splice(roles.indexOf(r), 1);
						}

						setAttributes( { roles: JSON.stringify(checkedRoles) } );
					} }
				/>
			);
		}

		return [
			<InspectorControls>
				<PanelBody title={ __( "Roles" ) }>
					<BaseControl
						label={ __("Select which roles are able to view the protected content:") }
					>
						{checkboxes}
					</BaseControl>
				</PanelBody>
			</InspectorControls>,
			<div className={ props.className }>
				{ __( 'Add Blocks to protect' ) }
				<InnerBlocks />
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
			<InnerBlocks.Content />
		);
	},
} );
