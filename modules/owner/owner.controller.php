<?php

class owner extends owner_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function delattach()
  {
    if($_GET['id']){
      $this->deleteAttachFile($_GET['id']);
      $this->redirect();
    }
  }

  public function delete()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->deleteExec(0, $ids);
      $this->redirect(CPURL . '/' . Module);
    } else{
      $this->deleteExec($_GET['id']);
    }
  }

  public function reset()
  {
    if(empty($_GET['id'])){
      echo 0;
      exit;
    }
    $accounts = $this->auto_load('manager');
    $newpass = $this->salt(10);
    $accounts->updatePassword(md5($newpass), $_GET['id']);
    $this->sendNotify('recoverPWD', array('userid' => $_GET['id'], 'newpass' => $newpass, 'path' => OWNER_DIR_PATH));
    echo 1;
    exit;
  }

  public function loginas()
  {
    if(empty($_GET['id'])){
      exit;
    }
    $owner = $this->readrecord($_GET['id']);
    if(empty($owner['userid'])){
      $this->showmsg(gettext('لم يتم العثور على حساب. يرجى تعيين اسم مستخدم و كلمة مرور اولاً.'), 0);
    }
    $adminID = $_SESSION['userid'];
    session_destroy();
    session_name(COOKIEPREFIX);
    session_start();
    $_SESSION['adminid'] = $adminID;
    $_SESSION['userid'] = $owner['userid'];
    define('OUTPUT_NO_SIDEBAR', true);
    $this->redirect(BASEURL.'/'.OWNER_DIR_PATH);
    exit;
  }

  public function search()
  {
    if(strlen(utf8_decode($_GET['q'])) < 1){
      //return false;
    }
    $results = $this->searchQuery($_GET['q']);
    echo json_encode($results);
  }

  public function add()
  {
    if($_POST){
      if(!empty($_FILES['idcopy']['tmp_name'])){
        $idcopy = $this->Upload_File($_FILES['idcopy'], 'owner', 'image');
      }

      if(!empty($_POST['username'])){
        $this->checkExist(['email','password']);
        $accounts = $this->auto_load('manager');
        $account = [];
        $account['name'] = $_POST['fname'] . ' ' . $_POST['fathname'];
        $account['username'] = $_POST['username'];
        $account['password'] = md5($_POST['password']);
        $account['groupid'] = 2;
        $account['email'] = $_POST['email'];
        $account['mobile'] = $_POST['mobile'];
        $account['active'] = $_POST['active'];
        $account['lang'] = $_SESSION['userinfo']['lang'];
        $account['joindate'] = TIMENOW;
        $userid = $accounts->addNewManager($account);
      } else {
        $userid = 0;
      }

      $data = array();
      $data['userid'] = $userid;
      $data['fname'] = $_POST['fname'];
      $data['lname'] = $_POST['lname'];
      $data['fathname'] = $_POST['fathname'];
      $data['famname'] = $_POST['famname'];
      $data['email'] = $_POST['email'];
      $data['address'] = $_POST['address'];
      $data['nationality'] = $_POST['nationality'];
      $data['idtype'] = $_POST['idtype'];
      $data['idnumber'] = $_POST['idnumber'];
      if(!empty($_FILES['idcopy']['tmp_name'])){
        $data['idcopy'] = $idcopy;
      }
      $data['work'] = $_POST['work'];
      $data['homephone'] = $_POST['homephone'];
      $data['mobile'] = $_POST['mobile'];
      $data['workphone'] = $_POST['workphone'];
      $ownerid = $this->addrecord($data);
      $this->registerLog($ownerid);
      $this->moveAttachments($_POST['tempid'], $ownerid);
      if($_POST['quick_form']){
        $qformTitle = $_POST['fname'] . ' ' . $_POST['lname'] . ' ' . $_POST['fathname'] . ' ' . $_POST['famname'];
        $this->showmsg(json_encode(array('id' => $ownerid, 'title' => $qformTitle, 'element' => $_POST['elementID'])), 'AutoAdder');
      }
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->loadNationalities();
      $this->Output('edit');
    }
  }

  public function edit()
  {
    if($_POST){
      if(!empty($_FILES['idcopy']['tmp_name'])){
        $idcopy = $this->Upload_File($_FILES['idcopy'], 'owner', 'image');
      }

      if(!empty($_POST['username'])){
        $owner = $this->readrecord($_POST['id']);
        $this->checkExist(['email']);
        $accounts = $this->auto_load('manager');
        $account = [];
        $account['username'] = $_POST['username'];
        if(!empty($_POST['password'])){
          $account['password'] = md5($_POST['password']);
        }
        $account['groupid'] = 2;
        $account['name'] = $_POST['fname'] . ' ' . $_POST['fathname'];
        $account['email'] = $_POST['email'];
        $account['mobile'] = $_POST['mobile'];
        $account['active'] = $_POST['active'];
        if($owner['userid'] > 0) {
          $accounts->updateManager($account, $owner['userid']);
          $userid = $owner['userid'];
        } else {
          $this->checkExist(['password']);
          $account['lang'] = $_SESSION['userinfo']['lang'];
          $account['joindate'] = TIMENOW;
          $userid = $accounts->addNewManager($account);
        }
      }

      $data = array();
      $data['userid'] = $userid;
      $data['fname'] = $_POST['fname'];
      $data['lname'] = $_POST['lname'];
      $data['fathname'] = $_POST['fathname'];
      $data['famname'] = $_POST['famname'];
      $data['email'] = $_POST['email'];
      $data['address'] = $_POST['address'];
      $data['nationality'] = $_POST['nationality'];
      $data['idtype'] = $_POST['idtype'];
      $data['idnumber'] = $_POST['idnumber'];
      if(!empty($_FILES['idcopy']['tmp_name'])){
        $data['idcopy'] = $idcopy;
      }
      $data['work'] = $_POST['work'];
      $data['homephone'] = $_POST['homephone'];
      $data['mobile'] = $_POST['mobile'];
      $data['workphone'] = $_POST['workphone'];
      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      $this->moveAttachments($_POST['tempid'], $_POST['id']);
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else {
      $data = $this->readrecord($_GET['id']);
      $this->Smarty->assign('data', $data);
      $this->Smarty->assign('account', $this->auto_load_read($data['userid'], 'manager'));
      $this->loadNationalities();
      $this->Output();
    }
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['fullname']){
      $where[] = "(owner.fname LIKE '%$_GET[fullname]%' OR owner.lname LIKE '%$_GET[fullname]%' OR owner.fathname LIKE '%$_GET[fullname]%' OR owner.famname LIKE '%$_GET[fullname]%')";
    }
    if($_GET['mobile']){
      $where[] = "owner.mobile='$_GET[mobile]'";
    }
    if($_GET['email']){
      $where[] = "owner.email='$_GET[email]'";
    }
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "owner.timepost BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
