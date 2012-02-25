<?php

// Google Calendar Event Display
// Displays current event from Google Calendar
// Copyright (c) 2011 Joseph Huckaby
// Released under the MIT License

$cal_id = $_GET['cal'];

$cache_dir = 'cache';
$cache_ttl = 60;

$wday_map = array(
	'SU' => 0,
	'MO' => 1,
	'TU' => 2,
	'WE' => 3,
	'TH' => 4,
	'FR' => 5,
	'SA' => 6
);

$cache_file = $cache_dir . '/cache-' . $cal_id . '.json';
if (file_exists($cache_file)) {
	$mod = filemtime($cache_file);
	if (time() - $mod < $cache_ttl) {
		// cache is fresh, return immediately
		send_response(json_decode(file_get_contents($cache_file), true));
	}
}

// refresh cache
$cal_raw = file_get_contents( 'http://www.google.com/calendar/ical/'.$cal_id.'%40group.calendar.google.com/public/basic.ics' );
if (!$cal_raw) {
	send_response(array( 'Code' => 1, 'Description' => 'Failed to fetch calendar from google.' ));
}
$cal_raw = preg_replace('/\r/', "\n", preg_replace('/\r\n/', "\n", $cal_raw)); // fix line breaks, in case they are DOS

@file_put_contents( $cache_dir . '/temp-' . $cal_id . '.ics', $cal_raw );

$default_tz = 'America/Toronto';
date_default_timezone_set($default_tz);
$events = array();
$event = null;

foreach (preg_split('/\n/', $cal_raw) as $line) {
	$line = trim($line);
	
	// TZID:America/Toronto
	if (preg_match('/^TZID\:(.+)$/', $line, $matches)) $default_tz = $matches[1];
	
	// BEGIN:VEVENT
	if (preg_match('/^(BEGIN\:VEVENT)/', $line)) {
		$event = array(
			'Start' => '',
			'End' => '',
			'Duration' => '',
			'Freq' => '',
			'ByDay' => '',
			'ByMonthDay' => '',
			'Count' => '',
			'Until' => '',
			'Title' => '',
			'Description' => '',
			'ID' => ''
		);
	}
	
	if ($event !== null) {
		// DTSTART;TZID=America/Los_Angeles:20110622T230000
		// DTSTART:20110628T163000Z
		if (preg_match('/^DTSTART/', $line)) {
			if (preg_match('/TZID\=(.+?)\:/', $line, $matches)) date_default_timezone_set($matches[1]);
			else date_default_timezone_set($default_tz);
			if (preg_match('/\:(\d+\w+)$/', $line, $matches)) $event['Start'] = strtotime($matches[1]);
		}
		
		// DTEND;TZID=America/Los_Angeles:20110623T000000
		// DTEND:20110628T183000Z
		if (preg_match('/^DTEND/', $line)) {
			if (preg_match('/TZID\=(.+?)\:/', $line, $matches)) date_default_timezone_set($matches[1]);
			else date_default_timezone_set($default_tz);
			if (preg_match('/\:(\d+\w+)$/', $line, $matches)) $event['End'] = strtotime($matches[1]);
		}
		
		// RRULE:FREQ=YEARLY
		// RRULE:FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR
		// RRULE:FREQ=MONTHLY;BYMONTHDAY=22
		// RRULE:FREQ=DAILY
		// RRULE:FREQ=WEEKLY;COUNT=10;BYDAY=WE
		// RRULE:FREQ=WEEKLY;UNTIL=20110727T160000Z;BYDAY=WE
		// RRULE:FREQ=MONTHLY;BYDAY=4WE
		if (preg_match('/RRULE\:/', $line)) {
			if (preg_match('/FREQ\=(\w+)/', $line, $matches)) $event['Freq'] = $matches[1];
			if (preg_match('/BYDAY\=([\w\,]+)/', $line, $matches)) $event['ByDay'] = $matches[1];
			if (preg_match('/BYMONTHDAY\=(\d+)/', $line, $matches)) $event['ByMonthDay'] = $matches[1];
			if (preg_match('/COUNT\=(\d+)/', $line, $matches)) $event['Count'] = $matches[1];
			if (preg_match('/UNTIL\=(\d+\w+)/', $line, $matches)) $event['Until'] = strtotime($matches[1]);
		}
		
		// SUMMARY:This is a yearly event
		if (preg_match('/^SUMMARY\:(.+)$/', $line, $matches)) $event['Title'] = preg_replace('/\\\\/', '', $matches[1]);
		
		// DESCRIPTION:Yearly yo
		if (preg_match('/^DESCRIPTION\:(.+)$/', $line, $matches)) $event['Description'] = $matches[1];
		
		// UID:tpk027ole6vf9nqvtu74dqkmh8@google.com
		if (preg_match('/^UID\:(.+)$/', $line, $matches)) $event['ID'] = $matches[1];
	}
	
	// END:VEVENT
	if (preg_match('/^(END\:VEVENT)/', $line)) {
		if (!$event['ID']) $event['ID'] = md5( '' . microtime(true) . rand() . getmypid() );
		$event['Duration'] = $event['End'] - $event['Start'];
		$event['dargs'] = getdate( $event['Start'] );
		
		array_push( $events, $event );
		$event = null;
	}
}

// process events
$data = array( 'Code' => 0 );

$now = time();
$cur_event = find_current_event( $now );
$next_event = null;

// call JTV API if desired
$jtv_data = null;
if (isset($_GET['channel'])) {
	$channel_id = $_GET['channel'];
	$jtv_data = json_decode( file_get_contents('http://api.justin.tv/api/stream/list.json?channel='.$channel_id), true );
	if (isset($jtv_data[0])) $jtv_data = $jtv_data[0];
	if (isset($jtv_data['stream'])) $jtv_data = $jtv_data['stream'];
	if (is_array($jtv_data)) {
		$data['JTVLive'] = 1;
		$data['JTVStatus'] = (isset($jtv_data['channel']) && isset($jtv_data['channel']['status'])) ? $jtv_data['channel']['status'] : '';
	}
}

// if JTV channel provided, only show "live" event if stream is live
if ($cur_event && isset($_GET['channel'])) {
	if (!$jtv_data) {
		// channel is not live, remove current event
		// if show is less than half over, adjust time so that NextEvent will be the current one
		if ($now - $event['CurrentStart'] < ($event['Duration'] / 2)) {
			$now -= 1800;
		}
		$cur_event = null;
	}
}

if (!$cur_event) {
	// no current event, so try to find "next" event in the future
	// only scan N weeks out, as this is brute force and slow
	$max_halfs = 336 * 4; // 4 weeks
	for ($idx = 0; $idx < $max_halfs; $idx++) {
		$now += 1800;
		$next_event = find_current_event( $now );
		if ($next_event) $idx = $max_halfs;
	}
}

if ($cur_event) {
	$data['CurrentEvent'] = $cur_event;
	unset( $data['CurrentEvent']['dargs'] );
}
if ($next_event) {
	$data['NextEvent'] = $next_event;
	unset( $data['NextEvent']['dargs'] );
}

// DEBUG DATA
foreach ($events as &$event) {
	$event['NiceStart'] = date('r', $event['Start']);
	$event['NiceEnd'] = date('r', $event['End']);
	unset( $event['dargs'] );
}
// $data['Calendar'] = array( 'Event' => $events );
// @file_put_contents( $cache_dir . '/temp-' . $cal_id . '.debug', print_r($events, true) );

// save data to cache for subsequent requests
$result = @file_put_contents( $cache_file, json_encode($data) );
if (!$result) send_response(array( 'Code' => 1, 'Description' => 'Failed to save cache file to server.  Check permissions of cache dir?' ));

// send data to client
send_response($data);

exit();

function find_current_event( $now ) {
	// scan all events for one that is currently active
	global $events;
	$dargs_now = getdate( $now );
	$cur_event = null;

	foreach ($events as $event) {
		if ($cur_start_time = check_event( $event, $now, $dargs_now )) {
			$cur_event = $event;
			$cur_event['CurrentStart'] = $cur_start_time;
			break;
		}
	}
	
	return $cur_event;
}

function check_event( $event, $now, $dargs_now ) {
	// process single event, and see if it matches current
	global $wday_map;
	$dargs = $event['dargs'];
	
	if ($now < $event['Start']) return false;
	if ($event['Until'] && ($now > $event['Until'])) return false;
	
	if ($event['Count']) {
		// this is exact for yearly and monthly
		// but approximated for weekly and daily
		switch (strtolower($event['Freq'])) {
			case 'yearly':
				if ($dargs_now['year'] - $dargs['year'] >= $event['Count']) return false;
			break;

			case 'monthly':
				$start = ($dargs['year'] * 12) + $dargs['mon'];
				$end = ($dargs_now['year'] * 12) + $dargs['mon'];
				if ($end - $start >= $event['Count']) return false;
			break;

			case 'weekly':
				// RRULE:FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR
				$start = ($dargs['year'] * 52) + ($dargs['yday'] / 7);
				$end = ($dargs_now['year'] * 52) + ($dargs_now['yday'] / 7);
				$mult = 1;
				if (preg_match('/\,/', $event['ByDay'])) {
					$mult = count( preg_split('/\,\s*/', $event['ByDay']) );
				}
				if (($end - $start) * $mult >= $event['Count']) return false;
			break;

			case 'daily':
				$start = ($dargs['year'] * 365.25) + $dargs['yday'];
				$end = ($dargs_now['year'] * 365.25) + $dargs_now['yday'];
				if ($end - $start >= $event['Count']) return false;
			break;
		}
	} // count
	
	switch (strtolower($event['Freq'])) {
		case 'yearly':
			if ($dargs['yday'] == $dargs_now['yday']) {
				$todays_run = mktime( $dargs['hours'], $dargs['minutes'], $dargs['seconds'], $dargs_now['mon'], $dargs_now['mday'], $dargs_now['year'] );
				if (($now >= $todays_run) && ($now < ($todays_run + $event['Duration']))) {
					return $todays_run;
				}
			}
		break;
		
		case 'monthly':
			if (preg_match('/^(\d+)(\w+)/', $event['ByDay'], $matches)) {
				// RRULE:FREQ=MONTHLY;BYDAY=4WE
				list($dummy, $num, $wday) = $matches;
				if ($dargs_now['wday'] == $wday_map[$wday]) {
					if (intval($dargs_now['mday'] / 7) + 1 == $num) {
						$todays_run = mktime( $dargs['hours'], $dargs['minutes'], $dargs['seconds'], $dargs_now['mon'], $dargs_now['mday'], $dargs_now['year'] );
						if (($now >= $todays_run) && ($now < ($todays_run + $event['Duration']))) {
							return $todays_run;
						}
					} // right week
				} // right day of week
			}
			else {
				// RRULE:FREQ=MONTHLY;BYMONTHDAY=22
				if ($event['ByMonthDay']) $dargs['mday'] = $event['ByMonthDay'];
				if ($dargs['mday'] == $dargs_now['mday']) {
					$todays_run = mktime( $dargs['hours'], $dargs['minutes'], $dargs['seconds'], $dargs_now['mon'], $dargs_now['mday'], $dargs_now['year'] );
					if (($now >= $todays_run) && ($now < ($todays_run + $event['Duration']))) {
						return $todays_run;
					}
				}
			}
		break;
		
		case 'weekly':
			// RRULE:FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR
			$wdays = array();
			if ($event['ByDay']) {
				foreach (preg_split('/\,\s*/', $event['ByDay']) as $wday) {
					$wdays[ $wday_map[$wday] ] = 1;
				}
			}
			else $wdays[ $dargs['wday'] ] = 1;
			
			if (isset($wdays[ $dargs_now['wday'] ])) {
				$todays_run = mktime( $dargs['hours'], $dargs['minutes'], $dargs['seconds'], $dargs_now['mon'], $dargs_now['mday'], $dargs_now['year'] );
				if (($now >= $todays_run) && ($now < ($todays_run + $event['Duration']))) {
					return $todays_run;
				}
			}
		break;
		
		case 'daily':
			$todays_run = mktime( $dargs['hours'], $dargs['minutes'], $dargs['seconds'], $dargs_now['mon'], $dargs_now['mday'], $dargs_now['year'] );
			if (($now >= $todays_run) && ($now < ($todays_run + $event['Duration']))) {
				return $todays_run;
			}
		break;
		
		default:
			// no repeat
			if (($now >= $event['Start']) && ($now < $event['End'])) {
				return $event['Start'];
			}
		break;
	}
	
	// this event is not currently active
	return false;
}

function send_response($data) {
	// js response
	header('Content-Type: text/javascript');
	print $_GET['callback'] . '(' . json_encode($data) . ');';
	exit();
}

?>
