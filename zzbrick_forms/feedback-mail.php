<?php

/**
 * feedback module
 * Feedback form via mail module
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/feedback
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz = zzform_include('mails');

$zz['access'] = 'add_only';
$zz['title'] = '';
$zz['setting']['zzform_autofocus'] = false;

foreach ($zz['fields'] as $no => $field) {
	$fieldname = $field['field_name'] ?? $field['table_name'] ?? $field['table'];
	switch ($fieldname) {
		case 'mail':
			$zz['fields'][$no]['title'] = wrap_text('Message', ['context' => 'E-Mail']);
			$zz['fields'][$no]['show_title'] = false;
			$zz['fields'][$no]['rows'] = 14;
			break;
		case 'mail_date':
		case 'headers_sender':
		case 'headers_subject':
		case 'headers_recipients':
		default:
			$zz['fields'][$no]['type'] = 'hidden';
			$zz['fields'][$no]['hide_in_form'] = true;
			break;
	}
}

$no++;

$zz['fields'][$no]['separator_before'] = 'text <h2>'.wrap_text('How can we get in touch with you?').'</h2>';
$zz['fields'][$no]['field_name'] = '';
// evaluate local parameter mailonly=1
$zz['fields'][$no]['title'] = 'E-Mail or phone';
$zz['fields'][$no]['type'] = 'text';

$no++;

$zz['fields'][$no]['field_name'] = '';
$zz['fields'][$no]['title'] = 'Your Name';
$zz['fields'][$no]['type'] = 'text';


wrap_text_set('Add a record', 'Your Message');
wrap_text_set('Add record', 'Submit Message');
