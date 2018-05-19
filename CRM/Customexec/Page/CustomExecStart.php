<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

use CRM_Customexec_ExtensionUtil as E;

class CRM_Customexec_Page_CustomExecStart extends CRM_Core_Page {
  private $notfound = 0;
  private $found = 0;
  private $multiMatch = 0;
  private $errorMessage = [];

  public function run() {
    CRM_Utils_System::setTitle(E::ts('Custom Code Execution'));

    $limit = CRM_Utils_Array::value('limit', $_GET);
    if (!$limit) {
      $limit = 1;
    }

    $this->processContacts($limit);

    $this->assign('found', $this->found);
    $this->assign('notfound', $this->notfound);
    $this->assign('multiple', $this->multiMatch);
    $this->assign('errorMessage', $this->errorMessage);

    parent::run();
  }

  private function processContacts($limit) {
    $sql = "select * from tmp_import_initiatieven where contact_id IS NULL and skip = 0 order by id limit 0,$limit";
    $dao = CRM_Core_DAO::executeQuery($sql);

    while ($dao->fetch()) {
      // lookup the contact based on the email
      $params = [
        'email' => $dao->email,
        'contact_type' => 'Individual',
        'is_deleted' => 0,
        'sequential' => 1,
      ];
      $contact = civicrm_api3('Contact', 'get', $params);

      // check if new, existing, or multiple matches
      if ($contact['count'] == 0) {
        $this->notfound++;

        // create contact
        $contactID = $this->createContact($dao->email);

        if ($dao->opt_out) {
          $this->optOut($dao->id, $contactID);
        }
        else {
          $this->updateNewsletterPrefs($dao->id, $contactID, $dao->group_name);
        }
      }
      else if ($contact['count'] == 1) {
        $this->found++;

        if ($dao->opt_out) {
          $this->optOut($dao->id, $contactID);
        }
        else {
          $this->updateNewsletterPrefs($dao->id, $contact['id'], $dao->group_name);
        }
      }
      else {
        $this->multiMatch++;
        $this->errorMessage[] = $dao->email;
        $this->updateRecord($dao->id, 'NULL', 1);
      }
    }
  }

  private function updateRecord($id, $contactID, $skip) {
    $sql = "update tmp_import_initiatieven
      set contact_id = $contactID, skip = $skip
      where id = $id
    ";
    CRM_Core_DAO::executeQuery($sql);
  }

  private function optOut($tmpTableID, $contactID) {
    $params = [
      'id' => $contactID,
      'is_opt_out' => 1,
    ];
    civicrm_api3("Contact", "create", $params);

    // mark as processed
    $this->updateRecord($tmpTableID, $contactID, 0);
  }

  private function createContact($email) {
    $params = [
      'contact_type' => 'Individual',
      'first_name' => $email,
      'source' => 'import mei 2018',
      'api.email.create' => [
        'email' => $email,
        'location_type_id' => 2,
      ]
    ];
    $contact = civicrm_api3("Contact", "create", $params);
    return $contact['id'];
  }

  private function updateNewsletterPrefs($tmpTableID, $contactID, $groupName) {
    $sql = "
      SELECT
        comm.*
      FROM
        civicrm_contact c
      LEFT OUTER JOIN
        civicrm_value_kunstenpunt_communicatie comm on comm.entity_id = c.id
      WHERE
        c.id = %1
    ";
    $sqlParams = [
      1 => [$contactID, 'Integer'],
    ];

    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    if (!$dao->fetch() || $dao->N != 1) {
     throw new Exception("Expected to find contact with id = $contactID");
    }

    switch ($groupName) {
      case 'beleidKunstendecreet':
        $value = 1;
        break;
      case 'positieKunstenaar':
        $value = 8;
        break;
      case 'calls':
        $value = 2;
        break;
      case 'documentatie':
        $value = 4;
        break;
      case 'internationaalWerken':
        $value = 6;
        break;
      case 'interculturaliteit':
        $value = 5;
        break;
      case 'cultuurLokaal':
        $value = 3;
        break;
      case 'publiekeRuimte':
        $value = 7;
        break;
      default:
        throw new Exception("unexpected groupname '$groupName'");
    }

    // check if the value is present
    $existingOptions = explode(CRM_Core_DAO::VALUE_SEPARATOR, $dao->initiatieven_themas);

    // strip separator
    if (count($existingOptions) > 0 && $existingOptions[0] == CRM_Core_DAO::VALUE_SEPARATOR) {
      unset($existingOptions[0]);
    }
    if (count($existingOptions) > 0 && array_values(array_slice($existingOptions, -1))[0] == CRM_Core_DAO::VALUE_SEPARATOR) {
      array_pop($existingOptions);
    }

    if (!in_array($value, $existingOptions)) {
      // add value
      $existingOptions[] = $value;
      sort($existingOptions);

      $newOptions = implode(CRM_Core_DAO::VALUE_SEPARATOR, $existingOptions) . CRM_Core_DAO::VALUE_SEPARATOR;
      if ($newOptions[0] != CRM_Core_DAO::VALUE_SEPARATOR) {
        $newOptions = CRM_Core_DAO::VALUE_SEPARATOR . $newOptions;
      }

      if ($dao->id) {
        $sql = "
          UPDATE
            civicrm_value_kunstenpunt_communicatie
          SET
            initiatieven_themas = %2
          WHERE
            entity_id = %1
        ";
      }
      else {
        $sql = "
          INSERT INTO
            civicrm_value_kunstenpunt_communicatie (entity_id, kunstenpunt_nieuws, flanders_arts_institute_news, initiatieven_themas)
          VALUES
            (%1, '', '', %2)
        ";
      }

      $sqlParams = [
        1 => [$contactID, 'Integer'],
        2 => [$newOptions, 'String'],
      ];

      CRM_Core_DAO::executeQuery($sql, $sqlParams);
    }

    // mark as processed
    $this->updateRecord($tmpTableID, $contactID, 0);
  }
}

