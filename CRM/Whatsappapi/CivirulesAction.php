<?php

/**
 * CiviRules action: send a WhatsApp message.
 *
 * Modelled on CRM_Smsapi_CivirulesAction. CiviRules' generic API action does the
 * heavy lifting: it calls the API entity/action returned below with the parameters
 * configured on the rule, so all this class has to do is name the call and fill in
 * the contact.
 *
 * The actual sending lives in the whatsapp extension (Whatsapp.send, APIv3), which
 * hands off to the configured provider. That keeps this extension provider-agnostic:
 * whatever the whatsapp extension supports, this action supports.
 *
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */
class CRM_Whatsappapi_CivirulesAction extends CRM_CivirulesActions_Generic_Api {

  /**
   * The API entity to call.
   *
   * @return string
   */
  protected function getApiEntity() {
    return 'Whatsapp';
  }

  /**
   * The API action to call.
   *
   * @return string
   */
  protected function getApiAction() {
    return 'send';
  }

  /**
   * Add the contact (and activity, when the rule was triggered by one) to the
   * parameters the rule was configured with.
   *
   * @param array $parameters
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return array
   */
  protected function alterApiParameters($parameters, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $parameters['contact_id'] = $triggerData->getContactId();
    if ($triggerData->getEntity() == 'Activity') {
      $parameters['activity_id'] = $triggerData->getEntityId();
    }

    // Type 'template': a provider-side approved template (a Content Template at
    // Twilio). Mind the seam: template_id means something different on this route
    // than it does for 'text'. The Twilio provider uses it to look up a row in
    // civicrm_whatsapp_template (namespace = Content SID, token_example = the
    // variables JSON) — NOT a msg_template. That is why the form stores the choice
    // separately as whatsapp_template_id, which is mapped onto template_id here;
    // the msg_template hydration below stays out of this route entirely.
    if (($parameters['type'] ?? 'text') === 'template') {
      if (empty($parameters['whatsapp_template_id'])) {
        throw new CRM_Core_Exception(
          'whatsappapi: message type "template" requires a WhatsApp template (whatsapp_template_id).'
        );
      }
      $whatsappTemplate = \Civi\Api4\WhatsappTemplate::get(FALSE)
        ->addWhere('id', '=', $parameters['whatsapp_template_id'])
        ->addWhere('is_active', '=', TRUE)
        ->execute()
        ->first();
      if (empty($whatsappTemplate)) {
        throw new CRM_Core_Exception(
          'whatsappapi: active WhatsApp template ' . $parameters['whatsapp_template_id'] . ' was not found.'
        );
      }
      // On this route the text is only the local record (civicrm_whatsapp.body):
      // what the recipient actually sees is rendered by Meta from the approved
      // template. Passing the registration's mirror text keeps the archive readable.
      $parameters['text'] = $whatsappTemplate['text'];
      $parameters['template_id'] = $whatsappTemplate['id'];
      return $parameters;
    }

    // Prepare the message template's text. This MUST happen here: the whatsapp
    // extension declares template_id in its API spec but never reads the template,
    // so without this step an empty message goes out. (smsapi does the same work in
    // its own api/v3/Sms/Send.php.)
    //
    // Pass on the RAW text only, without replacing tokens: Whatsapp::send() does
    // that itself via Whatsapp.replaceTokens. Doing it here as well would run the
    // token replacement twice.
    if (!empty($parameters['template_id']) && empty($parameters['text'])) {
      $messageTemplate = new CRM_Core_DAO_MessageTemplate();
      $messageTemplate->id = $parameters['template_id'];
      $messageTemplate->is_active = TRUE;
      if ($messageTemplate->find(TRUE)) {
        $text = $messageTemplate->msg_text;
        if (empty($text) && !empty($messageTemplate->msg_html)) {
          // Only an HTML body: flatten it, WhatsApp has no notion of HTML.
          $text = CRM_Utils_String::htmlToText($messageTemplate->msg_html);
        }
        if (trim((string) $text) === '') {
          throw new CRM_Core_Exception(
            'whatsappapi: message template ' . $parameters['template_id'] . ' has no text or HTML body.'
          );
        }
        $parameters['text'] = $text;
      }
      else {
        throw new CRM_Core_Exception(
          'whatsappapi: active message template ' . $parameters['template_id'] . ' was not found.'
        );
      }
    }

    return $parameters;
  }

  /**
   * Where CiviRules sends the user to configure this action.
   *
   * @param int $ruleActionId
   *
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return CRM_Utils_System::url('civicrm/civirules/actions/whatsappapi', 'rule_action_id=' . $ruleActionId);
  }

  /**
   * One-line summary of the configured action, shown in the rule overview.
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $params = $this->getActionParameters();

    $template = ts('unknown template');
    if (($params['type'] ?? 'text') === 'template' && !empty($params['whatsapp_template_id'])) {
      // Template route: the title comes from civicrm_whatsapp_template, not msg_template.
      $whatsappTemplate = \Civi\Api4\WhatsappTemplate::get(FALSE)
        ->addSelect('title')
        ->addWhere('id', '=', $params['whatsapp_template_id'])
        ->execute()
        ->first();
      if (!empty($whatsappTemplate['title'])) {
        $template = $whatsappTemplate['title'];
      }
    }
    elseif (!empty($params['template_id'])) {
      $messageTemplate = new CRM_Core_DAO_MessageTemplate();
      $messageTemplate->id = $params['template_id'];
      $messageTemplate->is_active = TRUE;
      if ($messageTemplate->find(TRUE)) {
        $template = $messageTemplate->msg_title;
      }
    }

    // The whatsapp extension keeps its providers in its own table, so read the
    // title straight from there rather than through CRM_SMS_BAO_Provider.
    $provider = ts('unknown provider');
    if (!empty($params['provider_id'])) {
      $providerInfo = CRM_Whatsapp_BAO_WhatsappProvider::getProviderInfo($params['provider_id']);
      if (!empty($providerInfo['title'])) {
        $provider = $providerInfo['title'];
      }
    }

    $to = ts('the contact');
    if (!empty($params['alternative_receiver_phone_number'])) {
      $to = $params['alternative_receiver_phone_number'];
    }

    $type = !empty($params['type']) ? $params['type'] : 'text';

    return ts('Send WhatsApp (%1) with provider "%2" using template "%3" to %4', [
      1 => $type,
      2 => $provider,
      3 => $template,
      4 => $to,
    ]);
  }

}
