<?php

class emailtemplate extends emailtemplate_model
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
    $this->redirect();
  }

  public function inactive()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markasInactive(0, $ids);
    } else{
      $this->markasInactive($_GET['id']);
    }
    $this->redirect();
  }

  public function edit()
  {
    if($_POST){
      $data = array();
      $data['sms'] = $_POST['sms'];
      $data['content'] = $_POST['content'];
      $data['active'] = $_POST['active'];
      $data['sms_active'] = $_POST['sms_active'];
      $this->saveTemplate($data, $_POST['id']);
      $this->showmsg(CPURL . '/' . Module, 1);
    } else{
      $this->Smarty->assign('data', $this->readTemplate($_GET['id']));
      $this->Output();
    }
  }

  public function index()
  {
    $results = $this->listTemplate();
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}