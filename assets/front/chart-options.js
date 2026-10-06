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

	return { formatValue, deepMerge, withAlpha, buildConfig, legendItems };
} );
