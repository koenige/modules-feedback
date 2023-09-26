<?php

/**
 * feedback module
 * Feedback form
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/feedback
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2014, 2016-2023 Gustaf Mossakowski
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
	if (isset($_GET['another'])) {
		$page['meta'][] = ['name' => 'robots', 'content' => 'noindex'];
		wrap_setting('cache', false); // no need to cache second mail pages
	}
	
	$form = [];
	$form['spam'] = false;
	$form['mailonly'] = !empty($setting['mailonly']) ? true : false;
	$form['mailcopy'] = !empty($setting['mailcopy']) ? true : false;
	$form['form_lead'] = $setting['form_lead'] ?? '';

	// Read form data, test if spam
	$fields = ['feedback', 'contact', 'sender', 'url'];
	if (!empty($setting['extra_fields']))
		$fields = array_merge($fields, $setting['extra_fields']);

	$rejected = wrap_tsv_parse('feedback-spam-phrases');
	
	foreach ($fields as $field) {
		$form[$field] = (!empty($_POST[$field]) ? trim($_POST[$field]) : '');
		if ($form[$field] AND !$form['spam']) {
			foreach ($rejected as $word) {
				$spam = strpos($form[$field], $word);
				if ($spam === false) continue;
				$form['spam'] = true;
				break;
			}
		}
	}
	// message just one word? not enough
	if (!empty($_POST) AND !strstr($form['feedback'], ' ')) {
		$form['spam'] = true;
		$form['one_word_only'] = true;
	}
	if (!empty($_POST['url']) AND $_POST['url'] === 'Hi!') $form['spam'] = true;
	if (!empty($_POST)) {
		// check for some simple hidden fields
		if (empty($_POST['feedback_domain'])) $form['spam'] = true;
		elseif ($_POST['feedback_domain'] !== wrap_setting('hostname')) $form['spam'] = true;
		elseif (empty($_POST['feedback_status'])) $form['spam'] = true;
		elseif ($_POST['feedback_status'] !== 'sent') $form['spam'] = true;
		if (empty($_POST['status'])) $form['spam'] = true;
		else {
			$form['status'] = $_POST['status'];
			$check = mod_feedback_feedback_checktime($form['status'], mb_strlen($form['feedback']));
			if (!$check) $form['spam'] = true;
		}
		$form['repost'] = mod_feedback_feedback_settime();
	} else {
		$form['status'] = mod_feedback_feedback_settime();
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
		if (empty($_POST) AND $form['url'] === wrap_setting('host_base').wrap_setting('request_uri') AND !array_key_exists('another', $_GET)) {
			// page does not link itself, therefore referer = request is impossible
			$form['url'] = 'Hi!';
		} elseif ($form['url'] !== 'Hi!')  {
			$referer = parse_url($form['url']);
			if (empty($referer['scheme'])) {
				// incorrect referer URL
				wrap_error(sprintf('Potential SPAM mail because referer is set to %s', $form['url']));
				$form['url'] = 'Hi!';
			} else {
				if ($form['url'] === sprintf('%s://%s', $referer['scheme'], $referer['host'])) {
					// missing trailing slash
					$form['url'] = 'Hi!';
				} elseif (!empty(wrap_setting('canonical_hostname'))
					AND in_array('/', wrap_setting('https_urls'))
					AND !empty($referer['path'])
					AND $referer['path'] !== parse_url(wrap_setting('request_uri'), PHP_URL_PATH)) // no https redirect
				{
					// missing https, although it's required for the site?
					if ($referer['scheme'] === 'http' AND $referer['host'] === wrap_setting('canonical_hostname')) {
						$form['url'] = 'Hi!';
					} elseif (substr(wrap_setting('canonical_hostname'), 0, 4) === 'www.'
						AND $referer['scheme'] === 'http'
						AND $referer['host'] === substr(wrap_setting('canonical_hostname'), 4))
					{
						$form['url'] = 'Hi!';
					}
				}
			}
		}
		$form['code'] = mod_feedback_feedback_code($form['url']);
	}

	$form['wrong_e_mail'] = false;
	$e_mail_valid = false;
	$form['send_copy'] = ($form['mailcopy'] AND !empty($_POST['mailcopy']) AND $_POST['mailcopy'] === 'on') ? true : false;
	if (wrap_mail_valid($form['contact'])) {
		$e_mail_valid = true;
		$sender_mail = $form['contact'];
	} elseif ($sender_mail = mod_feedback_feedback_extract_mail($form['contact'])) {
		$e_mail_valid = true;
	} elseif ($form['mailonly'] OR $form['send_copy']) {
		$form['wrong_e_mail'] = true;
	}

	// get user agent for mail
	$form['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	// no normal user agent has " in it, some spammers have
	if (strstr($form['user_agent'], '"')) $form['spam'] = true;

	// All form fields filled out? Send mail and say thank you
	if ($form['sender'] AND $form['contact'] AND $form['feedback']
		AND !$form['spam'] AND !$form['wrong_e_mail']) {

		$form['ip'] = wrap_setting('remote_ip');
		if ($form['url'] === 'Hi!') $form['url'] = ''; // remove spam marker
		
		$page['replace_db_text'] = true;
		if (!empty($setting['mailto'])) {
			$mail['to'] = $setting['mailto'];
		} elseif ($from_name = wrap_setting('own_name')) {
			$mail['to']['e_mail'] = wrap_setting('own_e_mail');
			$mail['to']['name'] = $from_name;
		} else {
			$mail['to'] = wrap_setting('own_e_mail');
		}
		if ($e_mail_valid) {
			$header = empty($setting['reply_to']) ? 'From' : 'Reply-To';
			$mail['headers'][$header]['e_mail'] = $sender_mail;
			$mail['headers'][$header]['name'] = $form['sender'];
		}
		if (!empty($setting['subject'])) {
			$mail['subject'] = $setting['subject'];
		} else {
			$mail['subject'] = sprintf(
				wrap_text('Feedback via %s'), wrap_setting('hostname')
			);
		}
		if (!empty($setting['no_mail_subject_prefix'])) {
			$old_mail_subject_prefix = wrap_setting('mail_subject_prefix');
			wrap_setting('mail_subject_prefix', false);
		}
		if (!empty($setting['extra_lead']))
			$form['extra_lead'] = $setting['extra_lead'];
		$mail['message'] = wrap_template('feedback-mail', $form, 'ignore positions');
		$mail['parameters'] = '-f '.wrap_setting('own_e_mail');
		$success = wrap_mail($mail);
		if ($success) {
			$form['mail_sent'] = true;
			if ($form['send_copy']) {
				$mail['headers']['From']['e_mail'] = $mail['to'];
				$mail['headers']['From']['name'] = wrap_setting('project');
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
		if (!empty($setting['no_mail_subject_prefix'])) {
			if (str_starts_with($old_mail_subject_prefix, '['))
				$old_mail_subject_prefix = '\\'.$old_mail_subject_prefix;
			wrap_setting('mail_subject_prefix', $old_mail_subject_prefix);
		}
	} elseif (!empty($_POST)) {
		// form incomplete or spam
		$page['replace_db_text'] = true;
		if (!$form['spam']) $form['more_info_necessary'] = true;
		else wrap_error('Potential Spam Mail: '.json_encode($_POST, true));
	}

	$page['query_strings'] = ['another'];
	$page['text'] = wrap_template('feedback', $form, 'ignore positions');
	return $page;
}

function mod_feedback_feedback_code($str) {
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
