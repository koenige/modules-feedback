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
 * Insert further mail headers after successful insert
 * 
 * @param array $ops
 */
function mf_feedback_forminsert($ops) {
	wrap_include_files('zzform/batch', 'mail');
	// are there existing headers in record?
	$existing_headers = [];
	foreach ($ops['return'] as $index => $table) {
		if ($table['table'] !== wrap_db_prefix('/*_PREFIX_*/mails_headers')) continue;
		$existing_headers[] = $ops['record_new'][$index]['header_field_category_id'];
	}
	// go through all headers, add to database, not the existing ones
	foreach (wrap_static('zzform', 'mail_headers') as $header => $body) {
		$header = strtolower($header);
		if (in_array(wrap_category_id('mail-headers/'.$header), $existing_headers)) continue;
		mf_mail_add_header_db($header, $body, $ops['id']);
	}
}
