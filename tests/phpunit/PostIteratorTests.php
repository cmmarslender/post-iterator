<?php

namespace Cmmarslender\PostIterator;

use \Mockery as m;

class PostIteratorTests extends TestCase {

	public function setUp() {
		global $wpdb;

		parent::setUp();

		$wpdb = m::mock( 'wpdb' );
		$wpdb->posts = 'wp_posts';
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing( function() {
			$args = func_get_args();
			$query = array_shift( $args );

			// From WPDB::prepare method
			$query = str_replace( "'%s'", '%s', $query ); // in case someone mistakenly already singlequoted it
			$query = str_replace( '"%s"', '%s', $query ); // doublequote unquoting
			$query = preg_replace( '|(?<!%)%f|' , '%F', $query ); // Force floats to be locale unaware
			$query = preg_replace( '|(?<!%)%s|', "'%s'", $query ); // quote the strings, avoiding escaped strings like %%s

			return vsprintf( $query, $args );
		} );
		$wpdb->shouldReceive( '_real_escape' )->andReturnUsing( function( $arg ) { return $arg; });
	}

	public function invoke_method( $object, $method_name, $parameters = array() ) {
		$reflection = new \ReflectionClass( get_class( $object ) );
		$method = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $object, $parameters );
	}

	public function get_iterator( $args = array() ) {
		$iterator = m::mock( 'Cmmarslender\\PostIterator\\PostIterator[process_post]', array( $args ) );

		return $iterator;
	}

	public function test_object_construction_with_defaults() {
		$iterator = $this->get_iterator();

		$this->assertEquals( 'post', $iterator->post_type );
		$this->assertEquals( 'publish', $iterator->post_status );
		$this->assertEquals( 100, $iterator->per_page );
		$this->assertEquals( 1, $iterator->page );
		$this->assertEquals( -1, $iterator->limit );
		$this->assertEquals( 'post_date', $iterator->orderby );
		$this->assertEquals( 'DESC', $iterator->order );
	}

	public function test_object_construction_with_overrides() {
		$overrides = array(
			'post_type' => 'page',
			'post_status' => 'any',
			'per_page' => 5,
			'page' => 2,
			'limit' => 25,
			'orderby' => 'post_title',
			'order' => 'ASC',
		);
		$iterator = $this->get_iterator( $overrides );

		$this->assertEquals( $overrides['post_type'], $iterator->post_type );
		$this->assertEquals( $overrides['post_status'], $iterator->post_status );
		$this->assertEquals( $overrides['per_page'], $iterator->per_page );
		$this->assertEquals( $overrides['page'], $iterator->page );
		$this->assertEquals( $overrides['limit'], $iterator->limit );
		$this->assertEquals( $overrides['orderby'], $iterator->orderby );
		$this->assertEquals( $overrides['order'], $iterator->order );
	}

	public function test_we_have_a_timer_instance() {
		$iterator = $this->get_iterator();

		$this->assertInstanceOf( 'Cmmarslender\\Timer\\Timer', $iterator->timer );
	}

	/**
	 * Test that when we get a result from wpdb->get_var it gets assigned to total posts
	 */
	public function test_count_posts() {
		global $wpdb;

		$wpdb->shouldReceive( 'get_var' )->andReturn( '26' );

		$iterator = $this->get_iterator();
		$this->invoke_method( $iterator, 'count_posts' );
		$this->assertEquals( 13, $iterator->total_posts );
	}

	public function test_get_count_query_base() {
		$iterator = $this->get_iterator();

		$expected = "SELECT count(ID) FROM wp_posts";
		$result = $this->invoke_method( $iterator, 'get_count_query_base' );
		$this->assertEquals( $expected, $result );
	}

	public function test_get_query_base() {
		$iterator = $this->get_iterator();

		$expected = "SELECT ID FROM wp_posts";
		$result = $this->invoke_method( $iterator, 'get_query_base' );
		$this->assertEquals( $expected, $result );
	}

	public function test_get_where_with_defaults() {
		$iterator = $this->get_iterator();

		$expected = "WHERE post_type='post' AND post_status='publish'";
		$result = $this->invoke_method( $iterator, 'get_where' );
		$this->assertEquals( $expected, $result );
	}

	public function test_get_where_with_post_status_any() {
		$iterator = $this->get_iterator( array( 'post_status' => 'any' ) );

		$expected = "WHERE post_type='post'";
		$result = $this->invoke_method( $iterator, 'get_where' );
		$this->assertEquals( $expected, $result );
	}

	public function test_get_where_with_post_type_page() {
		$iterator = $this->get_iterator( array( 'post_type' => 'page' ) );

		$expected = "WHERE post_type='page' AND post_status='publish'";
		$result = $this->invoke_method( $iterator, 'get_where' );
		$this->assertEquals( $expected, $result );
	}

	public function test_get_orderby_with_defaults() {
		$iterator = $this->get_iterator();

		$expected = "ORDER BY post_date DESC";
		$result = $this->invoke_method( $iterator, 'get_orderby' );
		$this->assertEquals( $expected, $result );
	}

	public function test_orderby_with_overrides() {
		$iterator = $this->get_iterator( array( 'orderby' => 'post_title', 'order' => 'ASC' ) );

		$expected = "ORDER BY post_title ASC";
		$result = $this->invoke_method( $iterator, 'get_orderby' );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test query offsets. These are also indirectly tested by the ::get_limit() tests
	 */
	public function test_get_query_offset() {
		$iterator = $this->get_iterator( array( 'page' => 1, 'per_page' => 5 ) );

		$result = $this->invoke_method( $iterator, 'get_query_offset' );
		$this->assertEquals( 0, $result );

		$iterator->page = 3;
		$result = $this->invoke_method( $iterator, 'get_query_offset' );
		$this->assertEquals( 10, $result );
	}

	public function test_get_limit_with_defaults() {
		$iterator = $this->get_iterator();

		$expected = "LIMIT 0,100";
		$result = $this->invoke_method( $iterator, 'get_limit' );
		$this->assertEquals( $expected, $result );
	}

	public function test_get_limit_with_page() {
		$iterator = $this->get_iterator( array( 'page' => 2 ) );

		$expected = "LIMIT 100,100";
		$result = $this->invoke_method( $iterator, 'get_limit' );
		$this->assertEquals( $expected, $result );
	}

	public function test_get_limit_with_per_page() {
		$iterator = $this->get_iterator( array( 'per_page' => 5 ) );

		$expected = "LIMIT 0,5";
		$result = $this->invoke_method( $iterator, 'get_limit' );
		$this->assertEquals( $expected, $result );
	}

	public function test_get_limit_with_page_and_per_page() {
		$iterator = $this->get_iterator( array( 'per_page' => 5, 'page' => 4 ) );

		$expected = "LIMIT 15,5";
		$result = $this->invoke_method( $iterator, 'get_limit' );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test that the query is in the correct order.
	 *
	 * Possible variations are tested in the specific sub-method tests above, so this just tests default values.
	 */
	public function test_get_count_query() {
		$iterator = $this->get_iterator();

		$expected = "SELECT count(ID) FROM wp_posts WHERE post_type='post' AND post_status='publish'";
		$result = $this->invoke_method( $iterator, 'get_count_query' );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test that the query is in the correct order.
	 *
	 * Possible variations are tested in the specific sub-method tests above, so this just tests default values.
	 */
	public function test_get_query() {
		$iterator = $this->get_iterator();

		$expected = "SELECT ID FROM wp_posts WHERE post_type='post' AND post_status='publish' ORDER BY post_date DESC LIMIT 0,100";
		$result = $this->invoke_method( $iterator, 'get_query' );
		$this->assertEquals( $expected, $result );
	}

}
