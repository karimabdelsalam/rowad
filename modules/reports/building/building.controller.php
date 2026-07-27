<?php

class building extends building_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function view()
  {
    $pays_pending = $this->auto_load('pays_pending');
    $this->Smarty->assign('data', $pays_pending->readrecord($_GET['id']));
    $this->Output('printable');
  }

  public function printable()
  {
    $pays_pending = $this->auto_load('pays_pending');
    $this->Smarty->assign('data', $pays_pending->readrecord($_GET['id']));
    $this->printVersion();
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if(empty($_GET['buildid'])){
      $results = array();
      $this->Output();
      exit;
    }
    if(empty($_GET['fdate']) || empty($_GET['todate'])){
      $_GET['fdate'] = date('Y-m-d', strtotime('-4 Years'));
      $_GET['todate'] = date('Y-m-d');
    }

    $this->Smarty->assign('build', $this->getBuilding($_GET['buildid']));
    $where[] = "payments.buildid='$_GET[buildid]'";

    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "payments.paydate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    if($_GET['status'] == 'processed'){
      $where[] = "payments.gone='1'";
    }
    if($_GET['status'] == 'pending'){
      $where[] = "payments.gone='0'";
    }

    $results = $this->listrecord($where, true, $inner, $_GET['buildid']);
    $this->Smarty->assign('results', $results);
    $this->enqueueJSLibrary('charts');
    $this->enqueueJSLibrary('timeline');
    $this->Output('', $results);
  }

}
