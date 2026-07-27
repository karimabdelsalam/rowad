<?php

class income extends income_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function view()
  {
    $statementsin = $this->auto_load('statementsin');
    $this->Smarty->assign('data', $statementsin->readrecord($_GET['id']));
    $this->Smarty->assign('companyowners', $statementsin->readOwners());
    $this->Smarty->assign('bankaccounts', $this->auto_load_list('bank_accounts'));
    $this->Smarty->assign('banks', $this->auto_load_list('banks'));
    $this->Output('printable');
  }

  public function printable()
  {
    $statementsin = $this->auto_load('statementsin');
    $this->Smarty->assign('data', $statementsin->readrecord($_GET['id']));
    $this->Smarty->assign('companyowners', $statementsin->readOwners());
    $this->Smarty->assign('bankaccounts', $this->auto_load_list('bank_accounts'));
    $this->Smarty->assign('banks', $this->auto_load_list('banks'));
    $this->printVersion();
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "statement_in.issueddate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    if($_GET['famount'] != '' && $_GET['toamount'] != ''){
      $where[] = "statement_in.amount BETWEEN '" . $_GET['famount'] . "' AND '" . $_GET['toamount'] . "'";
    }
    if($_GET['from_type']){
      $where[] = "statement_in.from_type='$_GET[from_type]'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Smarty->assign('methods', $this->readmMethods());

    $this->enqueueJSLibrary('charts');
    $this->Output('', $results);
  }

}
