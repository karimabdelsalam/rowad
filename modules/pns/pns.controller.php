<?php

class pns extends pns_model
{

  public function __construct()
  {
    parent::__construct();
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

  public function device_token()
  {
    if($_POST){
      $this->checkExist(array('device_token','device_type'));

      $tokenid = $this->checkAdded($_POST['device_token'], $_POST['device_type']);

      if($tokenid) {
        $this->updaterecord(['userid' => $_SESSION['userid']], $tokenid);
      } else {
        $data = array();
        $data['userid'] = $_SESSION['userid'];
        $data['token'] = $_POST['device_token'];
        $data['token_md5'] = md5($_POST['device_token']);
        $data['platform'] = $_POST['device_type'];

        $this->addrecord($data);
      }

      $this->showmsg('Added successfully', 1);
    }
  }

  public function list()
  {
    $where = array();
    $inner = '';
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
