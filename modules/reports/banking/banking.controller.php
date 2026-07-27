<?php

class banking extends banking_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function view()
  {
    $statementsout = $this->auto_load('statementsout');
    $this->Smarty->assign('data', $statementsout->readrecord($_GET['id']));
    $this->Smarty->assign('companyowners', $statementsout->readOwners());
    $this->Smarty->assign('bankaccounts', $this->auto_load_list('bank_accounts'));
    $this->Smarty->assign('banks', $this->auto_load_list('banks'));
    $this->Output('printable');
  }

  public function printable()
  {
    $statementsout = $this->auto_load('statementsout');
    $this->Smarty->assign('data', $statementsout->readrecord($_GET['id']));
    $this->Smarty->assign('companyowners', $statementsout->readOwners());
    $this->Smarty->assign('bankaccounts', $this->auto_load_list('bank_accounts'));
    $this->Smarty->assign('banks', $this->auto_load_list('banks'));
    $this->printVersion();
  }

  public function index()
  {
    $where = array();
    $inner = '';

    if($_GET['account']){
      $where[] = "bankacc_transactions.bankid='$_GET[account]'";
    }
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "bankacc_transactions.createdtime BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    if($_GET['famount'] != '' && $_GET['toamount'] != ''){
      $where[] = "bankacc_transactions.amount BETWEEN '" . $_GET['famount'] . "' AND '" . $_GET['toamount'] . "'";
    }

    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Smarty->assign('bankaccounts', $this->auto_load_list('bank_accounts'));

    $this->enqueueJSLibrary('charts');
    $this->Output('', $results);
  }

}
