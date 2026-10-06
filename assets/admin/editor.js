( function ( $, wp, settings ) {
	'use strict';

	const input = document.getElementById( 'wa-chart-config-input' );
	if ( ! settings || ! input ) {
		return;
	}

	const { __, sprintf } = wp.i18n;
	const State = window.WaChartsState;
	const Paste = window.WaChartsPaste;
	const types = settings.types;
	const palette = settings.palette;
	const seriesName = ( n ) =>
		sprintf(
			/* translators: %d: series number. */ __(
				'Series %d',
				'wa-charts'
			),
			n + 1
		);

	let state = State.normalize( JSON.parse( input.value ), types, palette );
	let undoState = null;
	let previewTimer = null;
	let previewRequest = 0;

	function el( tag, attrs = {}, children = [] ) {
		const node = document.createElement( tag );
		Object.keys( attrs ).forEach( ( key ) => {
			const value = attrs[ key ];
			if ( key === 'text' ) {
				node.textContent = value;
			} else if ( key.startsWith( 'on' ) ) {
				node.addEventListener( key.slice( 2 ), value );
			} else if ( value === true ) {
				node.setAttribute( key, '' );
			} else if (
				value !== false &&
				value !== null &&
				value !== undefined
			) {
				node.setAttribute( key, String( value ) );
			}
		} );
		children
			.filter( Boolean )
			.forEach( ( child ) => node.appendChild( child ) );
		return node;
	}

	function option( value, label, current ) {
		const node = el( 'option', { value, text: label } );
		node.selected = String( value ) === String( current );
		return node;
	}

	function icon( name ) {
		return el( 'span', {
			class: `dashicons dashicons-${ name }`,
			'aria-hidden': 'true',
		} );
	}

	function update( next, rerender = {} ) {
		state = next;
		input.value = JSON.stringify( state );
		if ( rerender.types ) {
			renderTypes();
		}
		if ( rerender.data ) {
			renderData();
		}
		if ( rerender.display ) {
			renderDisplay();
		}
		schedulePreview();
	}

	/* Type picker ------------------------------------------------------ */

	function renderTypes() {
		const root = document.getElementById( 'wa-charts-type-picker' );
		root.textContent = '';
		Object.keys( types ).forEach( ( id ) => {
			const active = id === state.type;
			root.appendChild(
				el(
					'button',
					{
						type: 'button',
						class: `wa-charts-type${ active ? ' is-active' : '' }`,
						'aria-pressed': active ? 'true' : 'false',
						onclick: () => chooseType( id ),
					},
					[
						icon( types[ id ].icon ),
						el( 'span', { text: types[ id ].label } ),
					]
				)
			);
		} );
		root.appendChild(
			el( 'div', {
				id: 'wa-charts-type-warning',
				class: 'notice notice-warning inline',
				hidden: true,
			} )
		);
	}

	function chooseType( id ) {
		if ( id === state.type ) {
			return;
		}
		const before = state;
		const result = State.switchType( state, id, types, palette );
		update( result.state, { types: true, data: true, display: true } );
		if ( ! result.droppedSeries && ! result.droppedRows ) {
			undoState = null;
			return;
		}
		undoState = before;
		const warning = document.getElementById( 'wa-charts-type-warning' );
		warning.appendChild(
			el( 'p', {
				text: __(
					'This chart type cannot show all of your data. The extra series or rows will be removed when you save.',
					'wa-charts'
				),
			} )
		);
		warning.appendChild(
			el( 'p', {}, [
				el( 'button', {
					type: 'button',
					class: 'button',
					text: __( 'Undo type change', 'wa-charts' ),
					onclick: () => {
						const previous = undoState;
						undoState = null;
						update( previous, {
							types: true,
							data: true,
							display: true,
						} );
					},
				} ),
			] )
		);
		warning.hidden = false;
	}

	/* Data table ------------------------------------------------------- */

	function colorInput( color, onChange ) {
		const field = el( 'input', {
			type: 'text',
			class: 'wa-charts-color',
			value: color || '',
		} );
		field.waOnChange = onChange;
		return field;
	}

	function initColorPickers( root ) {
		$( root )
			.find( '.wa-charts-color' )
			.each( function () {
				const field = this;
				$( field ).wpColorPicker( {
					change: ( event, ui ) =>
						field.waOnChange( ui.color.toString() ),
					clear: () => field.waOnChange( null ),
				} );
			} );
	}

	// State stores null for unreadable input; keep the raw text so the server
	// can report it when saving instead of silently turning it into 0.
	function setCellValue( row, seriesIndex, raw ) {
		const next = State.setValue( state, row, seriesIndex, raw );
		const parsed = Paste.parseNumber( raw );
		if ( parsed !== null && Number.isNaN( parsed ) ) {
			next.series[ seriesIndex ].values[ row ] = String( raw ).trim();
		}
		return next;
	}

	function valueInput( value, onChange ) {
		const field = el( 'input', {
			type: 'text',
			inputmode: 'decimal',
			class: 'wa-charts-value',
			value: value === null || value === undefined ? '' : value,
			'aria-label': __( 'Value', 'wa-charts' ),
		} );
		field.classList.toggle(
			'is-invalid',
			typeof value === 'string' &&
				Number.isNaN( Paste.parseNumber( value ) )
		);
		field.addEventListener( 'input', () => {
			const parsed = Paste.parseNumber( field.value );
			field.classList.toggle(
				'is-invalid',
				parsed !== null && Number.isNaN( parsed )
			);
			onChange( field.value );
		} );
		return field;
	}

	function removeButton( label, onClick ) {
		return el(
			'button',
			{
				type: 'button',
				class: 'button-link wa-charts-remove',
				'aria-label': label,
				onclick: onClick,
			},
			[ icon( 'no-alt' ) ]
		);
	}

	function seriesHeader( series, n ) {
		const name = el( 'input', {
			type: 'text',
			class: 'wa-charts-series-name',
			value: series.name,
			maxlength: 200,
			'aria-label': __( 'Series name', 'wa-charts' ),
		} );
		name.addEventListener( 'input', () =>
			update( State.setSeriesField( state, n, 'name', name.value ) )
		);
		const parts = [
			name,
			colorInput( series.color, ( color ) =>
				update( State.setSeriesField( state, n, 'color', color ) )
			),
		];
		if ( state.type === 'mixed' ) {
			const render = el(
				'select',
				{ 'aria-label': __( 'Show as', 'wa-charts' ) },
				[
					option( 'bar', __( 'Bars', 'wa-charts' ), series.render ),
					option( 'line', __( 'Line', 'wa-charts' ), series.render ),
				]
			);
			render.addEventListener( 'change', () =>
				update(
					State.setSeriesField( state, n, 'render', render.value )
				)
			);
			parts.push( render );
		}
		if ( state.series.length > 1 ) {
			parts.push(
				removeButton( __( 'Remove series', 'wa-charts' ), () =>
					update( State.removeSeries( state, n ), { data: true } )
				)
			);
		}
		return parts;
	}

	function dataRow( label, row, single ) {
		const labelInput = el( 'input', {
			type: 'text',
			class: 'wa-charts-label',
			value: label,
			maxlength: 200,
			'aria-label': __( 'Label', 'wa-charts' ),
		} );
		labelInput.addEventListener( 'input', () =>
			update( State.setLabel( state, row, labelInput.value ) )
		);

		const cells = [
			el(
				'td',
				{
					class: 'wa-charts-handle',
					title: __( 'Drag to reorder', 'wa-charts' ),
				},
				[ icon( 'menu' ) ]
			),
			el( 'td', {}, [ labelInput ] ),
		];
		state.series.forEach( ( series, n ) => {
			cells.push(
				el( 'td', {}, [
					valueInput( series.values[ row ], ( raw ) =>
						update( setCellValue( row, n, raw ) )
					),
				] )
			);
		} );
		if ( single ) {
			cells.push(
				el( 'td', {}, [
					colorInput( state.point_colors[ row ], ( color ) =>
						update( State.setPointColor( state, row, color ) )
					),
				] )
			);
		}
		cells.push(
			el( 'td', {}, [
				removeButton( __( 'Remove row', 'wa-charts' ), () =>
					update( State.removeRow( state, row ), { data: true } )
				),
			] )
		);
		return el( 'tr', { 'data-row': row }, cells );
	}

	function toolbar( single ) {
		const button = ( text, onclick, primary = false ) =>
			el( 'button', {
				type: 'button',
				class: primary ? 'button button-primary' : 'button',
				text,
				onclick,
			} );
		return el( 'p', { class: 'wa-charts-toolbar' }, [
			button( __( 'Add row', 'wa-charts' ), () =>
				update( State.addRow( state, types, palette ), { data: true } )
			),
			single
				? null
				: button( __( 'Add series', 'wa-charts' ), () =>
						update(
							State.addSeries(
								state,
								palette,
								settings.limits.series,
								seriesName
							),
							{ data: true }
						)
					),
			button( __( 'Fill colours from palette', 'wa-charts' ), () =>
				update( State.fillPalette( state, types, palette ), {
					data: true,
				} )
			),
			button( __( 'Paste from Excel / CSV', 'wa-charts' ), togglePaste ),
		] );
	}

	function renderData() {
		const root = document.getElementById( 'wa-charts-data-editor' );
		root.textContent = '';
		const single = State.shapeOf( types, state.type ) === 'single';

		const head = [
			el( 'th', { class: 'wa-charts-handle-col' } ),
			el( 'th', {
				text: single
					? __( 'Label', 'wa-charts' )
					: __( 'Category', 'wa-charts' ),
			} ),
		];
		if ( single ) {
			head.push(
				el( 'th', { text: __( 'Value', 'wa-charts' ) } ),
				el( 'th', { text: __( 'Colour', 'wa-charts' ) } )
			);
		} else {
			state.series.forEach( ( series, n ) =>
				head.push(
					el(
						'th',
						{ class: 'wa-charts-series-head' },
						seriesHeader( series, n )
					)
				)
			);
		}
		head.push( el( 'th', { class: 'wa-charts-remove-col' } ) );

		const body = el(
			'tbody',
			{},
			state.labels.map( ( label, row ) => dataRow( label, row, single ) )
		);
		root.appendChild(
			el( 'table', { class: 'widefat wa-charts-table' }, [
				el( 'thead', {}, [ el( 'tr', {}, head ) ] ),
				body,
			] )
		);
		root.appendChild( toolbar( single ) );
		initColorPickers( root );

		$( body ).sortable( {
			handle: '.wa-charts-handle',
			axis: 'y',
			update: ( event, ui ) => {
				const from = Number( ui.item.attr( 'data-row' ) );
				const to = ui.item.index();
				window.setTimeout(
					() =>
						update( State.moveRow( state, from, to ), {
							data: true,
						} ),
					0
				);
			},
		} );
	}

	function togglePaste() {
		const root = document.getElementById( 'wa-charts-data-editor' );
		const existing = root.querySelector( '.wa-charts-paste' );
		if ( existing ) {
			existing.remove();
			return;
		}
		let parsed = null;
		const area = el( 'textarea', {
			rows: 8,
			class: 'large-text code',
			placeholder: __(
				'Paste cells from Excel, or rows separated by commas or semicolons. The first column is the label.',
				'wa-charts'
			),
		} );
		const preview = el( 'div', { class: 'wa-charts-paste-preview' } );
		const apply = ( mode ) =>
			update(
				State.applyPaste(
					state,
					parsed,
					mode,
					types,
					palette,
					settings.limits.series,
					seriesName
				),
				{ data: true }
			);
		const replace = el( 'button', {
			type: 'button',
			class: 'button button-primary',
			text: __( 'Replace data', 'wa-charts' ),
			disabled: true,
			onclick: () => apply( 'replace' ),
		} );
		const append = el( 'button', {
			type: 'button',
			class: 'button',
			text: __( 'Append rows', 'wa-charts' ),
			disabled: true,
			onclick: () => apply( 'append' ),
		} );

		area.addEventListener( 'input', () => {
			parsed = Paste.parse( area.value );
			preview.textContent = '';
			replace.disabled = ! parsed.rows.length;
			append.disabled = ! parsed.rows.length;
			if ( ! parsed.rows.length ) {
				return;
			}
			preview.appendChild(
				el( 'p', {
					text: sprintf(
						/* translators: 1: number of rows, 2: number of unreadable values. */
						__(
							'%1$d rows found, %2$d values could not be read.',
							'wa-charts'
						),
						parsed.rows.length,
						parsed.invalid
					),
				} )
			);
			const headRow = parsed.seriesNames
				? el( 'thead', {}, [
						el(
							'tr',
							{},
							[ el( 'th' ) ].concat(
								parsed.seriesNames.map( ( n ) =>
									el( 'th', { text: n } )
								)
							)
						),
					] )
				: null;
			const rows = parsed.rows.slice( 0, 10 ).map( ( r ) =>
				el(
					'tr',
					{},
					[ el( 'th', { text: r.label } ) ].concat(
						r.values.map( ( v ) =>
							el( 'td', {
								text: v === null ? '–' : String( v ),
							} )
						)
					)
				)
			);
			preview.appendChild(
				el( 'table', { class: 'widefat striped' }, [
					headRow,
					el( 'tbody', {}, rows ),
				] )
			);
		} );

		root.appendChild(
			el( 'div', { class: 'wa-charts-paste' }, [
				area,
				preview,
				el( 'p', { class: 'wa-charts-toolbar' }, [ replace, append ] ),
			] )
		);
		area.focus();
	}

	/* Display settings --------------------------------------------------- */

	const getPath = ( obj, path ) =>
		path.split( '.' ).reduce( ( o, k ) => ( o ? o[ k ] : undefined ), obj );

	function setPath( obj, path, value ) {
		const next = JSON.parse( JSON.stringify( obj ) );
		const keys = path.split( '.' );
		let o = next;
		keys.slice( 0, -1 ).forEach( ( k ) => {
			if (
				typeof o[ k ] !== 'object' ||
				o[ k ] === null ||
				Array.isArray( o[ k ] )
			) {
				o[ k ] = {};
			}
			o = o[ k ];
		} );
		o[ keys[ keys.length - 1 ] ] = value;
		return next;
	}

	function displayFields() {
		return [
			{
				path: 'display.heading',
				type: 'text',
				label: __( 'Heading', 'wa-charts' ),
				help: __( 'You can use <br>, <strong> and <em>.', 'wa-charts' ),
			},
			{
				path: 'display.subheading',
				type: 'text',
				label: __( 'Subheading (for example the total)', 'wa-charts' ),
			},
			{
				path: 'display.layout',
				type: 'enum',
				label: __( 'Layout', 'wa-charts' ),
				choices: {
					left: __( 'Chart left, text right', 'wa-charts' ),
					right: __( 'Chart right, text left', 'wa-charts' ),
					top: __( 'Chart above text', 'wa-charts' ),
				},
			},
			{
				path: 'display.height',
				type: 'int',
				min: 100,
				max: 2000,
				label: __( 'Height (px)', 'wa-charts' ),
			},
			{
				path: 'display.legend.position',
				type: 'enum',
				label: __( 'Legend', 'wa-charts' ),
				choices: {
					bottom: __( 'Below the chart', 'wa-charts' ),
					right: __( 'Right of the chart', 'wa-charts' ),
					none: __( 'Hidden', 'wa-charts' ),
				},
			},
			{
				path: 'display.legend.columns',
				type: 'enum',
				number: true,
				label: __( 'Legend columns', 'wa-charts' ),
				choices: { 1: '1', 2: '2' },
			},
			{
				path: 'display.legend.show_values',
				type: 'bool',
				label: __( 'Show values in the legend', 'wa-charts' ),
			},
			{
				path: 'display.value.prefix',
				type: 'text',
				label: __( 'Value prefix', 'wa-charts' ),
			},
			{
				path: 'display.value.suffix',
				type: 'text',
				label: __( 'Value suffix (for example M€)', 'wa-charts' ),
			},
			{
				path: 'display.value.decimals',
				type: 'int',
				min: 0,
				max: 6,
				label: __( 'Maximum decimals', 'wa-charts' ),
			},
			{
				path: 'display.value.locale',
				type: 'enum',
				label: __( 'Number format', 'wa-charts' ),
				choices: {
					'fi-FI': '1 234,5 (fi)',
					'en-US': '1,234.5 (en)',
					'sv-SE': '1 234,5 (sv)',
					'de-DE': '1.234,5 (de)',
				},
			},
			{
				path: 'display.data_labels',
				type: 'bool',
				label: __( 'Show values on the chart', 'wa-charts' ),
			},
			{
				path: 'display.tooltips',
				type: 'bool',
				label: __( 'Tooltips', 'wa-charts' ),
			},
			{
				path: 'display.animation',
				type: 'bool',
				label: __( 'Animation', 'wa-charts' ),
			},
			{
				path: 'display.font_family',
				type: 'text',
				label: __( 'Font (empty = theme font)', 'wa-charts' ),
			},
		];
	}

	function displayField( spec ) {
		const id = `wa-charts-field-${ spec.path.replace( /\./g, '-' ) }`;
		const value = getPath( state, spec.path );
		let control;

		if ( spec.type === 'bool' ) {
			control = el( 'input', { type: 'checkbox', id } );
			control.checked = !! value;
			control.addEventListener( 'change', () =>
				update( setPath( state, spec.path, control.checked ) )
			);
			return el(
				'p',
				{ class: 'wa-charts-field wa-charts-field--bool' },
				[ control, el( 'label', { for: id, text: spec.label } ) ]
			);
		}

		if ( spec.type === 'enum' ) {
			control = el(
				'select',
				{ id },
				Object.keys( spec.choices ).map( ( key ) =>
					option( key, spec.choices[ key ], value )
				)
			);
			control.addEventListener( 'change', () =>
				update(
					setPath(
						state,
						spec.path,
						spec.number ? Number( control.value ) : control.value
					)
				)
			);
		} else if ( spec.type === 'color' ) {
			control = colorInput( value, ( color ) =>
				update( setPath( state, spec.path, color ) )
			);
			control.id = id;
		} else if ( spec.type === 'int' || spec.type === 'float' ) {
			control = el( 'input', {
				type: 'number',
				id,
				min: spec.min,
				max: spec.max,
				step: spec.type === 'int' ? '1' : 'any',
				value,
			} );
			control.addEventListener( 'input', () => {
				if ( control.value !== '' ) {
					update(
						setPath( state, spec.path, Number( control.value ) )
					);
				}
			} );
		} else {
			control = el( 'input', {
				type: 'text',
				id,
				class: 'regular-text',
				value: value || '',
			} );
			control.addEventListener( 'input', () =>
				update( setPath( state, spec.path, control.value ) )
			);
		}

		return el( 'p', { class: 'wa-charts-field' }, [
			el( 'label', { for: id, text: spec.label } ),
			control,
			spec.help
				? el( 'span', { class: 'description', text: spec.help } )
				: null,
		] );
	}

	function renderDisplay() {
		const root = document.getElementById( 'wa-charts-display-editor' );
		root.textContent = '';
		root.appendChild(
			el(
				'div',
				{ class: 'wa-charts-fields' },
				displayFields().map( displayField )
			)
		);

		const options = types[ state.type ].options || {};
		const keys = Object.keys( options );
		if ( keys.length ) {
			root.appendChild(
				el( 'h4', {
					text: sprintf(
						/* translators: %s: chart type name. */
						__( '%s options', 'wa-charts' ),
						types[ state.type ].label
					),
				} )
			);
			root.appendChild(
				el(
					'div',
					{ class: 'wa-charts-fields' },
					keys.map( ( key ) =>
						displayField( {
							...options[ key ],
							path: `type_options.${ key }`,
						} )
					)
				)
			);
		}
		initColorPickers( root );
	}

	/* Preview ------------------------------------------------------------ */

	function schedulePreview() {
		window.clearTimeout( previewTimer );
		previewTimer = window.setTimeout( runPreview, 400 );
	}

	function runPreview() {
		const container = document.getElementById( 'wa-charts-preview' );
		const errors = document.getElementById( 'wa-charts-preview-errors' );
		if ( ! container || ! errors ) {
			return;
		}
		previewRequest += 1;
		const request = previewRequest;
		wp.apiFetch( {
			path: settings.previewPath,
			method: 'POST',
			data: { config: state, post_id: settings.postId },
		} )
			.then( ( response ) => {
				if ( request !== previewRequest ) {
					return;
				}
				errors.textContent = '';
				( response.errors || [] ).forEach( ( message ) =>
					errors.appendChild( el( 'li', { text: message } ) )
				);
				window.WaCharts.renderInto( container, response.payload, {
					height: 220,
				} );
			} )
			.catch( ( error ) => {
				if ( request !== previewRequest ) {
					return;
				}
				errors.textContent = '';
				errors.appendChild(
					el( 'li', {
						text:
							error && error.message
								? error.message
								: __(
										'The preview could not be loaded.',
										'wa-charts'
									),
					} )
				);
			} );
	}

	input.value = JSON.stringify( state );
	renderTypes();
	renderData();
	renderDisplay();
	runPreview();
} )( window.jQuery, window.wp, window.waChartsEditor );
