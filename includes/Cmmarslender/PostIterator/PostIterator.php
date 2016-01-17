<?php

namespace Cmmarslender\PostIterator;

use WP_CLI;

abstract class PostIterator  {

	public $post_type;

	public $post_status;

	public $offset;

	public $limit;

	public $orderby;

	public $order;

	public $start_time;

	/**
	 * Will contain the total number of posts returned that match the constraints, NOT INCLUDING the limits and offsets
	 *
	 * @var int
	 */
	public $total_posts;

	/**
	 * Counter that indicates the current post we're working on.
	 *
	 * @var int
	 */
	public $current_post_count = 0;

	/**
	 * The current post we are processing with process_post
	 *
	 * @var \WP_Post
	 */
	public $current_post_object;

	/**
	 * Original Post object
	 *
	 * @var \WP_Post
	 */
	public $original_post_object;

	public function __construct( $args = array() ) {
		$defaults = array(
			'post_type' => 'post',
			'post_status' => 'publish',
			'offset' => 0,
			'limit' => -1,
			'orderby' => 'post_date',
			'order' => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$this->post_type = $args['post_type'];
		$this->post_status = $args['post_status'];
		$this->offset = $args['offset'];
		$this->limit = $args['limit'];
		$this->orderby = $args['orderby'];
		$this->order = $args['order'];
	}

	/**
	 * Iterates over the posts matched by the args passed to the class
	 */
	public function go() {
		$this->count_posts();
		$this->start_time = time();
		$this->iterate();
	}

	protected function count_posts() {
		global $wpdb;

		$where = $this->get_where();

		$count = $wpdb->get_var( "select count(ID) from {$wpdb->posts} {$where}" );

		$this->total_posts = $count;
	}

	protected function iterate() {
		global $wpdb;

		$query_base = "SELECT ID from {$wpdb->posts}";
		$query_where = $this->get_where();
		$query_orderby = $this->get_orderby();

		$limit = intval( $this->limit );
		$offset = absint( $this->offset );

		if ( -1 == $limit ) {
			$limit = $this->total_posts;
		}

		$per_page = ( $limit > 100 ) ? 100 : $limit;

		do {
			$query_limit = $this->get_limit( $offset, $per_page );
			$query = implode( " ", array( $query_base, $query_where, $query_orderby, $query_limit ) );

			$results = $wpdb->get_results( $query );
			$post_ids = wp_list_pluck( $results, 'ID' );

			foreach( $post_ids as $post_id ) {
				$this->current_post_count++;
				$percent = round( $this->current_post_count / $limit * 100, 2 );
				WP_CLI::log( "{$this->current_post_count} / {$limit} ({$percent}%) | Processing Post ID {$post_id}");

				$current_time = time();
				$total_seconds = $current_time - $this->start_time;
				$average_per_post = $total_seconds / $this->current_post_count;
				$average_pretty = round( $average_per_post, 3 );
				$minutes = floor( $total_seconds / 60 );
				$seconds = $total_seconds % 60;
				$remaining = ( $limit - $this->current_post_count ) * $average_per_post;
				$remaining_min = floor( $remaining / 60 );
				$remaining_sec = $remaining % 60;
				WP_CLI::log( "We've been at this for {$minutes}:{$seconds}. Based on the average of {$average_pretty} seconds per post, this will finish in {$remaining_min}:{$remaining_sec}" );

				$post = get_post( $post_id );

				$this->original_post_object = clone $post;
				$this->current_post_object = $post;

				$this->process_post( $post );
			}

			$offset += $per_page;
			$more_posts = ( $this->current_post_count < $limit );
			Utils::stop_the_insanity();
		} while ( $more_posts );
	}

	/**
	 * The function that actually implements the work we need to get done.
	 *
	 * @param \WP_Post $post The current post to work on
	 *
	 * @return void
	 */
	abstract function process_post( \WP_Post $post );

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

	protected function get_limit( $offset, $limit ) {
		$query = sprintf( "LIMIT %d,%d", $offset, $limit );

		return $query;
	}

	/**
	 * Calls wp_update_post
	 *
	 * @param $post
	 */
	public function update_post( $post ) {
		wp_update_post( $post );
		WP_CLI::log( "Updated post ID {$post->ID}" );
	}

}
