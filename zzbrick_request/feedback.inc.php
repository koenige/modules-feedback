<?php

/**
 * feedback module
 * Feedback form
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/modules/feedback
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2014, 2016 Gustaf Mossakowski
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

	$form = array();
	$form['spam'] = false;
	$form['mailonly'] = !empty($setting['mailonly']) ? true : false;
	$form['mailcopy'] = !empty($setting['mailcopy']) ? true : false;

	// Read form data, test if spam
	$fields = array('feedback', 'contact', 'sender');
	$rejected = array('<a href=', '[url=', '[link=');
	foreach ($fields as $field) {
		$form[$field] = (!empty($_POST[$field]) ? $_POST[$field] : '');
		if ($form[$field] AND !$form['spam']) {
			foreach ($rejected as $word) {
				$spam = strpos($form[$field], $word);
				if ($spam !== false) $form['spam'] = true;
			}
		}
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

		$form['referer'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
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
				$mail['to'] = array();
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
