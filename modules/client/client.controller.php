<?php

class client extends client_model
{

  public function __construct()
  {
    define('OUTPUT_NO_SIDEBAR', true);
    parent::__construct();
    $this->Smarty->assign('pagetitle', gettext('عرض معلومات العقد الآمن'));
  }

  private function generate()
  {
    echo 'sell owner: <a href="'.CPURL.'/client/?secret='.$this->encodeHash('type=sell&id=14&person=owner&time='.TIMENOW).'" target="_blank">click here</a><br>';
    echo 'sell customer: <a href="'.CPURL.'/client/?secret='.$this->encodeHash('type=sell&id=14&person=customer&time='.TIMENOW).'" target="_blank">click here</a><br>';
    echo 'rent owner: <a href="'.CPURL.'/client/?secret='.$this->encodeHash('type=rent&id=30&person=owner&time='.TIMENOW).'" target="_blank">click here</a><br>';
    echo 'rent customer: <a href="'.CPURL.'/client/?secret='.$this->encodeHash('type=rent&id=30&person=customer&time='.TIMENOW).'" target="_blank">click here</a><br>';
  }

  private function sellContract($contractid, $person)
  {
    $pays_pending = $this->auto_load('pays_pending');
    $sell_contracts = $this->auto_load('sell');

    $contract = $sell_contracts->readrecord($contractid, true);
    $contract['nextpayment'] = $pays_pending->salePaymentInfo($contractid);

    $this->Smarty->assign('results', $this->db->get_results('SELECT payments.*,payment_types.title AS pay_details FROM `payments`
        INNER JOIN payment_types ON(payment_types.name=payments.type)
        WHERE payments.module="sell" AND payments.contractid='.$contractid.' ORDER BY payments.id ASC'));
    $this->Smarty->assign('build', $this->auto_load_read($contract['buildid'], 'building'));
    $this->Smarty->assign('contract', $contract);
    $this->Smarty->assign('type', 'sell');
    $this->Smarty->assign('person', $person);

    $this->Output('index');
  }

  private function rentContract($contractid, $person)
  {
    $pays_pending = $this->auto_load('pays_pending');
    $rent_contracts = $this->auto_load('rent');

    $contract = $rent_contracts->readrecord($contractid, true);
    $contract['nextpayment'] = $pays_pending->rentPaymentInfo($contractid);
    $openContracts = $this->db->get_results("SELECT startdate,enddate FROM `rent_contracts` WHERE buildid='$contract[buildid]' AND startdate BETWEEN DATE(NOW() - INTERVAL 2 YEAR) AND DATE(NOW() + INTERVAL 5 YEAR) ORDER BY startdate ASC");
    if($openContracts){
      foreach($openContracts as $openContract){
        $contract['contracts_timeline'][] = [ 'start' => $openContract['startdate'], 'end' => $openContract['enddate'] ];
      }
    }

    $this->Smarty->assign('results', $this->db->get_results('SELECT payments.*,payment_types.title AS pay_details FROM `payments`
        INNER JOIN payment_types ON(payment_types.name=payments.type)
        WHERE payments.module="rent" AND payments.contractid='.$contractid.' ORDER BY payments.id ASC'));
    $this->Smarty->assign('build', $this->auto_load_read($contract['buildid'], 'building'));
    $this->Smarty->assign('contract', $contract);
    $this->Smarty->assign('type', 'rent');
    $this->Smarty->assign('person', $person);

    $this->enqueueJSLibrary('timeline');
    $this->Output('index');
  }

  public function index()
  {
    if(empty($_REQUEST['secret'])){
      $this->showmsg(gettext('الرابط الإلكتروني غير صالح.'), 0);
    }

    $_REQUEST['secret'] = str_replace(' ', '+', urldecode($_REQUEST['secret']));
    $sentpayload = $this->decodeHash($_REQUEST['secret']);
    if(empty($sentpayload)){
      exit;
    }
    parse_str($sentpayload, $payload);

    if($payload['type'] == 'invoice'){
      $invoice = $this->auto_load('invoices');
      $_GET['id'] = $payload['id'];
      $invoice->taxbill();
    } elseif($payload['type'] == 'sell'){
      $contract = $this->db->get_row('SELECT buildid,buyer FROM `sell_contracts` WHERE id='.$payload['id']);
      if(empty($contract)){
        $this->showmsg(gettext('الرابط الإلكتروني غير صالح.'), 0);
      }
      if($_POST['passcode'] || $this->config['ec_sms_passcode'] == 0){
        $this->verifyPassCode($_POST['passcode'], $payload);
        $this->sellContract($payload['id'], $payload['person']);
        exit;
      }
      $passcode = $this->salt(6);
      if($payload['person'] == 'owner'){
        $this->sendOwnersPassCode($passcode, $contract['buildid']);
      } else {
        $this->sendCustomersPassCode($passcode, json_decode($contract['buyer'], true));
      }
    } elseif($payload['type'] == 'rent'){
      $contract = $this->db->get_row('SELECT buildid,buyer FROM `rent_contracts` WHERE id='.$payload['id']);
      if(empty($contract)){
        $this->showmsg(gettext('الرابط الإلكتروني غير صالح.'), 0);
      }
      if($_POST['passcode'] || $this->config['ec_sms_passcode'] == 0){
        $this->verifyPassCode($_POST['passcode'], $payload);
        $this->rentContract($payload['id'], $payload['person']);
        exit;
      }
      $passcode = $this->salt(6);
      if($payload['person'] == 'owner'){
        $this->sendOwnersPassCode($passcode, $contract['buildid']);
      } else {
        $this->sendCustomersPassCode($passcode, json_decode($contract['buyer'], true));
      }
    } else {
      $this->showmsg(gettext('الرابط الإلكتروني غير صالح.'), 0);
    }

    $this->Smarty->assign('secret', $this->encodeHash('type='.$payload['type'].'&id='.$payload['id'].'&person='.$payload['person'].'&passcode='.$passcode.'&time='.TIMENOW));
    if(DEBUG_MODE == 'localhost'){
      $this->Smarty->assign('testpasscode', $passcode);
    }

    $this->Output('verify');
  }

  private function verifyPassCode($inptpass, $payload){
    if($this->config['ec_sms_passcode'] == 0){
      return true;
    }
    if(strtolower($inptpass) != strtolower($payload['passcode'])){
      $this->showmsg(gettext('كود التأمين غير صحيح'), 1);
    }
    if($payload['time']+600 < TIMENOW){
      $this->showmsg(gettext('كود التأمين انتهت صلاحيته'), 1);
    }
  }

  private function sendOwnersPassCode($passcode, $buildid){
    $buildmodule = $this->auto_load('building');
    $ownermodule = $this->auto_load('owner');

    $building = $buildmodule->readrecord($buildid);
    foreach($building['owners'] as $ownerid){
      $owner = $ownermodule->readrecord($ownerid);
      if($owner['ec_passcode'] < $this->config['ec_sms_tries']){
        $this->sendSMS($owner['mobile'], gettext('كود التأكيد الخاص بك').' '.$passcode);
        $this->db->query("UPDATE `owner` SET `ec_passcode`=`ec_passcode`+1 WHERE id=".$owner['id']);
      }
    }
  }

  private function sendCustomersPassCode($passcode, $buyers){
    $buyermodule = $this->auto_load('buyer');
    foreach($buyers['id'] as $buyerid){
      $buyer = $buyermodule->readrecord($buyerid);
      if($buyer['ec_passcode'] < $this->config['ec_sms_tries']){
        $this->sendSMS($buyer['mobile'], gettext('كود التأكيد الخاص بك').' '.$passcode);
        $this->db->query("UPDATE `buyer` SET `ec_passcode`=`ec_passcode`+1 WHERE id=".$buyer['id']);
      }
    }
  }

}
