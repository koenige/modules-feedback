<?php

/**
 * feedback module
 * Feedback form
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/feedback
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2014, 2016-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Show a feedback form and send feedback via mail
 *
 * @param array $vars (-)
 * @param array $setting (via webpages)
 *		mailto=... send to a different e-mail address
 *		mailcopy=1 show field to send a copy of e-mail to sender
 *		mailonly=1 only allow e-mail address, no phone number
 *		reply_to=1 set sender of mail not as From but as Reply-To
 */
function mod_feedback_feedback($vars, $setting) {
	wrap_setting('mail_with_signature', false);
	wrap_setting_add('extra_http_headers', 'X-Frame-Options: Deny');
	wrap_setting_add('extra_http_headers', "Content-Security-Policy: frame-ancestors 'self'");
	
	$hook = wrap_hook($setting);
	
	// sent receipt?
	if (array_key_exists('sent', $_GET)) {
		$page['meta'][] = ['name' => 'robots', 'content' => 'noindex'];
		wrap_setting('cache', false); // no need to cache sent return page
		$form['mail_sent'] = true;
		$page['query_strings'] = ['sent'];
		$page['text'] = wrap_template('feedback', $form, 'ignore positions');
		return $page;
	}

	$extra_fields = $setting['extra_fields'] ?? [];
	$field_phone = $setting['field_phone'] ?? wrap_setting('feedback_field_phone');
	if ($field_phone AND !in_array('phone', $extra_fields)) $extra_fields[] = 'phone';
	$form = mod_feedback_feedback_fields($extra_fields);
	$form['mail_error'] = mod_feedback_feedback_hash_fail();
	$form['feedback_hash'] = mod_feedback_feedback_hash_create();
	$form['spam'] = mod_feedback_feedback_spam($form);
	$form['url_shortener'] = mod_feedback_feedback_urlshort($form);

	$form['mail_only'] = $setting['mailonly'] ?? wrap_setting('feedback_mail_only');
	$form['mail_copy'] = $setting['mailcopy'] ?? wrap_setting('feedback_mail_copy');
	$form['field_contact_required'] = $setting['field_contact_required'] ?? wrap_setting('feedback_field_contact_required');
	$form['field_phone'] = $field_phone;
	if ($form['field_phone']) $form['mail_only'] = true; // label contact field with mail
	$form['field_phone_required'] = $setting['field_phone_required'] ?? wrap_setting('feedback_field_phone_required');
	$form['form_lead'] = $setting['form_lead'] ?? '';
	
	if ($_SERVER['REQUEST_METHOD'] === 'POST')
		$form['repost'] = mod_feedback_feedback_settime();
	else
		$form['status'] = mod_feedback_feedback_settime();

	$form['url'] = mod_feedback_feedback_referer($form['url']);
	$form['code'] = mod_feedback_feedback_code($form['url']);

	$form['wrong_e_mail'] = false;
	$form['e_mail_valid'] = false;
	$form['send_copy'] = ($form['mail_copy'] AND !empty($_POST['mail_copy']) AND $_POST['mail_copy'] === 'on') ? true : false;
	if (!$form['field_contact_required'] AND !$form['contact']) {
		// .. okay, no mail required, do not check it
		$form['sender_mail'] = '';
	} elseif (wrap_mail_valid($form['contact'])) {
		$form['e_mail_valid'] = true;
		$form['sender_mail'] = $form['contact'];
	} elseif ($form['sender_mail'] = mod_feedback_feedback_extract_mail($form['contact'])) {
		$form['e_mail_valid'] = true;
	} elseif ($form['mail_only'] OR $form['send_copy']) {
		if ($_SERVER['REQUEST_METHOD'] === 'POST')
			$form['wrong_e_mail'] = true;
	}

	// get user agent for mail
	$form['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
	// no normal user agent has " in it, some spammers have
	if (strstr($form['user_agent'], '"'))
		$form['spam'] = wrap_text('User Agent with quotes detected: %s.', ['values' => wrap_html_escape($form['user_agent'])]);
	
	$form['mail'] = mod_feedback_feedback_headers($form, $setting);

	// save feedback mail in database?
	$form_recheck = false;
	if (wrap_setting('feedback_mail_db')) {
		$form['upload'] = mod_feedback_feedback_upload($setting);
		$form['upload_folder'] = $setting['upload_folder'] ?? '';
		$result = brick_format('%%% forms feedback-mail %%%', $form);
		$form['form'] = $result['text'];
		if (!empty($_POST)) $form_recheck = true;
		$validation_errors = wrap_static('zzform', 'errors');
		if (!empty($validation_errors['more_info_necessary']))
			$form['more_info_necessary'] = true;
	} else {
		// All form fields filled out? Send mail and say thank you
		if (mod_feedback_feedback_complete($form)) {
			if ($hook['finish']) $form = $hook['finish']($form, $vars);
			$mail_sent = mod_feedback_feedback_mail($form, $setting);
			if ($mail_sent) {
				mod_feedback_feedback_hash_delete();
				wrap_redirect_change('?sent');
			}
			$form['mail_error'] = true;
			$form_recheck = true;
		} elseif (!empty($_POST)) {
			if (!$form['spam'] AND !$form['mail_error']) $form['more_info_necessary'] = true;
			$form_recheck = true;
		}
	}
	if ($form_recheck) {
		// form incomplete or spam
		$page['replace_db_text'] = true;
		if ($form['spam']) mod_feedback_feedback_log($form);
		elseif ($form['mail_error']) wrap_error('Mail was not sent at first try: '.json_encode($_POST, true));
	}
	$page['text'] = wrap_template('feedback', $form, 'ignore positions');
	return $page;
}

/**
 * set form fields and get data from POST
 *
 * @param array $extra_fields
 * @return array
 */
function mod_feedback_feedback_fields($extra_fields = []) {
    $form = [];
	$fields = wrap_setting('feedback_fields');
	if (wrap_setting('feedback_mail_db')) {
		$form['feedback_field_name'] = 'mail';
		$index = array_search('feedback', $fields);
		if (!$index !== NULL)
			unset($fields[$index]);
		$fields[] = 'mail';
	} else {
		$form['feedback_field_name'] = 'feedback';
	}
	$fields = array_merge($fields, $extra_fields);
	foreach ($fields as $field) {
		$form[$field] = $_POST[$field] ?? '';
		if (is_array($form[$field])) {
			$form[$field] = '';
			continue;
		}
		if (!$form[$field]) continue;
		$form[$field] = trim($form[$field]);
	}
	return $form;
}

/**
 * check if it is a legitimate form sent
 *
 * @param array $form
 * @return string
 */
function mod_feedback_feedback_spam(&$form) {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') return '';
	if (wrap_setting('feedback_mail_db') AND $_POST === ['zz_html_fragment' => '1']) return '';

	$rejected = wrap_tsv_parse('feedback-spam-phrases');
	foreach ($form as $field => $field_value) {
		if (!$field_value) continue;
		if (is_array($field_value)) return wrap_text('Field %s has array as value.', ['values' => [$field]]);
		foreach ($rejected as $word) {
			$spam = stripos($field_value, $word);
			if ($spam === false) continue;
			return wrap_text('Spam phrase `%s` found.', ['values' => [wrap_html_escape($word)]]);
		}
		if (wrap_setting('feedback_max_http_links')
			AND preg_match_all('/http[s]*:\/\//i', $field_value, $count))
			if (count($count[0]) > wrap_setting('feedback_max_http_links'))
				return wrap_text('Too many HTTP Links found (%d).', ['values' => [count($count[0])]]);
	}

	// message just one word? not enough
	if (!strstr($form[$form['feedback_field_name']], ' ') AND !strstr($form[$form['feedback_field_name']], "\n")) {
		$form['one_word_only'] = true;
		return wrap_text('Forms are accepted only with more than one word.');
	}

	// wrong referer?	
	if ($form['url'] === wrap_setting('feedback_spam_referer_marker'))
		return wrap_text('Webpage with contact form was accessed directly.');

	// check for some simple hidden fields
	if (empty($_POST['feedback_domain']))
		return wrap_text('Feedback domain is missing.');
	if ($_POST['feedback_domain'] !== wrap_setting('hostname'))
		return wrap_text('Feedback domain wrong (%s).', ['values' => [wrap_html_escape($_POST['feedback_domain'])]]);
	if (empty($_POST['feedback_status']))
		return wrap_text('Feedback status is missing.');
	if ($_POST['feedback_status'] !== 'sent')
		return wrap_text('Feedback status is wrong: %s.', ['values' => [wrap_html_escape($_POST['feedback_status'])]]);
	if (!$form['status'])
		return wrap_text('Form status is missing.');
	if (!mod_feedback_feedback_checktime($form['status'], mb_strlen($form[$form['feedback_field_name']])))
		return wrap_text('Form was submitted too fast.');

	// code to check if referer is set via HTTP_REFERER or sent from bot
	if (!empty($_POST['code']) AND $_POST['code'] !== mod_feedback_feedback_code($form['url']))
		return wrap_text('Code is wrong: %s.', ['values' => wrap_html_escape($_POST['code'])]);

	return '';
}

/**
 * simple encoding to ensure that 'url' POST parameter is not changed manually
 *
 * @param string $str
 * @return string
 */
function mod_feedback_feedback_code($str) {
	if (!$str) return $str;
	return substr(md5(str_rot13($str)), 0, 12);
}

/**
 * set timestamp for feedback form, with super cheap encryption
 *
 * @return string
 * @todo use real encryption if necessary
 */
function mod_feedback_feedback_settime() {
	$time = time();
	$time = str_split($time);
	for ($i = 0; $i < count($time); $i++) {
		$chars[] = chr(100 + $time[$i]);
	}
	$time = implode('', $chars);
	return $time;
}

/**
 * feedback messages written in below 5 seconds are probably spam
 * show form again to resubmit
 *
 * @param string $time (encrypted)
 * @param int $characters
 * @return bool true = everything ok
 */
function mod_feedback_feedback_checktime($time, $characters) {
	$time = str_split($time);
	for ($i = 0; $i < count($time); $i++) {
		if (($number = ord($time[$i])) < 100) return false;
		$chars[] = $number - 100;
	}
	$time = implode('', $chars);
	$min_time = wrap_setting('feedback_write_min_seconds');
	if (!$min_time) {
		$min_time = 5;
		if (empty($_POST['repost'])) {
			// just for first POSTing, check if text was copied/pasted
			// or really written
			$time_to_write = floor($characters / 20);
			if ($time_to_write > $min_time)
				$min_time = $time_to_write;
		}
	}
	if (time() - $time < $min_time) {
		return false;
	}
	return true;
}

/**
 * set and check 'url' to referer and check if it is correct
 *
 * @param string $url
 * @return string
 */
function mod_feedback_feedback_referer($url) {
	if (!$url AND $_SERVER['REQUEST_METHOD'] === 'GET') $url = $_SERVER['HTTP_REFERER'] ?? '';
	if (!$url) return $url;

	if (empty($_POST) AND $url === wrap_setting('host_base').wrap_setting('request_uri'))
		// page does not link itself, therefore referer = request is impossible
		return wrap_setting('feedback_spam_referer_marker');

	if ($url === wrap_setting('feedback_spam_referer_marker')) return $url;

	$referer = parse_url($url);
	if (empty($referer['scheme']) OR empty($referer['host'])) {
		// incorrect referer URL
		wrap_error(sprintf('Potential SPAM mail because referer is set to %s', $url));
		return wrap_setting('feedback_spam_referer_marker');
	}
	if ($url === sprintf('%s://%s', $referer['scheme'], $referer['host']))
		// missing trailing slash
		return wrap_setting('feedback_spam_referer_marker');

	// check if no https redirect	
	if (!wrap_setting('canonical_hostname')) return $url;
	if (!in_array('/', wrap_setting('https_urls'))) return $url;
	if (empty($referer['path'])) return $url;
	if ($referer['path'] === parse_url(wrap_setting('request_uri'), PHP_URL_PATH)) return $url;
	
	// missing https, although it's required for the site?
	if ($referer['scheme'] === 'http' AND $referer['host'] === wrap_setting('canonical_hostname'))
		return wrap_setting('feedback_spam_referer_marker');
		
	if (substr(wrap_setting('canonical_hostname'), 0, 4) !== 'www.') return $url;
	if ($referer['scheme'] !== 'http') return $url;
	if ($referer['host'] !== substr(wrap_setting('canonical_hostname'), 4)) return $url;
	
	return wrap_setting('feedback_spam_referer_marker');
}

/**
 * extract e-mail from contact data (if sometimes people give phone and mail)
 *
 * @param string $contact
 * @return string
 */
function mod_feedback_feedback_extract_mail($contact) {
	if (!strstr($contact, '@')) return '';
	if (!strstr($contact, ' ')) return '';
	$contact = explode(' ', $contact);
	foreach ($contact as $part) {
		if (!strstr($part, '@')) continue;
		$part = trim($part, ',');
		if (wrap_mail_valid($part)) return $part;
	}
	return '';
}

/**
 * get/set feedback mail headers and body
 *
 * @param array $form
 * @param array $setting
 * @return array
 */
function mod_feedback_feedback_headers($form, $setting) {
	$mail = [];
	
	// Subject:
	if (!empty($form['subject']))
		$mail['subject'] = $form['subject'];
	elseif (!empty($setting['subject']))
		$mail['subject'] = $setting['subject'];
	else
		$mail['subject'] = wrap_text(
			'Feedback via %s from %s', ['values' => [wrap_setting('hostname'), $form['sender']]]
		);

	// To:
	if (!empty($setting['mailto'])) {
		$mail['to'] = $setting['mailto'];
	} elseif ($from_name = wrap_setting('own_name')) {
		$mail['to']['e_mail'] = wrap_setting('own_e_mail');
		$mail['to']['name'] = $from_name;
	} else {
		$mail['to'] = wrap_setting('own_e_mail');
	}

	// From: or Reply-To:
	if ($form['e_mail_valid']) {
	    $reply_to = $setting['reply_to'] ?? wrap_setting('feedback_reply_to') ?? false;
		$header = $reply_to ? 'Reply-To' : 'From';
		$mail[$header]['e_mail'] = $form['sender_mail'];
		$mail[$header]['name'] = $form['sender'];
	}
	
	// From:
	if (empty($mail['From'])) {
		$mail['From']['e_mail'] = wrap_setting('own_e_mail');
		$mail['From']['name'] = wrap_setting('project');
	}
	
	// Message body
	if ($form['url'] === wrap_setting('feedback_spam_referer_marker'))
		$form['url'] = ''; // remove spam marker
	if (!empty($setting['extra_lead']))
		$form['extra_lead'] = $setting['extra_lead'];
	if (wrap_setting('feedback_mail_db'))
		$form['feedback'] = $form['mail'];
	$mail['message'] = wrap_template('feedback-mail', $form, 'ignore positions');
	if ($form['send_copy']) {
		$form['copy'] = true;
		$mail['message_copy'] = wrap_template('feedback-mail', $form, 'ignore positions');
	}

	return $mail;
}

/**
 * send feedback mail
 *
 * @param array $form
 * @param array $setting
 * @return bool
 */
function mod_feedback_feedback_mail($form, $setting) {
	$mail = $form['mail'];
	// via headers
	$move_to_headers = ['From', 'Reply-To'];
	foreach ($move_to_headers as $header) {
		if (!array_key_exists($header, $mail)) continue;
		$mail['headers'][$header] = $mail[$header];
		unset($mail[$header]);
	}
	if (!empty($setting['no_mail_subject_prefix'])) {
		$old_mail_subject_prefix = wrap_setting('mail_subject_prefix');
		wrap_setting('mail_subject_prefix', false);
	}
	$mail['parameters'] = '-f '.wrap_setting('own_e_mail');
	$success = wrap_mail($mail);
	if ($success AND $form['send_copy']) {
		$mail['headers']['From']['e_mail'] = $mail['to'];
		$mail['headers']['From']['name'] = wrap_setting('project');
		$mail['to'] = [];
		$mail['to']['e_mail'] = $form['contact'];
		$mail['to']['name'] = $form['sender'];
		$mail['subject'] .= ' '.wrap_text('(Your Copy)');
		$mail['message'] = $mail['message_copy'];
		wrap_mail($mail);
	}
	if (!empty($setting['no_mail_subject_prefix'])) {
		if (str_starts_with($old_mail_subject_prefix, '['))
			$old_mail_subject_prefix = '\\'.$old_mail_subject_prefix;
		wrap_setting('mail_subject_prefix', $old_mail_subject_prefix);
	}
	return $success;
}

/**
 * check if upload of a file should be possible, return list of possible filetypes
 *
 * @param array $setting
 * @return array
 */
function mod_feedback_feedback_upload($setting) {
	if (empty($setting['upload_filetypes'])) return [];
	
	$filetypes = $setting['upload_filetypes'];
	$where = [];
	// check for placeholders
	foreach ($filetypes as $index => $filetype) {
		if (!str_ends_with($filetype, '*')) continue;
		$where[] = sprintf('filetype LIKE ("%s%%")', substr($filetype, 0, -1));
		unset($filetypes[$index]);
	}
	if ($filetypes)
		$where[] = sprintf('filetype IN ("%s")', implode('","', $filetypes));
	
	// get possible types from database
	$sql = 'SELECT filetype_id, filetype
		FROM filetypes
		WHERE (%s)';
	$sql = sprintf($sql, implode(') OR (', $where));
	return wrap_db_fetch($sql, 'filetype_id', 'key/value');
}

/**
 * check if a URL shortener is used
 *
 * @param array $form
 * @return bool
 */
function mod_feedback_feedback_urlshort($form) {
	if (!wrap_setting('feedback_reject_url_shorteners')) return false;
	$services = wrap_tsv_parse('url-shorteners');
	if (empty($form[$form['feedback_field_name']])) return false;
	foreach ($services as $service) {
		$service = sprintf('https://%s/', $service);
		if (strstr($form[$form['feedback_field_name']], $service)) return true;
	}
	return false;
}

/**
 * return a feedback hash
 *
 * @return string
 */
function mod_feedback_feedback_hash_create() {
	if (!wrap_setting('feedback_logfile_hashes')) return '';
	wrap_include('file', 'zzwrap');
	if (!empty($_POST['feedback_hash'])) return $_POST['feedback_hash'];
	$hash = wrap_random_hash(16);
	wrap_file_log('feedback/hashes', 'write', [time(), $hash, wrap_setting('remote_ip'), wrap_setting('request_uri')]);
	return $hash;
}

/**
 * check if feedback hash failed
 *
 * @return bool
 */
function mod_feedback_feedback_hash_fail() {
	if (!wrap_setting('feedback_logfile_hashes')) return false;
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
	wrap_include('file', 'zzwrap');

	if (empty($_POST['feedback_hash'])) return true;
	$logs = wrap_file_log('feedback/hashes');
	foreach ($logs as $index => $log) {
		if ($log['feedback_hash'] !== $_POST['feedback_hash']) continue;
		if ($log['request_uri'] !== wrap_setting('request_uri')) {
			$_POST['feedback_hash'] = NULL;
			return true;
		}
		if ($log['remote_ip'] !== wrap_setting('remote_ip'))  {
			$_POST['feedback_hash'] = NULL;
			return true;
		}
		return false;
	}
	$_POST['feedback_hash'] = NULL;
	return true;
}

/**
 * delete a feedback hash after mail was sent successfully
 *
 * @return bool
 */
function mod_feedback_feedback_hash_delete() {
	if (!wrap_setting('feedback_logfile_hashes')) return false;
	if (empty($_POST['feedback_hash'])) return false;
	wrap_include('file', 'zzwrap');
	wrap_file_log('feedback/hashes', 'delete', ['feedback_hash' => $_POST['feedback_hash']]);
}

/**
 * check if all required fields have been filled out
 * and there is no sign that this is an unsolicited mail
 *
 * @param array $form
 * @return bool true everything ok
 */
function mod_feedback_feedback_complete($form) {
	if (!$form['sender']) return false;
	if (!$form['contact'] AND $form['field_contact_required']) return false;
	if (empty($form['phone']) AND !empty($form['field_phone_required'])) return false;
	if (!$form[$form['feedback_field_name']]) return false;
	if ($form['spam']) return false;
	if ($form['wrong_e_mail']) return false;
	if ($form['url_shortener']) return false;
	if ($form['mail_error']) return false;
	return true;
}

/**
 * log suspicious mails
 *
 * @param array $form
 */
function mod_feedback_feedback_log($form) {
	$settings['log_post_data'] = false;
	// log feedback as first key
	$data[wrap_text('Error')] = 'Potential Spam Mail';
	$data[wrap_text('Reason')] = $form['spam'];
	$data[$form['feedback_field_name']] = $_POST[$form['feedback_field_name']] ?? [];
	$data += $_POST;
	wrap_error('[json]2 '.json_encode($data, true), E_USER_NOTICE, $settings);
}
