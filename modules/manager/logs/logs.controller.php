<?php

class logs extends logs_model
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
      $this->redirect();
    } else{
      $this->deleteExec($_GET['id']);
    }
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['username']){
      $where[] = "user.name='$_GET[username]'";
    }
    if($_GET['moduleid']){
      $moduleSubIDs = $this->getSubModules($_GET['moduleid']);
      $where[] = "cp_logs.moduleid IN ($moduleSubIDs)";
    }
    if($_GET['rentid']){
      $where[] = "cp_logs.objectid='$_GET[rentid]' AND moduleid='97'";
    }
    if($_GET['sellid']){
      $where[] = "cp_logs.objectid='$_GET[sellid]' AND moduleid='98'";
    }
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "cp_logs.timepost BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
