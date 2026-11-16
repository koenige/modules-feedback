<?php

/**
 * feedback module
 * Spam checks, usable for other forms as well
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/feedback
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2014, 2016-2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * check a text for spam phrases and too many links
 *
 * @param string $text
 * @return string reason why text looks like spam, empty if it does not
 */
function mf_feedback_spam_text($text) {
	static $rejected = NULL;
	if (!$text) return '';
	if ($rejected === NULL) $rejected = wrap_tsv_parse('feedback-spam-phrases');

	foreach ($rejected as $word) {
		if (stripos($text, $word) === false) continue;
		return wrap_text('Spam phrase `%s` found.', ['values' => [wrap_html_escape($word)]]);
	}

	if (!wrap_setting('feedback_max_http_links')) return '';
	$links = preg_match_all('/http[s]*:\/\//i', $text);
	if ($links <= wrap_setting('feedback_max_http_links')) return '';
	return wrap_text('Too many HTTP Links found (%d).', ['values' => [$links]]);
}

/**
 * check if a URL shortener is used in a text
 *
 * @param string $text
 * @return bool
 */
function mf_feedback_spam_url_shortener($text) {
	static $services = NULL;
	if (!$text) return false;
	if (!wrap_setting('feedback_reject_url_shorteners')) return false;
	if ($services === NULL) $services = wrap_tsv_parse('url-shorteners');

	foreach ($services as $service) {
		$service = sprintf('https://%s/', $service);
		if (str_contains($text, $service)) return true;
	}
	return false;
}

/**
 * check if user agent looks like spam
 *
 * @return string reason why user agent looks like spam, empty if it does not
 */
function mf_feedback_spam_user_agent() {
	$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
	// no normal user agent has " in it, some spammers have
	if (!str_contains($user_agent, '"')) return '';
	return wrap_text('User Agent with quotes detected: %s.', ['values' => [wrap_html_escape($user_agent)]]);
}

/**
 * log suspicious form submissions
 *
 * @param string $reason
 * @param string $field_name (optional) POST field to log first
 * @return void
 */
function mf_feedback_spam_log($reason, $field_name = '') {
	$settings['log_post_data'] = false;
	// set indices to keep sort order
	$data[wrap_text('Reason')] = $reason;
	if ($field_name)
		$data[$field_name] = $_POST[$field_name] ?? [];
	$data += $_POST;
	wrap_error([
		'Potential Spam Mail',
		['data' => $data, 'log_text_parts' => 2],
	], E_USER_NOTICE, $settings);
}

