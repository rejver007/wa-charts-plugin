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

	/**
	 * Legend behaviour that highlights a slice or series instead of hiding
	 * it: hovering or focusing an item highlights it for as long as the
	 * pointer or focus stays, and clicking pins the highlight until the item
	 * (or another one) is clicked.
	 *
	 * @param {Object} chart   Chart.js instance.
	 * @param {Object} payload Chart payload.
	 * @return {Object} Highlighter with a bind( item, li, button ) method.
	 */
	function legendHighlighter( chart, payload ) {
		const tooltips =
			! payload.display || payload.display.tooltips !== false;
		let pinned = null;

		const activate = ( item ) => {
			const sizes = chart.data.datasets.map( ( ds ) => ds.data.length );
			const targets = item
				? WaCharts.highlightTargets( item, sizes )
				: [];
			chart.setActiveElements( targets );
			if ( chart.tooltip ) {
				const first = targets[ 0 ];
				const element =
					first &&
					chart.getDatasetMeta( first.datasetIndex ).data[
						first.index
					];
				chart.tooltip.setActiveElements(
					tooltips && element ? targets : [],
					element ? element.tooltipPosition() : { x: 0, y: 0 }
				);
			}
			chart.update();
		};

		const entries = [];
		const mark = () => {
			entries.forEach( ( entry ) => {
				const on = pinned === entry.item;
				entry.li.classList.toggle( 'is-active', on );
				entry.button.setAttribute(
					'aria-pressed',
					on ? 'true' : 'false'
				);
			} );
		};

		// Chart.js clears hover state when the pointer leaves the canvas;
		// bring a pinned highlight back afterwards.
		if ( chart.canvas ) {
			chart.canvas.addEventListener( 'mouseleave', () => {
				if ( pinned ) {
					window.setTimeout( () => activate( pinned ), 0 );
				}
			} );
		}

		return {
			bind( item, li, button ) {
				entries.push( { item, li, button } );
				const show = () => activate( item );
				const restore = () => activate( pinned );
				button.addEventListener( 'mouseenter', show );
				button.addEventListener( 'focus', show );
				button.addEventListener( 'mouseleave', restore );
				button.addEventListener( 'blur', restore );
				button.addEventListener( 'click', () => {
					pinned = pinned === item ? null : item;
					mark();
					activate( pinned || item );
				} );
			},
		};
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
		const highlight =
			legend.on_click === 'highlight'
				? legendHighlighter( chart, payload )
				: null;
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
			} else if ( highlight ) {
				button.setAttribute( 'aria-pressed', 'false' );
				highlight.bind( item, li, button );
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
