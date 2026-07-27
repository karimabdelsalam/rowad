<?php

class corp_taxes extends corp_taxes_model
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
    $where[] = 'transactions.gone = 1';
    $where[] = 'transactions.typeid=\'comp_tax\'';

    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "transactions.gone_date BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }

    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
