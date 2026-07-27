<?php

class notification extends notification_model
{

  public function __construct($args, $payload)
  {
    parent::__construct();
    $this->args = $args;
    $this->payload = $payload;
    $this->notify = array();
    $this->insert_notifcation = false;
  }

  public function visaEnd15()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $this->insert_notifcation = 'alert';
    $this->args['receiver'] = $this->config['email'];
    $this->args['mobile'] = $this->config['mobile'];

    $this->processVar('visa_number', $this->payload['idnum']);
    $this->processVar('name', $this->payload['fname'] . ' ' . $this->payload['fathname'] . ' ' . $this->payload['lname'] . ' ' . $this->payload['famname']);
    $this->processVar('expire_date', (($this->payload['calendar'] == 2) ? uCal::g2u($this->payload['idexpire']) : $this->payload['idexpire']));
    $this->startQueue();
  }

  public function licenseEnd15()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $this->insert_notifcation = 'alert';
    $this->args['receiver'] = $this->config['email'];
    $this->args['mobile'] = $this->config['mobile'];

    $this->processVar('license', $this->payload['title']);
    $this->processVar('expire_date', (($this->payload['calendar'] == 2) ? uCal::g2u($this->payload['expiredate']) : $this->payload['expiredate']));
    $this->startQueue();
  }

  public function finishBuildOwner()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $this->insert_notifcation = 'info';
    $renters = array();
    $building = $this->auto_load_read($this->payload['buildid'], 'building');
    $contract = $this->auto_load_read($building['contractid'], 'rent');
    $buyermodule = $this->auto_load('buyer');
    $ownermodule = $this->auto_load('owner');

    foreach($contract['buyers'] as $buyerid){
      $buyer = $buyermodule->readrecord($buyerid);
      $renters[] = $buyer['surname'];
    }

    foreach($building['owner']['id'] as $ownerid){
      $owner = $ownermodule->readrecord($ownerid);
      $this->args['receiver'] = $owner['email'];
      $this->args['mobile'] = $owner['mobile'];

      $this->processVar('name', $owner['fname']);
      $this->processVar('build', $building['title']);
      $this->processVar('location', $building['district']);
      $this->processVar('renters', implode(gettext(' و '), $renters));
      $this->startQueue();
    }
  }

  public function ownerAmountTo()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $this->insert_notifcation = 'info';
    $renters = array();
    $building = $this->auto_load_read($this->payload['buildid'], 'building');
    $buyermodule = $this->auto_load('buyer');
    $ownermodule = $this->auto_load('owner');

    foreach($this->payload['buyers'] as $buyerid){
      $buyer = $buyermodule->readrecord($buyerid);
      $renters[] = $buyer['surname'];
    }

    foreach($building['owner']['id'] as $ownerid){
      $owner = $ownermodule->readrecord($ownerid);
      $this->args['receiver'] = $owner['email'];
      $this->args['mobile'] = $owner['mobile'];

      $this->processVar('name', $owner['fname']);
      $this->processVar('build', $building['title']);
      $this->processVar('location', $building['district']);
      $this->processVar('renters', implode(gettext(' و '), $renters));
      $this->processVar('amount', $this->payload['amount']);
      $this->processVar('trans_no', $this->payload['id']);
      $this->processVar('contract_no', $building['contractid']);
      $this->startQueue();
    }
  }

  public function ownerBuildRented()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $this->insert_notifcation = 'info';
    $renters = array();
    $building = $this->auto_load_read($this->payload['buildid'], 'building');
    $buyermodule = $this->auto_load('buyer');
    $ownermodule = $this->auto_load('owner');

    foreach($this->payload['buyers'] as $buyerid){
      $buyer = $buyermodule->readrecord($buyerid);
      $renters[] = $buyer['surname'];
    }

    foreach($building['owner']['id'] as $ownerid){
      $owner = $ownermodule->readrecord($ownerid);
      $this->args['receiver'] = $owner['email'];
      $this->args['mobile'] = $owner['mobile'];

      $this->processVar('name', $owner['fname']);
      $this->processVar('build', $building['title']);
      $this->processVar('location', $building['district']);
      $this->processVar('renters', implode(gettext(' و '), $renters));
      $this->processVar('period', $this->payload['periodnum'] . ' ' . (($this->payload['period'] == 'month') ? gettext('شهر') : gettext('عام')));
      $this->processVar('amount', ($this->payload['rentvalue'] - $building['rentcomm']));
      $this->processVar('contract_no', $this->payload['id']);
      $this->processVar('start_date', (($this->payload['calendar'] == 2) ? uCal::g2u($this->payload['startdate']) : $this->payload['startdate']));
      $this->startQueue();
    }
  }

  public function finishBuildRenter()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $this->insert_notifcation = 'info';
    $buyermodule = $this->auto_load('buyer');
    $building = $this->auto_load_read($this->payload['buildid'], 'building');
    $contract = $this->auto_load_read($building['contractid'], 'rent');
    foreach($contract['buyers'] as $buyerid){
      $buyer = $buyermodule->readrecord($buyerid);
      $this->args['receiver'] = $buyer['email'];
      $this->args['mobile'] = $buyer['mobile'];

      $this->processVar('name', $buyer['surname']);
      $this->processVar('contract_no', $contract['id']);
      $this->startQueue();
    }
  }

  public function payRent15()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $this->insert_notifcation = 'info';
    $buyermodule = $this->auto_load('buyer');
    $contract = $this->auto_load_read($this->payload['contractid'], 'rent');
    foreach($contract['buyers'] as $buyerid){
      $buyer = $buyermodule->readrecord($buyerid);
      $this->args['receiver'] = $buyer['email'];
      $this->args['mobile'] = $buyer['mobile'];

      $this->processVar('date', (($this->payload['calendar'] == 2) ? uCal::g2u($this->payload['paydate']) : $this->payload['paydate']));
      $this->processVar('amount', $this->payload['amount']);
      $this->startQueue();
    }
  }

  public function sellPayx()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $this->insert_notifcation = 'alert';
    $buyermodule = $this->auto_load('buyer');
    $contract = $this->auto_load_read($this->payload['contractid'], 'sell');
    foreach($contract['buyers'] as $buyerid){
      $buyer = $buyermodule->readrecord($buyerid);
      $this->args['receiver'] = $buyer['email'];
      $this->args['mobile'] = $buyer['mobile'];

      $this->processVar('name', $buyer['surname']);
      $this->processVar('date', (($this->payload['calendar'] == 2) ? uCal::g2u($this->payload['paydate']) : $this->payload['paydate']));
      $this->processVar('amount', $this->payload['amount']);
      $this->processVar('contract_no', $this->payload['contractid']);
      $this->startQueue();
    }
  }

  public function sellPay0()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $this->insert_notifcation = 'alert';
    $buyermodule = $this->auto_load('buyer');
    $contract = $this->auto_load_read($this->payload['contractid'], 'sell');
    foreach($contract['buyers'] as $buyerid){
      $buyer = $buyermodule->readrecord($buyerid);
      $this->args['receiver'] = $buyer['email'];
      $this->args['mobile'] = $buyer['mobile'];

      $this->processVar('name', $buyer['surname']);
      $this->processVar('date', (($this->payload['calendar'] == 2) ? uCal::g2u($this->payload['paydate']) : $this->payload['paydate']));
      $this->processVar('amount', $this->payload['amount']);
      $this->processVar('contract_no', $this->payload['contractid']);
      $this->startQueue();
    }
  }

  public function sellPay1()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $this->insert_notifcation = 'alert';
    $buyermodule = $this->auto_load('buyer');
    $contract = $this->auto_load_read($this->payload['contractid'], 'sell');
    foreach($contract['buyers'] as $buyerid){
      $buyer = $buyermodule->readrecord($buyerid);
      $this->args['receiver'] = $buyer['email'];
      $this->args['mobile'] = $buyer['mobile'];

      $this->processVar('name', $buyer['surname']);
      $this->processVar('date', (($this->payload['calendar'] == 2) ? uCal::g2u($this->payload['paydate']) : $this->payload['paydate']));
      $this->processVar('amount', $this->payload['amount']);
      $this->processVar('contract_no', $this->payload['contractid']);
      $this->startQueue();
    }
  }

  public function sellPay15()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $this->insert_notifcation = 'info';
    $buyermodule = $this->auto_load('buyer');
    $contract = $this->auto_load_read($this->payload['contractid'], 'sell');
    foreach($contract['buyers'] as $buyerid){
      $buyer = $buyermodule->readrecord($buyerid);
      $this->args['receiver'] = $buyer['email'];
      $this->args['mobile'] = $buyer['mobile'];

      $this->processVar('name', $buyer['surname']);
      $this->processVar('date', (($this->payload['calendar'] == 2) ? uCal::g2u($this->payload['paydate']) : $this->payload['paydate']));
      $this->processVar('amount', $this->payload['amount']);
      $this->processVar('contract_no', $this->payload['contractid']);
      $this->startQueue();
    }
  }

  public function collectSellPart()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $this->insert_notifcation = 'info';
    $ownermodule = $this->auto_load('owner');
    $owner = $ownermodule->readrecord($this->payload['ownerid']);
    $building = $this->auto_load_read($this->payload['buildid'], 'building');
    $this->args['receiver'] = $owner['email'];
    $this->args['mobile'] = $owner['mobile'];

    $this->processVar('name', $owner['surname']);
    $this->processVar('amount', $this->payload['amount']);
    $this->processVar('trans_id', $this->payload['id']);
    $this->processVar('contract_id', $building['contractid']);
    $this->processVar('date', ($this->payload['calendar'] == 2) ? uCal::g2u($this->payload['issueddate']) : $this->payload['issueddate']);
    $this->startQueue();
  }

  public function newRentContract()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $this->insert_notifcation = 'info';
    $buyermodule = $this->auto_load('buyer');
    foreach($this->payload['buyers'] as $buyerid){
      $buyer = $buyermodule->readrecord($buyerid);
      $this->args['receiver'] = $buyer['email'];
      $this->args['mobile'] = $buyer['mobile'];

      $this->processVar('name', $buyer['surname']);
      $this->processVar('no', $this->payload['id']);
      $this->startQueue();
    }
  }

  public function otpReset()
  {
    if($this->fetchEmailBody('smsLogin') === false){
      return false;
    }
    $this->processVar('code', $this->args['code']);
    $this->generateResetOTP($this->args['code']);
    $this->startQueue();
  }

  public function smsLogin()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $this->processVar('code', $this->args['code']);
    $this->startQueue();
  }

  public function resetAccountPWD()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $resetlink = CPURL . '/login/recover/' . $this->generateResetHash();
    $this->processVar('username', $this->userinfo['username']);
    $this->processVar('resetlink', $resetlink);
    $this->startQueue();
  }

  public function updateEmail()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $activelink = CPURL . '/login/emailupdate/' . $this->generateResetHash();
    $this->processVar('activelink', $activelink);
    $this->startQueue();
  }

  public function recoverPWD()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false) {
      return false;
    }
    $this->processVar('username', $this->userinfo['username']);
    $this->processVar('password', $this->args['newpass']);
    $this->startQueue();
  }

  public function newRegister()
  {
    if($this->fetchEmailBody(__FUNCTION__) === false){
      return false;
    }
    $activelink = CPURL . '/login/activation/' . $this->generateResetHash();
    $this->processVar('activelink', $activelink);
    $this->startQueue();
  }

}
