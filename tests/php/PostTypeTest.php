<?php
namespace WebAula\Charts\Tests;

use WebAula\Charts\Chart_Data;
use WebAula\Charts\Post_Type;
use WP_REST_Request;

class PostTypeTest extends TestCase {

	public function test_post_type_is_private_with_ui() {
		$type = get_post_type_object( Post_Type::SLUG );
		$this->assertNotNull( $type );
		$this->assertFalse( $type->public );
		$this->assertFalse( $type->publicly_queryable );
		$this->assertTrue( $type->exclude_from_search );
		$this->assertTrue( $type->show_ui );
		$this->assertTrue( $type->show_in_rest );
		$this->assertSame( 'edit_wa_charts', $type->cap->edit_posts );
		$this->assertFalse( use_block_editor_for_post_type( Post_Type::SLUG ) );
	}

	public function test_meta_is_registered_and_hidden_from_rest() {
		// The WP test suite unregisters meta keys after every test, so register again.
		Post_Type::register();
		$meta = get_registered_meta_keys( 'post', Post_Type::SLUG );
		$this->assertArrayHasKey( Chart_Data::META_KEY, $meta );
		$this->assertFalse( $meta[ Chart_Data::META_KEY ]['show_in_rest'] );
	}

	public function test_editor_can_edit_charts_but_author_cannot() {
		$chart  = $this->create_chart();
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->assertTrue( user_can( $editor, 'edit_post', $chart ) );
		$this->assertTrue( user_can( $editor, 'edit_wa_charts' ) );
		$this->assertFalse( user_can( $author, 'edit_post', $chart ) );
		$this->assertFalse( user_can( $author, 'edit_wa_charts' ) );
	}

	public function test_rest_listing_requires_chart_capability() {
		$this->create_chart();
		$request = new WP_REST_Request( 'GET', '/wp/v2/wa_chart' );

		wp_set_current_user( 0 );
		$this->assertSame( 401, rest_get_server()->dispatch( $request )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
	}

	public function test_columns_and_shortcode_field() {
		$columns = Post_Type::columns(
			array(
				'cb'    => '<input type="checkbox" />',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);
		$this->assertSame( array( 'cb', 'title', 'wa_type', 'wa_shortcode', 'wa_usage', 'wa_modified' ), array_keys( $columns ) );
		$this->assertSame( '[wa_chart id="42"]', Post_Type::shortcode( 42 ) );
		$field = Post_Type::shortcode_field( 42 );
		$this->assertStringContainsString( 'data-wa-copy="[wa_chart id=&quot;42&quot;]"', $field );
	}

	public function test_type_column_shows_label() {
		$chart = $this->create_chart( array( 'type' => 'donut' ) );
		ob_start();
		Post_Type::column_content( 'wa_type', $chart );
		$this->assertStringContainsString( 'Donut', ob_get_clean() );
	}

	public function test_duplicate_copies_config_as_draft() {
		$chart = $this->create_chart( array( 'labels' => array( 'A', 'B' ) ) );
		$copy  = Post_Type::duplicate( $chart );
		$this->assertIsInt( $copy );
		$this->assertSame( 'draft', get_post_status( $copy ) );
		$this->assertSame( 'Test chart (copy)', get_the_title( $copy ) );
		$this->assertSame( Chart_Data::get( $chart ), Chart_Data::get( $copy ) );
		$this->assertWPError( Post_Type::duplicate( self::factory()->post->create() ) );
	}

	public function test_row_action_only_for_chart_editors() {
		$chart = get_post( $this->create_chart() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertArrayNotHasKey( 'wa_duplicate', Post_Type::row_actions( array(), $chart ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$actions = Post_Type::row_actions( array(), $chart );
		$this->assertStringContainsString( '_wpnonce=', $actions['wa_duplicate'] );
	}

	public function test_rest_single_read_requires_chart_capability() {
		$chart   = $this->create_chart();
		$request = new WP_REST_Request( 'GET', '/wp/v2/wa_chart/' . $chart );

		wp_set_current_user( 0 );
		$this->assertSame( 401, rest_get_server()->dispatch( $request )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
	}

	/**
	 * Runs the duplicate handler and returns the die message, or 'redirect' on success.
	 */
	private function run_duplicate_handler( int $chart, string $nonce ): string {
		$_GET['post']         = (string) $chart;
		$_REQUEST['_wpnonce'] = $nonce;
		$redirect             = static function () {
			throw new \RuntimeException( 'redirect' );
		};
		add_filter( 'wp_redirect', $redirect );
		try {
			Post_Type::handle_duplicate();
			return 'returned';
		} catch ( \WPDieException $e ) {
			return 'die';
		} catch ( \RuntimeException $e ) {
			return $e->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $redirect );
			unset( $_GET['post'], $_REQUEST['_wpnonce'] );
		}
	}

	private function count_copies(): int {
		return count(
			get_posts(
				array(
					'post_type'   => Post_Type::SLUG,
					'post_status' => 'draft',
					'title'       => 'Test chart (copy)',
					'numberposts' => -1,
				)
			)
		);
	}

	public function test_handle_duplicate_rejects_bad_nonce() {
		$chart = $this->create_chart();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 'die', $this->run_duplicate_handler( $chart, 'bad' ) );
		$this->assertSame( 0, $this->count_copies() );
	}

	public function test_handle_duplicate_rejects_author_with_valid_nonce() {
		$chart = $this->create_chart();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$nonce = wp_create_nonce( Post_Type::DUPLICATE_ACTION . '_' . $chart );
		$this->assertSame( 'die', $this->run_duplicate_handler( $chart, $nonce ) );
		$this->assertSame( 0, $this->count_copies() );
	}

	public function test_handle_duplicate_creates_draft_for_editor() {
		$chart = $this->create_chart();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$nonce = wp_create_nonce( Post_Type::DUPLICATE_ACTION . '_' . $chart );
		$this->assertSame( 'redirect', $this->run_duplicate_handler( $chart, $nonce ) );
		$this->assertSame( 1, $this->count_copies() );
	}
}
