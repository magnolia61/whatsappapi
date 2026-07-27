<?php

require_once 'whatsappapi.civix.php';

use CRM_Whatsappapi_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function whatsappapi_civicrm_config(&$config): void {
  _whatsappapi_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function whatsappapi_civicrm_install(): void {
  _whatsappapi_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function whatsappapi_civicrm_enable(): void {
  _whatsappapi_civix_civicrm_enable();
}
