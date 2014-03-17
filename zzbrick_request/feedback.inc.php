<?php

/**
 * feedback module
 * Feedback form
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/modules/feedback
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2014 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_feedback_feedback($vars) {
	global $zz_conf;
	global $zz_setting;

	$form = array();
	$form['spam'] = false;

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

	// All form fields filled out? Send mail and say thank you
	if ($form['sender'] AND $form['contact'] AND $form['feedback']
		AND !$form['spam']) {
		
		$page['replace_db_text'] = true;
		$mail['to'] = $zz_setting['own_e_mail'];
		if (wrap_mail_valid($form['contact'])) {
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
