<?php

namespace Cmmarslender\PostIterator;

use Cmmarslender\Timer\Timer;

abstract class PostIterator  {


	protected function iterate() {

		do {
				$percent = $this->timer->percent_complete();
				Logger::log( "{$this->timer->current_item} / {$limit} ({$percent}%) | Processing Post ID {$post_id}");

				$elapsed_pretty = $this->timer->format_time( $this->timer->elapsed_time() );
				$remaining_pretty = $this->timer->format_time( $this->timer->remaining_time() );
				$average_pretty = $this->timer->format_time( $this->timer->average() );
				Logger::log( "We've been at this for {$elapsed_pretty}. Based on the average of {$average_pretty} seconds per post, this will finish in {$remaining_pretty}" );
			}
		} while ( $more_posts );
	}


}
