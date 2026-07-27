<?php

if(!defined('CRONJOB_ENABLED')) die();

$buyermodule = $this->auto_load('buyer');
$buildmodule = $this->auto_load('building');
$ownermodule = $this->auto_load('owner');

$this->db->query("UPDATE login_activity SET retries=0");

$payments = $this->db->get_results("SELECT * FROM payments WHERE gone='0' AND alert='0' AND paydate>=DATE(NOW() + INTERVAL 15 DAY)");
if($payments){
  foreach($payments as $payment){
    $this->db->query("UPDATE payments SET alert='15' WHERE id='$payment[id]'");
    if($payment['module'] == 'sell'){
      $this->sendNotify('sellPay15', array(), $payment);
    } elseif($payment['module'] == 'rent'){
      $this->sendNotify('payRent15', array(), $payment);
    }
  }
}

$payments = $this->db->get_results("SELECT * FROM payments WHERE gone='0' AND alert='15' AND paydate>=DATE(NOW() + INTERVAL 1 DAY)");
if($payments){
  foreach($payments as $payment){
    $this->db->query("UPDATE payments SET alert='1' WHERE id='$payment[id]'");
    if($payment['module'] == 'sell'){
      $this->sendNotify('sellPay1', array(), $payment);
    }
  }
}

$payments = $this->db->get_results("SELECT * FROM payments WHERE gone='0' AND alert='1' AND paydate>=DATE(NOW())");
if($payments){
  foreach($payments as $payment){
    $this->db->query("UPDATE payments SET alert='-1' WHERE id='$payment[id]'");
    if($payment['module'] == 'sell'){
      $this->sendNotify('sellPay0', array(), $payment);
    }
  }
}

$payments = $this->db->get_results("SELECT * FROM payments WHERE gone='0' AND alert='-1' AND paydate<DATE(NOW() + INTERVAL 1 DAY)");
if($payments){
  foreach($payments as $payment){
    if($payment['module'] == 'sell'){
      $this->sendNotify('sellPayx', array(), $payment);
    }
  }
}

$gov_licenses = $this->db->get_results("SELECT * FROM gov_licenses WHERE notify='1' AND alert='0' AND expiredate<DATE(NOW() + INTERVAL 15 DAY)");
if($gov_licenses){
  foreach($gov_licenses as $gov_license){
    $this->db->query("UPDATE gov_licenses SET alert='15' WHERE id='$gov_license[id]'");
    $this->sendNotify('licenseEnd15', array(), $gov_license);
  }
}

$employees = $this->db->get_results("SELECT * FROM employees WHERE alert='0' AND idexpire<DATE(NOW() + INTERVAL 15 DAY)");
if($employees){
  foreach($employees as $employee){
    $this->db->query("UPDATE employees SET alert='15' WHERE id='$employee[id]'");
    $this->sendNotify('visaEnd15', array(), $employee);
  }
}

$this->refreshContracts();

//Reset temp directory files
if($handle = opendir(ROOT_DIR . '/' . UPLOAD_DIR . '/temp')){
  while(false !== ($entry = readdir($handle))){
    if($entry != '.' && $entry != '..'){
      if((filemtime(ROOT_DIR . '/' . UPLOAD_DIR . '/temp/' . $entry) + 86400) < TIMENOW){
        @unlink(ROOT_DIR . '/' . UPLOAD_DIR . '/temp/' . $entry);
      }
    }
  }
  closedir($handle);
}
//Reset temp directory files

//Reset e-contracts SMS tries
$this->db->query('UPDATE owner SET ec_passcode=0');
$this->db->query('UPDATE buyer SET ec_passcode=0;');
//Reset e-contracts SMS tries

//Send log errors
if(date('d') % 7 == 0 && ERROR_LOG_FILE){
  $this->sendEmail('smartiolabs@gmail.com', 'Phantom Property System log errors tracker', $_SERVER['HTTP_HOST'], '', ERROR_LOG_FILE);
}
//Send log errors
