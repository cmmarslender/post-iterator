<?php

namespace Cmmarslender\PostIterator;

class Utils {
	public static function stop_the_insanity() {
		global $wpdb, $wp_actions, $wp_object_cache;

		Logger::log( "Stopping the insanity" );
		Logger::log( " -- Memory Usage Before: " . memory_get_usage() );

		$wpdb->queries         = array(); // or define( 'WP_IMPORTING', true );
		// Reset $wp_actions to keep it from growing out of control
		$wp_actions = array();

		if ( is_object( $wp_object_cache ) ) {
			$wp_object_cache->group_ops      = array();
			$wp_object_cache->stats          = array();
			$wp_object_cache->memcache_debug = array();
			$wp_object_cache->cache          = array();
			if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
				$wp_object_cache->__remoteset();
			}
		}

		Logger::log( " -- Memory Usage After: " . memory_get_usage() );
	}
}
