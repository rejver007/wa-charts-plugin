<?php
namespace WebAula\Charts\Tests;

class VendorTest extends TestCase {

	public function test_bundled_versions_match_constants() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local fixture file.
		$versions = json_decode( file_get_contents( WA_CHARTS_DIR . 'assets/vendor/versions.json' ), true );
		$this->assertSame( WA_CHARTS_CHARTJS_VERSION, $versions['chart.js'] );
		$this->assertSame( WA_CHARTS_DATALABELS_VERSION, $versions['chartjs-plugin-datalabels'] );
		$this->assertFileExists( WA_CHARTS_DIR . 'assets/vendor/chart.umd.min.js' );
		$this->assertFileExists( WA_CHARTS_DIR . 'assets/vendor/chartjs-plugin-datalabels.min.js' );
	}
}
