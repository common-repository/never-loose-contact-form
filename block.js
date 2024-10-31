( function( blocks, editor, element , components,blockEditor,serverSideRender) 
{

	const el = element.createElement;
    const { registerBlockType } = blocks;
	const { serverSideRender: ServerSideRender } = wp;
    const { Fragment } = element;
	const {
		
        TextControl,
        CheckboxControl,
        RadioControl,
        SelectControl,
        TextareaControl,
        ToggleControl,
        RangeControl,
        Panel,
        PanelBody,
        PanelRow
    } = components;
    const {ToolbarControls,InspectorControls, PlainText, RichText,withColors, PanelColorSettings, getColorClassName } = blockEditor;// License: GPLv2+



    
/*
 * Here's where we register the block in JavaScript.
 *
 * It's not yet possible to register a block entirely without JavaScript, but
 * that is something I'd love to see happen. This is a barebones example
 * of registering the block, and giving the basic ability to edit the block
 * attributes. (In this case, there's only one attribute, 'foo'.)
 */
registerBlockType( 'never-loose-contact-form/contact-form', {
	title: 'Contact Form',
	icon: 'email',
	category:'widgets',
    
	/*
	 * In most other blocks, you'd see an 'attributes' property being defined here.
	 * We've defined attributes in the PHP, that information is automatically sent
	 * to the block editor, so we don't need to redefine it here.
	 */

	edit: function( props ) {
		console.log('Attributes:'+props.attributes);
		return [
			/*
			 * The ServerSideRender element uses the REST API to automatically call
			 * php_block_render() in your PHP code whenever it needs to get an updated
			 * view of the block.
			 */
			el( ServerSideRender, {
				key:'server-side-render',
				block: 'never-loose-contact-form/contact-form',
				attributes: props.attributes,
			} ),
			/*
			 * InspectorControls lets you add controls to the Block sidebar. In this case,
			 * we're adding a TextControl, which lets us edit the 'foo' attribute (which
			 * we defined in the PHP). The onChange property is a little bit of magic to tell
			 * the block editor to update the value of our 'foo' property, and to re-render
			 * the block.
			 */
			el( InspectorControls, {key:'inspector-controls'},
				el( TextControl, {
					key:'text-control',
					label: 'Title',
					value: props.attributes.title,
					onChange: ( value ) => { props.setAttributes( { title: value } ); },
				} ),
			   
			),
		];
	},

	// We're going to be rendering in PHP, so save() can just return null.
	save: function() {
		return null;
	},
} );

})
(
	window.wp.blocks,
    window.wp.editor,
	window.wp.element,
    window.wp.components,
    window.wp.blockEditor,
	window.wp.serverSideRender,
);