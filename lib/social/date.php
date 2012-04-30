<?php
/**
 * Date helper.
 *
 * This helper is originally Kohana_Date in the Kohana Framework. This has been renamed and modified for Social's needs.
 *
 * @package    Kohana
 * @category   Helpers
 * @author     Kohana Team
 * @copyright  (c) 2007-2011 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Social_Date {

	// Second amounts for various time increments
	const YEAR = 31556926;
	const MONTH = 2629744;
	const WEEK = 604800;
	const DAY = 86400;
	const HOUR = 3600;
	const MINUTE = 60;

	/**
	 * Returns time difference between two timestamps, in human readable format.
	 * If the second timestamp is not given, the current time will be used.
	 * Also consider using [self::fuzzy_span] when displaying a span.
	 *
	 *     $span = self::span(60, 182, 'minutes,seconds'); // array('minutes' => 2, 'seconds' => 2)
	 *     $span = self::span(60, 182, 'minutes'); // 2
	 *
	 * @param   integer  timestamp to find the span of
	 * @param   integer  timestamp to use as the baseline
	 * @param   string   formatting string
	 * @return  string   when only a single output is requested
	 * @return  array    associative list of all outputs requested
	 */
	public static function span($remote, $local = NULL, $output = 'years,months,weeks,days,hours,minutes,seconds') {
		// Normalize output
		$output = trim(strtolower((string)$output));

		if (!$output) {
			// Invalid output
			return FALSE;
		}

		// Array with the output formats
		$output = preg_split('/[^a-z]+/', $output);

		// Convert the list of outputs to an associative array
		$output = array_combine($output, array_fill(0, count($output), 0));

		// Make the output values into keys
		extract(array_flip($output), EXTR_SKIP);

		if ($local === NULL) {
			// Calculate the span from the current time
			$local = time();
		}

		// Calculate timespan (seconds)
		$timespan = abs($remote - $local);

		if (isset($output['years'])) {
			$timespan -= self::YEAR * ($output['years'] = (int)floor($timespan / self::YEAR));
		}

		if (isset($output['months'])) {
			$timespan -= self::MONTH * ($output['months'] = (int)floor($timespan / self::MONTH));
		}

		if (isset($output['weeks'])) {
			$timespan -= self::WEEK * ($output['weeks'] = (int)floor($timespan / self::WEEK));
		}

		if (isset($output['days'])) {
			$timespan -= self::DAY * ($output['days'] = (int)floor($timespan / self::DAY));
		}

		if (isset($output['hours'])) {
			$timespan -= self::HOUR * ($output['hours'] = (int)floor($timespan / self::HOUR));
		}

		if (isset($output['minutes'])) {
			$timespan -= self::MINUTE * ($output['minutes'] = (int)floor($timespan / self::MINUTE));
		}

		// Seconds ago, 1
		if (isset($output['seconds'])) {
			$output['seconds'] = $timespan;
		}

		if (count($output) === 1) {
			// Only a single output was requested, return it
			return array_pop($output);
		}

		// Return array
		return $output;
	}

	/**
	 * Returns a formatted span.
	 *
	 * @static
	 * @param  string  $remote
	 * @param  string  $local
	 * @return string
	 */
	public static function span_formatted($remote, $local = NULL) {
		if ($local === NULL) {
			$local = current_time('timestamp', 1);
		}

		$span = self::span($remote, $local);
		$timespan = abs($remote - $local);

		// Years
		if (!empty($span['years'])) {
			if ($span['years'] == '1') {
				return __('1 year', 'social');
			}
			else {
				return sprintf(__('%s years', 'social'), $span['years']);
			}
		}

		// Months
		if (!empty($span['months'])) {
			if ($span['months'] == '1') {
				return __('1 month', 'social');
			}
			else {
				return sprintf(__('%s months', 'social'), $span['months']);
			}
		}

		// Weeks
		if (!empty($span['weeks'])) {
			if ($span['weeks'] == '1') {
				return __('1 week', 'social');
			}
			else {
				return sprintf(__('%s weeks', $span['weeks']), $span['weeks']);
			}
		}

		// Days
		if (!empty($span['days'])) {
			if ($span['days'] == '1') {
				return __('1 day', 'social');
			}
			else {
				return sprintf(__('%s days', 'social'), $span['days']);
			}
		}

		// Hours
		$hours = '';
		if (!empty($span['hours'])) {
			if ($span['hours'] == '1') {
				$hours = __('1 hour', 'social');
			}
			else {
				$hours = sprintf(__('%s hours', 'social'), $span['hours']);
			}
		}

		// Minutes
		$minutes = '';
		if (!empty($span['minutes'])) {
			if ($span['minutes'] == '1') {
				$minutes = __('1 minute', 'social');
			}
			else {
				$minutes = sprintf(__('%s minutes', 'social'), $span['minutes']);
			}
		}

		// Seconds
		if (empty($hours) and empty($minutes)) {
			if (!empty($span['seconds'])) {
				if ($span['seconds'] == '1') {
					return __('1 second', 'social');
				}
				else {
					return sprintf(__('%s seconds', 'social'), $span['seconds']);
				}
			}
		}

		if (!empty($hours) and !empty($minutes)) {
			return $hours.' '.$minutes;
		}
		else if (!empty($hours)) {
			return $hours;
		}
		else {
			return $minutes;
		}
	}

	/**
	 * Returns a formatted span for comments. Uses absolute date after 1 year.
	 *
	 * @static
	 * @param  string  $remote
	 * @param  string  $local
	 * @return string
	 */
	public static function span_comment($remote, $local = NULL) {
		if ($local === NULL) {
			$local = current_time('timestamp', 1);
		}

		$span = self::span($remote, $local);
		$timespan = abs($remote - $local);

		// Years
		if (!empty($span['years'])) {
			return get_comment_date();
		}

		// Months
		if (!empty($span['months'])) {
			if ($span['months'] == '1') {
				return __('1 month ago', 'social');
			}
			else {
				return sprintf(__('%s months ago', 'social'), $span['months']);
			}
		}

		// Weeks
		if (!empty($span['weeks'])) {
			if ($span['weeks'] == '1') {
				return __('1 week ago', 'social');
			}
			else {
				return sprintf(__('%s weeks ago', $span['weeks']), $span['weeks']);
			}
		}

		// Days
		if (!empty($span['days'])) {
			if ($span['days'] == '1') {
				return __('1 day', 'social');
			}
			else {
				return sprintf(__('%s days ago', 'social'), $span['days']);
			}
		}

		// Hours
		$hours = '';
		if (!empty($span['hours'])) {
			if ($span['hours'] == '1') {
				$hours = __('1 hour', 'social');
			}
			else {
				$hours = sprintf(__('%s hours', 'social'), $span['hours']);
			}
		}

		// Minutes
		$minutes = '';
		if (!empty($span['minutes'])) {
			if ($span['minutes'] == '1') {
				$minutes = __('1 minute', 'social');
			}
			else {
				$minutes = sprintf(__('%s minutes', 'social'), $span['minutes']);
			}
		}

		// Seconds
		if (empty($hours) and empty($minutes)) {
			return __('just now', 'social');
		}

		if (!empty($hours)) {
			if ($span['hours'] > 1) {
				return sprintf(__('%s ago', 'social'), $hours);
			}
			else {
				return sprintf(__('%s ago', 'social'), $hours.' '.$minutes);
			}
		}
		else {
			return sprintf(__('%s ago', 'social'), $minutes);
		}
	}

	/**
	 * Returns the difference between a time and now in a "fuzzy" way.
	 * Displaying a fuzzy time instead of a date is usually faster to read and understand.
	 *
	 *     $span = self::fuzzy_span(time() - 10); // "moments ago"
	 *     $span = self::fuzzy_span(time() + 20); // "in moments"
	 *
	 * A second parameter is available to manually set the "local" timestamp,
	 * however this parameter shouldn't be needed in normal usage and is only
	 * included for unit tests
	 *
	 * @param   integer  "remote" timestamp
	 * @param   integer  "local" timestamp, defaults to time()
	 * @return  string
	 */
	public static function fuzzy_span($timestamp, $local_timestamp = NULL) {
		$local_timestamp = ($local_timestamp === NULL) ? time() : (int)$local_timestamp;

		// Determine the difference in seconds
		$offset = abs($local_timestamp - $timestamp);

		if ($offset <= self::MINUTE) {
			$span = __('moments', 'social');
		}
		elseif ($offset < (self::MINUTE * 20))
		{
			$span = __('a few minutes', 'social');
		}
		elseif ($offset < self::HOUR)
		{
			$span = __('less than an hour', 'social');
		}
		elseif ($offset < (self::HOUR * 4))
		{
			$span = __('a couple of hours', 'social');
		}
		elseif ($offset < self::DAY)
		{
			$span = __('less than a day', 'social');
		}
		elseif ($offset < (self::DAY * 2))
		{
			$span = __('about a day', 'social');
		}
		elseif ($offset < (self::DAY * 4))
		{
			$span = __('a couple of days', 'social');
		}
		elseif ($offset < self::WEEK)
		{
			$span = __('less than a week', 'social');
		}
		elseif ($offset < (self::WEEK * 2))
		{
			$span = __('about a week', 'social');
		}
		elseif ($offset < self::MONTH)
		{
			$span = __('less than a month', 'social');
		}
		elseif ($offset < (self::MONTH * 2))
		{
			$span = __('about a month', 'social');
		}
		elseif ($offset < (self::MONTH * 4))
		{
			$span = __('a couple of months', 'social');
		}
		elseif ($offset < self::YEAR)
		{
			$span = __('less than a year', 'social');
		}
		elseif ($offset < (self::YEAR * 2))
		{
			$span = __('about a year', 'social');
		}
		elseif ($offset < (self::YEAR * 4))
		{
			$span = __('a couple of years', 'social');
		}
		elseif ($offset < (self::YEAR * 8))
		{
			$span = __('a few years', 'social');
		}
		elseif ($offset < (self::YEAR * 12))
		{
			$span = __('about a decade', 'social');
		}
		elseif ($offset < (self::YEAR * 24))
		{
			$span = __('a couple of decades', 'social');
		}
		elseif ($offset < (self::YEAR * 64))
		{
			$span = __('several decades', 'social');
		}
		else
		{
			$span = __('a long time', 'social');
		}

		if ($timestamp <= $local_timestamp) {
			// This is in the past
			return sprintf(__('% ago', 'social'), $span);
		}
		else
		{
			// This in the future
			return sprintf(__('in %s', 'social'), $span);
		}
	}

} // End Social_Date
