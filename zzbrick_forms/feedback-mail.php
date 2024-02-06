<?php

/**
 * feedback module
 * Feedback form via mail module
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/feedback
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2023-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz = zzform_include('mails');

$zz['access'] = 'add_only';
$zz['title'] = '';
$zz['setting']['zzform_autofocus'] = false;

foreach ($zz['fields'] as $no => $field) {
	$fieldname = $field['field_name'] ?? $field['table_name'] ?? $field['table'];
	switch ($fieldname) {
		case 'mail_id':
			break;
		case 'mail':
			$zz['fields'][$no]['title'] = wrap_text('Message', ['context' => 'E-Mail']);
			$zz['fields'][$no]['show_title'] = false;
			$zz['fields'][$no]['rows'] = 14;
			break;
		case 'headers_subject':
			if (!$brick['parameter']['headers']['subject']) break;
			$zz['fields'][$no]['hide_in_form'] = true;
			break;
		case 'last_update':
		case 'headers_sender':
		case 'headers_recipients':
			$zz['fields'][$no]['hide_in_form'] = true;
			break;
		case 'mail_date':
		default:
			$zz['fields'][$no]['hide_in_form'] = true;
			$zz['fields'][$no]['type_detail'] = $zz['fields'][$no]['type'];
			$zz['fields'][$no]['type'] = 'hidden';
			break;
	}
}

$no++;
$zz['fields'][$no]['separator_before'] = 'text <h2>'.wrap_text('How can we get in touch with you?').'</h2>';
$zz['fields'][$no]['field_name'] = 'contact';
// @todo evaluate local parameter mailonly=1
$zz['fields'][$no]['title'] = 'E-Mail or phone';
$zz['fields'][$no]['type'] = 'text';
$zz['fields'][$no]['input_only'] = true;

$no++;
$zz['fields'][$no]['field_name'] = 'sender';
$zz['fields'][$no]['title'] = 'Your Name';
$zz['fields'][$no]['type'] = 'text';
$zz['fields'][$no]['input_only'] = true;

$no++;
$zz['fields'][$no]['field_name'] = 'url';
$zz['fields'][$no]['show_title'] = false;
$zz['fields'][$no]['type'] = 'display';
$zz['fields'][$no]['class'] = 'hidden';
$zz['fields'][$no]['required'] = false;
$zz['fields'][$no]['hidden_value'] = $brick['parameter']['url'];

$no++;
$zz['fields'][$no]['field_name'] = 'code';
$zz['fields'][$no]['show_title'] = false;
$zz['fields'][$no]['type'] = 'display';
$zz['fields'][$no]['class'] = 'hidden';
$zz['fields'][$no]['required'] = false;
$zz['fields'][$no]['hidden_value'] = $brick['parameter']['code'];

$no++;
$zz['fields'][$no]['field_name'] = 'status';
$zz['fields'][$no]['show_title'] = false;
$zz['fields'][$no]['type'] = 'display';
$zz['fields'][$no]['class'] = 'hidden';
$zz['fields'][$no]['hidden_value'] = $brick['parameter']['status'];

if (isset($brick['parameter']['repost'])) {
	$no++;
	$zz['fields'][$no]['field_name'] = 'repost';
	$zz['fields'][$no]['show_title'] = false;
	$zz['fields'][$no]['type'] = 'display';
	$zz['fields'][$no]['class'] = 'hidden';
	$zz['fields'][$no]['hidden_value'] = $brick['parameter']['repost'];
}

$no++;
$zz['fields'][$no]['field_name'] = 'feedback_domain';
$zz['fields'][$no]['show_title'] = false;
$zz['fields'][$no]['type'] = 'display';
$zz['fields'][$no]['class'] = 'hidden';
$zz['fields'][$no]['hidden_value'] = wrap_setting('hostname');

$no++;
$zz['fields'][$no]['field_name'] = 'feedback_status';
$zz['fields'][$no]['show_title'] = false;
$zz['fields'][$no]['type'] = 'display';
$zz['fields'][$no]['class'] = 'hidden';
$zz['fields'][$no]['hidden_value'] = 'sent';


$zz['record']['redirect']['successful_insert'] = wrap_setting('request_uri').'?sent';

$zz['hooks']['after_validation'][] = 'mf_feedback_formvalidate';
$zz['hooks']['after_insert'][] = 'mf_feedback_forminsert';

$zz['vars']['mail_headers'] = $brick['parameter']['headers'];
$zz['vars']['errors']['one_word_only'] = $brick['parameter']['one_word_only'] ?? false;
$zz['vars']['errors']['spam'] = $brick['parameter']['spam'] ?? false;
$zz['vars']['errors']['wrong_e_mail'] = $brick['parameter']['wrong_e_mail'] ?? false;


wrap_text_set('Add a record', 'Your Message');
wrap_text_set('Add record', 'Submit Message');
