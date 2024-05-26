<?php 

/**
 * feedback module
 * Database hooks for zzform
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/feedback
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Validate feedback form
 * 
 * @param array $ops
 * @return array
 */
function mf_feedback_formvalidate($ops) {
	$change = [];

	$validation_errors = wrap_static('zzform', 'errors');
	if ($validation_errors['one_word_only'])
		$change['no_validation'] = true;
	elseif ($validation_errors['spam'])
		$change['no_validation'] = true;
	elseif ($validation_errors['wrong_e_mail']) {
		$change['no_validation'] = true;
		wrap_static('zzform', 'errors', ['more_info_necessary' => true], 'add');
	} elseif (!empty($ops['no_validation']))
		wrap_static('zzform', 'errors', ['more_info_necessary' => true], 'add');
		
	return $change;
}

/**
 * Insert further mail headers after successful insert
 * 
 * @param array $ops
 */
function mf_feedback_forminsert($ops) {
	wrap_include('zzform/batch', 'mail');
	// are there existing headers in record?
	$existing_headers = [];
	foreach ($ops['return'] as $index => $table) {
		if ($table['table'] !== wrap_db_prefix('/*_PREFIX_*/mails_headers')) continue;
		$existing_headers[] = $ops['record_new'][$index]['header_field_category_id'];
	}
	// go through all headers, add to database, not the existing ones
	foreach (wrap_static('zzform', 'mail_headers') as $header => $body) {
		$header = strtolower($header);
		if ($header === 'message') {
			mf_mail_update_body_db($body, $ops['id']);
			continue;
		}
		if (in_array(wrap_category_id('mail-headers/'.$header), $existing_headers)) continue;
		mf_mail_add_header_db($header, $body, $ops['id']);
	}
	wrap_job(wrap_path('mail_send', $ops['id']));
}
