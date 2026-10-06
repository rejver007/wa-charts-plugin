/* eslint-disable no-console */
const fs = require( 'fs' );
const path = require( 'path' );

const root = path.join( __dirname, '..' );
const dest = path.join( root, 'assets', 'vendor' );
fs.mkdirSync( dest, { recursive: true } );

function pkg( name ) {
	const dir = path.join( root, 'node_modules', name );
	const json = JSON.parse(
		fs.readFileSync( path.join( dir, 'package.json' ), 'utf8' )
	);
	return { dir, version: json.version };
}

function copyFirst( dir, candidates, target ) {
	const found = candidates.find( ( file ) =>
		fs.existsSync( path.join( dir, file ) )
	);
	if ( ! found ) {
		throw new Error( `None of ${ candidates.join( ', ' ) } in ${ dir }` );
	}
	fs.copyFileSync( path.join( dir, found ), path.join( dest, target ) );
}

const chartjs = pkg( 'chart.js' );
copyFirst(
	chartjs.dir,
	[ 'dist/chart.umd.min.js', 'dist/chart.umd.js' ],
	'chart.umd.min.js'
);
copyFirst( chartjs.dir, [ 'LICENSE.md', 'LICENSE' ], 'chart.js-LICENSE.md' );

const labels = pkg( 'chartjs-plugin-datalabels' );
copyFirst(
	labels.dir,
	[ 'dist/chartjs-plugin-datalabels.min.js' ],
	'chartjs-plugin-datalabels.min.js'
);
copyFirst(
	labels.dir,
	[ 'LICENSE.md', 'LICENSE' ],
	'chartjs-plugin-datalabels-LICENSE.md'
);

fs.writeFileSync(
	path.join( dest, 'versions.json' ),
	JSON.stringify(
		{
			'chart.js': chartjs.version,
			'chartjs-plugin-datalabels': labels.version,
		},
		null,
		2
	) + '\n'
);
console.log( 'Vendor files copied:', chartjs.version, labels.version );
