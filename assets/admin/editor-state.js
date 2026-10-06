/* global self */
( function ( root, factory ) {
	if ( typeof module === 'object' && module.exports ) {
		module.exports = factory( require( './paste-parser' ) );
	} else {
		root.WaChartsState = factory( root.WaChartsPaste );
	}
} )( typeof self !== 'undefined' ? self : this, function ( Paste ) {
	'use strict';

	const clone = ( value ) => JSON.parse( JSON.stringify( value ) );
	const pick = ( palette, i ) => palette[ i % palette.length ];
	const shapeOf = ( types, type ) =>
		types[ type ] ? types[ type ].shape : 'single';
	const maxRows = ( types, type ) =>
		types[ type ] && types[ type ].max_rows ? types[ type ].max_rows : 500;

	function move( list, from, to ) {
		if (
			! Array.isArray( list ) ||
			from === to ||
			from < 0 ||
			from >= list.length
		) {
			return;
		}
		const [ item ] = list.splice( from, 1 );
		list.splice( to, 0, item );
	}

	function typeDefaults( types, type ) {
		const options = types[ type ] ? types[ type ].options || {} : {};
		const out = {};
		Object.keys( options ).forEach( ( key ) => {
			out[ key ] = options[ key ].default;
		} );
		return out;
	}

	function mixedRender( render ) {
		return render === 'line' ? 'line' : 'bar';
	}

	function normalize( input, types, palette ) {
		const state = clone( input );
		state.labels = Array.isArray( state.labels ) ? state.labels : [];
		state.point_colors = Array.isArray( state.point_colors )
			? state.point_colors
			: [];
		if ( ! Array.isArray( state.series ) || ! state.series.length ) {
			state.series = [
				{ name: '', color: null, render: null, values: [] },
			];
		}
		if ( ! state.labels.length ) {
			state.labels = [ '' ];
		}
		state.series.forEach( ( s ) => {
			s.values = Array.isArray( s.values ) ? s.values : [];
			while ( s.values.length < state.labels.length ) {
				s.values.push( null );
			}
			s.values.length = state.labels.length;
		} );
		if ( shapeOf( types, state.type ) === 'single' ) {
			while ( state.point_colors.length < state.labels.length ) {
				state.point_colors.push(
					pick( palette, state.point_colors.length )
				);
			}
		}
		if ( ! state.type_options || Array.isArray( state.type_options ) ) {
			state.type_options = typeDefaults( types, state.type );
		}
		return state;
	}

	function switchType( input, newType, types, palette ) {
		const state = clone( input );
		const from = shapeOf( types, state.type );
		const to = shapeOf( types, newType );
		let droppedSeries = 0;
		let droppedRows = 0;

		if ( from === 'single' && to === 'multi' ) {
			state.series = [ state.series[ 0 ] ];
			state.series[ 0 ].color =
				state.point_colors[ 0 ] || pick( palette, 0 );
			state.point_colors = [];
		} else if ( from === 'multi' && to === 'single' ) {
			droppedSeries = state.series.length - 1;
			state.series = [ state.series[ 0 ] ];
			state.series[ 0 ].color = null;
			state.point_colors = state.labels.map( ( _, i ) =>
				pick( palette, i )
			);
		}
		state.series.forEach( ( s ) => {
			s.render = newType === 'mixed' ? mixedRender( s.render ) : null;
		} );

		const limit = maxRows( types, newType );
		if ( state.labels.length > limit ) {
			droppedRows = state.labels.length - limit;
			state.labels.length = limit;
			state.series.forEach( ( s ) => {
				s.values.length = limit;
			} );
			if ( state.point_colors.length > limit ) {
				state.point_colors.length = limit;
			}
		}

		const options = typeDefaults( types, newType );
		const old = state.type_options || {};
		Object.keys( options ).forEach( ( key ) => {
			if ( Object.prototype.hasOwnProperty.call( old, key ) ) {
				options[ key ] = old[ key ];
			}
		} );
		state.type_options = options;
		state.type = newType;
		return { state, droppedSeries, droppedRows };
	}

	function addRow( input, types, palette ) {
		const state = clone( input );
		if ( state.labels.length >= maxRows( types, state.type ) ) {
			return state;
		}
		state.labels.push( '' );
		state.series.forEach( ( s ) => s.values.push( null ) );
		if ( shapeOf( types, state.type ) === 'single' ) {
			state.point_colors.push( pick( palette, state.labels.length - 1 ) );
		}
		return state;
	}

	function removeRow( input, index ) {
		const state = clone( input );
		state.labels.splice( index, 1 );
		state.series.forEach( ( s ) => s.values.splice( index, 1 ) );
		if ( state.point_colors.length > index ) {
			state.point_colors.splice( index, 1 );
		}
		return state;
	}

	function moveRow( input, from, to ) {
		const state = clone( input );
		move( state.labels, from, to );
		state.series.forEach( ( s ) => move( s.values, from, to ) );
		if ( state.point_colors.length ) {
			move( state.point_colors, from, to );
		}
		return state;
	}

	function addSeries( input, palette, limit, seriesName ) {
		const state = clone( input );
		if ( state.series.length >= limit ) {
			return state;
		}
		const n = state.series.length;
		state.series.push( {
			name: seriesName( n ),
			color: pick( palette, n ),
			render: state.type === 'mixed' ? 'bar' : null,
			values: state.labels.map( () => null ),
		} );
		return state;
	}

	function removeSeries( input, index ) {
		const state = clone( input );
		if ( state.series.length > 1 ) {
			state.series.splice( index, 1 );
		}
		return state;
	}

	function setLabel( input, row, text ) {
		const state = clone( input );
		state.labels[ row ] = text;
		return state;
	}

	function setValue( input, row, seriesIndex, raw ) {
		const state = clone( input );
		const parsed = Paste.parseNumber( raw );
		state.series[ seriesIndex ].values[ row ] =
			parsed === null || Number.isNaN( parsed ) ? null : parsed;
		return state;
	}

	function setPointColor( input, row, color ) {
		const state = clone( input );
		state.point_colors[ row ] = color;
		return state;
	}

	function setSeriesField( input, index, field, value ) {
		const state = clone( input );
		state.series[ index ][ field ] = value;
		return state;
	}

	function fillPalette( input, types, palette ) {
		const state = clone( input );
		if ( shapeOf( types, state.type ) === 'single' ) {
			state.point_colors = state.labels.map( ( _, i ) =>
				pick( palette, i )
			);
		} else {
			state.series.forEach( ( s, n ) => {
				s.color = pick( palette, n );
			} );
		}
		return state;
	}

	function replaceSeries( state, parsed, palette, seriesLimit, seriesName ) {
		const widest = parsed.rows.reduce(
			( max, r ) => Math.max( max, r.values.length ),
			0
		);
		const wanted = parsed.seriesNames ? parsed.seriesNames.length : widest;
		const count = Math.max( 1, Math.min( seriesLimit, wanted ) );
		const old = state.series;
		const next = [];
		for ( let n = 0; n < count; n++ ) {
			const prev = old[ n ];
			const named = parsed.seriesNames && parsed.seriesNames[ n ];
			let render = null;
			if ( state.type === 'mixed' ) {
				render = ( prev && prev.render ) || 'bar';
			}
			next.push( {
				name: named || ( prev ? prev.name : seriesName( n ) ),
				color: prev ? prev.color : pick( palette, n ),
				render,
				values: [],
			} );
		}
		return next;
	}

	function applyPaste(
		input,
		parsed,
		mode,
		types,
		palette,
		seriesLimit,
		seriesName
	) {
		const state = clone( input );
		const single = shapeOf( types, state.type ) === 'single';
		const limit = maxRows( types, state.type );

		if ( mode === 'replace' ) {
			state.labels = [];
			state.point_colors = [];
			if ( single ) {
				state.series = [
					{
						name: state.series[ 0 ] ? state.series[ 0 ].name : '',
						color: null,
						render: null,
						values: [],
					},
				];
			} else {
				state.series = replaceSeries(
					state,
					parsed,
					palette,
					seriesLimit,
					seriesName
				);
			}
		}

		parsed.rows.forEach( ( row ) => {
			if ( state.labels.length >= limit ) {
				return;
			}
			state.labels.push( row.label );
			state.series.forEach( ( s, n ) => {
				const value = row.values[ n ];
				s.values.push( value === undefined ? null : value );
			} );
			if ( single ) {
				state.point_colors.push(
					pick( palette, state.labels.length - 1 )
				);
			}
		} );
		return state;
	}

	return {
		normalize,
		switchType,
		addRow,
		removeRow,
		moveRow,
		addSeries,
		removeSeries,
		setLabel,
		setValue,
		setPointColor,
		setSeriesField,
		fillPalette,
		applyPaste,
		shapeOf,
	};
} );
