( function ( window, document ) {
	'use strict';

	const WaCharts = ( window.WaCharts = window.WaCharts || {} );

	const centerText = {
		id: 'waCenterText',
		afterDraw( chart, args, pluginOptions ) {
			const meta = chart.getDatasetMeta( 0 );
			const arc = meta && meta.data ? meta.data[ 0 ] : null;
			if ( ! pluginOptions || ! pluginOptions.text || ! arc ) {
				return;
			}
			const size = Math.max( 12, Math.round( arc.outerRadius * 0.28 ) );
			const family =
				( pluginOptions.font && pluginOptions.font.family ) ||
				'sans-serif';
			const { ctx } = chart;
			ctx.save();
			ctx.font = `600 ${ size }px ${ family }`;
			ctx.fillStyle = pluginOptions.color || '#1d2327';
			ctx.textAlign = 'center';
			ctx.fillText( pluginOptions.text, arc.x, arc.y - size * 0.15 );
			ctx.restore();
		},
	};

	function pluginsFor( payload ) {
		const plugins = [ centerText ];
		if (
			window.ChartDataLabels &&
			payload.display &&
			payload.display.data_labels
		) {
			plugins.push( window.ChartDataLabels );
		}
		return plugins;
	}

	function renderLegend( root, chart, payload ) {
		const list = root.querySelector( '.wa-chart__legend' );
		if ( ! list ) {
			return;
		}
		list.textContent = '';
		const legend = ( payload.display && payload.display.legend ) || {};
		if ( legend.position === 'none' ) {
			list.hidden = true;
			return;
		}
		WaCharts.legendItems( payload ).forEach( ( item ) => {
			const li = document.createElement( 'li' );
			li.className = 'wa-chart__legend-item';
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'wa-chart__legend-button';
			button.setAttribute( 'aria-pressed', 'true' );

			const swatch = document.createElement( 'span' );
			swatch.className = 'wa-chart__swatch';
			swatch.setAttribute( 'aria-hidden', 'true' );
			swatch.style.backgroundColor = item.color || '';
			const label = document.createElement( 'span' );
			label.className = 'wa-chart__legend-label';
			label.textContent = item.label;
			button.append( swatch, label );
			if ( item.value ) {
				const value = document.createElement( 'span' );
				value.className = 'wa-chart__legend-value';
				value.textContent = item.value;
				button.append( value );
			}

			if ( payload.type === 'radial' ) {
				button.disabled = true;
			} else {
				button.addEventListener( 'click', () => {
					if ( item.kind === 'data' ) {
						chart.toggleDataVisibility( item.index );
					} else {
						chart.setDatasetVisibility(
							item.index,
							! chart.isDatasetVisible( item.index )
						);
					}
					chart.update();
					const visible =
						item.kind === 'data'
							? chart.getDataVisibility( item.index )
							: chart.isDatasetVisible( item.index );
					button.setAttribute(
						'aria-pressed',
						visible ? 'true' : 'false'
					);
					li.classList.toggle( 'is-hidden', ! visible );
				} );
			}
			li.append( button );
			list.append( li );
		} );
		list.hidden = false;
	}

	function createChart( root, payload ) {
		const canvas = root.querySelector( '.wa-chart__canvas canvas' );
		if ( ! canvas || ! window.Chart ) {
			throw new Error( 'Chart.js or the canvas is missing' );
		}
		const config = WaCharts.buildConfig( payload, {
			fontFamily: window.getComputedStyle( root ).fontFamily,
		} );
		config.plugins = pluginsFor( payload );
		const chart = new window.Chart( canvas, config );
		renderLegend( root, chart, payload );
		return chart;
	}

	function fallback( figure, error ) {
		const table = figure.querySelector( '.wa-chart__table' );
		if ( table ) {
			table.classList.remove( 'screen-reader-text' );
		}
		figure.classList.add( 'wa-chart--error' );
		if ( window.console && error ) {
			window.console.error( '[wa-charts]', error );
		}
	}

	function draw( figure ) {
		if ( figure.waChart ) {
			return figure.waChart;
		}
		try {
			const node = figure.querySelector( '.wa-chart__config' );
			const payload = node ? JSON.parse( node.textContent ) : null;
			if ( ! payload ) {
				throw new Error( 'Chart data is missing' );
			}
			figure.waChart = createChart( figure, payload );
			figure.classList.add( 'wa-chart--ready' );
			return figure.waChart;
		} catch ( error ) {
			fallback( figure, error );
			return null;
		}
	}

	function renderInto( container, payload, overrides = {} ) {
		if ( container.waChart ) {
			container.waChart.destroy();
			container.waChart = null;
		}
		const display = payload.display || {};
		const legend = display.legend || {};
		container.className = `wa-chart wa-chart--preview wa-chart--${ payload.type } wa-chart--legend-${ legend.position || 'bottom' }`;
		container.style.setProperty(
			'--wa-chart-height',
			`${ overrides.height || display.height || 320 }px`
		);
		container.style.setProperty(
			'--wa-chart-legend-columns',
			String( legend.columns || 1 )
		);
		container.innerHTML =
			'<div class="wa-chart__plot"><div class="wa-chart__canvas"><canvas></canvas></div><ul class="wa-chart__legend" hidden></ul></div>';
		container.waChart = createChart( container, payload );
		return container.waChart;
	}

	function init( scope ) {
		const figures = ( scope || document ).querySelectorAll(
			'figure.wa-chart:not([data-wa-init])'
		);
		const observer =
			'IntersectionObserver' in window
				? new window.IntersectionObserver(
						( entries ) => {
							entries.forEach( ( entry ) => {
								if ( entry.isIntersecting ) {
									observer.unobserve( entry.target );
									draw( entry.target );
								}
							} );
						},
						{ rootMargin: '200px' }
					)
				: null;
		figures.forEach( ( figure ) => {
			figure.setAttribute( 'data-wa-init', '' );
			if ( observer ) {
				observer.observe( figure );
			} else {
				draw( figure );
			}
		} );
	}

	Object.assign( WaCharts, { init, draw, renderInto } );

	let elementorHooked = false;
	const hookElementor = () => {
		const hooks =
			window.elementorFrontend && window.elementorFrontend.hooks;
		if ( elementorHooked || ! hooks ) {
			return;
		}
		elementorHooked = true;
		hooks.addAction(
			'frontend/element_ready/shortcode.default',
			( $scope ) => init( $scope[ 0 ] )
		);
	};
	window.addEventListener( 'elementor/frontend/init', hookElementor );
	if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', hookElementor );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => init() );
	} else {
		init();
	}
} )( window, document );
