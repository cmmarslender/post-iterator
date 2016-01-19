<?php

namespace Cmmarslender\PostIterator;

use Cmmarslender\Timer\Timer;

abstract class PostIterator  {

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

	/**
	 * Iterates over the posts matched by the args passed to the class
	 */
	public function go() {
		$this->count_posts();
		$this->timer->set_total_items( $this->total_posts );
		$this->timer->start();
		$this->iterate();
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
				$this->timer->tick();

				$percent = $this->timer->percent_complete();
				Logger::log( "{$this->timer->current_item} / {$limit} ({$percent}%) | Processing Post ID {$post_id}");

				$elapsed_pretty = $this->timer->format_time( $this->timer->elapsed_time() );
				$remaining_pretty = $this->timer->format_time( $this->timer->remaining_time() );
				$average_pretty = $this->timer->format_time( $this->timer->average() );
				Logger::log( "We've been at this for {$elapsed_pretty}. Based on the average of {$average_pretty} seconds per post, this will finish in {$remaining_pretty}" );

				$post = get_post( $post_id );

				$this->original_post_object = clone $post;
				$this->current_post_object = $post;

				$this->process_post();
				$this->update_post();
			}

			$offset += $per_page;
			$more_posts = ( $this->current_post_count < $limit );
			Utils::stop_the_insanity();
		} while ( $more_posts );
	}

	/**
	 * The function that actually implements the work we need to get done.
	 *
	 * @return void
	 */
	abstract function process_post();

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
	 */
	public function update_post() {
		// @todo make sure this comparison actually works
		if ( $this->original_post_object != $this->current_post_object ) {
			wp_update_post( $this->current_post_object );
			Logger::log( "Updated post ID {$this->current_post_object->ID}" );
		}
	}

}
