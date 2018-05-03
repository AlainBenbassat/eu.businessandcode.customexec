<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

use CRM_Customexec_ExtensionUtil as E;

class CRM_Customexec_Page_CustomExecStart extends CRM_Core_Page {

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('Custom Code Execution'));

    // Example: Assign a variable for use in a template
    $this->assign('currentTime', date('Y-m-d H:i:s'));


    parent::run();
  }

}
