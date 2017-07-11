<?php

/**
 * feedback module
 * Feedback form
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/modules/feedback
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2014, 2016-2017 Gustaf Mossakowski
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
 */
function mod_feedback_feedback($vars, $setting) {
	global $zz_conf;
	global $zz_setting;

	$zz_setting['mail_with_signature'] = false;
	$zz_setting['extra_http_headers'][] = 'X-Frame-Options: Deny';
	$zz_setting['extra_http_headers'][] = "Content-Security-Policy: frame-ancestors 'self'";

	$form = [];
	$form['spam'] = false;
	$form['mailonly'] = !empty($setting['mailonly']) ? true : false;
	$form['mailcopy'] = !empty($setting['mailcopy']) ? true : false;

	// Read form data, test if spam
	$fields = ['feedback', 'contact', 'sender', 'url'];
	if (!empty($setting['extra_fields'])) {
		$fields = array_merge($fields, $setting['extra_fields']);
	}
	$rejected = ['<a href=', '[url=', '[link=', '??????'];
	if (file_exists($file = $zz_setting['custom_wrap_dir'].'/feedback-spam-phrases.txt')) {
		// add local spam phrases, setting these globally could mark too many mails
		// that are valid
		$data = file($file);
		foreach ($data as $line) {
			if (substr($line, 0, 1) === '#') continue;
			if (!trim($line)) continue;
			$rejected[] = trim($line);
		}
	}
	foreach ($fields as $field) {
		$form[$field] = (!empty($_POST[$field]) ? $_POST[$field] : '');
		if ($form[$field] AND !$form['spam']) {
			foreach ($rejected as $word) {
				$spam = strpos($form[$field], $word);
				if ($spam !== false) $form['spam'] = true;
			}
		}
	}
	if (empty($form['url'])) {
		$form['url'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
	}
	if (!empty($form['url'])) {
		// code to check if referer is set via HTTP_REFERER or sent from bot
		if (!empty($_POST['code'])) {
			if ($_POST['code'] !== mod_feedback_feedback_code($form['url'])) {
				$form['spam'] = true;
			}
		}
		$form['code'] = mod_feedback_feedback_code($form['url']);
	}

	$form['wrong_e_mail'] = false;
	$e_mail_valid = false;
	$form['send_copy'] = ($form['mailcopy'] AND !empty($_POST['mailcopy']) AND $_POST['mailcopy'] === 'on') ? true : false;
	if (wrap_mail_valid($form['contact'])) {
		$e_mail_valid = true;
	} elseif ($form['mailonly'] OR $form['send_copy']) {
		$form['wrong_e_mail'] = true;
	}

	// All form fields filled out? Send mail and say thank you
	if ($form['sender'] AND $form['contact'] AND $form['feedback']
		AND !$form['spam'] AND !$form['wrong_e_mail']) {

		$form['ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		$form['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
		
		$page['replace_db_text'] = true;
		$mail['to'] = !empty($setting['mailto']) ? $setting['mailto'] : $zz_setting['own_e_mail'];
		if ($e_mail_valid) {
			$mail['headers']['From']['e_mail'] = $form['contact'];
			$mail['headers']['From']['name'] = $form['sender'];
		}
		$mail['subject'] = sprintf(
			wrap_text('Feedback via %s'), $zz_setting['hostname']
		);
		$mail['message'] = wrap_template('feedback-mail', $form, 'ignore positions');
		$mail['parameters'] = '-f '.$zz_setting['own_e_mail'];
		$success = wrap_mail($mail);
		if ($success) {
			$form['mail_sent'] = true;
			if ($form['send_copy']) {
				$mail['headers']['From']['e_mail'] = $mail['to'];
				$mail['headers']['From']['name'] = $zz_conf['project'];
				$mail['to'] = [];
				$mail['to']['e_mail'] = $form['contact'];
				$mail['to']['name'] = $form['sender'];
				$mail['subject'] .= ' '.wrap_text('(Your Copy)');
				$form['copy'] = true;
				$mail['message'] = wrap_template('feedback-mail', $form, 'ignore positions');
				$success = wrap_mail($mail);
			}
		} else {
			$form['mail_error'] = true;
		}
	} elseif (!empty($_POST)) {
		// form incomplete or spam
		$page['replace_db_text'] = true;
		if (!$form['spam']) $form['more_info_necessary'] = true;
	}

	$page['text'] = wrap_template('feedback', $form, 'ignore positions');
	return $page;
}

function mod_feedback_feedback_code($str) {
	return substr(md5(str_rot13($str)), 0, 12);
}
