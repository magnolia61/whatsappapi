# Changelog

## Version 1.0.0 (in development)

* Send WhatsApp action for CiviRules, modelled on the smsapi and emailapi
  extensions. Sending is delegated to the whatsapp extension (`Whatsapp.send`,
  APIv3), so every provider that extension supports works here too.
* Message type *template* sends a provider-side approved template (a Content
  Template at Twilio) via `civicrm_whatsapp_template`; message type *text*
  renders a CiviCRM message template. Free-form text is only accepted by
  WhatsApp within 24 hours of the contact's own last message, so *template*
  is the default.
* Form validation enforces the template choice that matches the message type.
* README documents the coupling between `token_example` and the rule's
  Smarty setting, and the fixes the whatsapp extension's Twilio provider
  needs (MR !2) on version 1.2.7.
