<!--
# feedback module
# Help: Spam protection
#
# Part of »Zugzwang Project«
# https://www.zugzwang.org/modules/feedback
#
# @author Gustaf Mossakowski <gustaf@koenige.org>
# @copyright Copyright © 2026 Gustaf Mossakowski
# @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
#
# Variables
# audience = admin
# audience = programmer
-->

# Spam protection

The feedback module marks a submission as spam if one of the checks below
fails. The form is shown again with a notice, nothing is sent, and the
attempt is logged as notice »Potential Spam Mail« together with the reason
and the submitted data.

The content checks (spam phrases, links, URL shorteners, user agent) live in
`feedback/spam.inc.php` and can be used by other forms as well, see
[Other forms](#other-forms).

## Content checks

These checks look at the text of each field.

- **Spam phrases**: the text contains a phrase from
`configuration/feedback-spam-phrases.tsv`, e. g. `<a href=`, `[url=`,
`<iframe` or `MIME-Version:`. Case does not matter. The list can be
extended per site with a file of the same name in the custom folder.
Setting: `feedback_spam_phrases` (default: on).
- **Too many links**: the text contains more `http://` or `https://` links
than allowed. Setting: `feedback_max_http_links` (default: 5; `0` disables
the check).
- **URL shorteners**: the text contains a link to a URL shortener from
`configuration/url-shorteners.tsv`, e. g. `https://bit.ly/`. In the feedback
form, only the message field is checked. Setting:
`feedback_reject_url_shorteners` (default: on).
- **User agent**: the browser’s user agent contains a double quote `"`,
which normal browsers never send. Setting: `feedback_spam_user_agent`
(default: on).

## Checks of the feedback form only

These checks rely on hidden fields and values of the feedback form itself.

- **Array values**: a field was sent as an array instead of a single value.
- **One word only**: the message consists of a single word, without space
or line break.
- **Referer**: the page with the form was not reached via a link. The
referer is set to the marker from `feedback_spam_referer_marker` (default:
`Hi!`) if
	- the referer is the form page itself on the first request,
	- the referer is no valid URL,
	- the referer is just the host name without trailing slash,
	- the referer uses `http` although the site requires `https`.
- **Hidden fields**: `feedback_domain` must contain the host name of the
site, `feedback_status` must be `sent`.
- **Too fast**: the form was sent too soon after it was shown. The minimum
time is `feedback_write_min_seconds`; if not set, 5 seconds, and on the first
submission at least one second per 20 characters of the message, so pasted
texts need a second try.
- **Code**: the hidden field `code`, a hash of the referer URL, must match
the URL that was sent.
- **Form hash**: with `feedback_logfile_hashes` (default: on), each form
gets a random hash, logged together with URL and IP address. A submission
without a known hash, from a different URL or a different IP address is not
sent; the form shows a notice about a temporary problem instead. Entries are
valid for `feedback_logfile_hashes_validity_in_minutes` (default: 60).

## Other forms

zzform forms can use the content checks via the hook
`mf_feedback_spamcheck()`:

	if (wrap_package('feedback'))
		$zz['hooks']['before_insert'][] = 'mf_feedback_spamcheck';

The hook is skipped for logged-in users. It checks every text value of the
new record for spam phrases, too many links and URL shorteners and marks the
first field with a match with an explanation. A suspicious user agent stops
the insert without a field notice. All matches are logged as described above.

The single checks can also be called directly after
`wrap_include('spam', 'feedback')`:

- `mf_feedback_spam_text($text)`: spam phrases and links; returns the reason
or an empty string
- `mf_feedback_spam_url_shortener($text)`: returns `true` if a URL shortener
is found
- `mf_feedback_spam_user_agent()`: returns the reason or an empty string
- `mf_feedback_spam_log($reason, $field_name)`: logs a submission;
`$field_name` is optional and puts this POST field first in the log
