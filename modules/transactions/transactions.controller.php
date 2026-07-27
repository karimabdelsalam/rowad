<?php

class transactions extends transactions_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function statmin()
  {
    $ids = implode(',', $_GET['ids']);
    $this->redirect(CPURL.'/statementsin/add/?transid='.$ids);
  }

  public function statmout()
  {
    $ids = implode(',', $_GET['ids']);
    $this->redirect(CPURL.'/statementsout/add/?transid='.$ids);
  }

  public function finish()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->finishStat(0, $ids);
      $this->redirect();
    } else{
      $this->finishStat($_GET['id']);
      $transaction = $this->readrecord($_GET['id']);
      $this->showmsg('showStatoutModal', 'callback', ['transid' => $_GET['id'], 'ownerid' => $transaction['ownerid'] ]);
    }
  }

  public function cancel()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->returnStat(0, $ids);
      $this->redirect();
    } else{
      $this->returnStat($_GET['id']);
    }
  }

  public function printable()
  {
    $this->Smarty->assign('data', $this->readrecord($_GET['id']));
    $this->printVersion();
  }

  public function collectStatement($statementid, $statement = array())
  {
    if(empty($statement)){
      $statement = $this->auto_load_read($statementid, 'statementsout');
    }
    $building = $this->auto_load_read($statement['buildid'], 'building');
    $transaction = array();
    $transaction['buildid'] = $statement['buildid'];
    $transaction['statoutid'] = $statementid;
    $transaction['module'] = 'sell';

    foreach($building['owner']['id'] as $key => $ownerid){
      if(!empty($building['owner']['outgoings'])){
        $transaction['typeid'] = 'outgoings';
        $transaction['ownerid'] = $building['owner']['id'][$key];
        $transaction['contractid'] = $building['contractid'];
        $transaction['amount'] = round(($statement['amount'] * $building['owner']['outgoings'][$key]) / 100, 2);
        $transaction['direction'] = 'credit';
        $transaction['paydate'] = $statement['issueddate'];
        if($this->config['income_tax_status'] == 1){
          //$transaction['tax'] = round($transaction['amount'] * ($this->config['income_tax_value'] / 100), 2);
        }
        $this->addrecord($transaction);
      }
    }
  }

  public function collectPayment($paymentid)
  {
    if($this->checkrecord($paymentid)){
      return false;
    }
    $payment = $this->auto_load_read($paymentid, 'pays_pending');
    if($payment['type'] == 'expenses'){
      return false;
    }
    $payment['amount'] -= $payment['commission2'];

    $transaction = array();
    $transaction['contractid'] = $payment['contractid'];
    $transaction['module'] = $payment['module'];
    $transaction['buildid'] = $payment['buildid'];
    $transaction['paymentid'] = $paymentid;

    foreach($payment['owner']['id'] as $key => $ownerid){
      //rent or installement amount
      $total = round(($payment['amount'] * $payment['owner']['share'][$key]) / 100, 2);
      $transaction['typeid'] = 'payment';
      $transaction['ownerid'] = $payment['owner']['id'][$key];
      $transaction['amount'] = $total;
      $transaction['direction'] = 'debit';
      $transaction['paydate'] = $payment['paydate'];
      $this->addrecord($transaction);
      //calulate tax
      if(!empty($payment['tax'])){
        $tax = round(($payment['tax'] * $payment['owner']['share'][$key]) / 100, 2);
        $transaction['typeid'] = 'tax';
        $transaction['amount'] = $tax;
        $transaction['direction'] = 'credit';
        $this->addrecord($transaction);
      } else {
        $tax = 0;
      }
      //calulate office comission
      if(!empty($payment['owner']['outgoings']) && !empty($payment['commission'])){
        $commission = round(($payment['commission'] * $payment['owner']['outgoings'][$key]) / 100, 2);
        $transaction['typeid'] = 'commission';
        $transaction['amount'] = $commission;
        $transaction['direction'] = 'credit';
        $transID = $this->addrecord($transaction);
        if($this->config['income_tax_status'] == 1){
          $transaction['amount'] = round($transaction['amount'] * ($this->config['income_tax_value'] / 100), 2);
          $transaction['typeid'] = 'comp_tax';
          $transaction['parent'] = $transID;
          $this->addrecord($transaction);
        }
        $transaction['parent'] = 0;
      } else {
        $commission = 0;
      }
      if($this->config['owner_tax_status'] == 1){
        $transaction['typeid'] = 'owner_tax';
        $transaction['amount'] = round((($total-$tax-$commission) * $this->config['owner_tax_value']) / 100, 2);
        $transaction['direction'] = 'credit';
        $this->addrecord($transaction);
      }
    }

    if($payment['commission2'] > 0){
      $transaction['amount'] = $payment['commission2'];
      $transaction['typeid'] = 'commission2';
      $transaction['ownerid'] = 0;
      $transaction['direction'] = 'debit';
      $transaction['statinid'] = $payment['statementid'];
      $transaction['paydate'] = $payment['paydate'];
      $transaction['gone_date'] = $payment['paydate'];
      $transaction['gone'] = 1;
      $transID = $this->addrecord($transaction);
      if($this->config['income_tax_status'] == 1){
        $transaction['statinid'] = 0;
        $transaction['gone_date'] = '0000-00-00';
        $transaction['gone'] = 0;
        $transaction['direction'] = 'credit';
        $transaction['amount'] = round($transaction['amount'] * ($this->config['income_tax_value'] / 100), 2);
        $transaction['typeid'] = 'comp_tax';
        $transaction['parent'] = $transID;
        $this->addrecord($transaction);
      }
    }
  }

  public function pending()
  {
    $this->listController(array("transactions.gone='0'"));
  }

  public function processed()
  {
    $this->listController(array("transactions.gone='1'"));
  }

  public function index()
  {
    $this->listController([]);
  }

  private function listController($where)
  {
    $inner = '';
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "transactions.paydate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    if($_GET['typeid']){
      $where[] = "transactions.typeid='$_GET[typeid]'";
    }
    if($_GET['ownerid']){
      $owner = $this->db->get_row("SELECT fname,lname,fathname,famname FROM owner WHERE id='$_GET[ownerid]'");
      $this->Smarty->assign('owner', $owner);
      $where[] = "transactions.ownerid='$_GET[ownerid]'";
    }
    if($_GET['buildid']){
      $buildtitle = $this->db->get_var("SELECT title FROM building WHERE id='$_GET[buildid]'");
      $this->Smarty->assign('buildtitle', $buildtitle);
      $where[] = "transactions.buildid='$_GET[buildid]'";
    }
    if($_GET['rentid']){
      $buildid = $this->db->get_var("SELECT buildid FROM rent_contracts WHERE id='$_GET[rentid]'");
      $this->Smarty->assign('build', $this->auto_load_read($buildid, 'building'));
      $where[] = "transactions.contractid='$_GET[rentid]' AND transactions.module='rent'";
    }
    if($_GET['sellid']){
      $buildid = $this->db->get_var("SELECT buildid FROM sell_contracts WHERE id='$_GET[sellid]'");
      $this->Smarty->assign('build', $this->auto_load_read($buildid, 'building'));
      $where[] = "transactions.contractid='$_GET[sellid]' AND transactions.module='sell'";
    }

    $url = parse_url($_SERVER['REQUEST_URI']);
    $this->Smarty->assign('params', preg_replace('/type=([a-zA-Z]+)&?/', '', $url['query']));

    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('transtypes', $this->listTypes());
    $this->Smarty->assign('results', $results);
    $this->Output('list');
  }

}
