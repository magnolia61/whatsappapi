# whatsappapi

Adds a **Send WhatsApp** action to [CiviRules](https://lab.civicrm.org/extensions/civirules),
in the same spirit as the `smsapi` and `emailapi` extensions: pick a message template and a
provider, and the rule sends a WhatsApp message to the contact.

The sending itself is delegated to the
[whatsapp extension](https://lab.civicrm.org/extensions/whatsapp) (`Whatsapp.send`, APIv3), so
whatever provider that extension supports — Twilio, 360Dialog — works here too. This extension
only supplies the CiviRules glue.

Licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* CiviCRM 6.16+
* `org.civicoop.civirules`
* `whatsapp` (the extension that does the actual sending, with at least one provider configured)

## Getting started

1. Install and enable the extension. The action is registered automatically on install; if you
   see no **Send WhatsApp** action, check that `civirules` was installed first and re-run the
   upgrader.
2. Create or edit a rule under **Administer → Automation → CiviRules**.
3. Add the action **Send WhatsApp** and configure it:

   | Field | Notes |
   |---|---|
   | WhatsApp provider | from `civicrm_whatsapp_provider` |
   | Message template | any active, non-workflow message template |
   | Message type | `text` or `template` — see below |
   | Message sender | set this when the rule can fire without a logged-in user |
   | Alternative phone number | overrides the contact's own number |

## Message type matters

WhatsApp only accepts **free-form text** within 24 hours of the contact's own last message (the
customer service window). A rule usually fires outside that window — a reminder, a scheduled job
— and free-form text is then rejected by Meta.

For those cases choose **template**, which sends a provider-side approved template. At Twilio that
is a Content Template; put its SID (starting with `HX`) in the *Namespace* field of the WhatsApp
template, and the JSON variables in *token_example*. That mapping mirrors how the whatsapp
extension already uses those columns for 360Dialog, so no schema change is needed.

## Tokens

The message body comes from the selected message template, so tokens behave as they do in e-mail —
with one caveat: only **fully qualified** tokens are replaced. `{contact.first_name}` works,
`{first_name}` is left in the text verbatim.

### token_example en de Smarty-instelling horen bij elkaar

De variabelen-JSON in *token_example* gaat door dezelfde tokenvervanger als de berichttekst,
en dus ook door Smarty wanneer de rule op *Use Smarty* staat. De twee combinaties die werken:

* **Smarty uit** (`smarty=disable`) + token_example als **gewone JSON**:
  `{"1":"{contact.first_name}","2":"cid1={contact.contact_id}&{contact.checksum}"}`.
  Alleen CiviCRM-tokens, geen `{$...}`-variabelen. Dit is de standaardkeuze.
* **Smarty aan** (`smarty=use`) + token_example met **`{ldelim}`/`{rdelim}`** in plaats van
  letterlijke accolades, zodat Smarty de JSON niet als eigen syntax leest:
  `{capture assign="otlfull"}{contact.custom_2216}{/capture}{assign var="otlsuffix" value=$otlfull|regex_replace:"#^https://www\.onvergetelijk\.nl/#":""}{ldelim}"1":"{contact.first_name}","2":"{$user_kampkort}","3":"{$otlsuffix}"{rdelim}`.
  Nodig zodra een variabele Smarty vergt: `{$user_kampkort}` e.d. (het subjectvars-blok van
  nl.onvergetelijk.cssinliner) of string-bewerkingen zoals het afknippen van het domein van de
  one-time-loginlink (PRIVACY `onetimelink_url`, custom 2216) tot het pad-suffix voor een
  URL-knop.

De verkeerde combinatie faalt pas bij het vuren van de rule: gewone JSON door Smarty geeft een
parse-fout, `{ldelim}` zonder Smarty blijft letterlijk staan en levert ongeldige JSON op.

## Known issues

* **The Twilio provider in `whatsapp` 1.2.7 does not work as released.** `Providers/Twilio.php`
  implements only 2 of the 5 abstract methods, so `Provider::singleton()` dies with a fatal error
  before anything reaches Twilio — and several further bugs block sending from a rule entirely
  (permission checks on internal bookkeeping, `sender_id = 0` violating a foreign key, and `$type`
  being ignored so a template request went out as plain text). Fixes are proposed upstream in
  [merge request !2](https://lab.civicrm.org/extensions/whatsapp/-/merge_requests/2). Until that
  is merged you need those patches, otherwise this action cannot send over Twilio. 360Dialog is
  unaffected.
* The `whatsapp` extension ships no `vendor/` directory, so install the SDK yourself in its
  directory (`composer require "twilio/sdk:^8.11"`) — and again after every update, since
  `vendor/` is gitignored there.
* Media messages (image, document, video, audio) are not supported from a rule: a rule has no way
  to supply a file.
* The action inherits whatever the `whatsapp` extension's provider can do. If a provider is
  offline or misconfigured, the failure surfaces there, not here — check
  `civicrm_whatsapp.status` and the CiviCRM log.
