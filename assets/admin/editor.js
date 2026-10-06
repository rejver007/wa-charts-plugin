( function ( $, wp, settings ) {
	'use strict';

	const input = document.getElementById( 'wa-chart-config-input' );
	if ( ! settings || ! input ) {
		return;
	}

	const { __, _n, sprintf } = wp.i18n;
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

	function svgIcon( markup ) {
		const holder = el( 'span', {
			class: 'wa-charts-icon',
			'aria-hidden': 'true',
		} );
		// Hard-coded markup from the constants above, never user data.
		holder.innerHTML = markup;
		return holder;
	}

	// Static type thumbnails (48×36), keyed by type ID.
	const THUMBS = {
		pie: '<svg viewBox="0 0 48 36" width="48" height="36" focusable="false"><circle cx="24" cy="18" r="15" fill="#d3e0e6"/><path d="M24 18V3a15 15 0 0 1 13 22.5z" fill="#1a98d1"/><path d="M24 18l13 7.5A15 15 0 0 1 12 27z" fill="#08588c"/><path d="M24 18 12 27a15 15 0 0 1-3-12z" fill="#a1bf23"/></svg>',
		donut: '<svg viewBox="0 0 48 36" width="48" height="36" focusable="false"><circle cx="24" cy="18" r="12" fill="none" stroke="#d3e0e6" stroke-width="7"/><circle cx="24" cy="18" r="12" fill="none" stroke="#1a98d1" stroke-width="7" stroke-dasharray="32 76" transform="rotate(-90 24 18)"/><circle cx="24" cy="18" r="12" fill="none" stroke="#08588c" stroke-width="7" stroke-dasharray="20 76" stroke-dashoffset="-32" transform="rotate(-90 24 18)"/></svg>',
		polar: '<svg viewBox="0 0 48 36" width="48" height="36" focusable="false"><path d="M24 18V2a16 16 0 0 1 16 16z" fill="#1a98d1"/><path d="M24 18h10a10 10 0 0 1-10 10z" fill="#08588c"/><path d="M24 18v13A13 13 0 0 1 11 18z" fill="#a1bf23"/><path d="M24 18H16a8 8 0 0 1 8-8z" fill="#113c56"/></svg>',
		radial: '<svg viewBox="0 0 48 36" width="48" height="36" focusable="false"><path d="M8 30a16 16 0 0 1 32 0" fill="none" stroke="#e0e0e0" stroke-width="6"/><path d="M8 30a16 16 0 0 1 22-14.8" fill="none" stroke="#1a98d1" stroke-width="6"/></svg>',
		bar: '<svg viewBox="0 0 48 36" width="48" height="36" focusable="false"><rect x="8" y="14" width="7" height="18" fill="#1a98d1"/><rect x="20" y="6" width="7" height="26" fill="#08588c"/><rect x="32" y="20" width="7" height="12" fill="#a1bf23"/></svg>',
		'bar-horizontal':
			'<svg viewBox="0 0 48 36" width="48" height="36" focusable="false"><rect x="6" y="6" width="30" height="6" fill="#1a98d1"/><rect x="6" y="15" width="38" height="6" fill="#08588c"/><rect x="6" y="24" width="20" height="6" fill="#a1bf23"/></svg>',
		stacked:
			'<svg viewBox="0 0 48 36" width="48" height="36" focusable="false"><rect x="8" y="18" width="8" height="14" fill="#1a98d1"/><rect x="8" y="10" width="8" height="8" fill="#a1bf23"/><rect x="20" y="14" width="8" height="18" fill="#1a98d1"/><rect x="20" y="4" width="8" height="10" fill="#a1bf23"/><rect x="32" y="22" width="8" height="10" fill="#1a98d1"/><rect x="32" y="15" width="8" height="7" fill="#a1bf23"/></svg>',
		line: '<svg viewBox="0 0 48 36" width="48" height="36" focusable="false"><polyline points="4,28 14,18 24,22 34,8 44,12" fill="none" stroke="#1a98d1" stroke-width="2.5"/><polyline points="4,32 14,26 24,28 34,20 44,22" fill="none" stroke="#a1bf23" stroke-width="2.5"/></svg>',
		area: '<svg viewBox="0 0 48 36" width="48" height="36" focusable="false"><path d="M4 28 14 18 24 22 34 8 44 12V34H4z" fill="#1a98d1" fill-opacity=".35"/><polyline points="4,28 14,18 24,22 34,8 44,12" fill="none" stroke="#1a98d1" stroke-width="2.5"/></svg>',
		mixed: '<svg viewBox="0 0 48 36" width="48" height="36" focusable="false"><rect x="8" y="16" width="7" height="16" fill="#d3e0e6"/><rect x="20" y="10" width="7" height="22" fill="#d3e0e6"/><rect x="32" y="20" width="7" height="12" fill="#d3e0e6"/><polyline points="11,14 23,6 35,16" fill="none" stroke="#08588c" stroke-width="2.5"/></svg>',
		radar: '<svg viewBox="0 0 48 36" width="48" height="36" focusable="false"><polygon points="24,3 39,14 33,32 15,32 9,14" fill="none" stroke="#e0e0e0"/><polygon points="24,8 34,15 30,27 18,29 13,15" fill="#1a98d1" fill-opacity=".35" stroke="#1a98d1" stroke-width="2"/></svg>',
	};
	const INFO_ICON =
		'<svg width="16" height="16" viewBox="0 0 16 16" focusable="false"><circle cx="8" cy="8" r="7" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8 7v4M8 4.5v.5" stroke="currentColor" stroke-width="1.5"/></svg>';
	const PLUS_ICON =
		'<svg width="14" height="14" viewBox="0 0 14 14" focusable="false"><path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="1.6"/></svg>';

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
		const titles = {
			single: __( 'Parts of a whole', 'wa-charts' ),
			multi: __( 'Compare and show trends', 'wa-charts' ),
		};
		State.groupTypes( types ).forEach( ( group ) => {
			const titleId = `wa-charts-type-group-${ group.shape }`;
			root.appendChild(
				el( 'div', { class: 'wa-charts-type-group' }, [
					el( 'h3', {
						id: titleId,
						class: 'wa-charts-type-group__title',
						text: titles[ group.shape ],
					} ),
					el(
						'div',
						{
							class: 'wa-charts-types',
							role: 'group',
							'aria-labelledby': titleId,
						},
						group.ids.map( ( id ) => typeCard( id ) )
					),
				] )
			);
		} );
		root.appendChild(
			el(
				'div',
				{
					id: 'wa-charts-type-notice',
					class: 'wa-charts-notice',
					role: 'status',
					hidden: true,
				},
				[
					svgIcon( INFO_ICON ),
					el( 'div', { class: 'wa-charts-notice__text' } ),
				]
			)
		);
	}

	function typeCard( id ) {
		const active = id === state.type;
		return el(
			'button',
			{
				type: 'button',
				class: `wa-charts-type${ active ? ' is-active' : '' }`,
				'data-type': id,
				'aria-pressed': active ? 'true' : 'false',
				onclick: () => chooseType( id ),
			},
			[
				Object.prototype.hasOwnProperty.call( THUMBS, id )
					? svgIcon( THUMBS[ id ] )
					: icon( types[ id ].icon ),
				el( 'span', {
					class: 'wa-charts-type__label',
					text: types[ id ].label,
				} ),
			]
		);
	}

	function chooseType( id ) {
		if ( id === state.type ) {
			return;
		}
		const result = State.switchType( state, id, types, palette );
		update( result.state, { types: true, data: true, display: true } );
		const notice = document.getElementById( 'wa-charts-type-notice' );
		const messages = [];
		if ( result.hiddenRows > 0 ) {
			messages.push(
				sprintf(
					/* translators: %s: chart type name, for example "Gauge". */
					__(
						'%s shows only the first row. The other rows are kept and come back if you choose another chart type.',
						'wa-charts'
					),
					types[ id ].label
				)
			);
		}
		if ( result.hiddenSeries > 0 ) {
			messages.push(
				__(
					'This chart type shows only the first series. The other series are kept and come back if you choose another chart type.',
					'wa-charts'
				)
			);
		}
		const text = notice.querySelector( '.wa-charts-notice__text' );
		messages.forEach( ( message ) =>
			text.appendChild( el( 'p', { text: message } ) )
		);
		notice.hidden = ! messages.length;
		// The picker was rebuilt, so put keyboard focus back on the chosen card.
		const card = Array.from(
			document.querySelectorAll(
				'#wa-charts-type-picker .wa-charts-type'
			)
		).find( ( node ) => node.getAttribute( 'data-type' ) === id );
		if ( card ) {
			card.focus();
		}
	}

	/* Data table ------------------------------------------------------- */

	function colorInput( color, onChange, label ) {
		const field = el( 'input', {
			type: 'text',
			class: 'wa-charts-color',
			value: color || '',
		} );
		field.waOnChange = onChange;
		field.waLabel = label || '';
		return field;
	}

	// Accessible name of the swatch that wpColorPicker puts in front of a field.
	function setSwatchLabel( field, label ) {
		field.waLabel = label;
		$( field )
			.closest( '.wp-picker-container' )
			.find( '.wp-color-result' )
			.attr( 'aria-label', label );
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
				if ( field.waLabel ) {
					setSwatchLabel( field, field.waLabel );
				}
				if ( $( field ).closest( '.wa-charts-table' ).length ) {
					$( field )
						.closest( '.wp-picker-container' )
						.find( '.wp-color-result' )
						.on( 'click', () =>
							window.requestAnimationFrame( placeOpenPickers )
						);
				}
			} );
	}

	// Places an open table colour picker next to its swatch with fixed
	// coordinates, inside the viewport, flipping above when there is no room.
	function placePicker( container ) {
		const trigger = container.querySelector( '.wp-color-result' );
		const wrap = container.querySelector( '.wp-picker-input-wrap' );
		const holder = container.querySelector( '.wp-picker-holder' );
		if ( ! trigger || ! wrap || ! holder ) {
			return;
		}
		const rect = trigger.getBoundingClientRect();
		const gap = 6;
		const width = 257;
		const wrapHeight = wrap.offsetHeight || 44;
		const total = wrapHeight + holder.offsetHeight;
		const maxLeft = document.documentElement.clientWidth - width - 8;
		const left = Math.max( 8, Math.min( rect.left - 8, maxLeft ) );
		let top = rect.bottom + gap;
		if (
			top + total > window.innerHeight - 8 &&
			rect.top - gap - total >= 8
		) {
			top = rect.top - gap - total;
		}
		wrap.style.left = `${ left }px`;
		holder.style.left = `${ left }px`;
		wrap.style.top = `${ top }px`;
		holder.style.top = `${ top + wrapHeight }px`;
	}

	function placeOpenPickers() {
		document
			.querySelectorAll(
				'#wa-charts-data .wa-charts-table .wp-picker-active'
			)
			.forEach( placePicker );
	}

	const colourOf = ( name ) =>
		sprintf(
			/* translators: %s: row label or series name, for example "UVA". */
			__( 'Colour of %s', 'wa-charts' ),
			name
		);
	const rowName = ( label, row ) =>
		label ||
		sprintf(
			/* translators: %d: row number. */ __( 'row %d', 'wa-charts' ),
			row + 1
		);

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
			class: 'wa-charts-cell wa-charts-value',
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
				class: 'wa-charts-remove',
				'aria-label': label,
				title: label,
				onclick: onClick,
			},
			[ icon( 'no-alt' ) ]
		);
	}

	// Two to four mutually exclusive buttons. `onChoose` gets the chosen key.
	function segmented( choices, current, onChoose, attrs = {} ) {
		const group = el( 'div', { role: 'group', ...attrs } );
		Object.keys( choices ).forEach( ( key ) => {
			group.appendChild(
				el( 'button', {
					type: 'button',
					'data-value': key,
					'aria-pressed':
						String( key ) === String( current ) ? 'true' : 'false',
					text: choices[ key ],
					onclick: () => {
						group
							.querySelectorAll( 'button' )
							.forEach( ( node ) =>
								node.setAttribute(
									'aria-pressed',
									node.getAttribute( 'data-value' ) ===
										String( key )
										? 'true'
										: 'false'
								)
							);
						onChoose( key );
					},
				} )
			);
		} );
		return group;
	}

	function seriesHeader( series, n ) {
		const fallback = () => seriesName( n );
		const color = colorInput(
			series.color,
			( value ) =>
				update( State.setSeriesField( state, n, 'color', value ) ),
			colourOf( series.name || fallback() )
		);
		const name = el( 'input', {
			type: 'text',
			class: 'wa-charts-cell wa-charts-series-name',
			value: series.name,
			maxlength: 200,
			placeholder: fallback(),
			'aria-label': __( 'Series name', 'wa-charts' ),
		} );
		const showAs = ( text ) =>
			sprintf(
				/* translators: %s: series name, for example "Budget". */
				__( 'Show %s as', 'wa-charts' ),
				text
			);
		let renderGroup = null;
		name.addEventListener( 'input', () => {
			setSwatchLabel( color, colourOf( name.value || fallback() ) );
			if ( renderGroup ) {
				renderGroup.setAttribute(
					'aria-label',
					showAs( name.value || fallback() )
				);
			}
			update( State.setSeriesField( state, n, 'name', name.value ) );
		} );
		const parts = [ color, name ];
		if ( state.type === 'mixed' ) {
			renderGroup = segmented(
				{
					bar: __( 'Bars', 'wa-charts' ),
					line: __( 'Line', 'wa-charts' ),
				},
				series.render === 'line' ? 'line' : 'bar',
				( value ) =>
					update( State.setSeriesField( state, n, 'render', value ) ),
				{
					class: 'wa-charts-seg wa-charts-seg--mini',
					'aria-label': showAs( series.name || fallback() ),
				}
			);
			parts.push( renderGroup );
		}
		if ( state.series.length > 1 ) {
			parts.push(
				removeButton( __( 'Remove series', 'wa-charts' ), () =>
					update( State.removeSeries( state, n ), { data: true } )
				)
			);
		}
		return [ el( 'div', { class: 'wa-charts-series-head' }, parts ) ];
	}

	function dataRow( label, row, single, visibleRows ) {
		const labelInput = el( 'input', {
			type: 'text',
			class: 'wa-charts-cell wa-charts-label',
			value: label,
			maxlength: 200,
			'aria-label': __( 'Label', 'wa-charts' ),
		} );
		let swatch = null;
		labelInput.addEventListener( 'input', () => {
			if ( swatch ) {
				setSwatchLabel(
					swatch,
					colourOf( rowName( labelInput.value, row ) )
				);
			}
			update( State.setLabel( state, row, labelInput.value ) );
		} );

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
		state.series
			.slice( 0, single ? 1 : undefined )
			.forEach( ( series, n ) => {
				cells.push(
					el( 'td', {}, [
						valueInput( series.values[ row ], ( raw ) =>
							update( setCellValue( row, n, raw ) )
						),
					] )
				);
			} );
		if ( single ) {
			swatch = colorInput(
				state.point_colors[ row ],
				( color ) => update( State.setPointColor( state, row, color ) ),
				colourOf( rowName( label, row ) )
			);
			cells.push(
				el( 'td', { class: 'wa-charts-colour-cell' }, [ swatch ] )
			);
		}
		cells.push(
			el( 'td', { class: 'wa-charts-remove-cell' }, [
				removeButton( __( 'Remove row', 'wa-charts' ), () =>
					update( State.removeRow( state, row ), { data: true } )
				),
			] )
		);
		const unused = row >= visibleRows;
		return el(
			'tr',
			{
				'data-row': row,
				class: unused ? 'wa-charts-row--unused' : null,
				title: unused
					? __( 'Not shown by this chart type', 'wa-charts' )
					: null,
			},
			cells
		);
	}

	function button( text, onclick, variant = 'ghost', withIcon = false ) {
		const node = el( 'button', {
			type: 'button',
			class: `wa-charts-btn wa-charts-btn--${ variant }`,
			onclick,
		} );
		if ( withIcon ) {
			node.appendChild( svgIcon( PLUS_ICON ) );
		}
		node.appendChild( document.createTextNode( text ) );
		return node;
	}

	function toolbar( single ) {
		return el( 'div', { class: 'wa-charts-toolbar' }, [
			button(
				__( 'Add row', 'wa-charts' ),
				() =>
					update(
						State.addRow(
							state,
							types,
							palette,
							settings.limits.labels
						),
						{ data: true }
					),
				'outline',
				true
			),
			single
				? null
				: button(
						__( 'Add series', 'wa-charts' ),
						() =>
							update(
								State.addSeries(
									state,
									palette,
									settings.limits.series,
									seriesName
								),
								{ data: true }
							),
						'outline',
						true
					),
			button( __( 'Paste from Excel or CSV', 'wa-charts' ), togglePaste ),
			button( __( 'Use palette colours', 'wa-charts' ), () =>
				update( State.fillPalette( state, types, palette ), {
					data: true,
				} )
			),
		] );
	}

	function renderData() {
		const root = document.getElementById( 'wa-charts-data-editor' );
		root.textContent = '';
		const single = State.shapeOf( types, state.type ) === 'single';
		const shown = State.usage( state, types );

		const head = [
			el( 'th', { class: 'wa-charts-handle-col' } ),
			el( 'th', {
				class: 'wa-charts-label-col',
				text: single
					? __( 'Label', 'wa-charts' )
					: __( 'Category', 'wa-charts' ),
			} ),
		];
		if ( single ) {
			head.push(
				el( 'th', {
					class: 'wa-charts-value-col',
					text: __( 'Value', 'wa-charts' ),
				} ),
				el( 'th', {
					class: 'wa-charts-colour-col',
					text: __( 'Colour', 'wa-charts' ),
				} )
			);
		} else {
			state.series.forEach( ( series, n ) =>
				head.push(
					el(
						'th',
						{ class: 'wa-charts-series-col' },
						seriesHeader( series, n )
					)
				)
			);
		}
		head.push( el( 'th', { class: 'wa-charts-remove-col' } ) );

		const body = el(
			'tbody',
			{},
			state.labels.map( ( label, row ) =>
				dataRow( label, row, single, shown.visibleRows )
			)
		);
		// Wide tables scroll inside the box; open colour pickers are placed
		// with fixed positioning (placePicker) so the scroller cannot clip them.
		const scroller = el( 'div', { class: 'wa-charts-table-scroll' }, [
			el(
				'table',
				{
					class: `wa-charts-table wa-charts-table--${
						single ? 'single' : 'multi'
					}`,
				},
				[ el( 'thead', {}, [ el( 'tr', {}, head ) ] ), body ]
			),
		] );
		scroller.addEventListener( 'scroll', placeOpenPickers, {
			passive: true,
		} );
		root.appendChild( scroller );
		const hiddenSeries = state.series.length - shown.visibleSeries;
		if ( hiddenSeries > 0 ) {
			root.appendChild(
				el( 'p', {
					class: 'description wa-charts-series-note',
					text: sprintf(
						/* translators: %d: number of series. */
						_n(
							'%d more series is kept but not shown by this chart type.',
							'%d more series are kept but not shown by this chart type.',
							hiddenSeries,
							'wa-charts'
						),
						hiddenSeries
					),
				} )
			);
		}
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
			'aria-label': __( 'Data to paste', 'wa-charts' ),
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
					seriesName,
					settings.limits.labels
				),
				{ data: true }
			);
		const replace = button(
			__( 'Replace data', 'wa-charts' ),
			() => apply( 'replace' ),
			'primary'
		);
		const append = button(
			__( 'Append rows', 'wa-charts' ),
			() => apply( 'append' ),
			'outline'
		);
		replace.disabled = true;
		append.disabled = true;

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
				el( 'table', { class: 'wa-charts-paste-table' }, [
					headRow,
					el( 'tbody', {}, rows ),
				] )
			);
		} );

		root.appendChild(
			el( 'div', { class: 'wa-charts-paste' }, [
				area,
				preview,
				el( 'div', { class: 'wa-charts-toolbar' }, [
					replace,
					append,
				] ),
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

	function displaySections() {
		return [
			{
				title: __( 'Text', 'wa-charts' ),
				fields: [
					{
						path: 'display.heading',
						type: 'text',
						label: __( 'Heading', 'wa-charts' ),
						help: __(
							'You can use <br>, <strong> and <em>.',
							'wa-charts'
						),
					},
					{
						path: 'display.subheading',
						type: 'text',
						label: __( 'Subheading', 'wa-charts' ),
						help: __( 'For example the total.', 'wa-charts' ),
					},
				],
			},
			{
				title: __( 'Layout', 'wa-charts' ),
				fields: [
					{
						path: 'display.layout',
						type: 'enum',
						wide: true,
						label: __( 'Chart position', 'wa-charts' ),
						choices: {
							left: __( 'Chart left, text right', 'wa-charts' ),
							right: __( 'Chart right, text left', 'wa-charts' ),
							top: __( 'Chart above text', 'wa-charts' ),
						},
					},
					{
						path: 'display.legend.position',
						type: 'enum',
						label: __( 'Legend', 'wa-charts' ),
						choices: {
							bottom: __( 'Below', 'wa-charts' ),
							right: __( 'Right', 'wa-charts' ),
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
						path: 'display.height',
						type: 'int',
						min: 100,
						max: 2000,
						label: __( 'Height (px)', 'wa-charts' ),
					},
					{
						path: 'display.font_family',
						type: 'text',
						label: __( 'Font', 'wa-charts' ),
						help: __(
							'Leave empty to use the theme font.',
							'wa-charts'
						),
					},
				],
			},
			{
				title: __( 'Numbers', 'wa-charts' ),
				fields: [
					{
						path: 'display.value.prefix',
						type: 'text',
						label: __( 'Value prefix', 'wa-charts' ),
					},
					{
						path: 'display.value.suffix',
						type: 'text',
						label: __( 'Value suffix', 'wa-charts' ),
						help: __( 'For example M€.', 'wa-charts' ),
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
						select: true,
						label: __( 'Number format', 'wa-charts' ),
						choices: {
							'fi-FI': '1 234,5 (fi)',
							'en-US': '1,234.5 (en)',
							'sv-SE': '1 234,5 (sv)',
							'de-DE': '1.234,5 (de)',
						},
					},
				],
			},
			{
				title: __( 'Behaviour', 'wa-charts' ),
				fields: [
					{
						path: 'display.legend.show_values',
						type: 'bool',
						label: __( 'Show values in the legend', 'wa-charts' ),
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
				],
			},
		];
	}

	function toggleField( spec, id, value ) {
		const control = el( 'input', {
			type: 'checkbox',
			role: 'switch',
			id,
		} );
		control.checked = !! value;
		control.addEventListener( 'change', () =>
			update( setPath( state, spec.path, control.checked ) )
		);
		return el( 'label', { class: 'wa-charts-toggle', for: id }, [
			control,
			el( 'span', {
				class: 'wa-charts-toggle__track',
				'aria-hidden': 'true',
			} ),
			el( 'span', { text: spec.label } ),
		] );
	}

	function numberControl( spec, id, value ) {
		const control = el( 'input', {
			type: 'number',
			id,
			class: 'wa-charts-input',
			min: spec.min,
			max: spec.max,
			step: spec.type === 'int' ? '1' : 'any',
			value,
		} );
		control.addEventListener( 'input', () => {
			if ( control.value !== '' ) {
				update( setPath( state, spec.path, Number( control.value ) ) );
			}
		} );
		return control;
	}

	function displayField( spec ) {
		const id = `wa-charts-field-${ spec.path.replace( /\./g, '-' ) }`;
		const value = getPath( state, spec.path );
		if ( spec.type === 'bool' ) {
			return toggleField( spec, id, value );
		}

		const unit = State.unitFor( spec.path );
		const label = unit ? State.stripUnit( spec.label ) : spec.label;
		const labelId = `${ id }-label`;
		const describedBy = [];
		let control;
		let labelNode = el( 'label', {
			id: labelId,
			for: id,
			class: 'wa-charts-field__label',
			text: label,
		} );

		const choices = spec.choices || {};
		if (
			spec.type === 'enum' &&
			! spec.select &&
			Object.keys( choices ).length <= 4
		) {
			labelNode = el( 'span', {
				id: labelId,
				class: 'wa-charts-field__label',
				text: label,
			} );
			control = segmented(
				choices,
				value,
				( key ) =>
					update(
						setPath(
							state,
							spec.path,
							spec.number ? Number( key ) : key
						)
					),
				{ class: 'wa-charts-seg', 'aria-labelledby': labelId }
			);
		} else if ( spec.type === 'enum' ) {
			control = el(
				'select',
				{ id, class: 'wa-charts-select' },
				Object.keys( choices ).map( ( key ) =>
					option( key, choices[ key ], value )
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
			control = numberControl( spec, id, value );
		} else {
			control = el( 'input', {
				type: 'text',
				id,
				class: 'wa-charts-input',
				value: value || '',
			} );
			control.addEventListener( 'input', () =>
				update( setPath( state, spec.path, control.value ) )
			);
		}

		let unitNode = null;
		if ( unit ) {
			unitNode = el( 'span', {
				id: `${ id }-unit`,
				class: 'wa-charts-unit__addon',
				text: unit,
			} );
			describedBy.push( `${ id }-unit` );
		}
		let helpNode = null;
		if ( spec.help ) {
			helpNode = el( 'div', {
				id: `${ id }-help`,
				class: 'wa-charts-help',
				text: spec.help,
			} );
			describedBy.push( `${ id }-help` );
		}
		if ( describedBy.length ) {
			control.setAttribute( 'aria-describedby', describedBy.join( ' ' ) );
		}

		return el(
			'div',
			{
				class: `wa-charts-field${
					spec.wide ? ' wa-charts-field--wide' : ''
				}`,
			},
			[
				labelNode,
				unitNode
					? el( 'div', { class: 'wa-charts-unit' }, [
							control,
							unitNode,
						] )
					: control,
				helpNode,
			]
		);
	}

	function displaySection( title, specs ) {
		const fields = specs.filter( ( spec ) => spec.type !== 'bool' );
		const toggles = specs.filter( ( spec ) => spec.type === 'bool' );
		return el( 'div', { class: 'wa-charts-section' }, [
			el( 'h3', { class: 'wa-charts-section__title', text: title } ),
			fields.length
				? el(
						'div',
						{ class: 'wa-charts-fields' },
						fields.map( displayField )
					)
				: null,
			toggles.length
				? el(
						'div',
						{ class: 'wa-charts-toggles' },
						toggles.map( displayField )
					)
				: null,
		] );
	}

	function renderDisplay() {
		const root = document.getElementById( 'wa-charts-display-editor' );
		root.textContent = '';
		displaySections().forEach( ( section ) =>
			root.appendChild( displaySection( section.title, section.fields ) )
		);

		const options = types[ state.type ].options || {};
		const keys = Object.keys( options );
		if ( keys.length ) {
			root.appendChild(
				displaySection(
					sprintf(
						/* translators: %s: chart type name. */
						__( '%s options', 'wa-charts' ),
						types[ state.type ].label
					),
					keys.map( ( key ) => ( {
						...options[ key ],
						path: `type_options.${ key }`,
					} ) )
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

	window.addEventListener( 'scroll', placeOpenPickers, { passive: true } );
	window.addEventListener( 'resize', placeOpenPickers );

	input.value = JSON.stringify( state );
	renderTypes();
	renderData();
	renderDisplay();
	runPreview();
} )( window.jQuery, window.wp, window.waChartsEditor );
