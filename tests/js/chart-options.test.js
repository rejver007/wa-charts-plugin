/* global describe, it, expect */
const {
	formatValue,
	deepMerge,
	withAlpha,
	buildConfig,
	legendItems,
} = require( '../../assets/front/chart-options' );

const nbsp = ( s ) => s.replace( /[\u00a0\u202f]/g, ' ' );

const display = ( overrides = {} ) => ( {
	heading: '',
	subheading: '',
	layout: 'left',
	height: 320,
	legend: { position: 'bottom', columns: 1, show_values: true },
	value: { prefix: '', suffix: 'M€', decimals: 2, locale: 'en-US' },
	data_labels: false,
	label_format: 'value',
	tooltips: true,
	animation: true,
	font_family: '',
	...overrides,
} );

const pie = ( overrides = {} ) => ( {
	type: 'pie',
	labels: [ 'UVA', 'Aalto' ],
	series: [ { name: '', color: null, render: null, values: [ 3.65, 1.6 ] } ],
	pointColors: [ '#1A98D1', '#08588C' ],
	display: display(),
	typeOptions: { start_angle: 0 },
	libraryOverrides: {},
	...overrides,
} );

const multi = ( type, overrides = {} ) => ( {
	type,
	labels: [ 'Q1', 'Q2' ],
	series: [
		{ name: 'A', color: '#111111', render: null, values: [ 1, 3 ] },
		{ name: 'B', color: '#222222', render: null, values: [ 3, 1 ] },
	],
	pointColors: [],
	display: display(),
	typeOptions: {},
	libraryOverrides: {},
	...overrides,
} );

describe( 'formatValue', () => {
	it( 'drops trailing zeros and adds affixes verbatim', () => {
		expect(
			formatValue( 1.6, { locale: 'en-US', decimals: 2, suffix: 'M€' } )
		).toBe( '1.6M€' );
		expect(
			formatValue( 3.6512, { locale: 'en-US', decimals: 2, prefix: '$' } )
		).toBe( '$3.65' );
	} );
	it( 'uses the locale', () => {
		expect(
			nbsp( formatValue( 1234.5, { locale: 'fi-FI', decimals: 1 } ) )
		).toBe( '1 234,5' );
	} );
	it( 'returns empty for non-numbers', () => {
		expect( formatValue( null, {} ) ).toBe( '' );
		expect( formatValue( 'abc', {} ) ).toBe( '' );
	} );
} );

describe( 'deepMerge', () => {
	it( 'merges nested objects and ignores prototype keys', () => {
		const target = { a: { b: 1, c: 2 } };
		deepMerge( target, JSON.parse( '{"a":{"c":3},"__proto__":{"x":1}}' ) );
		expect( target ).toEqual( { a: { b: 1, c: 3 } } );
		expect( {}.x ).toBeUndefined();
	} );
} );

describe( 'withAlpha', () => {
	it( 'converts hex to rgba', () => {
		expect( withAlpha( '#1A98D1', 0.5 ) ).toBe( 'rgba(26, 152, 209, 0.5)' );
		expect( withAlpha( 'red', 0.5 ) ).toBe( 'red' );
	} );
} );

describe( 'buildConfig', () => {
	it( 'builds a pie', () => {
		const c = buildConfig( pie( { typeOptions: { start_angle: 90 } } ) );
		expect( c.type ).toBe( 'pie' );
		expect( c.data.labels ).toEqual( [ 'UVA', 'Aalto' ] );
		expect( c.data.datasets[ 0 ].data ).toEqual( [ 3.65, 1.6 ] );
		expect( c.data.datasets[ 0 ].backgroundColor ).toEqual( [
			'#1A98D1',
			'#08588C',
		] );
		expect( c.options.rotation ).toBe( 90 );
		expect( c.options.plugins.legend.display ).toBe( false );
		expect(
			c.options.plugins.tooltip.callbacks.label( {
				label: 'UVA',
				raw: 3.65,
			} )
		).toBe( 'UVA: 3.65M€' );
	} );

	it( 'builds a donut with a hole', () => {
		const c = buildConfig(
			pie( { type: 'donut', typeOptions: { hole_size: 50 } } )
		);
		expect( c.type ).toBe( 'doughnut' );
		expect( c.options.cutout ).toBe( '50%' );
	} );

	it( 'builds a gauge from the first value', () => {
		const c = buildConfig(
			pie( {
				type: 'radial',
				typeOptions: { max: 10, track_color: '#EEEEEE' },
			} )
		);
		expect( c.type ).toBe( 'doughnut' );
		expect( c.data.datasets[ 0 ].data ).toEqual( [ 3.65, 6.35 ] );
		expect( c.data.datasets[ 0 ].backgroundColor ).toEqual( [
			'#1A98D1',
			'#EEEEEE',
		] );
		expect( c.options.circumference ).toBe( 180 );
		expect( c.options.plugins.waCenterText.text ).toBe( '3.65M€' );
	} );

	it( 'draws only the first series of a pie', () => {
		const c = buildConfig(
			pie( {
				series: [
					{ name: 'A', color: null, render: null, values: [ 1, 2 ] },
					{
						name: 'B',
						color: '#222222',
						render: null,
						values: [ 5, 6 ],
					},
				],
			} )
		);
		expect( c.data.datasets ).toHaveLength( 1 );
		expect( c.data.datasets[ 0 ].data ).toEqual( [ 1, 2 ] );
	} );

	it( 'uses only the first row of a gauge with many rows', () => {
		const c = buildConfig(
			pie( {
				type: 'radial',
				labels: [ 'One', 'Two', 'Three' ],
				series: [
					{
						name: '',
						color: null,
						render: null,
						values: [ 4, 8, 9 ],
					},
				],
				pointColors: [ '#1A98D1', '#08588C', '#113C56' ],
				typeOptions: { max: 10 },
			} )
		);
		expect( c.data.datasets ).toHaveLength( 1 );
		expect( c.data.datasets[ 0 ].data ).toEqual( [ 4, 6 ] );
		expect( c.data.labels ).toEqual( [ 'One', '' ] );
		expect( c.options.plugins.waCenterText.text ).toBe( '4M€' );
	} );

	it( 'ignores point colours for multi types', () => {
		const c = buildConfig(
			multi( 'bar', { pointColors: [ '#abcdef', '#fedcba' ] } )
		);
		expect( c.data.datasets.map( ( d ) => d.backgroundColor ) ).toEqual( [
			'#111111',
			'#222222',
		] );
	} );

	it( 'lists only the first row of a gauge in the legend', () => {
		const items = legendItems(
			pie( {
				type: 'radial',
				labels: [ 'One', 'Two', 'Three' ],
				series: [
					{
						name: '',
						color: null,
						render: null,
						values: [ 4, 8, 9 ],
					},
				],
				pointColors: [ '#1A98D1', '#08588C', '#113C56' ],
			} )
		);
		expect( items ).toHaveLength( 1 );
		expect( items[ 0 ].label ).toBe( 'One' );
	} );

	it( 'uses only series 0 values in a pie legend', () => {
		const items = legendItems(
			pie( {
				series: [
					{ name: 'A', color: null, render: null, values: [ 1, 2 ] },
					{
						name: 'B',
						color: '#222222',
						render: null,
						values: [ 5, 6 ],
					},
				],
			} )
		);
		expect( items ).toHaveLength( 2 );
		expect( items.map( ( i ) => i.value ) ).toEqual( [ '1M€', '2M€' ] );
	} );

	it( 'builds horizontal bars', () => {
		const c = buildConfig(
			multi( 'bar-horizontal', { typeOptions: { bar_width: 50 } } )
		);
		expect( c.type ).toBe( 'bar' );
		expect( c.options.indexAxis ).toBe( 'y' );
		expect( c.data.datasets[ 0 ].barPercentage ).toBe( 0.5 );
		expect( c.options.scales.x.ticks.callback( 2 ) ).toBe( '2M€' );
	} );

	it( 'stacks to 100 % and keeps original values for tooltips', () => {
		const c = buildConfig(
			multi( 'stacked', { typeOptions: { stack_100: true } } )
		);
		expect( c.data.datasets[ 0 ].data ).toEqual( [ 25, 75 ] );
		expect( c.options.scales.y.max ).toBe( 100 );
		expect( c.options.scales.y.stacked ).toBe( true );
		const label = c.options.plugins.tooltip.callbacks.label( {
			dataset: c.data.datasets[ 0 ],
			dataIndex: 0,
		} );
		expect( label ).toBe( 'A: 1M€' );
	} );

	it( 'mixes bars and lines', () => {
		const payload = multi( 'mixed', {
			typeOptions: { curve: 'straight', markers: false },
		} );
		payload.series[ 1 ].render = 'line';
		const c = buildConfig( payload );
		expect( c.type ).toBe( 'bar' );
		expect( c.data.datasets.map( ( d ) => d.type ) ).toEqual( [
			'bar',
			'line',
		] );
		expect( c.data.datasets[ 1 ].tension ).toBe( 0 );
		expect( c.data.datasets[ 1 ].pointRadius ).toBe( 0 );
	} );

	it( 'fills areas and radar', () => {
		const area = buildConfig( multi( 'area' ) );
		expect( area.type ).toBe( 'line' );
		expect( area.data.datasets[ 0 ].fill ).toBe( true );
		expect( area.data.datasets[ 0 ].backgroundColor ).toBe(
			'rgba(17, 17, 17, 0.35)'
		);
		const radar = buildConfig( multi( 'radar' ) );
		expect( radar.type ).toBe( 'radar' );
		expect( radar.options.scales.r ).toBeDefined();
	} );

	it( 'does not share font objects between options', () => {
		const c = buildConfig(
			multi( 'bar', {
				libraryOverrides: {
					plugins: { tooltip: { bodyFont: { size: 20 } } },
				},
			} )
		);
		expect( c.options.plugins.tooltip.bodyFont.size ).toBe( 20 );
		expect( c.options.scales.y.ticks.font.size ).toBeUndefined();
	} );

	it( 'honours animation, font and overrides', () => {
		const c = buildConfig(
			pie( {
				display: display( { animation: false } ),
				libraryOverrides: { plugins: { tooltip: { enabled: false } } },
			} ),
			{ fontFamily: 'IBM Plex Sans' }
		);
		expect( c.options.animation ).toBe( false );
		expect( c.options.plugins.tooltip.enabled ).toBe( false );
		expect( c.options.plugins.tooltip.bodyFont.family ).toBe(
			'IBM Plex Sans'
		);
	} );
} );

describe( 'legendItems', () => {
	it( 'lists slices with values for single charts', () => {
		expect( legendItems( pie() ) ).toEqual( [
			{
				kind: 'data',
				index: 0,
				label: 'UVA',
				color: '#1A98D1',
				value: '3.65M€',
			},
			{
				kind: 'data',
				index: 1,
				label: 'Aalto',
				color: '#08588C',
				value: '1.6M€',
			},
		] );
	} );
	it( 'hides values when disabled and lists series for multi charts', () => {
		const p = pie( {
			display: display( {
				legend: { position: 'bottom', columns: 1, show_values: false },
			} ),
		} );
		expect( legendItems( p )[ 0 ].value ).toBe( '' );
		expect(
			legendItems( multi( 'bar' ) ).map( ( i ) => [ i.kind, i.label ] )
		).toEqual( [
			[ 'dataset', 'A' ],
			[ 'dataset', 'B' ],
		] );
	} );
} );

describe( 'percentage data labels', () => {
	const values = [ 10, 5, 6, 8, 4, 2, 1 ];
	const percentPie = ( type = 'pie', overrides = {} ) =>
		pie( {
			type,
			labels: values.map( ( _, i ) => `L${ i }` ),
			series: [ { name: '', color: null, render: null, values } ],
			pointColors: values.map( () => '#336699' ),
			display: display( {
				data_labels: true,
				label_format: 'percent',
				...overrides,
			} ),
		} );
	const ctx = ( dataIndex, visible = () => true ) => ( {
		dataIndex,
		dataset: { data: values },
		chart: { getDataVisibility: visible },
	} );
	const labels = ( payload ) =>
		buildConfig( payload, {} ).options.plugins.datalabels;

	it( 'shows each slice as a share of the total', () => {
		const dl = labels( percentPie() );
		expect( dl.formatter( 10, ctx( 0 ) ) ).toBe( '27.8%' );
		expect( dl.formatter( 5, ctx( 1 ) ) ).toBe( '13.9%' );
	} );
	it( 'ignores the value prefix and suffix for percentages', () => {
		const dl = labels( percentPie() );
		expect( dl.formatter( 10, ctx( 0 ) ) ).not.toContain( 'M€' );
	} );
	it( 'uses the locale for the percent format', () => {
		const dl = labels(
			percentPie( 'pie', {
				value: {
					prefix: '',
					suffix: '',
					decimals: 2,
					locale: 'fi-FI',
				},
			} )
		);
		expect( nbsp( dl.formatter( 10, ctx( 0 ) ) ) ).toBe( '27,8 %' );
	} );
	it( 'recalculates when a slice is hidden', () => {
		const dl = labels( percentPie() );
		const hideLast = ( i ) => i !== 6;
		expect( dl.formatter( 10, ctx( 0, hideLast ) ) ).toBe( '28.6%' );
	} );
	it( 'falls back to all values without visibility support', () => {
		const dl = labels( percentPie() );
		const bare = { dataIndex: 0, dataset: { data: values } };
		expect( dl.formatter( 10, bare ) ).toBe( '27.8%' );
	} );
	it( 'returns no label for slices under 3 percent', () => {
		const dl = labels( percentPie() );
		expect( dl.formatter( 1, ctx( 6 ) ) ).toBe( '' );
		expect( dl.formatter( 2, ctx( 5 ) ) ).toBe( '5.6%' );
	} );
	it( 'works for donut and polar charts', () => {
		[ 'donut', 'polar' ].forEach( ( type ) => {
			expect(
				labels( percentPie( type ) ).formatter( 10, ctx( 0 ) )
			).toBe( '27.8%' );
		} );
	} );
	it( 'keeps value labels with prefix and suffix by default', () => {
		const dl = labels( percentPie( 'pie', { label_format: 'value' } ) );
		expect( dl.formatter( 10, ctx( 0 ) ) ).toBe( '10M€' );
	} );
	it( 'styles single-shape labels white and semi-bold, centred', () => {
		[ 'value', 'percent' ].forEach( ( format ) => {
			const dl = labels( percentPie( 'pie', { label_format: format } ) );
			expect( dl.color ).toBe( '#fff' );
			expect( dl.font.weight ).toBe( 600 );
			expect( dl.anchor ).toBe( 'center' );
		} );
	} );
	it( 'falls back to values on multi-shape charts', () => {
		const p = multi( 'bar', {
			display: display( { data_labels: true, label_format: 'percent' } ),
		} );
		const dl = labels( p );
		expect( dl.formatter( 3, { dataIndex: 0, dataset: {} } ) ).toBe(
			'3M€'
		);
		expect( dl.color ).toBeUndefined();
	} );
	it( 'keeps radial labels off', () => {
		const p = percentPie( 'radial' );
		expect( labels( p ).display ).toBe( false );
	} );
	it( 'lets libraryOverrides win', () => {
		const p = percentPie( 'pie', {} );
		p.libraryOverrides = { plugins: { datalabels: { color: '#000' } } };
		expect( labels( p ).color ).toBe( '#000' );
	} );
} );

describe( 'datalabels collision handling', () => {
	const values = [ 10, 5, 6, 8, 4, 2, 1 ];
	const cfg = ( format ) =>
		buildConfig(
			pie( {
				labels: values.map( ( _, i ) => `L${ i }` ),
				series: [ { name: '', color: null, render: null, values } ],
				display: display( {
					data_labels: true,
					label_format: format,
				} ),
			} ),
			{}
		).options.plugins.datalabels;
	const ctx = ( dataIndex ) => ( {
		dataIndex,
		dataset: { data: values },
		chart: { getDataVisibility: () => true },
	} );

	it( 'uses three bands in percent mode', () => {
		const dl = cfg( 'percent' );
		expect( dl.display( ctx( 0 ) ) ).toBe( true );
		expect( dl.display( ctx( 1 ) ) ).toBe( true );
		expect( dl.display( ctx( 5 ) ) ).toBe( 'auto' );
		expect( dl.display( ctx( 6 ) ) ).toBe( false );
	} );
	it( 'keeps the plain boolean display in value mode', () => {
		expect( cfg( 'value' ).display ).toBe( true );
	} );
	it( 'does not throw when the dataset has no data', () => {
		const dl = cfg( 'percent' );
		expect( dl.display( { dataIndex: 0, dataset: {} } ) ).toBe( true );
		expect( dl.display( {} ) ).toBe( true );
	} );
	it( 'keeps labels off when data labels are off', () => {
		const dl = buildConfig( pie(), {} ).options.plugins.datalabels;
		expect( dl.display ).toBe( false );
	} );
} );
