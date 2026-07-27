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
