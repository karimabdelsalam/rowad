<?php

/*======================================================================*\
|| #################################################################### ||
|| # Smart Aqaari System 1.0                                          # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2014-2017 Smart IO Labs Inc. All Rights Reserved.     # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # --- Smart Push Notification System IS NOT FREE SOFTWARE ---      # ||
|| # https://smartiolabs.com/product/smart-push-notification-system   # ||
|| #################################################################### ||
\*======================================================================*/

class updatecore_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function saveUpdateInfo($updatestring)
  {
    $this->db->update('setting', array('value' => $updatestring), array('varname' => 'updatecore'));
  }

}
