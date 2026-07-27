<?php

class rent extends rent_model
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
    if(empty($_GET['fdate'])){
      $_GET['fdate'] = date('Y-m-1');
      $_GET['todate'] = date('Y-m-t');
    }
    if($_GET['type'] == 'rent'){
      $where[] = "payments.type!='expenses'";
    }
    if($_GET['type'] == 'expenses'){
      $where[] = "payments.type='expenses'";
    }
    if($_GET['transtype'] == 'unpaid'){
      $where[] = "payments.gone='0'";
    }
    if($_GET['transtype'] == 'paid'){
      $where[] = "payments.gone='1'";
    }

    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "payments.paydate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }

    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
