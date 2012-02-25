<?php
if ( ! defined( 'ABSPATH' ) ) {
	header( $_SERVER["SERVER_PROTOCOL"] . " 404 Not Found" );
	header( "Status: 404 Not Found" );
	die();
}
/**
 * Class for BackWPup cron methods
 */
class BackWPup_Cron {
	/**
	 * @static
	 *
	 * @param $args
	 */
	public static function run( $args ) {
		if ( $workingdata = backwpup_get_workingdata() ) {
			if ( $args == 'restart' ) {
				wp_schedule_single_event( time() + 300, 'backwpup_cron', array( 'main'=> 'restart' ) );
				$not_worked_time = microtime( true ) - $workingdata['TIMESTAMP'];
				if ( $not_worked_time > 300 ) {
					if (defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON) {
						define('DONOTCACHEPAGE', true);
						define('DONOTCACHEDB', true);
						define('DONOTMINIFY', true);
						define('DONOTCDN', true);
						//define('DONOTCACHCEOBJECT', true);
						//define E_DEPRECATED if PHP lower than 5.3
						if ( ! defined( 'E_DEPRECATED' ) )
							define('E_DEPRECATED', 8192);
						if ( ! defined( 'E_USER_DEPRECATED' ) )
							define('E_USER_DEPRECATED', 16384);
						//try to disable safe mode
						@ini_set( 'safe_mode', '0' );
						// Now user abort
						@ini_set( 'ignore_user_abort', '0' );
						ignore_user_abort( true );
						@set_time_limit( backwpup_get_option( 'cfg', 'jobrunmaxexectime' ) );
						new BackWPup_Job('restart');
					} else {
						backwpup_jobrun_url('restart');
					}
				}
			} else {
				//reschedule in 5 min
				wp_schedule_single_event( time() + 300, 'backwpup_cron', array( 'main'=> $args ) );
			}
		} else {
			if ( $args == 'restart' ) {
				wp_clear_scheduled_hook( 'backwpup_cron', array( 'main'=> 'restart' ) );
			} else {
				if (defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON) {
					define('DONOTCACHEPAGE', true);
					define('DONOTCACHEDB', true);
					define('DONOTMINIFY', true);
					define('DONOTCDN', true);
					//define('DONOTCACHCEOBJECT', true);
					//define E_DEPRECATED if PHP lower than 5.3
					if ( ! defined( 'E_DEPRECATED' ) )
						define('E_DEPRECATED', 8192);
					if ( ! defined( 'E_USER_DEPRECATED' ) )
						define('E_USER_DEPRECATED', 16384);
					//try to disable safe mode
					@ini_set( 'safe_mode', '0' );
					// Now user abort
					@ini_set( 'ignore_user_abort', '0' );
					ignore_user_abort( true );
					@set_time_limit( backwpup_get_option( 'cfg', 'jobrunmaxexectime' ) );
					//reschedule
					$cronnxet = BackWPup_Cron::cron_next( backwpup_get_option( $args, 'cron' ) );
					$offset   = get_option( 'gmt_offset' ) * 3600;
					wp_schedule_single_event( $cronnxet - $offset, 'backwpup_cron', array( 'main'=> $args ) );
					new BackWPup_Job('cronrun', backwpup_get_option( $args, 'jobid' ));
				}  else {
					backwpup_jobrun_url('cronrun',backwpup_get_option( $args, 'jobid' ));
				}
			}
		}
	}


	/**
	 *
	 * Get the local time timestamp of the next cron execution
	 *
	 * @param string $cronstring string of cron (* * * * *)
	 *
	 * @return timestamp
	 */
	public static function cron_next( $cronstring ) {
		$cron      = array();
		$cronarray = array();
		//Cron string
		list($cronstr['minutes'], $cronstr['hours'], $cronstr['mday'], $cronstr['mon'], $cronstr['wday']) = explode( ' ', $cronstring, 5 );

		//make arrays form string
		foreach ( $cronstr as $key => $value ) {
			if ( strstr( $value, ',' ) )
				$cronarray[$key] = explode( ',', $value );
			else
				$cronarray[$key] = array( 0=> $value );
		}
		//make arrays complete with ranges and steps
		foreach ( $cronarray as $cronarraykey => $cronarrayvalue ) {
			$cron[$cronarraykey] = array();
			foreach ( $cronarrayvalue as $value ) {
				//steps
				$step = 1;
				if ( strstr( $value, '/' ) )
					list($value, $step) = explode( '/', $value, 2 );
				//replace weekday 7 with 0 for sundays
				if ( $cronarraykey == 'wday' )
					$value = str_replace( '7', '0', $value );
				//ranges
				if ( strstr( $value, '-' ) ) {
					list($first, $last) = explode( '-', $value, 2 );
					if ( ! is_numeric( $first ) || ! is_numeric( $last ) || $last > 60 || $first > 60 ) //check
						return 2147483647;
					if ( $cronarraykey == 'minutes' && $step < 5 ) //set step minimum to 5 min.
						$step = 5;
					$range = array();
					for ( $i = $first; $i <= $last; $i = $i + $step )
					{
						$range[] = $i;
					}
					$cron[$cronarraykey] = array_merge( $cron[$cronarraykey], $range );
				} elseif ( $value == '*' ) {
					$range = array();
					if ( $cronarraykey == 'minutes' ) {
						if ( $step < 5 ) //set step minimum to 5 min.
							$step = 5;
						for ( $i = 0; $i <= 59; $i = $i + $step )
						{
							$range[] = $i;
						}
					}
					if ( $cronarraykey == 'hours' ) {
						for ( $i = 0; $i <= 23; $i = $i + $step )
						{
							$range[] = $i;
						}
					}
					if ( $cronarraykey == 'mday' ) {
						for ( $i = $step; $i <= 31; $i = $i + $step )
						{
							$range[] = $i;
						}
					}
					if ( $cronarraykey == 'mon' ) {
						for ( $i = $step; $i <= 12; $i = $i + $step )
						{
							$range[] = $i;
						}
					}
					if ( $cronarraykey == 'wday' ) {
						for ( $i = 0; $i <= 6; $i = $i + $step )
						{
							$range[] = $i;
						}
					}
					$cron[$cronarraykey] = array_merge( $cron[$cronarraykey], $range );
				} else {
					//Month names
					if ( strtolower( $value ) == 'jan' )
						$value = 1;
					if ( strtolower( $value ) == 'feb' )
						$value = 2;
					if ( strtolower( $value ) == 'mar' )
						$value = 3;
					if ( strtolower( $value ) == 'apr' )
						$value = 4;
					if ( strtolower( $value ) == 'may' )
						$value = 5;
					if ( strtolower( $value ) == 'jun' )
						$value = 6;
					if ( strtolower( $value ) == 'jul' )
						$value = 7;
					if ( strtolower( $value ) == 'aug' )
						$value = 8;
					if ( strtolower( $value ) == 'sep' )
						$value = 9;
					if ( strtolower( $value ) == 'oct' )
						$value = 10;
					if ( strtolower( $value ) == 'nov' )
						$value = 11;
					if ( strtolower( $value ) == 'dec' )
						$value = 12;
					//Week Day names
					if ( strtolower( $value ) == 'sun' )
						$value = 0;
					if ( strtolower( $value ) == 'sat' )
						$value = 6;
					if ( strtolower( $value ) == 'mon' )
						$value = 1;
					if ( strtolower( $value ) == 'tue' )
						$value = 2;
					if ( strtolower( $value ) == 'wed' )
						$value = 3;
					if ( strtolower( $value ) == 'thu' )
						$value = 4;
					if ( strtolower( $value ) == 'fri' )
						$value = 5;
					if ( ! is_numeric( $value ) || $value > 60 ) //check
						return 2147483647;
					$cron[$cronarraykey] = array_merge( $cron[$cronarraykey], array( 0=> $value ) );
				}
			}
		}
		//generate next 10 years
		for ( $i = date( 'Y' ); $i < 2038; $i ++ )
		{
			$cron['year'][] = $i;
		}

		//calc next timestamp
		$current_timestamp = current_time( 'timestamp' );
		foreach ( $cron['year'] as $year ) {
			foreach ( $cron['mon'] as $mon ) {
				foreach ( $cron['mday'] as $mday ) {
					foreach ( $cron['hours'] as $hours ) {
						foreach ( $cron['minutes'] as $minutes ) {
							$timestamp = mktime( $hours, $minutes, 0, $mon, $mday, $year );
							if ( $timestamp && in_array( date( 'j', $timestamp ), $cron['mday'] ) && in_array( date( 'w', $timestamp ), $cron['wday'] ) && $timestamp > $current_timestamp ) {
								return $timestamp;
							}
						}
					}
				}
			}
		}
		return 2147483647;
	}

}
