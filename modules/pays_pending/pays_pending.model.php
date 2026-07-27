<?php

class pays_pending_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function processExec($id, $group = array())
  {
    if(!empty($group)){
      $ids = explode(',', $group);
      foreach($ids as $id){
        $this->processExec($id);
      }
    } else{
      $this->registerLog($id);
      $status = $this->db->get_var("SELECT gone FROM payments WHERE id='$id'");
      if($status == 0){
        $this->db->query("UPDATE payments SET gone='1', gone_date=CURDATE() WHERE id='$id'");
        $transaction = $this->auto_load('transactions');
        $transaction->collectPayment($id);
      }
    }
  }

  public function updaterecord($data, $id)
  {
    $this->db->update('payments', $data, array('id' => $id));
  }

  public function rentLastPaymentID($contractid)
  {
    return $this->db->get_var("SELECT id FROM payments WHERE contractid='$contractid' AND module='rent' AND gone='0' ORDER BY paydate ASC LIMIT 1");
  }

  public function rentPaymentInfo($contractid)
  {
    $payment = $this->db->get_row("SELECT * FROM payments WHERE contractid='$contractid' AND module='rent' AND gone='0' ORDER BY paydate ASC LIMIT 1");
    if(empty($payment)){
      return false;
    }
    if($payment['calendar'] == 2){
      $payment['paydate'] = uCal::g2u($payment['paydate']);
    }
    return $payment;
  }

  public function salePaymentInfo($contractid)
  {
    $payment = $this->db->get_row("SELECT * FROM payments WHERE contractid='$contractid' AND module='sell' AND gone='0' ORDER BY paydate ASC LIMIT 1");
    if(empty($payment)){
      return false;
    }
    if($payment['calendar'] == 2){
      $payment['paydate'] = uCal::g2u($payment['paydate']);
    }
    return $payment;
  }

  public function contractPayments($contractid, $alltime)
  {
    if(empty($contractid)) return [];
    $where = '';
    if(! $alltime) {
      $where = ' AND MONTH(paydate) = ' . date('m') . ' AND YEAR(paydate) = ' . date('Y');
    }
    $payments = $this->db->get_results("SELECT * FROM payments WHERE contractid='$contractid' $where ORDER BY paydate ASC");
    return $payments;
  }

  public function readrecord($id)
  {
    $payment = $this->db->get_row("SELECT payments.*,payment_types.title AS paymenttype,building.owner,building.title AS build FROM payments
    INNER JOIN building ON(building.id=payments.buildid)
    INNER JOIN payment_types ON(payment_types.name=payments.type) WHERE payments.id='$id'");
    if(empty($payment)){
      return false;
    }
    $payment['owner'] = json_decode($payment['owner'], true);
    foreach($payment['owner']['id'] as $key2 => $owner){
      $ownername = $this->db->get_row("SELECT id,fname,lname,fathname,famname FROM owner WHERE id='" . $payment['owner']['id'][$key2] . "'");
      $payment['owner']['name'][$key2] = '#' . $ownername['id'] . ' - ' . $ownername['fname'] . ' ' . $ownername['fathname'] . ' ' . $ownername['lname'] . ' ' . $ownername['famname'];
    }
    if($payment['module'] == 'sell'){
      $contract = $this->db->get_row("SELECT buyer FROM sell_contracts WHERE id='$payment[contractid]'");
      $payment['buyer'] = json_decode($contract['buyer'], true);
      foreach($contract['buyer']['id'] as $key2 => $buyer){
        $buyername = $this->db->get_row("SELECT id,fname,lname,fathname,famname FROM buyer WHERE id='" . $contract['buyer']['id'][$key2] . "'");
        $payment['buyer']['name'][$key2] = '#' . $buyername['id'] . ' - ' . $buyername['fname'] . ' ' . $buyername['fathname'] . ' ' . $buyername['lname'] . ' ' . $buyername['famname'];
      }
    } elseif($payment['module'] == 'rent'){
      $contract = $this->db->get_row("SELECT buyer FROM rent_contracts WHERE id='$payment[contractid]'");
      $payment['buyer'] = json_decode($contract['buyer'], true);
      foreach($contract['buyer']['id'] as $key2 => $buyer){
        $buyername = $this->db->get_row("SELECT id,fname,lname,fathname,famname FROM buyer WHERE id='" . $contract['buyer']['id'][$key2] . "'");
        $payment['buyer']['name'][$key2] = '#' . $buyername['id'] . ' - ' . $buyername['fname'] . ' ' . $buyername['fathname'] . ' ' . $buyername['lname'] . ' ' . $buyername['famname'];
      }
    }
    return $payment;
  }

  public function listrecord($where = array(), $paging = true, $inner = '', $ignore_case = false)
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'AND ' . implode(' AND ', $where);
    }
    if(! $ignore_case){
      $where .= "AND payments.gone='0'";
    }
    $sql = "SELECT payments.*,payment_types.title AS paymenttype,building.owner,building.title AS build,rent_contracts.buyer AS renters,sell_contracts.buyer AS buyers FROM payments
    INNER JOIN building ON(building.id=payments.buildid)
    LEFT JOIN rent_contracts ON(rent_contracts.id=payments.contractid AND payments.module='rent')
    LEFT JOIN sell_contracts ON(sell_contracts.id=payments.contractid AND payments.module='sell')
    INNER JOIN payment_types ON(payment_types.name=payments.type) $inner WHERE 1 $where ORDER BY payments.paydate, payments.id ASC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    if($results){
      $results[0]['totalAmounts'] = 0;
      foreach($results as $key => $result){
        if(!empty($results[$key]['renters'])){
          $results[$key]['relatedto'] = json_decode($results[$key]['renters'], true);
        }
        if(!empty($results[$key]['buyers'])){
          $results[$key]['relatedto'] = json_decode($results[$key]['buyers'], true);
        }
        $results[$key]['owner'] = json_decode($results[$key]['owner'], true);
        if(!empty($results[$key]['owner']['id'])){
          foreach($results[$key]['owner']['id'] as $key2 => $owner){
            $ownername = $this->db->get_row("SELECT id,fname,lname,fathname,famname FROM owner WHERE id='" . $results[$key]['owner']['id'][$key2] . "'");
            $results[$key]['owner']['name'][$key2] = '#' . $ownername['id'] . ' - ' . $ownername['fname'] . ' ' . $ownername['fathname'] . ' ' . $ownername['lname'] . ' ' . $ownername['famname'];
            $results[$key]['ownerinfo'][$key2] = $ownername;
          }
        }
        $results[0]['totalAmounts'] += $result['amount']-$result['paidamount'];
      }

      $results[0]['report']['debits'] = $this->db->get_results("SELECT payments.contractid, payments.module, SUM(payments.amount) AS counterValue FROM payments WHERE 1 $where AND `gone`=0 AND `paydate` < NOW() GROUP BY payments.module, payments.contractid");

    }
    return $results;
  }

}
