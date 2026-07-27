<?php

class pays_processed_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function getOwner($id)
  {
    return $this->db->get_row("SELECT id,fname,lname,fathname,famname FROM owner WHERE id='$id'");
  }

  public function pendingExec($id, $group = array())
  {
    if(!empty($group)){
      $ids = explode(',', $group);
      foreach($ids as $id){
        $this->pendingExec($id);
      }
    } else{
      $this->verifyDelete("SELECT id FROM transactions WHERE paymentid='$id' AND gone='1'", gettext('لا يمكن إلغاء ترحيل السجل رقم') . ' ' . $id . ' ' . gettext('لوجود إيرادات مالية مرتبطة به وتم ترحيلها.'));
      $this->registerLog($id);
      $this->db->query("UPDATE payments SET gone='0' WHERE id='$id'");
      $this->db->query("DELETE FROM transactions WHERE paymentid='$id'");
    }
  }

  public function readrecord($id)
  {
    $payment = $this->db->get_row("SELECT payments.*,payment_types.title AS paymenttype,building.owner,building.title AS build FROM payments
    INNER JOIN building ON(building.id=payments.buildid)
    INNER JOIN payment_types ON(payment_types.name=payments.type) WHERE payments.id='$id'");
    $payment['owner'] = json_decode($payment['owner'], true);
    foreach($payment['owner']['id'] as $key2 => $owner){
      $ownername = $this->db->get_row("SELECT id,fname,lname,fathname,famname FROM owner WHERE id='" . $payment['owner']['id'][$key2] . "'");
      $payment['owner']['name'][$key2] = '#' . $ownername['id'] . ' - ' . $ownername['fname'] . ' ' . $ownername['fathname'] . ' ' . $ownername['lname'] . ' ' . $ownername['famname'];
    }
    return $payment;
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'AND ' . implode(' AND ', $where);
    }
    $sql = "SELECT payments.*,payment_types.title AS paymenttype,building.owner,building.title AS build,rent_contracts.buyer AS renters,sell_contracts.buyer AS buyers FROM payments
    INNER JOIN building ON(building.id=payments.buildid)
    LEFT JOIN rent_contracts ON(rent_contracts.id=payments.contractid AND payments.module='rent')
    LEFT JOIN sell_contracts ON(sell_contracts.id=payments.contractid AND payments.module='sell')
    INNER JOIN payment_types ON(payment_types.name=payments.type) $inner WHERE payments.gone='1' $where ORDER BY payments.gone_date, payments.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    if($results){
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
      }
    }
    return $results;
  }

}
