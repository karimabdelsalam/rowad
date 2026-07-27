<?php

if(!defined('CRONJOB_ENABLED')) die();

//Check system updates
$updatestring = $this->curl($this->updateServer, 'post', array('purchase_code' => $this->config['purchase_code']));
$update = json_decode($updatestring, true);
if(!empty($update)){
  if(!empty($update['plan']['format_time'])){
    if(TIMENOW > strtotime($update['plan']['format_time'])){
      $this->db->update('setting', array('value' => '1'), array('varname' => 'license_fail'));
    } else {
      $this->db->update('setting', array('value' => '0'), array('varname' => 'license_fail'));
    }
  }
  if($update['version'] > $this->config['version']){
    $this->db->update('setting', array('value' => $update['version']), array('varname' => 'newversion'));
  }
  $this->db->update('setting', array('value' => $updatestring), array('varname' => 'updatecore'));
} elseif($this->curl_status == 401){
  $this->db->update('setting', array('value' => ''), array('varname' => 'purchase_code'));
}
//Check system updates

$this->db->update('setting', array('value' => $this->getDirectorySize(MEDIA_PATH)), array('varname' => 'total_file_size'));
$this->db->update('setting', array('value' => $this->db->get_var("SELECT COUNT(id) FROM building")), array('varname' => 'total_units'));

if(! DEBUG_MODE && !empty($update) && $update['version'] > $this->config['version']) {
  $updatecore = $this->auto_load('updatecore');
  $updatecore->update();
}
