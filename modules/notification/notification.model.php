<?php

class notification_model extends core
{
  public $args;
  public $notify;
  public $userinfo;
  public $template;
  public $insert_notifcation;

  public function __construct()
  {
    parent::__construct(DIR_MOD, CPURL);
  }

  public function fetchEmailBody($action)
  {
    $this->userinfo = [];
    if(!empty($this->args['userid'])){
      $this->userinfo = $this->db->get_row("SELECT * FROM user WHERE `id`='" . $this->args['userid'] . "'");
    }
    if(!empty($this->args['ownerid'])){
      $this->userinfo = $this->db->get_row("SELECT * FROM owner WHERE `id`='" . $this->args['ownerid'] . "'");
    }
    if(!empty($this->args['clientid'])){
      $this->userinfo = $this->db->get_row("SELECT * FROM buyer WHERE `id`='" . $this->args['clientid'] . "'");
    }
    $this->template = $this->db->get_row("SELECT * FROM email_templates WHERE `action`='$action'");
    if($this->template['active'] == 0 && $this->template['sms_active'] == 0) return false;
    $this->notify['sms_active'] = $this->template['sms_active'];
    $this->notify['sms'] = $this->template['sms'];
    $this->notify['active'] = $this->template['active'];
    $this->notify['subject'] = $this->template['subject'];
    $this->notify['message'] = $this->template['content'];
    $this->processVar('siteurl', BASEURL . (!empty($this->args['path']) ? '/' . ltrim($this->args['path'], '/') : ''));
    $this->processVar('org_arname', $this->config['sitename']);
    $this->processVar('org_enname', $this->config['en_sitename']);
    $this->processVar('org_logo', MEDIAURL . '/' . $this->config['logo']);
    $this->processVar('org_address', $this->config['address1']);
    $this->processVar('org_mobile', $this->config['mobile']);
    $this->processVar('org_phone', $this->config['phone']);
    $this->processVar('org_fax', $this->config['fax']);
    if(!empty($this->userinfo)){
      $this->args['receiver'] = $this->userinfo['email'];
      $this->args['mobile'] = $this->userinfo['mobile'];
      $this->processVar('user', $this->userinfo['username']);
      $this->processVar('name', (empty($this->userinfo['name'])) ? $this->userinfo['fname '] . ' ' . $this->userinfo['fathname '] : $this->userinfo['name']);
    }
    return true;
  }

  public function processVar($var, $value)
  {
    $this->notify['message'] = str_replace('{' . $var . '}', $value, $this->notify['message']);
    $this->notify['sms'] = str_replace('{' . $var . '}', $value, $this->notify['sms']);
  }

  public function generateResetHash()
  {
    $reset_code = md5(($this->args['userid'] . TIMENOW . rand(1000, 2000)));
    $this->db->insert('reset_codes', array('code' => $reset_code, 'userid' => $this->args['userid']));
    return $reset_code;
  }

  public function generateResetOTP($reset_code)
  {
    $this->db->insert('reset_codes', array('code' => $reset_code, 'userid' => $this->args['userid']));
  }

  public function generateNewPass()
  {
    $newpass = $this->salt(10);
    $this->db->update('user', array('password' => md5($newpass)), array('id' => $this->args['userid']));
    return $newpass;
  }

  public function startQueue()
  {
    if(!empty($this->args['receiver'])){
      $email = array();
      $email['subject'] = $this->notify['subject'];
      $email['message'] = '<table style="width:100%;font:14px bold Arial;"><tr><td>' . nl2br($this->notify['message']) . '</td></tr></table>';
      $email['sender'] = (!empty($this->args['sender'])) ? $this->args['sender'] : $this->config['email'];
      $email['receiver'] = (!empty($this->args['receiver'])) ? $this->args['receiver'] : $this->config['email'];
      $email['sendtime'] = 0;
      $this->db->insert('email_queue', $email);
    }
    if(!empty($this->args['mobile'])){
      $this->sendSMS($this->args['mobile'], $this->notify['sms']);
    }
    if(!empty($this->args['userid']) && $this->insert_notifcation){
      $this->addNotification($this->notify['subject'], $this->notify['sms'], 'alert', $this->args['userid']);
    }
    $this->notify['sms'] = $this->template['sms'];
    $this->notify['message'] = $this->template['content'];
  }

}
