/* global self */
( function ( root, factory ) {
	const api = factory();
	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	} else {
		root.WaCharts = Object.assign( root.WaCharts || {}, api );
	}
} )( typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	const CHART_TYPE = {
		pie: 'pie',
		donut: 'doughnut',
		polar: 'polarArea',
		radial: 'doughnut',
		bar: 'bar',
		'bar-horizontal': 'bar',
		stacked: 'bar',
		line: 'line',
		area: 'line',
		mixed: 'bar',
		radar: 'radar',
	};
	const SINGLE = [ 'pie', 'donut', 'polar', 'radial' ];
	const isSingle = ( type ) => SINGLE.includes( type );
	// Single-shape types that can show percentage labels (not the radial gauge).
	const PERCENT_TYPES = [ 'pie', 'donut', 'polar' ];
	// Slices below this share (0.03 = 3 %) get no percentage label, to avoid clutter.
	const MIN_PERCENT_SHARE = 0.03;
	// Slices at or above this share (0.10 = 10 %) are always labelled; smaller
	// ones use datalabels 'auto', so a larger slice wins when labels collide.
	const ALWAYS_SHOW_SHARE = 0.1;

	function formatValue( value, valueCfg ) {
		const cfg = valueCfg || {};
		const number = Number( value );
		if (
			value === null ||
			value === undefined ||
			! Number.isFinite( number )
		) {
			return '';
		}
		const decimals = Number.isInteger( cfg.decimals ) ? cfg.decimals : 2;
		let text;
		try {
			text = new Intl.NumberFormat( cfg.locale || 'fi-FI', {
				minimumFractionDigits: 0,
				maximumFractionDigits: decimals,
			} ).format( number );
		} catch {
			text = String( number );
		}
		return `${ cfg.prefix || '' }${ text }${ cfg.suffix || '' }`;
	}

	const isPlainObject = ( v ) =>
		v !== null && typeof v === 'object' && ! Array.isArray( v );

	function deepMerge( target, source ) {
		Object.keys( source || {} ).forEach( ( key ) => {
			if ( [ '__proto__', 'constructor', 'prototype' ].includes( key ) ) {
				return;
			}
			if (
				isPlainObject( source[ key ] ) &&
				isPlainObject( target[ key ] )
			) {
				deepMerge( target[ key ], source[ key ] );
			} else {
				target[ key ] = source[ key ];
			}
		} );
		return target;
	}

	function withAlpha( color, alpha ) {
		const match = /^#([0-9a-f]{6})([0-9a-f]{2})?$/i.exec( color || '' );
		if ( ! match ) {
			return color;
		}
		const hex = match[ 1 ];
		const channel = ( i ) => parseInt( hex.slice( i, i + 2 ), 16 );
		return `rgba(${ channel( 0 ) }, ${ channel( 2 ) }, ${ channel( 4 ) }, ${ alpha })`;
	}

	function toPercentages( series ) {
		const count = series.length ? series[ 0 ].values.length : 0;
		const totals = Array.from( { length: count }, ( _, i ) =>
			series.reduce(
				( sum, s ) => sum + ( Number( s.values[ i ] ) || 0 ),
				0
			)
		);
		return series.map( ( s ) =>
			s.values.map( ( v, i ) =>
				totals[ i ] ? ( ( Number( v ) || 0 ) / totals[ i ] ) * 100 : 0
			)
		);
	}

	/**
	 * A value's share (0 to 1) of the visible data in its dataset. Slices
	 * hidden through chart.getDataVisibility are left out of the total;
	 * without that support, all values are summed.
	 *
	 * @param {number} value Slice value.
	 * @param {Object} ctx   Datalabels context.
	 * @return {number} Share between 0 and 1.
	 */
	function shareOf( value, ctx ) {
		const data = ( ctx && ctx.dataset && ctx.dataset.data ) || [];
		const chart = ctx && ctx.chart;
		const canTell = chart && typeof chart.getDataVisibility === 'function';
		const total = data.reduce(
			( sum, v, i ) =>
				canTell && ! chart.getDataVisibility( i )
					? sum
					: sum + ( Number( v ) || 0 ),
			0
		);
		return total > 0 ? ( Number( value ) || 0 ) / total : 0;
	}

	/**
	 * Builds a datalabels formatter that prints each slice's share of the
	 * visible data. Slices hidden from the legend (via
	 * chart.getDataVisibility) are left out of the total, so the others
	 * recalculate. Without visibility support, all values are summed.
	 *
	 * @param {string} locale BCP 47 locale for the number format.
	 * @return {(value: number, ctx: Object) => string} Formatter.
	 */
	function percentFormatter( locale ) {
		let nf;
		try {
			nf = new Intl.NumberFormat( locale || 'fi-FI', {
				style: 'percent',
				minimumFractionDigits: 0,
				maximumFractionDigits: 1,
			} );
		} catch {
			nf = new Intl.NumberFormat( 'fi-FI', {
				style: 'percent',
				minimumFractionDigits: 0,
				maximumFractionDigits: 1,
			} );
		}
		return ( value, ctx ) => {
			const share = shareOf( value, ctx );
			return share < MIN_PERCENT_SHARE ? '' : nf.format( share );
		};
	}

	function buildConfig( payload, env ) {
		const type = payload.type;
		const display = payload.display || {};
		const opts = payload.typeOptions || {};
		const fmt = ( v ) => formatValue( v, display.value );
		const family =
			display.font_family || ( env && env.fontFamily ) || undefined;
		const font = () => ( { family } );
		const series = payload.series || [];
		const colors = payload.pointColors || [];
		let labels = ( payload.labels || [] ).slice();
		let datasets;

		const options = {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: { display: false },
				tooltip: {
					enabled: display.tooltips !== false,
					titleFont: font(),
					bodyFont: font(),
					callbacks: {},
				},
				datalabels: {
					display: !! display.data_labels,
					font: font(),
					formatter: ( value, ctx ) => {
						const original =
							ctx && ctx.dataset && ctx.dataset.originalData;
						return fmt(
							original ? original[ ctx.dataIndex ] : value
						);
					},
				},
			},
		};
		if ( display.animation === false ) {
			options.animation = false;
		}

		if ( type === 'radial' ) {
			const max = Number( opts.max ) > 0 ? Number( opts.max ) : 100;
			const raw = series[ 0 ]
				? Number( series[ 0 ].values[ 0 ] ) || 0
				: 0;
			const value = Math.min( Math.max( raw, 0 ), max );
			datasets = [
				{
					data: [ value, Math.round( ( max - value ) * 1e6 ) / 1e6 ],
					backgroundColor: [
						colors[ 0 ] || '#1A98D1',
						opts.track_color || '#E5E7EB',
					],
					borderWidth: 0,
				},
			];
			labels = [ labels[ 0 ] || '', '' ];
			Object.assign( options, {
				circumference: 180,
				rotation: -90,
				cutout: '75%',
			} );
			options.plugins.tooltip.enabled = false;
			options.plugins.datalabels.display = false;
			options.plugins.waCenterText = { text: fmt( raw ), font: font() };
		} else if ( isSingle( type ) ) {
			datasets = [
				{
					label: series[ 0 ] ? series[ 0 ].name : '',
					data: ( series[ 0 ] ? series[ 0 ].values : [] ).map(
						Number
					),
					backgroundColor: colors.slice(),
					borderWidth: 0,
				},
			];
			const labelCfg = options.plugins.datalabels;
			labelCfg.color = '#fff';
			labelCfg.anchor = 'center';
			labelCfg.font = { family, weight: 600 };
			const percent =
				display.label_format === 'percent' &&
				PERCENT_TYPES.includes( type );
			if ( display.data_labels && percent ) {
				// Big slices are always labelled; small ones hide when they
				// collide, and tiny ones get no label. Value mode keeps the
				// plain boolean from 1.1.0.
				labelCfg.display = ( ctx ) => {
					const data =
						ctx && ctx.dataset && Array.isArray( ctx.dataset.data )
							? ctx.dataset.data
							: null;
					// Chart.js may resolve this option without a dataset context.
					if ( ! data ) {
						return true;
					}
					const share = shareOf( data[ ctx.dataIndex ], ctx );
					if ( share < MIN_PERCENT_SHARE ) {
						return false;
					}
					return share >= ALWAYS_SHOW_SHARE ? true : 'auto';
				};
			}
			if ( percent ) {
				labelCfg.formatter = percentFormatter(
					( display.value || {} ).locale
				);
			}
			if ( type === 'pie' || type === 'donut' ) {
				options.rotation = Number( opts.start_angle ) || 0;
			}
			if ( type === 'donut' ) {
				options.cutout = `${ Number( opts.hole_size ) || 65 }%`;
			}
			if ( type === 'polar' ) {
				options.scales = {
					r: { ticks: { font: font(), callback: ( v ) => fmt( v ) } },
				};
			}
			options.plugins.tooltip.callbacks.label = ( ctx ) =>
				`${ ctx.label }: ${ fmt( ctx.raw ) }`;
		} else {
			const stack100 = type === 'stacked' && !! opts.stack_100;
			const values = stack100
				? toPercentages( series )
				: series.map( ( s ) => s.values.map( Number ) );
			datasets = series.map( ( s, n ) => {
				let renderAs = CHART_TYPE[ type ];
				if ( type === 'mixed' ) {
					renderAs = s.render === 'line' ? 'line' : 'bar';
				}
				const ds = {
					label: s.name,
					data: values[ n ],
					originalData: s.values.map( Number ),
					backgroundColor: s.color,
					borderColor: s.color,
				};
				if ( type === 'mixed' ) {
					ds.type = renderAs;
				}
				if ( renderAs === 'line' || renderAs === 'radar' ) {
					ds.tension = opts.curve === 'straight' ? 0 : 0.4;
					ds.pointRadius = opts.markers === false ? 0 : 3;
					ds.borderWidth = 2;
					ds.fill = type === 'area' || type === 'radar';
					if ( ds.fill ) {
						ds.backgroundColor = withAlpha(
							s.color,
							type === 'area' ? 0.35 : 0.2
						);
					}
				} else {
					ds.barPercentage = ( Number( opts.bar_width ) || 70 ) / 100;
				}
				return ds;
			} );

			if ( type === 'radar' ) {
				options.scales = {
					r: {
						pointLabels: { font: font() },
						ticks: { font: font(), callback: ( v ) => fmt( v ) },
					},
				};
			} else {
				const stacked = type === 'stacked';
				const valueAxis = {
					stacked,
					ticks: {
						font: font(),
						callback: stack100
							? ( v ) => `${ v }%`
							: ( v ) => fmt( v ),
					},
				};
				if ( stack100 ) {
					valueAxis.max = 100;
				}
				const categoryAxis = { stacked, ticks: { font: font() } };
				if ( type === 'bar-horizontal' ) {
					options.indexAxis = 'y';
					options.scales = { x: valueAxis, y: categoryAxis };
				} else {
					options.scales = { x: categoryAxis, y: valueAxis };
				}
			}
			options.plugins.tooltip.callbacks.label = ( ctx ) =>
				`${ ctx.dataset.label }: ${ fmt( ctx.dataset.originalData[ ctx.dataIndex ] ) }`;
		}

		deepMerge( options, payload.libraryOverrides || {} );
		return {
			type: CHART_TYPE[ type ] || 'pie',
			data: { labels, datasets },
			options,
		};
	}

	function legendItems( payload ) {
		const display = payload.display || {};
		const legend = display.legend || {};
		const series = payload.series || [];
		const colors = payload.pointColors || [];
		if ( isSingle( payload.type ) ) {
			const values = series[ 0 ] ? series[ 0 ].values : [];
			const labels =
				payload.type === 'radial'
					? ( payload.labels || [] ).slice( 0, 1 )
					: payload.labels || [];
			return labels.map( ( label, index ) => ( {
				kind: 'data',
				index,
				label,
				color: colors[ index ],
				value: legend.show_values
					? formatValue( values[ index ], display.value )
					: '',
			} ) );
		}
		return series.map( ( s, index ) => ( {
			kind: 'dataset',
			index,
			label: s.name,
			color: s.color,
			value: '',
		} ) );
	}

	/**
	 * Chart elements a legend item points at, for highlighting.
	 * A data item (a slice) maps to that point in every dataset long
	 * enough to have it; a dataset item maps to all of its points.
	 *
	 * @param {Object}   item  Legend item from legendItems().
	 * @param {number[]} sizes Number of points in each dataset.
	 * @return {Object[]} { datasetIndex, index } pairs.
	 */
	function highlightTargets( item, sizes ) {
		if ( item.kind === 'data' ) {
			return sizes
				.map( ( size, datasetIndex ) =>
					item.index < size
						? { datasetIndex, index: item.index }
						: null
				)
				.filter( Boolean );
		}
		const size = sizes[ item.index ] || 0;
		return Array.from( { length: size }, ( unused, index ) => ( {
			datasetIndex: item.index,
			index,
		} ) );
	}

	return {
		formatValue,
		deepMerge,
		withAlpha,
		buildConfig,
		legendItems,
		highlightTargets,
	};
} );
