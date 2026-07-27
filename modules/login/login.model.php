<?php

class login_model extends core
{
  private $user_location;
  private $user_signature;

  public function __construct()
  {
    parent::__construct();
  }

  public function userAuthorise($username, $password, $cookie = false)
  {
    if($cookie !== false){
      $username = addslashes($_COOKIE[$this->COOKIEUSER]);
      $password = addslashes($_COOKIE[$this->COOKIEPASS]);
    }
    $user = $this->db->get_row("SELECT id,active,password FROM user WHERE username='$username' AND groupid='".GROUP_ID."'");
    if(empty($user) || $user['password'] != $password || $user['active'] != 1) {
      return false;
    } else {
      return $user['id'];
    }
  }

  public function userInfo($userid)
  {
    $user = $this->db->get_row("SELECT * FROM user WHERE id='$userid'");
    $user['access_token'] = $this->accessToken($user['id']);

    return $user;
  }

  public function accessToken($userid)
  {
    $token = $this->db->get_var("SELECT token FROM user_access_token WHERE userid='$userid'");
    if(! $token) {
      $token = $this->salt(32);
      $this->db->insert('user_access_token', ['userid' => $userid, 'token' => $token]);
    }
    return $token;
  }

  public function checkSMSCode($userid, $code)
  {
    $this->collectUserHash();

    $user = $this->db->get_row("SELECT id,timeout FROM login_activity WHERE userid='$userid' AND code='$code' AND signature='$this->user_signature'");
    if(empty($user)){
      return 404;
    } elseif($user['timeout'] + 240 < TIMENOW){
      return 500;
    } else{
      $this->db->query("UPDATE login_activity SET verified=1 WHERE id='$user[id]'");
      return 200;
    }
  }

  public function checkRecoverHash($code)
  {
    $code = $this->db->get_row("SELECT userid,created_time FROM reset_codes WHERE code='$code'");
    if($code && strtotime($code['created_time'])+3600 < TIMENOW){
      return false;
    }
    return $code['userid'];
  }

  public function updatePassword($userid, $newpass)
  {
    $this->db->query("UPDATE user SET password='".md5($newpass)."' WHERE id='$userid'");
    $this->db->query("DELETE FROM reset_codes WHERE userid='$userid'");
  }

  public function collectUserHash()
  {
    include(LIB_DIR.'/class.browser.detect.php');

    $browser = new BrowserDetection();
    $this->user_location = json_decode(file_get_contents('http://ip-api.com/json/'.$this->getUserIP()), true);
    if(defined('LOCAL_SERVER') && LOCAL_SERVER) {
      $this->user_location = ['country' => 'Saudi Arabia', 'city' => 'Jeddah'];
    } elseif(empty($this->user_location['country'])) {
      $this->showmsg(gettext('حدث خطأ من فضلك حاول في وقت لاحق'), 0);
    }

    $this->user_signature = md5($browser->getPlatform().$browser->getName().$this->user_location['country'].$this->user_location['city']);
  }

  public function checkOTP($userid, $otpenabled)
  {
    $this->collectUserHash();

    $checkLogin = $this->db->get_row("SELECT id,verified,retries FROM login_activity WHERE userid='$userid' AND signature='$this->user_signature'");
    if($checkLogin && $checkLogin['retries'] >= 5 && $otpenabled){
      $this->showmsg(gettext('استنفذت الحد المتاح من عدد المحاولات'), 0);
    } elseif($checkLogin && $checkLogin['verified'] == 1){
      return true;
    } elseif($checkLogin && $checkLogin['verified'] == 0){
      if(! $otpenabled) return true;
      if(defined('API_ENABLED') && API_ENABLED){
        $verifyCode = $this->numSalt(4);
      } else {
        $verifyCode = strtoupper($this->salt(4));
      }
      $this->db->query("UPDATE login_activity SET code='$verifyCode',timeout='".TIMENOW."',retries=retries+1 WHERE id='$checkLogin[id]'");
      $this->sendNotify('smsLogin', array('userid' => $userid, 'code' => $verifyCode));
      return false;
    } else {
      $verifyCode = strtoupper($this->salt(4));

      $data = [];
      $data['userid'] = $userid;
      $data['code'] = $verifyCode;
      $data['timeout'] = TIMENOW;
      $data['retries'] = 1;
      $data['signature'] = $this->user_signature;
      $data['ip'] = $this->getUserIP();
      $data['agent'] = json_encode([ 'agent' => $_SERVER['HTTP_USER_AGENT'], 'location' => $this->user_location ]);
      $data['login_time'] = TIMENOW;
      $data['verified'] = ($otpenabled)? 0 : 1;
      $this->db->insert('login_activity', $data);

      if(! $otpenabled) return true;

      $this->sendNotify('smsLogin', array('userid' => $userid, 'code' => $verifyCode));
      return false;
    }
  }

  public function sendVerifyCode($userid)
  {
    $verifyCode = strtoupper($this->salt(4));
    $this->db->query("UPDATE user SET sms_code='$verifyCode',sms_code_timeout='" . TIMENOW . "' WHERE id='$userid'");
    $this->sendNotify('smsLogin', array('userid' => $userid, 'code' => $verifyCode));
  }

  public function isEmailAssoc($email)
  {
    return $this->db->get_var("SELECT id FROM user WHERE email='$email'");
  }

  public function isMobileAssoc($mobile)
  {
    return $this->db->get_var("SELECT id FROM user WHERE mobile='$mobile'");
  }

  public function newAccount($data)
  {
    $bool = $this->db->get_var("SELECT id FROM user WHERE username='$data[username]' OR email='$data[email]'");
    if($bool){
      return false;
    }
    $userid = $this->db->insert('user', $data);
    return $userid;
  }

  public function checkUsernameExist($username, $exceptID = false)
  {
    $where = '';
    if($exceptID) {
      $where = 'AND id<>' . $exceptID;
    }
    $bool = $this->db->get_var("SELECT id FROM user WHERE username='$username' " . $where);
    return $bool && $bool > 0;
  }

}
