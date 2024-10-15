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
	$identifier = zzform_field_identifier($field);
	switch ($identifier) {
		case 'mail_id':
			break;

		case 'mail':
			$zz['fields'][$no]['title'] = wrap_text('Message', ['context' => 'E-Mail']);
			$zz['fields'][$no]['show_title'] = false;
			$zz['fields'][$no]['rows'] = 14;
			break;

		case 'headers_subject':
			if (!$brick['parameter']['mail']['subject']) break;
			$zz['fields'][$no]['hide_in_form'] = true;
			break;

		case 'last_update':
		case 'headers_sender':
		case 'headers_recipients':
			$zz['fields'][$no]['hide_in_form'] = true;
			break;

		case 'attachments':
			// @todo use later?
			unset($zz['fields'][$no]);
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

if (!empty($brick['parameter']['upload'])) {
	$no++;
	$zz['fields'][$no] = zzform_include('media');
	$zz['fields'][$no]['type'] = 'foreign_table';
	$zz['fields'][$no]['title'] = 'Attachment';
	$zz['fields'][$no]['min_records'] = 1;
	$zz['fields'][$no]['max_records'] = 1;
	$zz['fields'][$no]['records_depend_on_upload'] = true;
	$foreign_id_field = $no;
	foreach ($zz['fields'][$no]['fields'] as $subno => $subfield) {
		$identifier = zzform_field_identifier($subfield);
		if (!$identifier) continue;
		switch ($identifier) {
			case 'main_medium_id':
				$zz['fields'][$no]['fields'][$subno]['type'] = 'hidden';
				$zz['fields'][$no]['fields'][$subno]['type_detail'] = 'select';
				if ($brick['parameter']['upload_folder'])
					$zz['fields'][$no]['fields'][$subno]['value'] = wrap_id('folders', $brick['parameter']['upload_folder']);
				$zz['fields'][$no]['fields'][$subno]['hide_in_form'] = true;
				break;

			case 'title':
				$zz['fields'][$no]['fields'][$subno]['hide_in_form'] = true;
				$zz['fields'][$no]['fields'][$subno]['dont_show_missing'] = true;
				break;

			case 'image':
				$zz['fields'][$no]['fields'][$subno]['image'][0]['required'] = false;
				$zz['fields'][$no]['fields'][$subno]['show_title'] = false;
				$zz['fields'][$no]['fields'][$subno]['title'] = 'Attachment';
				$zz['fields'][$no]['fields'][$subno]['input_filetypes'] = $brick['parameter']['upload'];
				break;

			case 'published':
				$zz['fields'][$no]['fields'][$subno]['type'] = 'hidden';
				$zz['fields'][$no]['fields'][$subno]['type_detail'] = 'select';
				$zz['fields'][$no]['fields'][$subno]['value'] = 'no';
				$zz['fields'][$no]['fields'][$subno]['hide_in_form'] = true;
				break;

			case 'thumb_filetype_id':
				// @todo support thumbnails, background operation needs to divert from contact form here
				unset($zz['fields'][$no]['fields'][$subno]['default']); // set to none
				// @todo on re-check form, some background thumbnail mechanism is triggered, look into it
				wrap_setting('zzform_upload_background_thumbnails', false);
				$zz['fields'][$no]['fields'][$subno]['hide_in_form'] = true;
				break;

			default:
				$zz['fields'][$no]['fields'][$subno]['hide_in_form'] = true;
				break;
		}
	}

	$no++;
	$zz['fields'][$no] = zzform_include('mails-media');
	$zz['fields'][$no]['type'] = 'subtable';
	$zz['fields'][$no]['show_title'] = false;
	$zz['fields'][$no]['min_records'] = 1;
	$zz['fields'][$no]['max_records'] = 1;
	foreach ($zz['fields'][$no]['fields'] as $subno => $subfield) {
		$identifier = zzform_field_identifier($subfield);
		if (!$identifier) continue;
		switch ($identifier) {
			case 'mail_id':
				$zz['fields'][$no]['fields'][$subno]['type'] = 'foreign_key';
				break;

			case 'medium_id':
				$zz['fields'][$no]['fields'][$subno]['type'] = 'foreign_id';
				$zz['fields'][$no]['fields'][$subno]['type_detail'] = 'select';
				$zz['fields'][$no]['fields'][$subno]['foreign_id_field'] = $foreign_id_field;
				$zz['fields'][$no]['fields'][$subno]['hide_in_form'] = true;
				break;

			case 'sequence':
				// show as hidden field so record is not ignored by zzform
				// @todo solve this in zzform() and remove this case
				$zz['fields'][$no]['fields'][$subno]['type'] = 'hidden';
				$zz['fields'][$no]['fields'][$subno]['value'] = 1;
				$zz['fields'][$no]['fields'][$subno]['class'] = 'hidden';
				break;

			default:
				$zz['fields'][$no]['fields'][$subno]['hide_in_form'] = true;
				break;
		}
	}
}

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
$zz['hooks']['successful_insert'][] = 'mf_feedback_forminsert';

$zz['vars']['mail_headers'] = $brick['parameter']['mail'];
$zz['vars']['errors']['one_word_only'] = $brick['parameter']['one_word_only'] ?? false;
$zz['vars']['errors']['spam'] = $brick['parameter']['spam'] ?? false;
$zz['vars']['errors']['wrong_e_mail'] = $brick['parameter']['wrong_e_mail'] ?? false;
$zz['vars']['errors']['url_shortener'] = $brick['parameter']['url_shortener'] ?? false;

// no :: are allowed in httpd usernames, so replace : with - for IPv6
wrap_setting('log_username', sprintf('%s (IP %s)', $_POST['sender'] ?? wrap_text('unknown'), str_replace(':', '-', wrap_setting('remote_ip'))));

wrap_text_set('Add a record', 'Your Message');
wrap_text_set('Add record', 'Submit Message');
