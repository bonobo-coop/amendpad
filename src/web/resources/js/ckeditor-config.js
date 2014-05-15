CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here.
	// For the complete reference:
	// http://docs.ckeditor.com/#!/api/CKEDITOR.config

	// The toolbar groups arrangement, optimized for two toolbar rows.
	config.toolbarGroups = [
		{ name: 'styles' },
		{ name: 'basicstyles',  groups: [ 'basicstyles', 'cleanup' ] },
		{ name: 'paragraph',    groups: [ 'list', 'indent'] },
		{ name: 'clipboard',   groups: [ 'clipboard', 'undo' ] },
		{ name: 'tools' },
		{ name: 'document',	   groups: [ 'mode', 'document', 'doctools' ] }
	];

	// Remove some buttons, provided by the standard plugins, which we don't
	// need to have in the Standard(s) toolbar.
	config.removeButtons = 'Underline,Subscript,Superscript,Strike,Styles';

	// Se the most common block elements.
	config.format_tags = 'p;h2;h3;h4;h5';

	// Make dialogs simpler.
	config.removeDialogTabs = 'image:advanced;link:advanced';
};
