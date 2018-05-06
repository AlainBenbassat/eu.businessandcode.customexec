<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

use CRM_Customexec_ExtensionUtil as E;

class CRM_Customexec_Page_CustomExecStart extends CRM_Core_Page {
  private $group = [];
  private $notfound = [];
  private $found = [];
  private $multiMatch = [];
  private $errorMessage = [];
  private $index = -1;

  public function run() {
    CRM_Utils_System::setTitle(E::ts('Custom Code Execution'));

    require_once 'extra.php';

    $this->processContacts('beleidKunstendecreet', $beleidKunstendecreet);
    $this->processContacts('positieKunstenaar', $positieKunstenaar);
    $this->processContacts('calls', $calls);
    $this->processContacts('documentatie', $documentatie);
    $this->processContacts('internationaalWerken', $internationaalWerken);
    $this->processContacts('interculturaliteit', $interculturaliteit);
    $this->processContacts('cultuurLokaal', $cultuurLokaal);
    $this->processContacts('publiekeRuimte', $publiekeRuimte);


    $this->assign('group', $this->group);
    $this->assign('found', $this->found);
    $this->assign('notfound', $this->notfound);
    $this->assign('multiple', $this->multiMatch);
    $this->assign('errorMessage', $this->errorMessage);

    parent::run();
  }

  private function processContacts($groupName, $list) {
    $errorList = [];

    $this->index++;

    $this->group[$this->index] = $groupName;
    $this->notfound[$this->index] = 0;
    $this->found[$this->index] = 0;
    $this->multiMatch[$this->index] = 0;


    foreach ($list as $email) {
      // lookup the contact based on the email
      $params = [
        'email' => $email,
        'contact_type' => 'Individual',
        'is_deleted' => 0,
        'sequential' => 1,
      ];
      $contact = civicrm_api3('Contact', 'get', $params);

      // check if new, existing, or multiple matches
      if ($contact['count'] == 0) {
        $this->notfound[$this->index]++;

        // create contact
        $contactID = $this->createContact($email);
        $this->updateNewsletterPrefs($contactID, $groupName);
      }
      else if ($contact['count'] == 1) {
        $this->found[$this->index]++;
        $this->updateNewsletterPrefs($contact['id'], $groupName);
      }
      else {
        $this->multiMatch[$this->index]++;
        $errorList[] = $email;
      }
    }

    $this->errorMessage[$this->index] = implode(', ', $errorList);
  }

  private function createContact($email) {
    $params = [
      'contact_type' => 'Individual',
      'first_name' => $email,
      'api.email.create' => [
        'email' => $email,
        'location_type_id' => 2,
      ]
    ];
    $contact = civicrm_api3("Contact", "create", $params);
    return $contact['id'];
  }

  private function updateNewsletterPrefs($contactID, $groupName) {
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
    /* TODO char voor en achter verwijderen */
    if (!in_array($value, $existingOptions)) {
      // add value
      $existingOptions[] = $value;
      sort($existingOptions);

      $newOptions = CRM_Core_DAO::VALUE_SEPARATOR . implode(CRM_Core_DAO::VALUE_SEPARATOR, $existingOptions) . CRM_Core_DAO::VALUE_SEPARATOR;

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
  }
}

