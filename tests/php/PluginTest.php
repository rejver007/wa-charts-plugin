<?php
namespace WebAula\Charts\Tests;

use WebAula\Charts\Plugin;

class PluginTest extends TestCase {

	public function test_constants_are_defined() {
		$this->assertSame( '1.1.3', WA_CHARTS_VERSION );
		$this->assertStringEndsWith( '/', WA_CHARTS_DIR );
	}

	public function test_autoloader_loads_plugin_class() {
		$this->assertTrue( class_exists( Plugin::class ) );
	}

	public function test_textdomain_is_hooked() {
		$this->assertNotFalse( has_action( 'init', array( Plugin::class, 'load_textdomain' ) ) );
	}
}
