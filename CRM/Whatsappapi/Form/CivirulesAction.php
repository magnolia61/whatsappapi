<?php

require_once 'CRM/Core/Form.php';

use CRM_Whatsappapi_ExtensionUtil as E;

/**
 * Configuration form for the "Send WhatsApp" CiviRules action.
 *
 * Modelled on CRM_Smsapi_Form_CivirulesAction. Two differences that WhatsApp needs:
 *  - providers come from civicrm_whatsapp_provider, not civicrm_sms_provider;
 *  - there is a message TYPE. 'text' is free-form and is only allowed inside an open
 *    24 hour customer service window; to start a conversation you need 'template',
 *    which sends a provider-side approved template (a Content Template at Twilio).
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Whatsappapi_Form_CivirulesAction extends CRM_Core_Form {

  protected $ruleActionId = FALSE;

  protected $ruleAction;

  protected $action;

  /**
   * @throws Exception when the action or rule action cannot be found
   */
  public function preProcess() {
    $this->ruleActionId = CRM_Utils_Request::retrieve('rule_action_id', 'Integer');

    $this->ruleAction = new CRM_Civirules_BAO_RuleAction();
    $this->action = new CRM_Civirules_BAO_Action();
    $this->ruleAction->id = $this->ruleActionId;
    if ($this->ruleAction->find(TRUE)) {
      $this->action->id = $this->ruleAction->action_id;
      if (!$this->action->find(TRUE)) {
        throw new Exception('CiviRules could not find action with id ' . $this->ruleAction->action_id);
      }
    }
    else {
      throw new Exception('CiviRules could not find rule action with id ' . $this->ruleActionId);
    }

    parent::preProcess();
  }

  /**
   * Active message templates, excluding workflow templates (those belong to core
   * processes and are not meant to be picked here).
   *
   * @return array
   */
  protected function getMessageTemplates() {
    $messageTemplates = [];
    $query  = 'SELECT id, msg_title FROM civicrm_msg_template WHERE is_active = %1 AND workflow_id IS NULL ORDER BY msg_title';
    $params = [1 => [1, 'Integer']];
    $dao    = CRM_Core_DAO::executeQuery($query, $params);
    while ($dao->fetch()) {
      $messageTemplates[$dao->id] = $dao->msg_title;
    }
    $messageTemplates[0] = '- select -';
    asort($messageTemplates);
    return $messageTemplates;
  }

  /**
   * Active WhatsApp templates from the whatsapp extension (civicrm_whatsapp_template).
   *
   * These are the provider-side goedgekeurde templates: bij Twilio een Content Template
   * waarvan de SID in de namespace-kolom staat. Alleen deze zijn bruikbaar voor het
   * message type 'template'; een gewone message template kan daar niets mee.
   *
   * @return array
   */
  protected function getWhatsappTemplates() {
    $whatsappTemplates = [];
    $query  = 'SELECT id, title, language FROM civicrm_whatsapp_template WHERE is_active = %1 ORDER BY title';
    $params = [1 => [1, 'Integer']];
    $dao    = CRM_Core_DAO::executeQuery($query, $params);
    while ($dao->fetch()) {
      $whatsappTemplates[$dao->id] = $dao->title . ($dao->language ? ' (' . $dao->language . ')' : '');
    }
    $whatsappTemplates[0] = '- select -';
    asort($whatsappTemplates);
    return $whatsappTemplates;
  }

  /**
   * Active WhatsApp providers from the whatsapp extension.
   *
   * @return array
   */
  protected function getWhatsappProviders() {
    $providers = [];
    $query  = 'SELECT id, title FROM civicrm_whatsapp_provider WHERE is_active = %1 ORDER BY title';
    $params = [1 => [1, 'Integer']];
    $dao    = CRM_Core_DAO::executeQuery($query, $params);
    while ($dao->fetch()) {
      $providers[$dao->id] = $dao->title;
    }
    $providers[0] = '- select -';
    asort($providers);
    return $providers;
  }

  /**
   * Message types this action supports.
   *
   * Deliberately only the two that make sense from a rule: media types would need a
   * file to send, which a rule has no way of supplying.
   *
   * @return array
   */
  protected function getMessageTypes() {
    return [
      'text'     => E::ts('Text (free-form, only within an open 24h window)'),
      'template' => E::ts('Template (required to start a conversation)'),
    ];
  }

  public function buildQuickForm() {

    $this->setFormTitle();

    $this->add('hidden', 'rule_action_id');
    $this->add('select', 'provider_id', E::ts('WhatsApp provider'), $this->getWhatsappProviders(), TRUE);
    // Which of the two template selects is required depends on the message type;
    // validateTemplateChoice() enforces that, so no required flag here.
    $this->add('select', 'template_id', E::ts('Message template (for type Text)'), $this->getMessageTemplates());
    $this->add('select', 'whatsapp_template_id', E::ts('WhatsApp template (for type Template)'), $this->getWhatsappTemplates());
    $this->add('select', 'type', E::ts('Message type'), $this->getMessageTypes(), TRUE);
    $this->addFormRule(['CRM_Whatsappapi_Form_CivirulesAction', 'validateTemplateChoice']);
    $this->addEntityRef('from_contact_id', E::ts('Message sender'));
    $this->add('select', 'smarty', E::ts('Smarty'), [
      'use'      => E::ts('Use Smarty'),
      'disable'  => E::ts('Disable Smarty'),
      'settings' => E::ts('Use standard settings'),
    ]);
    $this->add('checkbox', 'alternative_receiver', E::ts('Send to alternative phone number'));
    $this->add('text', 'alternative_receiver_phone_number', E::ts('Send to'));

    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')],
    ]);
  }

  /**
   * Per message type de juiste template-keuze afdwingen.
   *
   * 'text' rendert een gewone message template; 'template' verstuurt een provider-side
   * goedgekeurde WhatsApp-template. Zonder deze check vertrekt er bij een verkeerde
   * combinatie pas een foutmelding op het moment dat de rule vuurt - dat is te laat.
   *
   * @param array $values
   *
   * @return array|bool
   */
  public static function validateTemplateChoice($values) {
    $errors = [];
    $type = $values['type'] ?? 'text';
    if ($type === 'text' && empty($values['template_id'])) {
      $errors['template_id'] = E::ts('Message type Text requires a message template.');
    }
    if ($type === 'template' && empty($values['whatsapp_template_id'])) {
      $errors['whatsapp_template_id'] = E::ts('Message type Template requires a WhatsApp template.');
    }
    return empty($errors) ? TRUE : $errors;
  }

  /**
   * @return array
   */
  public function setDefaultValues() {
    $data          = [];
    $defaultValues = [];
    $defaultValues['rule_action_id'] = $this->ruleActionId;

    if (!empty($this->ruleAction->action_params)) {
      $data = unserialize($this->ruleAction->action_params);
    }
    if (!empty($data['provider_id'])) {
      $defaultValues['provider_id'] = $data['provider_id'];
    }
    if (!empty($data['template_id'])) {
      $defaultValues['template_id'] = $data['template_id'];
    }
    if (!empty($data['whatsapp_template_id'])) {
      $defaultValues['whatsapp_template_id'] = $data['whatsapp_template_id'];
    }
    $defaultValues['from_contact_id'] = $data['from_contact_id'] ?? NULL;

    if (!empty($data['alternative_receiver_phone_number'])) {
      $defaultValues['alternative_receiver_phone_number'] = $data['alternative_receiver_phone_number'];
      $defaultValues['alternative_receiver'] = TRUE;
    }
    // 'template' as the default: a rule usually fires outside an open 24 hour window,
    // and free-form text would then be rejected by Meta.
    $defaultValues['type']   = $data['type']   ?? 'template';
    $defaultValues['smarty'] = $data['smarty'] ?? 'settings';

    return $defaultValues;
  }

  public function postProcess() {
    $data['provider_id']          = $this->_submitValues['provider_id'];
    $data['template_id']          = $this->_submitValues['template_id'] ?? '';
    $data['whatsapp_template_id'] = $this->_submitValues['whatsapp_template_id'] ?? '';
    $data['type']                 = $this->_submitValues['type'] ?? 'template';
    $data['from_contact_id'] = $this->_submitValues['from_contact_id'] ?? CRM_Core_Session::getLoggedInContactID() ?? NULL;

    $data['alternative_receiver_phone_number'] = '';
    if (!empty($this->_submitValues['alternative_receiver_phone_number'])) {
      $data['alternative_receiver_phone_number'] = $this->_submitValues['alternative_receiver_phone_number'];
    }
    $data['smarty'] = $this->_submitValues['smarty'] ?? 'settings';

    $ruleAction = new CRM_Civirules_BAO_RuleAction();
    $ruleAction->id = $this->ruleActionId;
    $ruleAction->action_params = serialize($data);
    $ruleAction->save();

    $session = CRM_Core_Session::singleton();
    $session->setStatus(
      'Action ' . $this->action->label . ' parameters updated to CiviRule '
        . CRM_Civirules_BAO_Rule::getRuleLabelWithId($this->ruleAction->rule_id),
      'Action parameters updated',
      'success'
    );

    $redirectUrl = CRM_Utils_System::url('civicrm/civirule/form/rule', 'action=update&id=' . $this->ruleAction->rule_id, TRUE);
    CRM_Utils_System::redirect($redirectUrl);
  }

  protected function setFormTitle() {
    $title = 'CiviRules Edit Send WhatsApp Action parameters';
    $this->assign('ruleActionHeader', 'Edit action ' . $this->action->label . ' of CiviRule '
      . CRM_Civirules_BAO_Rule::getRuleLabelWithId($this->ruleAction->rule_id));
    CRM_Utils_System::setTitle($title);
  }

}
