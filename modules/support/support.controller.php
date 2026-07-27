<?php

class support extends support_model
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
    } else {
      $this->deleteExec($_GET['id']);
    }
  }

  public function read()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markasRead(0, $ids);
    } else {
      $this->markasRead($_GET['id']);
    }
    $this->redirect(CPURL . '/' . Module);
  }

  public function unread()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markasunRead(0, $ids);
    } else {
      $this->markasunRead($_GET['id']);
    }
    $this->redirect(CPURL . '/' . Module);
  }

  public function show()
  {
    $this->Smarty->assign('data', $this->readrecord($_GET['id']));
    $this->Output();
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['subject']){
      $where[] = "support.subject LIKE '%$_GET[subject]%'";
    }
    if($_GET['code']){
      $where[] = "support.code LIKE '$_GET[code]'";
    }
    if($_GET['type']){
      $where[] = "support.type = '$_GET[type]'";
    }
    if($_GET['state']){
      $where[] = "support.read_state = '".(($_GET['state'] == 'seen') ? 1 : 0 )."'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
