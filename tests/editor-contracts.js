/** Offline editor registration and metadata UI contracts (no WordPress required). */
'use strict';
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );
const { JSDOM } = require( 'jsdom' );
const root = path.join( __dirname, '..' );
const read = ( file ) => fs.readFileSync( path.join( root, file ), 'utf8' );

function editorContracts() {
	const metadata = JSON.parse( read( 'music-wave-core/blocks/release-meta/block.json' ) );
	const sentinel = { customContext: 'releaseId' };
	const registered = { [ metadata.name ]: { ...metadata, providesContext: sentinel } };
	const variations = [];
	let wrapperCalls = 0;
	const createElement = ( type, props, ...children ) => ( { type, props: props || {}, children: children.flat( Infinity ) } );
	const wp = {
		blocks: {
			getBlockType: ( name ) => registered[ name ],
			unregisterBlockType: ( name ) => delete registered[ name ],
			registerBlockType: ( name, settings ) => { registered[ name ] = settings; },
			registerBlockVariation: ( name, variation ) => variations.push( { name, ...variation } ),
		},
		element: { createElement, Fragment: 'Fragment' },
		blockEditor: {
			InspectorControls: 'InspectorControls',
			useBlockProps: ( props ) => { wrapperCalls++; return { ...props, 'data-native-props': true }; },
		},
		components: new Proxy( {}, { get: ( obj, key ) => key } ),
		i18n: { __: ( value ) => value },
		serverSideRender: 'ServerSideRender',
	};
	vm.runInNewContext( read( 'music-wave-core/assets/blocks.js' ), { window: { wp, musicWaveDynamicBlocks: [ metadata ] } } );
	assert.equal( registered[ metadata.name ].providesContext, sentinel, 'Unknown server metadata must survive registration.' );
	assert.equal( variations.length, 5, 'Five independent release facts are available in the inserter.' );
	for ( const variation of variations ) {
		const toggles = Object.keys( metadata.attributes ).filter( ( key ) => key.startsWith( 'show' ) && key !== 'showLabels' );
		assert.equal( toggles.filter( ( key ) => variation.attributes[ key ] === true ).length, 1, 'Each fact enables exactly one field.' );
		assert.equal( variation.isActive( variation.attributes ), true );
		assert.ok( variation.attributes.metadata.name, 'List View names identify each fact.' );
		assert.equal( variation.attributes.releaseId, undefined, 'Facts inherit Query Loop post context.' );
	}
	const settings = registered[ metadata.name ];
	const tree = settings.edit( {
		attributes: variations[ 0 ].attributes,
		context: { postId: 123, postType: 'mw_release' },
		setAttributes: () => assert.fail( 'Preview context must not persist attributes.' ),
	} );
	function find( node, predicate ) {
		if ( ! node || typeof node !== 'object' ) return null;
		if ( predicate( node ) ) return node;
		for ( const child of node.children || [] ) { const found = find( child, predicate ); if ( found ) return found; }
		return null;
	}
	assert.equal( wrapperCalls, 1, 'Native block props are called unconditionally.' );
	assert.ok( find( tree, ( node ) => node.props[ 'data-native-props' ] ), 'Native selection/style props reach the canvas wrapper.' );
	const preview = find( tree, ( node ) => node.type === 'ServerSideRender' );
	assert.equal( preview.props.attributes.releaseId, 123 );
	assert.equal( preview.props.urlQueryArgs.post_id, 123 );
	assert.equal( preview.props.skipBlockSupportAttributes, true, 'Preview must not apply spacing twice.' );
	assert.equal( settings.save(), null, 'Secure dynamic rendering stays in PHP.' );
}

async function metadataUI( endpoint, plain, fail = false ) {
	const dom = new JSDOM( '<div class="mw-metadata-lookup" data-post-id="7" data-release-types=\'["track"]\'></div>', {
		url: 'https://example.test/sub/wp-admin/post.php?post=7&action=edit',
		runScripts: 'outside-only',
	} );
	const { window } = dom;
	const calls = [];
	window.musicWaveMetadataLookup = { root: endpoint, nonce: 'test-nonce' };
	window.fetch = async ( url, options ) => {
		calls.push( { url: new URL( url ), options } );
		return {
			ok: true,
			json: async () => options.method === 'GET'
				? ( fail ? { success: false, code: 'provider_unavailable', message: 'Provider connection failed' } : {
					success: true,
					results: [ { title: '<img src=x onerror=alert(1)>', artist: 'Artist', provider: 'musicbrainz', reference_id: 'one' } ],
				} )
				: { success: true, fields: {}, warnings: [], message: 'Saved' },
		};
	};
	window.eval( read( 'music-wave-core/assets/metadata-lookup.js' ) );
	await new Promise( ( resolve ) => window.setTimeout( resolve, 0 ) );
	const input = window.document.querySelector( '.mw-metadata-lookup__input' );
	input.value = 'A & B + موسیقی';
	const search = window.document.querySelector( '.mw-metadata-lookup__search' );
	search.click();
	input.dispatchEvent( new window.KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } ) );
	await new Promise( ( resolve ) => window.setTimeout( resolve, 0 ) );
	assert.equal( calls.length, 1, 'Enter while busy must not duplicate provider requests.' );
	assert.equal( calls[ 0 ].url.searchParams.get( 'q' ), input.value );
	assert.equal( calls[ 0 ].url.searchParams.get( 'release_types[]' ), 'track' );
	assert.equal( calls[ 0 ].options.headers[ 'X-WP-Nonce' ], 'test-nonce' );
	assert.equal( calls[ 0 ].options.credentials, 'same-origin' );
	if ( plain ) {
		assert.equal( calls[ 0 ].url.searchParams.get( 'rest_route' ), '/music-wave/v1/metadata-lookup' );
		assert.equal( calls[ 0 ].url.searchParams.get( 'lang' ), 'fa' );
	} else {
		assert.equal( calls[ 0 ].url.pathname, '/sub/wp-json/music-wave/v1/metadata-lookup' );
	}
	if ( fail ) {
		assert.equal( window.document.querySelector( '.mw-metadata-lookup__status' ).textContent, 'Provider connection failed' );
		assert.equal( search.disabled, false, 'Provider failure allows retry.' );
	} else {
		assert.equal( window.document.querySelector( '.mw-metadata-lookup__title img' ), null, 'Provider strings must never become HTML.' );
		window.document.querySelector( '.mw-metadata-lookup__apply' ).click();
		await new Promise( ( resolve ) => window.setTimeout( resolve, 0 ) );
		assert.equal( calls.length, 2 );
		assert.equal( calls[ 1 ].options.method, 'POST' );
		assert.equal( JSON.parse( calls[ 1 ].options.body ).post_id, 7 );
		assert.equal( plain ? calls[ 1 ].url.searchParams.get( 'rest_route' ) : calls[ 1 ].url.pathname,
			plain ? '/music-wave/v1/metadata-lookup/apply' : '/sub/wp-json/music-wave/v1/metadata-lookup/apply' );
	}
	window.close();
}

( async () => {
	editorContracts();
	await metadataUI( 'https://example.test/sub/wp-json/music-wave/v1/metadata-lookup', false );
	await metadataUI( 'https://example.test/sub/index.php?rest_route=%2Fmusic-wave%2Fv1%2Fmetadata-lookup&lang=fa', true );
	await metadataUI( 'https://example.test/sub/wp-json/music-wave/v1/metadata-lookup', false, true );
	console.log( 'Editor contracts passed: native facts, context, styles, REST routing, safe rendering and retry.' );
} )().catch( ( error ) => { console.error( error ); process.exitCode = 1; } );
