/* global self */
( function ( root, factory ) {
	const api = factory();
	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	} else {
		root.WaChartsPaste = api;
	}
} )( typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	function parseNumber( raw ) {
		if ( raw === null || raw === undefined ) {
			return null;
		}
		if ( typeof raw === 'number' ) {
			return Number.isFinite( raw ) ? raw : NaN;
		}
		let s = String( raw ).replace( /[\s\u00a0\u202f]/g, '' );
		if ( s === '' ) {
			return null;
		}
		if ( /^-?\d+,\d+$/.test( s ) ) {
			s = s.replace( ',', '.' );
		} else if ( /^-?\d{1,3}(\.\d{3})+,\d+$/.test( s ) ) {
			s = s.replace( /\./g, '' ).replace( ',', '.' );
		} else if ( /^-?\d{1,3}(,\d{3})+(\.\d+)?$/.test( s ) ) {
			s = s.replace( /,/g, '' );
		}
		return /^-?(\d+(\.\d*)?|\.\d+)$/.test( s ) ? Number( s ) : NaN;
	}

	function splitLine( line, delimiter ) {
		const cells = [];
		let cell = '';
		let quoted = false;
		for ( let i = 0; i < line.length; i++ ) {
			const ch = line[ i ];
			if ( quoted ) {
				if ( ch === '"' && line[ i + 1 ] === '"' ) {
					cell += '"';
					i++;
				} else if ( ch === '"' ) {
					quoted = false;
				} else {
					cell += ch;
				}
			} else if ( ch === '"' && cell.trim() === '' ) {
				quoted = true;
				cell = '';
			} else if ( ch === delimiter ) {
				cells.push( cell.trim() );
				cell = '';
			} else {
				cell += ch;
			}
		}
		cells.push( cell.trim() );
		return cells;
	}

	function detectDelimiter( lines ) {
		if ( lines.some( ( line ) => line.includes( '\t' ) ) ) {
			return '\t';
		}
		return lines[ 0 ].includes( ';' ) ? ';' : ',';
	}

	function parse( text ) {
		const lines = String( text || '' )
			.split( /\r\n|\n|\r/ )
			.filter( ( line ) => line.trim() !== '' );
		const result = {
			delimiter: null,
			seriesNames: null,
			rows: [],
			invalid: 0,
		};
		if ( ! lines.length ) {
			return result;
		}
		result.delimiter = detectDelimiter( lines );
		let table = lines.map( ( line ) =>
			splitLine( line, result.delimiter )
		);
		const first = table[ 0 ].slice( 1 );
		const isHeader = first.some( ( cell ) => {
			const n = parseNumber( cell );
			return cell !== '' && ( n === null || Number.isNaN( n ) );
		} );
		if ( isHeader ) {
			result.seriesNames = first;
			table = table.slice( 1 );
		}
		result.rows = table.map( ( cells ) => ( {
			label: cells[ 0 ],
			values: cells.slice( 1 ).map( ( cell ) => {
				const n = parseNumber( cell );
				if ( n !== null && Number.isNaN( n ) ) {
					result.invalid++;
					return null;
				}
				return n;
			} ),
		} ) );
		return result;
	}

	return { parseNumber, splitLine, parse };
} );
