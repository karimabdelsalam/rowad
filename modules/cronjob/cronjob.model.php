<?php

/*======================================================================*\
|| #################################################################### ||
|| # Smart Push Notification System 5.0                               # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2014-2017 Smart IO Labs Inc. All Rights Reserved.     # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # --- Smart Push Notification System IS NOT FREE SOFTWARE ---      # ||
|| # https://smartiolabs.com/product/smart-push-notification-system   # ||
|| #################################################################### ||
\*======================================================================*/

class cronjob_model extends core {

  public function __construct() {
    parent::__construct();
  }

  public function updateCron($data, $id) {
    $this->db->update('cronjob', $data, array('id' => $id));
  }

  public function fetchActiveCrons() {
    return $this->db->get_results("SELECT * FROM cronjob WHERE starttime<'".date('Y-m-d H:i:s', TIMENOW)."' AND active='1'");
  }

}