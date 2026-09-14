/**
 * Runtime contract for the theme's two editor registration lanes.
 *
 * Static grep assertions (tests/template-integrity.php) can prove a string is
 * present; they cannot prove that `edit()` really hands InnerBlocks an unlocked
 * template, or that `save()` really emits InnerBlocks.Content — and for a static
 * block those two functions *are* the frontend markup. This suite executes the
 * real musicwave/assets/editor-blocks.js against a stubbed wp.* and asserts what
 * each lane actually does.
 *
 * Lane rule (docs/composability-architecture.md §5): a block either renders
 * itself in PHP (leaf + ServerSideRender preview) or stores itself from JS
 * (static container + InnerBlocks), never both. Both halves are asserted here,
 * so the five shipping PHP-rendered blocks are covered against regressions too.
 *
 * Dependency-free on purpose: plain node, so it runs in the existing JavaScript
 * CI job without a WordPress instance.
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const themeDir = path.join( __dirname, '..', 'musicwave' );
const scriptFile = path.join( themeDir, 'assets', 'editor-blocks.js' );

let failures = 0;

function check( condition, message ) {
	if ( ! condition ) {
		failures += 1;
		console.error( 'FAIL: ' + message );
	}
}

function same( expected, actual, message ) {
	check(
		JSON.stringify( expected ) === JSON.stringify( actual ),
		message + ' (expected ' + JSON.stringify( expected ) + ', got ' + JSON.stringify( actual ) + ')'
	);
}

/**
 * Mirrors musicwave_block_metadata_entry(): block.json is the single source of
 * truth and the browser payload is a field pass-through, so this is a test
 * double of that contract rather than a second source of truth. If PHP changes
 * the entry shape, the helper contract in tests/template-integrity.php fails.
 *
 * @param {string} dir Block directory name inside musicwave/blocks/.
 * @return {Object} Client registration metadata.
 */
function metadataEntry( dir ) {
	const meta = JSON.parse( fs.readFileSync( path.join( themeDir, 'blocks', dir, 'block.json' ), 'utf8' ) );
	return {
		name: meta.name,
		apiVersion: meta.apiVersion || 3,
		title: meta.title || '',
		description: meta.description || '',
		category: meta.category || 'music-wave',
		icon: meta.icon || 'format-audio',
		keywords: Array.isArray( meta.keywords ) ? meta.keywords : [],
		textdomain: meta.textdomain || 'musicwave',
		attributes: meta.attributes || {},
		supports: meta.supports || {},
		example: meta.example || {},
		styles: Array.isArray( meta.styles ) ? meta.styles : [],
	};
}

// The dynamic lane reads its dirs from musicwave_register_presentation_blocks();
// parse them out so this suite cannot drift from the registration site.
const functionsSource = fs.readFileSync( path.join( themeDir, 'functions.php' ), 'utf8' );
const dynamicDirs = [];
const dirPattern = /'dir'\s*=>\s*'([a-z0-9-]+)'/g;
let dirMatch = dirPattern.exec( functionsSource );
while ( null !== dirMatch ) {
	dynamicDirs.push( dirMatch[ 1 ] );
	dirMatch = dirPattern.exec( functionsSource );
}
const staticDirs = ( functionsSource.match( /function musicwave_static_block_dirs\(\)\s*:\s*array\s*\{[^}]*?'([a-z0-9-]+)'/ ) || [] ).slice( 1 );

check( dynamicDirs.length >= 5, 'expected the theme dynamic block dirs to be parsed from functions.php' );
check( staticDirs.length >= 1, 'expected the theme static block dirs to be parsed from functions.php' );

const presentationBlocks = [];
dynamicDirs.forEach( ( dir ) => {
	const entry = metadataEntry( dir );
	presentationBlocks.push( entry );
	const legacy = Object.assign( {}, entry, {
		name: 'musicwave/' + dir,
		supports: Object.assign( {}, entry.supports, { inserter: false } ),
	} );
	presentationBlocks.push( legacy );
} );
const staticBlocks = staticDirs.map( metadataEntry );

const registered = {};
const calls = { useBlockProps: [], useBlockPropsSave: [], innerBlocks: [], serverSideRender: [] };

function createElement( type, props, ...children ) {
	if ( type && type.__isInnerBlocks ) {
		calls.innerBlocks.push( props || {} );
	}
	if ( 'ServerSideRenderComponent' === type ) {
		calls.serverSideRender.push( props || {} );
	}
	return { type, props: props || {}, children };
}

function useBlockProps( props ) {
	calls.useBlockProps.push( props || {} );
	return Object.assign( { 'data-lane': 'edit' }, props );
}
useBlockProps.save = function saveBlockProps( props ) {
	calls.useBlockPropsSave.push( props || {} );
	return Object.assign( { 'data-lane': 'save' }, props );
};

const sandbox = {
	console,
	window: {
		wp: {
			blocks: {
				getBlockType: ( name ) => registered[ name ],
				registerBlockType: ( name, config ) => {
					check( ! registered[ name ], 'the editor script must not register ' + name + ' twice' );
					registered[ name ] = config;
				},
			},
			element: { createElement, Fragment: 'Fragment' },
			blockEditor: {
				useBlockProps,
				InnerBlocks: Object.assign( { __isInnerBlocks: true }, { Content: 'InnerBlocks.Content' } ),
			},
			components: {
				Placeholder: 'Placeholder',
				PanelBody: 'PanelBody',
				TextControl: 'TextControl',
				TextareaControl: 'TextareaControl',
				SelectControl: 'SelectControl',
				ToggleControl: 'ToggleControl',
				RangeControl: 'RangeControl',
			},
			i18n: { __: ( text ) => text },
			serverSideRender: 'ServerSideRenderComponent',
		},
		musicwavePresentationBlocks: presentationBlocks,
		musicwavePresentationVariations: {},
		musicwaveStaticBlocks: staticBlocks,
	},
};

const scriptSource = fs.readFileSync( scriptFile, 'utf8' );
vm.runInNewContext( scriptSource, sandbox, { filename: 'editor-blocks.js' } );

// ---------------------------------------------------------------- inventory
const names = Object.keys( registered );
dynamicDirs.forEach( ( dir ) => {
	const entry = metadataEntry( dir );
	check( !! registered[ entry.name ], 'the dynamic lane must register ' + entry.name );
	check( !! registered[ 'musicwave/' + dir ], 'the dynamic lane must keep the legacy alias musicwave/' + dir );
	same(
		false,
		registered[ 'musicwave/' + dir ] ? registered[ 'musicwave/' + dir ].supports.inserter : null,
		'the legacy alias musicwave/' + dir + ' must stay hidden from the inserter'
	);
} );
staticDirs.forEach( ( dir ) => {
	const entry = metadataEntry( dir );
	check( !! registered[ entry.name ], 'the static lane must register ' + entry.name );
	check(
		! registered[ 'musicwave/' + dir ],
		'the static lane must not invent a legacy alias for ' + entry.name + ': nothing was ever saved under that name'
	);
} );

// Re-running the script must be a no-op: the getBlockType() guard is what keeps
// a second enqueue from clobbering a hydrated block type.
const countAfterFirstRun = names.length;
vm.runInNewContext( scriptSource, sandbox, { filename: 'editor-blocks.js' } );
same(
	countAfterFirstRun,
	Object.keys( registered ).length,
	're-running the editor script must not duplicate registrations'
);

// ------------------------------------------------------- dynamic lane (SSR)
dynamicDirs.forEach( ( dir ) => {
	const entry = metadataEntry( dir );
	const block = registered[ entry.name ];
	if ( ! block ) {
		return;
	}
	const ssrBefore = calls.serverSideRender.length;
	const innerBefore = calls.innerBlocks.length;
	block.edit( { attributes: {}, setAttributes() {}, isSelected: true } );
	check(
		calls.serverSideRender.length > ssrBefore,
		entry.name + ' is PHP-rendered, so its editor preview must come from ServerSideRender'
	);
	same( innerBefore, calls.innerBlocks.length, entry.name + ' must not gain an InnerBlocks chrome' );
	same( null, block.save(), entry.name + ' must store nothing: its markup comes from the render callback' );
	same( entry.title, block.title, entry.name + ' must carry its block.json title' );
	same( entry.keywords.length, block.keywords.length, entry.name + ' must carry its block.json keywords' );
} );

// ------------------------------------------- static lane (InnerBlocks, no SSR)
staticDirs.forEach( ( dir ) => {
	const entry = metadataEntry( dir );
	const block = registered[ entry.name ];
	if ( ! block ) {
		return;
	}

	same( entry.title, block.title, entry.name + ' must carry its block.json title' );
	same( entry.category, block.category, entry.name + ' must stay in the shared inserter category' );
	same( {}, block.attributes, entry.name + ' must stay attribute-free: every value lives in a real child block' );

	const ssrBefore = calls.serverSideRender.length;
	const propsBefore = calls.useBlockProps.length;
	const innerBefore = calls.innerBlocks.length;
	const edited = block.edit( { attributes: {}, setAttributes() {}, isSelected: true } );

	same( ssrBefore, calls.serverSideRender.length, entry.name + ' must not use ServerSideRender: its children are real blocks' );
	check( calls.useBlockProps.length > propsBefore, entry.name + ' must build its editor shell through useBlockProps()' );
	same( 'mw-section-head', calls.useBlockProps[ calls.useBlockProps.length - 1 ].className, entry.name + ' must style its shell with the editorial component class' );
	same( 'div', edited.type, entry.name + ' must render a single shell element in the editor' );

	check( calls.innerBlocks.length > innerBefore, entry.name + ' must render its children through InnerBlocks' );
	const innerProps = calls.innerBlocks[ calls.innerBlocks.length - 1 ];
	same( false, innerProps.templateLock, entry.name + ' must leave the template unlocked so children can be added, removed and reordered' );
	// The seeded structure is the contract: only .mw-section-head__text stacks
	// its lines vertically, because the shell itself is a flex row that expects
	// siblings such as the __rule. Placeholders are asserted as present, not as
	// wording, so translating them does not break the structure test.
	same( 1, innerProps.template.length, entry.name + ' must seed a single text stack; the shell is the block itself' );
	const seededGroup = innerProps.template[ 0 ];
	same( 'core/group', seededGroup[ 0 ], entry.name + ' must seed its text stack as a real core/group child' );
	same(
		{ className: 'mw-section-head__text', layout: { type: 'default' } },
		seededGroup[ 1 ],
		entry.name + ' must seed the mw-section-head__text group that stacks the header lines'
	);
	same(
		[ 'core/paragraph', 'core/heading', 'core/paragraph' ],
		seededGroup[ 2 ].map( ( seeded ) => seeded[ 0 ] ),
		entry.name + ' must seed eyebrow, title and description in the order the PHP shelf header uses'
	);
	same( 'mw-eyebrow', seededGroup[ 2 ][ 0 ][ 1 ].className, entry.name + ' must seed the eyebrow with the mw-eyebrow kicker class' );
	same( 2, seededGroup[ 2 ][ 1 ][ 1 ].level, entry.name + ' must seed a level-2 heading, the level .mw-section-head h2 styles' );
	check(
		seededGroup[ 2 ].every( ( seeded ) => 'string' === typeof seeded[ 1 ].placeholder && seeded[ 1 ].placeholder.length > 0 ),
		entry.name + ' must give every seeded child a translated placeholder'
	);

	const savePropsBefore = calls.useBlockPropsSave.length;
	const saved = block.save();
	check( calls.useBlockPropsSave.length > savePropsBefore, entry.name + ' must build its stored shell through useBlockProps.save()' );
	same( 'mw-section-head', calls.useBlockPropsSave[ calls.useBlockPropsSave.length - 1 ].className, entry.name + ' must store the editorial component class' );
	same( 'div', saved.type, entry.name + ' must store a single shell element' );
	same(
		'InnerBlocks.Content',
		saved.children[ 0 ] && saved.children[ 0 ].type ? saved.children[ 0 ].type : null,
		entry.name + ' must store its children from InnerBlocks.Content'
	);
	same(
		saved.props.className,
		edited.props.className,
		entry.name + ' must store the same shell it edits, or block validation will report invalid content'
	);
} );

if ( failures > 0 ) {
	console.error( '\n' + failures + ' editor lane assertion(s) failed.' );
	process.exit( 1 );
}

console.log(
	'Editor lanes OK (' +
		countAfterFirstRun +
		' registrations: ' +
		dynamicDirs.length +
		' PHP-rendered + ' +
		dynamicDirs.length +
		' legacy aliases + ' +
		staticDirs.length +
		' static InnerBlocks).'
);
