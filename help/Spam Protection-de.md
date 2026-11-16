<!--
# feedback module
# Help: Spam protection, German
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

# Spam-Schutz

Das Feedback-Modul wertet eine Einsendung als Spam, wenn eine der folgenden
Prüfungen fehlschlägt. Das Formular wird mit einem Hinweis erneut angezeigt,
nichts wird versendet, und der Versuch wird als Hinweis »Potential Spam Mail«
zusammen mit dem Grund und den gesendeten Daten protokolliert.

Die Inhaltsprüfungen (Spam-Phrasen, Links, URL-Kürzer, User-Agent) liegen in
`feedback/spam.inc.php` und können auch von anderen Formularen genutzt werden,
siehe [Andere Formulare](#andere-formulare).

## Inhaltsprüfungen

Diese Prüfungen betrachten den Text jedes Feldes.

- **Spam-Phrasen**: Der Text enthält eine Phrase aus
`configuration/feedback-spam-phrases.tsv`, z. B. `<a href=`, `[url=`,
`<iframe` oder `MIME-Version:`. Groß- und Kleinschreibung spielt keine Rolle.
Die Liste lässt sich pro Website mit einer gleichnamigen Datei im
Custom-Ordner erweitern. Einstellung: `feedback_spam_phrases` (Voreinstellung:
an).
- **Zu viele Links**: Der Text enthält mehr `http://`- oder `https://`-Links
als erlaubt. Einstellung: `feedback_max_http_links` (Voreinstellung: 5; `0`
schaltet die Prüfung aus).
- **URL-Kürzer**: Der Text enthält einen Link zu einem URL-Kürzer aus
`configuration/url-shorteners.tsv`, z. B. `https://bit.ly/`. Im
Feedback-Formular wird nur das Nachrichtenfeld geprüft. Einstellung:
`feedback_reject_url_shorteners` (Voreinstellung: an).
- **User-Agent**: Der User-Agent des Browsers enthält ein doppeltes
Anführungszeichen `"`, das normale Browser nie senden. Einstellung:
`feedback_spam_user_agent` (Voreinstellung: an).

## Prüfungen nur für das Feedback-Formular

Diese Prüfungen nutzen versteckte Felder und Werte des Feedback-Formulars
selbst.

- **Array-Werte**: Ein Feld wurde als Array statt als einzelner Wert gesendet.
- **Nur ein Wort**: Die Nachricht besteht aus einem einzigen Wort, ohne
Leerzeichen oder Zeilenumbruch.
- **Referer**: Die Seite mit dem Formular wurde nicht über einen Link
erreicht. Der Referer wird auf die Markierung aus
`feedback_spam_referer_marker` gesetzt (Voreinstellung: `Hi!`), wenn
	- der Referer beim ersten Aufruf die Formularseite selbst ist,
	- der Referer keine gültige URL ist,
	- der Referer nur der Hostname ohne abschließenden Schrägstrich ist,
	- der Referer `http` verwendet, obwohl die Website `https` verlangt.
- **Versteckte Felder**: `feedback_domain` muss den Hostnamen der Website
enthalten, `feedback_status` muss `sent` sein.
- **Zu schnell**: Das Formular wurde zu kurz nach dem Anzeigen abgeschickt.
Die Mindestzeit ist `feedback_write_min_seconds`; ohne Einstellung 5 Sekunden,
und bei der ersten Einsendung mindestens eine Sekunde pro 20 Zeichen der
Nachricht, sodass eingefügte Texte einen zweiten Versuch brauchen.
- **Code**: Das versteckte Feld `code`, ein Hash der Referer-URL, muss zur
gesendeten URL passen.
- **Formular-Hash**: Mit `feedback_logfile_hashes` (Voreinstellung: an)
erhält jedes Formular einen zufälligen Hash, der zusammen mit URL und
IP-Adresse protokolliert wird. Eine Einsendung ohne bekannten Hash, von einer
anderen URL oder einer anderen IP-Adresse wird nicht versendet; das Formular
zeigt stattdessen einen Hinweis auf ein vorübergehendes Problem. Einträge
gelten `feedback_logfile_hashes_validity_in_minutes` Minuten (Voreinstellung:
60).

## Andere Formulare

zzform-Formulare können die Inhaltsprüfungen über den Hook
`mf_feedback_spamcheck()` nutzen:

	if (wrap_package('feedback'))
		$zz['hooks']['before_insert'][] = 'mf_feedback_spamcheck';

Die Einstellung `feedback_spamcheck` (Voreinstellung: an) schaltet den Hook
für die ganze Website aus oder für ein einzelnes Formular: Parameter der
Veranstaltungskategorie, der Formularkategorie und der Veranstaltung gelten
in dieser Reihenfolge, ein Veranstaltungsparameter gewinnt. Beispiel:
`feedback_spamcheck=0` in den Veranstaltungsparametern.

Der Hook entfällt für angemeldete Benutzer. Er prüft jeden Textwert des neuen
Datensatzes auf Spam-Phrasen, zu viele Links und URL-Kürzer und versieht das
erste Feld mit einem Treffer mit einer Erklärung. Ein auffälliger User-Agent
stoppt das Einfügen ohne Feldhinweis. Alle Treffer werden wie oben beschrieben
protokolliert.

Die einzelnen Prüfungen lassen sich auch direkt aufrufen, nach
`wrap_include('spam', 'feedback')`:

- `mf_feedback_spam_text($text)`: Spam-Phrasen und Links; liefert den Grund
oder eine leere Zeichenkette
- `mf_feedback_spam_url_shortener($text)`: liefert `true`, wenn ein URL-Kürzer
gefunden wurde
- `mf_feedback_spam_user_agent()`: liefert den Grund oder eine leere
Zeichenkette
- `mf_feedback_spam_log($reason, $field_name)`: protokolliert eine Einsendung;
`$field_name` ist optional und stellt dieses POST-Feld im Protokoll voran
