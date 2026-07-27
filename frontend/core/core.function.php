<?php

class core extends systemcore
{

  public function __construct()
  {
    parent::__construct(FRONT_DIR_MOD, CP_DIR_NAME);
  }

  public function check_api_interface()
  {

  }

  public function check_api_key()
  {

  }

  public function bootstrap()
  {
    $this->settings();
    if(! empty($_SESSION['userid'])) {
      $_SESSION['userinfo'] = $this->userAuth($_SESSION['userid']);
      if($_SESSION['userinfo']['lang'] == 'ar') {
        $_SESSION['lang'] = ['title' => 'العربية', 'code' => 'ar', 'dir' => 'rtl'];
      } elseif($_SESSION['userinfo']['lang'] == 'en') {
        $_SESSION['lang'] = ['title' => 'English', 'code' => 'en', 'dir' => 'ltr'];
      }
    } elseif($this->config['lang'] == 'ar') {
      $_SESSION['lang'] = ['title' => 'العربية', 'code' => 'ar', 'dir' => 'rtl'];
    } elseif($this->config['lang'] == 'en') {
      $_SESSION['lang'] = ['title' => 'English', 'code' => 'en', 'dir' => 'ltr'];
    }
  }

  public function userAuth($userid)
  {
    $this->userinfo = $this->db->get_row("SELECT * FROM user WHERE id='$userid'");
    return $this->userinfo;
  }

  public function settings()
  {
    $settings = $this->db->get_results("SELECT * FROM setting");
    foreach($settings as $setting){
      if(in_array($setting['varname'], ['billing_info', 'updatecore'])){
        $this->config[$setting['varname']] = json_decode($setting['value'], true);
      } else {
        $this->config[$setting['varname']] = $setting['value'];
      }
    }
    define('SYS_CALENDAR', $this->config['system_calendar']);
  }

}
