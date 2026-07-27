<?php

class pays_processed extends pays_processed_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function pending()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->pendingExec(0, $ids);
      $this->redirect();
    } else{
      $this->pendingExec($_GET['id']);
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

  public function printable()
  {
    $data = $this->readrecord($_GET['id']);
    $this->Smarty->assign('data', $data);

    if(!empty($data['statementid'])){
      $statement = $this->auto_load_read($data['statementid'], 'statementsin');
      $banksmodule = $this->auto_load('banks');

      if($statement['pay_method'] == 'cash'){
        $payment = gettext('نقدا');
      } elseif($statement['pay_method'] == 'cheque'){
        $bank = $banksmodule->readrecord($statement['cheque']['bankid']);
        $payment = gettext('شيك') . '<br />';
        $payment .= gettext('رقم الشيك') . ': ' . $statement['cheque']['no'] . '<br />';
        $payment .= gettext('اسم البنك') . ': ' . $bank['title'] . '<br />';
        $payment .= gettext('تاريخه') . ': ' . (($statement['calendar'] == 2) ? uCal::g2u($statement['cheque']['issuedate']) : $statement['cheque']['issuedate']);
      } elseif($statement['pay_method'] == 'bank'){
        $bank = $banksmodule->readrecord($statement['bank']['frombankid']);
        $payment = gettext('تحويل بنكي') . '<br />';
        $payment .= gettext('رقم مرجع التحويل') . ': ' . $statement['bank']['refno'] . '<br />';
        $payment .= gettext('اسم البنك') . ': ' . $bank['title'] . '<br />';
        $payment .= gettext('رقم الحساب') . ': ' . $bank['bank']['fromname'] . '<br />';
        $payment .= gettext('تاريخه') . ': ' . (($statement['calendar'] == 2) ? uCal::g2u($statement['bank']['issuedate']) : $statement['bank']['issuedate']);
      }
      $this->Smarty->assign('payment', $payment);
    }
    $this->printVersion();
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "payments.paydate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    if($_GET['ownerid']){
      $owner = $this->auto_load('owner');
      $this->Smarty->assign('from', $owner->readrecord($_GET['ownerid']));
      $buildIDs = $owner->getBuildsOwns($_GET['ownerid']);
      if(!empty($buildIDs)) $where[] = "payments.buildid IN($buildIDs)";
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

    $url = parse_url($_SERVER['REQUEST_URI']);
    $this->Smarty->assign('params', preg_replace('/type=([a-zA-Z]+)&?/', '', $url['query']));

    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
