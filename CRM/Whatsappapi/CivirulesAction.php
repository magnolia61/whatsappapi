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

    // Zet de tekst van de message template klaar. Dit MOET hier gebeuren: de whatsapp-extensie
    // declareert template_id wel in zijn API-spec, maar leest de template nergens uit - zonder
    // deze stap vertrekt er een leeg bericht. (smsapi doet hetzelfde werk in zijn eigen
    // api/v3/Sms/Send.php.)
    //
    // Alleen de RUWE tekst meegeven, geen tokens vervangen: dat doet Whatsapp::send() zelf via
    // Whatsapp.replaceTokens. Zou je het hier ook doen, dan draait de tokenvervanging twee keer.
    if (!empty($parameters['template_id']) && empty($parameters['text'])) {
      $messageTemplate = new CRM_Core_DAO_MessageTemplate();
      $messageTemplate->id = $parameters['template_id'];
      $messageTemplate->is_active = TRUE;
      if ($messageTemplate->find(TRUE)) {
        $tekst = $messageTemplate->msg_text;
        if (empty($tekst) && !empty($messageTemplate->msg_html)) {
          // Alleen een HTML-body: platmaken, want WhatsApp kent geen HTML.
          $tekst = CRM_Utils_String::htmlToText($messageTemplate->msg_html);
        }
        if (trim((string) $tekst) === '') {
          throw new CRM_Core_Exception(
            'whatsappapi: message template ' . $parameters['template_id'] . ' has no text or HTML body.'
          );
        }
        $parameters['text'] = $tekst;
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
    if (!empty($params['template_id'])) {
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
