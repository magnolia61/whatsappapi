<?php

use CRM_Whatsappapi_ExtensionUtil as E;

/**
 * Registers this extension's CiviRules action.
 *
 * Follows the emailapi pattern: the action is declared in civirules/actions.json and
 * handed to CiviRules' own upgrader helper, which inserts or updates the row in
 * civirule_action. That is idempotent, so running it again on upgrade is harmless.
 *
 * The civirules check matters: without it, installing this extension while civirules
 * is absent would fatal on a missing class.
 */
class CRM_Whatsappapi_Upgrader extends CRM_Extension_Upgrader_Base {

  /**
   * Insert the action when this extension is installed.
   */
  public function install(): void {
    $this->registerCivirulesActions();
  }

  /**
   * And again after an upgrade, so a renamed label or class lands too.
   */
  public function postUpgrade(): void {
    $this->registerCivirulesActions();
  }

  /**
   * Hand civirules/actions.json to CiviRules, if CiviRules is actually installed.
   */
  protected function registerCivirulesActions(): void {
    $civirules = \Civi\Api4\Extension::get(FALSE)
      ->addWhere('file', '=', 'civirules')
      ->addWhere('status:name', '=', 'installed')
      ->execute()
      ->first();

    if (empty($civirules)) {
      Civi::log()->warning('whatsappapi: civirules is not installed, skipping action registration.');
      return;
    }

    CRM_Civirules_Utils_Upgrader::insertActionsFromJson(E::path('civirules/actions.json'));
  }

}
