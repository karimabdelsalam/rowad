<?php

class sample extends sample_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function restore()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->backupRestore(0, $ids);
      $this->redirect(CPURL . '/' . Module);
    } else{
      $this->backupRestore($_GET['id']);
    }
  }

  public function edit()
  {
    if($_POST){
      $data = array();
      //$data['title'] = $_POST['title'];
      $data['content'] = $_POST['content'];
      $data['header'] = $_POST['header'];
      $data['footer'] = $_POST['footer'];
      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      if(DEBUG_MODE == 'localhost'){
        $this->showmsg(CPURL . '/' . Module . '/', 0);
      } else {
        $this->showmsg(CPURL . '/' . Module . '/', 1);
      }
    } else{
      $this->Smarty->assign('data', $this->getrecord($_GET['id']));
      $this->Output();
    }
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['title']){
      $where[] = "title LIKE '%$_GET[title]%'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
