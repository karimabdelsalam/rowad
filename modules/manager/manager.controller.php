<?php

class manager extends manager_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function active()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markasActive(0, $ids);
    } else{
      $this->markasActive($_GET['id']);
    }
    $this->redirect(CPURL . '/' . Module);
  }

  public function inactive()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markasinActive(0, $ids);
    } else{
      $this->markasinActive($_GET['id']);
    }
    $this->redirect(CPURL . '/' . Module);
  }

  public function edit()
  {
    if($_POST){
      $this->checkExist(array('userid', 'username', 'name', 'email'));
      $data = array();
      $userid = $_POST['userid'];
      $data['username'] = $_POST['username'];
      if($_POST['password']){
        $data['password'] = md5($_POST['password']);
      }
      if(!empty($_FILES['picture']['tmp_name'])){
        $data['picture'] = $this->Upload_File($_FILES['picture'], 'user_img', 'image');
      }
      $data['name'] = $_POST['name'];
      $data['email'] = $_POST['email'];
      $data['idnum'] = $_POST['idnum'];
      $data['mobile'] = $_POST['mobile'];
      $data['active'] = $_POST['active'];
      $data['lang'] = $_POST['lang'];
      $this->updateManager($data, $userid);
      $this->updateAdminPermission($userid);
      $this->registerLog($userid);
      $this->showmsg(CPURL . '/' . Module, 1);
    } else{
      $this->Smarty->assign('manager', $this->getUserInfo($_GET['id']));
      $this->Smarty->assign('permissions', $this->loadUserPermissions($_GET['id']));
      $this->Output();
    }
  }

  public function add()
  {
    if($_POST){
      $this->checkExist(array('username', 'name', 'password', 'email'));
      $data = array();
      if(!empty($_FILES['picture']['tmp_name'])){
        $data['picture'] = $this->Upload_File($_FILES['picture'], 'user_img', 'image');
      } else {
        $data['picture'] = 'user_img/default.png';
      }
      $data['groupid'] = 1;
      $data['username'] = $_POST['username'];
      $data['password'] = md5($_POST['password']);
      $data['name'] = $_POST['name'];
      $data['email'] = $_POST['email'];
      $data['idnum'] = $_POST['idnum'];
      $data['mobile'] = $_POST['mobile'];
      $data['active'] = $_POST['active'];
      $data['lang'] = $_POST['lang'];
      $data['joindate'] = TIMENOW;
      $userid = $this->addNewManager($data);
      $this->updateAdminPermission($userid);
      $this->registerLog($userid);
      $this->showmsg(CPURL . '/' . Module, 1);
    } else{
      $this->Smarty->assign('permissions', $this->loadUserPermissions(0));
      $this->Output('edit');
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

  public function index()
  {
    $where = array();
    if($_GET['name']){
      $where[] = "name LIKE '%$_GET[name]%'";
    }
    if($_GET['email']){
      $where[] = "email='$_GET[email]'";
    }
    if($_GET['username']){
      $where[] = "username='$_GET[username]'";
    }
    $results = $this->getManagers($where);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
