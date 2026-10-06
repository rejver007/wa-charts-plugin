/* global describe, it, expect */
const S = require( '../../assets/admin/editor-state' );

const types = {
	pie: {
		shape: 'single',
		max_rows: 500,
		options: { start_angle: { default: 0 } },
	},
	radial: {
		shape: 'single',
		max_rows: 1,
		options: { max: { default: 100 } },
	},
	bar: {
		shape: 'multi',
		max_rows: 500,
		options: { bar_width: { default: 70 } },
	},
	mixed: {
		shape: 'multi',
		max_rows: 500,
		options: { bar_width: { default: 70 }, curve: { default: 'smooth' } },
	},
};
const palette = [ '#111111', '#222222', '#333333' ];
const name = ( n ) => `Series ${ n + 1 }`;

const pie = () => ( {
	schema: 1,
	type: 'pie',
	labels: [ 'A', 'B' ],
	series: [ { name: '', color: null, render: null, values: [ 1, 2 ] } ],
	point_colors: [ '#aaaaaa', '#bbbbbb' ],
	display: {},
	type_options: { start_angle: 45 },
} );

describe( 'editor state', () => {
	it( 'does not mutate input', () => {
		const input = pie();
		S.addRow( input, types, palette );
		expect( input.labels ).toEqual( [ 'A', 'B' ] );
	} );

	it( 'normalizes empty state to one row', () => {
		const state = S.normalize(
			{ ...pie(), labels: [], series: [], point_colors: [] },
			types,
			palette
		);
		expect( state.labels ).toEqual( [ '' ] );
		expect( state.series[ 0 ].values ).toEqual( [ null ] );
		expect( state.point_colors ).toEqual( [ '#111111' ] );
	} );

	it( 'switches single → multi keeping data', () => {
		const { state, hiddenSeries, hiddenRows } = S.switchType(
			pie(),
			'bar',
			types,
			palette
		);
		expect( state.type ).toBe( 'bar' );
		expect( state.series[ 0 ].values ).toEqual( [ 1, 2 ] );
		expect( state.series[ 0 ].color ).toBe( '#aaaaaa' );
		expect( state.point_colors ).toEqual( [ '#aaaaaa', '#bbbbbb' ] );
		expect( state.type_options ).toEqual( { bar_width: 70 } );
		expect( hiddenSeries ).toBe( 0 );
		expect( hiddenRows ).toBe( 0 );
	} );

	it( 'switches multi → single keeping every series', () => {
		let state = S.switchType( pie(), 'mixed', types, palette ).state;
		state = S.addSeries( state, palette, 20, name );
		expect( state.series[ 1 ].render ).toBe( 'bar' );
		const result = S.switchType( state, 'pie', types, palette );
		expect( result.hiddenSeries ).toBe( 1 );
		expect( result.hiddenRows ).toBe( 0 );
		expect( result.state.series ).toHaveLength( 2 );
		expect( result.state.series[ 1 ].color ).toBe( '#222222' );
		expect( result.state.series[ 1 ].render ).toBe( 'bar' );
		expect( result.state.point_colors ).toEqual( [ '#aaaaaa', '#bbbbbb' ] );
	} );

	it( 'fills missing point colours from the palette and aligns them', () => {
		const input = { ...pie(), point_colors: [ '#aaaaaa' ] };
		const { state } = S.switchType( input, 'bar', types, palette );
		expect( state.point_colors ).toEqual( [ '#aaaaaa', '#222222' ] );
	} );

	it( 'defaults render to bar only when moving to mixed', () => {
		const mixed = S.switchType( pie(), 'mixed', types, palette ).state;
		expect( mixed.series[ 0 ].render ).toBe( 'bar' );
		const line = {
			...mixed,
			series: [ { ...mixed.series[ 0 ], render: 'line' } ],
		};
		expect(
			S.switchType( line, 'bar', types, palette ).state.series[ 0 ].render
		).toBe( 'line' );
	} );

	it( 'keeps all rows for the gauge and reports the hidden ones', () => {
		const result = S.switchType( pie(), 'radial', types, palette );
		expect( result.hiddenRows ).toBe( 1 );
		expect( result.hiddenSeries ).toBe( 0 );
		expect( result.state.labels ).toEqual( [ 'A', 'B' ] );
		expect( result.state.series[ 0 ].values ).toEqual( [ 1, 2 ] );
		expect( result.state.type_options ).toEqual( { max: 100 } );
		const back = S.switchType( result.state, 'pie', types, palette ).state;
		expect( back.labels ).toEqual( [ 'A', 'B' ] );
		expect( back.series[ 0 ].values ).toEqual( [ 1, 2 ] );
		expect( back.point_colors ).toEqual( [ '#aaaaaa', '#bbbbbb' ] );
	} );

	it( 'reports usage for the current type', () => {
		const gauge = S.switchType( pie(), 'radial', types, palette ).state;
		expect( S.usage( gauge, types ) ).toEqual( {
			visibleRows: 1,
			visibleSeries: 1,
		} );
		let bar = S.switchType( pie(), 'bar', types, palette ).state;
		bar = S.addSeries( bar, palette, 20, name );
		expect( S.usage( bar, types ) ).toEqual( {
			visibleRows: 2,
			visibleSeries: 2,
		} );
		expect( S.usage( { ...bar, type: 'pie' }, types ).visibleSeries ).toBe(
			1
		);
	} );

	it( 'adds, moves and removes rows across series and colours', () => {
		let state = S.addRow( pie(), types, palette );
		expect( state.labels ).toEqual( [ 'A', 'B', '' ] );
		expect( state.point_colors[ 2 ] ).toBe( '#333333' );
		state = S.moveRow( state, 0, 2 );
		expect( state.labels ).toEqual( [ 'B', '', 'A' ] );
		expect( state.series[ 0 ].values ).toEqual( [ 2, null, 1 ] );
		expect( state.point_colors ).toEqual( [
			'#bbbbbb',
			'#333333',
			'#aaaaaa',
		] );
		state = S.removeRow( state, 1 );
		expect( state.labels ).toEqual( [ 'B', 'A' ] );
	} );

	it( 'adds rows to a gauge but respects the label limit', () => {
		const state = S.switchType( pie(), 'radial', types, palette ).state;
		const added = S.addRow( state, types, palette, 500 );
		expect( added.labels ).toHaveLength( 3 );
		expect( added.point_colors ).toHaveLength( 3 );
		expect( S.addRow( added, types, palette, 3 ).labels ).toHaveLength( 3 );
	} );

	it( 'keeps point colours aligned for multi types too', () => {
		const bar = S.switchType( pie(), 'bar', types, palette ).state;
		const added = S.addRow( bar, types, palette );
		expect( added.point_colors ).toHaveLength( 3 );
		const moved = S.moveRow( added, 0, 2 );
		expect( moved.point_colors[ 2 ] ).toBe( '#aaaaaa' );
		const normalized = S.normalize(
			{ ...bar, point_colors: [] },
			types,
			palette
		);
		expect( normalized.point_colors ).toEqual( [ '#111111', '#222222' ] );
	} );

	it( 'sets values with decimal comma and clears invalid ones', () => {
		let state = S.setValue( pie(), 0, 0, '3,5' );
		expect( state.series[ 0 ].values[ 0 ] ).toBe( 3.5 );
		state = S.setValue( state, 0, 0, 'abc' );
		expect( state.series[ 0 ].values[ 0 ] ).toBeNull();
	} );

	it( 'keeps at least one series', () => {
		const state = S.switchType( pie(), 'bar', types, palette ).state;
		expect( S.removeSeries( state, 0 ).series ).toHaveLength( 1 );
	} );

	it( 'fills colours from the palette', () => {
		expect( S.fillPalette( pie(), types, palette ).point_colors ).toEqual( [
			'#111111',
			'#222222',
		] );
		const bar = S.addSeries(
			S.switchType( pie(), 'bar', types, palette ).state,
			palette,
			20,
			name
		);
		expect(
			S.fillPalette( bar, types, palette ).series.map( ( s ) => s.color )
		).toEqual( [ '#111111', '#222222' ] );
	} );

	it( 'keeps hidden series when replacing data on a single-shape type', () => {
		let state = S.switchType( pie(), 'bar', types, palette ).state;
		state = S.addSeries( state, palette, 20, name );
		state.series[ 1 ].values = [ 7, 8 ];
		state = S.switchType( state, 'pie', types, palette ).state;
		const parsed = {
			seriesNames: null,
			rows: [
				{ label: 'X', values: [ 1 ] },
				{ label: 'Y', values: [ 2 ] },
				{ label: 'Z', values: [ 3 ] },
			],
		};
		const out = S.applyPaste(
			state,
			parsed,
			'replace',
			types,
			palette,
			20,
			name
		);
		expect( out.labels ).toEqual( [ 'X', 'Y', 'Z' ] );
		expect( out.series ).toHaveLength( 2 );
		expect( out.series[ 0 ].values ).toEqual( [ 1, 2, 3 ] );
		expect( out.series[ 1 ].name ).toBe( 'Series 2' );
		expect( out.series[ 1 ].color ).toBe( '#222222' );
		expect( out.series[ 1 ].values ).toEqual( [ null, null, null ] );
		expect( out.point_colors ).toHaveLength( 3 );
	} );

	it( 'applies pasted data in replace and append modes', () => {
		const parsed = {
			seriesNames: [ 'Sales', 'Cost' ],
			rows: [
				{ label: 'Q1', values: [ 1, 2 ] },
				{ label: 'Q2', values: [ 3 ] },
			],
		};
		const bar = S.switchType( pie(), 'bar', types, palette ).state;
		const replaced = S.applyPaste(
			bar,
			parsed,
			'replace',
			types,
			palette,
			20,
			name
		);
		expect( replaced.labels ).toEqual( [ 'Q1', 'Q2' ] );
		expect( replaced.series.map( ( s ) => s.name ) ).toEqual( [
			'Sales',
			'Cost',
		] );
		expect( replaced.series[ 1 ].values ).toEqual( [ 2, null ] );

		const appended = S.applyPaste(
			pie(),
			{ seriesNames: null, rows: [ { label: 'C', values: [ 9 ] } ] },
			'append',
			types,
			palette,
			20,
			name
		);
		expect( appended.labels ).toEqual( [ 'A', 'B', 'C' ] );
		expect( appended.series[ 0 ].values ).toEqual( [ 1, 2, 9 ] );
		expect( appended.point_colors[ 2 ] ).toBe( '#333333' );
	} );
} );

describe( 'editor UI helpers', () => {
	it( 'groups types by shape in registry order', () => {
		const groups = S.groupTypes( {
			bar: { shape: 'multi' },
			pie: { shape: 'single' },
			line: { shape: 'multi' },
			radial: { shape: 'single' },
			odd: {},
		} );
		expect( groups ).toEqual( [
			{ shape: 'single', ids: [ 'pie', 'radial', 'odd' ] },
			{ shape: 'multi', ids: [ 'bar', 'line' ] },
		] );
	} );

	it( 'drops empty groups', () => {
		expect( S.groupTypes( { pie: { shape: 'single' } } ) ).toEqual( [
			{ shape: 'single', ids: [ 'pie' ] },
		] );
		expect( S.groupTypes( {} ) ).toEqual( [] );
	} );

	it( 'maps option keys to units', () => {
		expect( S.unitFor( 'height' ) ).toBe( 'px' );
		expect( S.unitFor( 'start_angle' ) ).toBe( '°' );
		expect( S.unitFor( 'hole_size' ) ).toBe( '%' );
		expect( S.unitFor( 'bar_width' ) ).toBe( '%' );
		expect( S.unitFor( 'display.height' ) ).toBe( 'px' );
		expect( S.unitFor( 'type_options.bar_width' ) ).toBe( '%' );
		expect( S.unitFor( 'max' ) ).toBeNull();
		expect( S.unitFor( 'constructor' ) ).toBeNull();
		expect( S.unitFor( '' ) ).toBeNull();
	} );

	it( 'strips a trailing parenthesised unit from labels', () => {
		expect( S.stripUnit( 'Start angle (°)' ) ).toBe( 'Start angle' );
		expect( S.stripUnit( 'Bar width (%)' ) ).toBe( 'Bar width' );
		expect( S.stripUnit( 'Reiän koko (%) ' ) ).toBe( 'Reiän koko' );
		expect( S.stripUnit( 'Height (px)' ) ).toBe( 'Height' );
		expect( S.stripUnit( 'Stack to 100 %' ) ).toBe( 'Stack to 100 %' );
		expect( S.stripUnit( 'Mid (x) label' ) ).toBe( 'Mid (x) label' );
		expect( S.stripUnit( '(%)' ) ).toBe( '(%)' );
		expect( S.stripUnit( undefined ) ).toBe( '' );
	} );
} );
