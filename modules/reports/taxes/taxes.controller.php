<?php

class taxes extends taxes_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function view()
  {
    $transactions = $this->auto_load('transactions');
    $this->Smarty->assign('data', $transactions->readrecord($_GET['id']));
    $this->Output('printable');
  }

  public function printable()
  {
    $transactions = $this->auto_load('transactions');
    $this->Smarty->assign('data', $transactions->readrecord($_GET['id']));
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
    $this->Smarty->assign('build', $this->getBuilding($_GET['buildid']));
    $where[] = "transactions.buildid='$_GET[buildid]'";

    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "transactions.paydate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }

    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
