<?php

namespace Cmmarslender\PostIterator;

use Cmmarslender\Timer\Timer;

abstract class PostIterator {

	/*
	 * Query Related Variables
	 */
	public $post_type;
	public $post_status;
	public $offset;
	public $limit;
	public $orderby;
	public $order;

	/**
	 * @var \Cmmarslender\Timer\Timer
	 */
	public $timer;

	/*
	 * State Related Variables
	 */
	/**
	 * Tracks if we've set the iterator up yet (Count posts, setup timer, etc)
	 *
	 * @var bool
	 */
	public $is_setup = false;

	/**
	 * Total number of posts found that match the constraints, NOT including limits and offsets
	 *
	 * @see count_posts();
	 *
	 * @var int
	 */
	public $total_posts;

	/**
	 * The original post object. Used to check if anything has changed, and call update_post
	 *
	 * @var \WP_Post
	 */
	public $original_post_object;

	/**
	 * Post object we're currently working with. Modify this object directly.
	 *
	 * @var \WP_Post
	 */
	public $current_post_object;

	public function __construct( $args = array() ) {
		$defaults = array(
			'post_type' => 'post',
			'post_status' => 'publish',
			'per_page' => 100,
			'page' => 1,
			'limit' => -1,
			'orderby' => 'post_date',
			'order' => 'DESC',
		);

		foreach( $defaults as $key => $value ) {
			$this->{$key} = isset( $args[ $key ] ) ? $args[ $key ] : $value;
		}

		$this->timer = new Timer();
	}

	public function setup() {
		if ( false === $this->is_setup ) {
			$this->count_posts();
			$this->timer->set_total_items( $this->total_posts );
			$this->is_setup = true;
		}
	}

	public function go() {
		$this->timer->start();
		while( $this->have_pages() ) {
			$this->process_page();
			Utils::stop_the_insanity();
		}
	}

	/**
	 * Indicates if we have posts to process
	 */
	public function have_pages() {
		$this->setup();

		$offset = $this->get_query_offset();

		return (bool) ( $offset < $this->total_posts );
	}

	public function process_page() {
		global $wpdb;

		// Just in case someone doesn't call have_pages() first
		$this->setup();

		$results = $wpdb->get_results( $this->get_query() );
		$post_ids = wp_list_pluck( $results, 'ID' );

		foreach ( $post_ids as $post_id ) {
			$this->timer->tick();

			// @todo add logging / status

			$post = get_post( $post_id );

			$this->original_post_object = clone $post;
			$this->current_post_object = $post;

			$this->process_post();
			$this->update_post();
		}

		$this->page++;
	}

	/**
	 * Counts the total number of posts that match the restrictions, not including pagination.
	 */
	protected function count_posts() {
		global $wpdb;

		$query = $this->get_count_query();

		$this->total_posts = $wpdb->get_var( $query );
	}

	protected function get_count_query_base() {
		global $wpdb;

		return "SELECT count(ID) FROM {$wpdb->posts}";
	}

	protected function get_query_base() {
		global $wpdb;

		return "SELECT ID FROM {$wpdb->posts}";
	}

	protected function get_where() {
		global $wpdb;

		$where = $wpdb->prepare( "WHERE post_type=%s", $this->post_type );

		if ( 'any' != $this->post_status ) {
			$where .= $wpdb->prepare( " AND post_status=%s", $this->post_status );
		}

		return $where;
	}

	protected function get_orderby() {
		global $wpdb;

		$orderby = sprintf( "ORDER BY %s %s", $wpdb->_real_escape( $this->orderby ), $wpdb->_real_escape( $this->order ) );

		return $orderby;
	}

	protected function get_query_offset() {
		$offset = ( $this->page - 1 ) * $this->per_page;

		return $offset;
	}

	protected function get_limit() {
		$limit = sprintf( "LIMIT %d,%d", $this->get_query_offset(), $this->per_page );

		return $limit;
	}

	protected function get_count_query() {
		$query = implode( " ", array( $this->get_count_query_base(), $this->get_where() ) );

		return $query;
	}

	protected function get_query() {
		 $query = implode( " ", array( $this->get_query_base(), $this->get_where(), $this->get_orderby(), $this->get_limit() ) );

		return $query;
	}


	abstract function process_post();

	public function update_post() {
		if ( $this->original_post_object != $this->current_post_object ) {
			wp_update_post( $this->current_post_object );
			Logger::log( "Updated post ID {$this->current_post_object->ID}" );
		}
	}

}
