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
		const { state, droppedSeries } = S.switchType(
			pie(),
			'bar',
			types,
			palette
		);
		expect( state.type ).toBe( 'bar' );
		expect( state.series[ 0 ].values ).toEqual( [ 1, 2 ] );
		expect( state.series[ 0 ].color ).toBe( '#aaaaaa' );
		expect( state.point_colors ).toEqual( [] );
		expect( state.type_options ).toEqual( { bar_width: 70 } );
		expect( droppedSeries ).toBe( 0 );
	} );

	it( 'switches multi → single and reports dropped series', () => {
		let state = S.switchType( pie(), 'mixed', types, palette ).state;
		state = S.addSeries( state, palette, 20, name );
		expect( state.series[ 1 ].render ).toBe( 'bar' );
		const result = S.switchType( state, 'pie', types, palette );
		expect( result.droppedSeries ).toBe( 1 );
		expect( result.state.series ).toHaveLength( 1 );
		expect( result.state.series[ 0 ].render ).toBeNull();
		expect( result.state.point_colors ).toEqual( [ '#111111', '#222222' ] );
	} );

	it( 'trims rows for the gauge and reports it', () => {
		const result = S.switchType( pie(), 'radial', types, palette );
		expect( result.droppedRows ).toBe( 1 );
		expect( result.state.labels ).toEqual( [ 'A' ] );
		expect( result.state.type_options ).toEqual( { max: 100 } );
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

	it( 'respects the row limit', () => {
		const state = S.switchType( pie(), 'radial', types, palette ).state;
		expect( S.addRow( state, types, palette ).labels ).toHaveLength( 1 );
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
