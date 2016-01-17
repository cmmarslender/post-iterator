<?php

namespace Cmmarslender\PostIterator;

use WP_CLI;

class Logger {

	public static function log( $message ) {
		if ( defined( "WP_CLI" ) ) {
			WP_CLI::log( $message );
		}
	}

}
