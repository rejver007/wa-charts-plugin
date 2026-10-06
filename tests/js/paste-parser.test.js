/* global describe, it, expect */
const {
	parseNumber,
	splitLine,
	parse,
} = require( '../../assets/admin/paste-parser' );

describe( 'parseNumber', () => {
	it.each( [
		[ '3,65', 3.65 ],
		[ '3.65', 3.65 ],
		[ '1 234,5', 1234.5 ],
		[ '1\u00a0234,5', 1234.5 ],
		[ '1.234,5', 1234.5 ],
		[ '1,234,567', 1234567 ],
		[ '1,234.5', 1234.5 ],
		[ '-0,5', -0.5 ],
		[ 7, 7 ],
	] )( '%p → %p', ( input, expected ) => {
		expect( parseNumber( input ) ).toBe( expected );
	} );
	it( 'returns null for empty and NaN for junk', () => {
		expect( parseNumber( '' ) ).toBeNull();
		expect( parseNumber( '  ' ) ).toBeNull();
		expect( parseNumber( null ) ).toBeNull();
		expect( parseNumber( 'abc' ) ).toBeNaN();
		expect( parseNumber( '1,2,3.4.5' ) ).toBeNaN();
	} );
} );

describe( 'splitLine', () => {
	it( 'handles quotes and escaped quotes', () => {
		expect( splitLine( '"a, b",2,"say ""hi"""', ',' ) ).toEqual( [
			'a, b',
			'2',
			'say "hi"',
		] );
	} );
} );

describe( 'parse', () => {
	it( 'parses Excel tab-separated data with a header row', () => {
		const result = parse( '\tSales\tCost\r\nQ1\t1,5\t2\r\nQ2\t3\tx\r\n' );
		expect( result.delimiter ).toBe( '\t' );
		expect( result.seriesNames ).toEqual( [ 'Sales', 'Cost' ] );
		expect( result.rows ).toEqual( [
			{ label: 'Q1', values: [ 1.5, 2 ] },
			{ label: 'Q2', values: [ 3, null ] },
		] );
		expect( result.invalid ).toBe( 1 );
	} );
	it( 'parses semicolon CSV without a header', () => {
		const result = parse( 'UVA;3,65\nAalto;1,6' );
		expect( result.delimiter ).toBe( ';' );
		expect( result.seriesNames ).toBeNull();
		expect( result.rows[ 0 ] ).toEqual( {
			label: 'UVA',
			values: [ 3.65 ],
		} );
	} );
	it( 'returns no rows for empty input', () => {
		expect( parse( '\n\n' ).rows ).toEqual( [] );
	} );
} );
