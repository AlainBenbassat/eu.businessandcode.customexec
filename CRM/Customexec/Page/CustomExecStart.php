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
    $sql = "select * from tmp_import_fai where contact_id IS NULL and skip = 0 order by id limit 0,$limit";
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
        $contactID = $this->createContact($dao->email, $dao->first_name, $dao->last_name);

        $this->updateNewsletterPrefs($dao->id, $contactID, $dao->fai_vinkjes);
      }
      else if ($contact['count'] == 1) {
        $this->found++;

        $this->updateNewsletterPrefs($dao->id, $contact['id'], $dao->fai_vinkjes);
      }
      else {
        $this->multiMatch++;
        $this->errorMessage[] = $dao->email;
        $this->updateRecord($dao->id, 'NULL', 1);
      }
    }
  }

  private function updateRecord($id, $contactID, $skip) {
    $sql = "update tmp_import_fai
      set contact_id = $contactID, skip = $skip
      where id = $id
    ";
    CRM_Core_DAO::executeQuery($sql);
  }

  private function createContact($email, $firstName, $lastName) {
    $params = [
      'contact_type' => 'Individual',
      'first_name' => $firstName,
      'last_name' => $lastName,
      'source' => 'import MailChimp mei 2018',
      'api.email.create' => [
        'email' => $email,
        'location_type_id' => 2,
      ]
    ];
    $contact = civicrm_api3("Contact", "create", $params);
    return $contact['id'];
  }

  private function updateNewsletterPrefs($tmpTableID, $contactID, $faiVinkjes) {
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

    if (!$dao->flanders_arts_institute_news) {
      $vinkjes = str_replace(',', CRM_Core_DAO::VALUE_SEPARATOR, $faiVinkjes);
      $vinkjes = CRM_Core_DAO::VALUE_SEPARATOR . $vinkjes . CRM_Core_DAO::VALUE_SEPARATOR;

      if ($dao->id) {
        $sql = "
          UPDATE
            civicrm_value_kunstenpunt_communicatie
          SET
            flanders_arts_institute_news = %2
          WHERE
            entity_id = %1
        ";
      }
      else {
        $sql = "
          INSERT INTO
            civicrm_value_kunstenpunt_communicatie (entity_id, kunstenpunt_nieuws, flanders_arts_institute_news, initiatieven_themas)
          VALUES
            (%1, '', %2, '')
        ";
      }

      $sqlParams = [
        1 => [$contactID, 'Integer'],
        2 => [$vinkjes, 'String'],
      ];

      CRM_Core_DAO::executeQuery($sql, $sqlParams);
    }

    // mark as processed
    $this->updateRecord($tmpTableID, $contactID, 0);
  }
}

