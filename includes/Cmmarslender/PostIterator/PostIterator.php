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
	 * Total number of posts found that match the constraints, NOT including limits and offsets
	 *
	 * @see count_posts();
	 *
	 * @var int
	 */
	public $total_posts;


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

}
