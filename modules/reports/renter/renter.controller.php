<?php

class renter extends renter_model
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
    if(empty($_GET['renterid'])){
      $results = array();
      $this->Output();
      exit;
    }
    $this->Smarty->assign('from', $this->getRenter($_GET['renterid']));
    $ids = $this->getRenterContracts($_GET['renterid']);
    if(empty($ids)){
      $results = array();
      $this->Output();
      exit;
    }
    $where[] = "payments.contractid IN($ids)";

    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "payments.paydate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }

    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}