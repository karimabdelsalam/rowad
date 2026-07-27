<?php

class pays_pending extends pays_pending_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function statmin()
  {
    $ids = implode(',', $_GET['ids']);
    $this->redirect(CPURL.'/statementsin/add/?payid='.$ids);
  }

  public function process()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->processExec(0, $ids);
      $this->redirect();
    } else {
      $this->processExec($_GET['id']);
      $this->showmsg('showStatinModal', 'callback', $_GET['id']);
    }
  }

  public function processninv()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->processExec(0, $ids);
      $this->redirect(CPURL.'/invoices/create/?ids='.$ids);
    } elseif($_GET['payid']){
      $this->processExec($_GET['payid']);
      $this->redirect(CPURL.'/invoices/create/?payid='.$_GET['payid']);
    }
  }

  public function invoice()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->redirect(CPURL.'/invoices/create/?ids='.$ids);
    } elseif($_GET['payid']){
      $this->redirect(CPURL.'/invoices/create/?payid='.$_GET['payid']);
    }
  }

  public function reset_partpay()
  {
    $data = array();
    $data['paidamount'] = 0;
    $this->updaterecord($data, $_GET['id']);
    echo 1;
    exit;
  }

  public function partpay()
  {
    if($_POST){
      if($_POST['partamount']){
        $payment = $this->readrecord($_POST['id']);
        if(($payment['paidamount']+$_POST['partamount']) > $payment['amount']){
          $this->showmsg(gettext('المبلغ المدفوع تعدى إجمالي المبلغ المستحق'), 0);
        }
        $data = array();
        $data['paidamount'] = $payment['paidamount']+$_POST['partamount'];
        $this->updaterecord($data, $_POST['id']);
      }
      if($_POST['process']){
        $this->processExec($_POST['id']);
      }
      if($_POST['processall']){
        $transactions = $this->auto_load('transactions');
        $transactions->finishStatByPaymentID($_POST['id']);
      }
      $this->showmsg('', 'close-quick-form');
    }
    elseif($_GET['contractid']){
      $paymentid = $this->rentLastPaymentID($_GET['contractid']);
      $this->Smarty->assign('data', $this->readrecord($paymentid));
      $this->Output();
    }
    else{
      $this->Smarty->assign('data', $this->readrecord($_GET['id']));
      $this->Output();
    }
  }

  public function printable()
  {
    $this->Smarty->assign('data', $this->readrecord($_GET['id']));
    $this->printVersion();
  }

  public function list()
  {
    $this->index();
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "payments.paydate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    if($_GET['ownerid']){
      $owner = $this->db->get_row("SELECT fname,lname,fathname,famname FROM owner WHERE id='$_GET[ownerid]'");
      $this->Smarty->assign('owner', $owner);
      $where[] = "JSON_CONTAINS(JSON_EXTRACT(building.owner, '$.id[*]'), '\"$_GET[ownerid]\"', '$')";
    }
    if($_GET['buyerid']){
      $this->Smarty->assign('buyer', $this->auto_load_read($_GET['buyerid'], 'buyer'));
      $where[] = "JSON_CONTAINS(JSON_EXTRACT(rent_contracts.buyer, '$.id[*]'), '\"$_GET[buyerid]\"', '$')";
    }
    if($_GET['buildid']){
      $buildtitle = $this->db->get_var("SELECT title FROM building WHERE id='$_GET[buildid]'");
      $this->Smarty->assign('buildtitle', $buildtitle);
      $where[] = "payments.buildid='$_GET[buildid]'";
    }
    if($_GET['rentid']){
      $buildid = $this->db->get_var("SELECT buildid FROM rent_contracts WHERE id='$_GET[rentid]'");
      $this->Smarty->assign('build', $this->auto_load_read($buildid, 'building'));
      $where[] = "payments.contractid='$_GET[rentid]' AND module='rent'";
    }
    if($_GET['sellid']){
      $buildid = $this->db->get_var("SELECT buildid FROM sell_contracts WHERE id='$_GET[sellid]'");
      $this->Smarty->assign('build', $this->auto_load_read($buildid, 'building'));
      $where[] = "payments.contractid='$_GET[sellid]' AND module='sell'";
    }
    if($_GET['type']){
      switch($_GET['type']) {
        case 'pending':
          $where[] = "paydate>NOW()";
          break;
        case 'late':
          $where[] = "paydate<NOW()";
          break;
      }
    }

    $url = parse_url($_SERVER['REQUEST_URI']);
    $this->Smarty->assign('params', preg_replace('/type=([a-zA-Z]+)&?/', '', $url['query']));

    $results = $this->listrecord($where, true, $inner, Action == 'list');
    $this->Smarty->assign('results', $results);
    $this->enqueueJSLibrary('charts');
    $this->Output('', $results);
  }

}
