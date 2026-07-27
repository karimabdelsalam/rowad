<?php

class bank_accounts extends bank_accounts_model
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
    } else{
      $this->deleteExec($_GET['id']);
    }
  }

  public function markasdefault()
  {
    $this->markAdDefault($_GET['id']);
    $this->redirect();
  }

  public function printable()
  {
    $this->Smarty->assign('data', $this->readrecord($_GET['id']));
    $this->printVersion();
  }

  public function sms()
  {
    if(empty($_GET['number']) || empty($_GET['id'])){
      echo 0;
      exit;
    }
    $bank = $this->readrecord($_GET['id']);
    $response = $this->sendSMS($_GET['number'], gettext('رقم الحساب') . ' ' . $bank['accno']);
    echo $response;
    exit;
  }

  public function add()
  {
    if($_POST){
      $data = array();
      $data['fullname'] = $_POST['fullname'];
      $data['bankid'] = $_POST['bankid'];
      $data['branchno'] = $_POST['branchno'];
      $data['accno'] = $_POST['accno'];
      $data['iban'] = $_POST['iban'];
      $data['balance'] = $_POST['balance'];
      $accid = $this->addrecord($data);
      $this->registerLog($accid);
      if($_POST['quick_form']){
        $this->showmsg(json_encode(array('id' => $accid, 'title' => $_POST['fullname'], 'element' => $_POST['elementID'])), 'AutoAdder');
      }
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->Smarty->assign('banks', $this->auto_load_list('banks'));
      $this->Output('edit');
    }
  }

  public function edit()
  {
    if($_POST){
      $bank = $this->readrecord($_POST['id']);
      if($_POST['balance'] < $bank['balance']){
        //$this->showmsg(gettext('لا يمكن الأستمرار حيث ان قيمة الرصيد المدخل حديثاً اقل من الرصيد السابق'), 0);
      }
      $data = array();
      $data['fullname'] = $_POST['fullname'];
      $data['bankid'] = $_POST['bankid'];
      $data['branchno'] = $_POST['branchno'];
      $data['accno'] = $_POST['accno'];
      $data['iban'] = $_POST['iban'];
      $data['balance'] = $_POST['balance'];
      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->Smarty->assign('banks', $this->auto_load_list('banks'));
      $this->Smarty->assign('data', $this->readrecord($_GET['id']));
      $this->Output();
    }
  }

  public function transactions()
  {
    $where = array();
    $inner = '';
    if($_GET['id']){
      $where[] = "bankacc_transactions.bankid='$_GET[id]'";
    }
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "bankacc_transactions.createdtime BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    $results = $this->listTransactions($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['bank']){
      $where[] = "banks.title LIKE '%$_GET[bank]%'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
