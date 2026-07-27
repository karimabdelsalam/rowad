<?php

class login extends login_model
{
  public $COOKIEUSER;
  public $COOKIEPASS;

  public function __construct()
  {
    parent::__construct();
    if(empty($_GET['quick_form'])){
      define('OUTPUT_NO_SIDEBAR', true);
    }
    $this->COOKIEUSER = COOKIEPREFIX . 'username';
    $this->COOKIEPASS = COOKIEPREFIX . 'password';
  }

  public function logout()
  {
    $this->registerLog(0);
    session_destroy();
    $this->deleteCookie();
    if(defined('API_ENABLED') && API_ENABLED){
      $this->showmsg(1, 'logged out successfully');
    } else {
      header('Location: ' . CPURL . '/login');
    }
  }

  private function deleteCookie()
  {
    unset($_COOKIE[$this->COOKIEUSER], $_COOKIE[$this->COOKIEPASS]);
    setcookie($this->COOKIEUSER, '', TIMENOW - 3600);
    setcookie($this->COOKIEPASS, '', TIMENOW - 3600);
  }

  private function saveCookie($username, $password)
  {
    setcookie($this->COOKIEUSER, $username, (TIMENOW + 604800));
    setcookie($this->COOKIEPASS, $password, (TIMENOW + 604800));
  }

  public function resetpwd()
  {
    if($_POST['email']) {
      if(!$this->IsEmail($_POST['email'])){
        $this->showmsg(gettext('البريد الإلكتروني غير صحيح'), 0);
      }
      if(!$userid = $this->isEmailAssoc($_POST['email'])){
        $this->showmsg(gettext('لا يمكننا العثور على البريد الإلكتروني المدخل عن طريقكم'), 0);
      }
      $this->sendNotify('resetAccountPWD', array('userid' => $userid));
      $this->showmsg(gettext('تم ارسال بريد إلكتروني اليك يحتوي على تعليمات استعادة كلمة المرور الخاصة بك'), 'success');
    } elseif($_POST['mobile']) {
      $mobile = $this->fixMobileNumber($_POST['mobile']);
      if(!$userid = $this->isMobileAssoc($mobile)){
        $this->showmsg(gettext('لم يمكن العثور على حساب مرتبط برقم الجوال المدخل'), 0);
      }
      $this->sendNotify('otpReset', array('userid' => $userid, 'code' => $this->numSalt(6)));
      $this->showmsg(gettext('تم ارسال رسالة نصية تحتوي على رمز استعادة كلمة المرور الخاصة بك'), 'success');
    }
  }

  public function resetbyotp()
  {
    if(empty($_POST['code']) || empty($_POST['mobile'])){
      $this->showmsg(gettext('رمز الأمان غير صحيح او انتهت فترة صلاحيته'), 0);
    }

    $mobile = $this->fixMobileNumber($_POST['mobile']);
    if(!$userid = $this->isMobileAssoc($mobile)){
      $this->showmsg(gettext('لم يمكن العثور على حساب مرتبط برقم الجوال المدخل'), 0);
    }

    $userid = $this->checkRecoverHash($_POST['code'], $userid);
    if(! $userid){
      $this->showmsg(gettext('رمز الأمان غير صحيح او انتهت فترة صلاحيته'), 0);
    }

    if($_POST['newpassword']){
      if($_POST['newpassword'] != $_POST['newpassword2']){
        $this->showmsg(gettext('حقلي كلمة المرور غير متطابق'), 0);
      }
      $this->updatePassword($userid, $_POST['newpassword']);
      $this->showmsg(gettext('تم تحديث كلمة المرور بنجاح'), 1);
    } else {
      $this->showmsg(gettext('رمز الأمان صحيح'), 1);
    }
  }

  public function verifycode()
  {
    $this->checkExist(array('code'));
    if(!empty($_POST['userid'])){
      $_SESSION['useridSMS'] = $_POST['userid'];
    }
    if(empty($_SESSION['useridSMS'])){
      $this->showmsg(gettext('انتهت صلاحية الكود يمكنك طلب كود جديد'), 0);
    }
    $response = $this->checkSMSCode($_SESSION['useridSMS'], strtoupper($_POST['code']));
    if($response == 200){
      $_SESSION['userid'] = $_SESSION['useridSMS'];
      if($_POST['quick_form'] == 1){
        $this->showmsg(CPURL . '/dashboard/', 'close-quick-form');
      } elseif(defined('API_ENABLED') && API_ENABLED){
        return $this->showmsg($this->userInfo($_SESSION['useridSMS']), 1);
      } else {
        $this->showmsg(CPURL . '/dashboard/', 1);
      }
    } elseif($response == 404){
      $this->showmsg(gettext('كود التأكيد غير صحيح'), 0);
    } elseif($response == 500){
      $this->showmsg(gettext('انتهت صلاحية الكود يمكنك طلب كود جديد'), 0);
    }
  }

  public function recover($hash='')
  {
    if(empty($hash)){
      $this->showmsg(gettext('رمز الأمان غير صحيح او انتهت فترة صلاحيته'), 0);
    }
    $userid = $this->checkRecoverHash($hash);
    if(! $userid){
      $this->showmsg(gettext('رمز الأمان غير صحيح او انتهت فترة صلاحيته'), 0);
    }

    if($_POST){
      $this->checkExist(array('newpassword'));
      if($_POST['newpassword'] != $_POST['newpassword2']){
        $this->showmsg(gettext('حقلي كلمة المرور غير متطابق'), 0);
      }
      $this->updatePassword($userid, $_POST['newpassword']);
      $this->showmsg(gettext('تم تحديث كلمة المرور بنجاح'), 'success');
    } else {
      $this->Smarty->assign('recoverHash', $hash);
      $this->Output();
    }
  }

  public function index()
  {
    if(!empty($_SESSION['userid'])){
      header('Location: ' . CPURL . '/dashboard/');
    }
    if(isset($_COOKIE[$this->COOKIEUSER]) && isset($_COOKIE[$this->COOKIEPASS]) && $this->config['smslogin'] == 0){
      $userid = $this->userAuthorise('', '', true);
      if($userid > 0){
        $_SESSION['userid'] = $userid;
        header('Location: ' . CPURL . '/dashboard/');
      } else{
        $this->deleteCookie();
      }
    }
    if($_POST){
      $this->checkExist(array('username', 'password'));
      $userid = $this->userAuthorise($_POST['username'], md5($_POST['password']));
      if($userid > 0){
        if(($this->config['smslogin'] == 1 && GROUP_ID == 1) || ($this->config['smslogin_clients'] == 1 && GROUP_ID != 1)){
          $_SESSION['useridSMS'] = $userid;
          $secured = $this->checkOTP($userid, true);
          if($secured === false && defined('API_ENABLED') && API_ENABLED){
            $this->showmsg($userid, 'otp-wizard');
          }
          if($secured === false){
            $this->showmsg('.login-form-login->#smsLogin', 'wizard');
          }
        } else {
          $this->checkOTP($userid, false);
        }
        if($_POST['rememberme']){
          $this->saveCookie($_POST['username'], md5($_POST['password']));
        }
        $_SESSION['userid'] = $userid;
        if(defined('API_ENABLED') && API_ENABLED){
          return $this->showmsg($this->userInfo($userid), 1);
        } elseif($_POST['quick_form'] == 1){
          $this->registerLog(0);
          $this->showmsg(CPURL . '/dashboard/', 'close-quick-form');
        } else {
          $this->registerLog(0);
          $this->showmsg(CPURL . '/dashboard/', 1);
        }
      } else{
        $this->showmsg(gettext('اسم المستخدم او كلمة المرور خطأ'), 0);
      }
    } else{
      $this->Smarty->assign('pagetitle', gettext('تسجيل الدخول'));
      $this->Output();
    }
  }

}
