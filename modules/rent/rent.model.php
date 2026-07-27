<?php

class rent_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function deleteExec($id, $group = '')
  {
    if(!empty($group)){
      $ids = explode(',', $group);
      foreach($ids as $id){
        $this->deleteExec($id);
      }
    } else{
      $this->verifyDelete("SELECT id FROM payments WHERE contractid='$id' AND gone='1' AND module='rent'", gettext('هذا العقد ما زال مرتبط به مبالغ مرحلة'));
      $this->registerLog($id, 'rent_contracts', 'id');
      $this->db->query("DELETE FROM rent_contracts WHERE id='$id'");
      $payments = $this->db->get_results("SELECT id FROM payments WHERE contractid='$id' AND module='rent'");
      if($payments){
        foreach($payments as $payment){
          $this->db->query("DELETE FROM payments WHERE id='$payment[id]'");
          $this->db->query("DELETE FROM transactions WHERE paymentid='$payment[id]'");
        }
      }
      $this->db->query("UPDATE building SET contractid='0',available='1' WHERE contractid='$id' AND type='rent'");
      $this->refreshContracts();
    }
  }

  public function markAsEnded($id)
  {
    $this->verifyDelete("SELECT id FROM payments WHERE contractid='$id' AND gone='0' AND module='rent'", gettext('هذا العقد مرتبط به بعض المبالغ الغير مرحلة'));
    $this->registerLog($id);
    $this->db->query("UPDATE rent_contracts SET ended='1' WHERE id='$id'");
    $this->db->query("UPDATE building SET contractid='0',available='1' WHERE contractid='$id' AND type='rent'");
    $this->refreshContracts();
  }

  public function markAsCancelled($id)
  {
    $this->registerLog($id);
    $this->db->query("UPDATE rent_contracts SET ended='1' WHERE id='$id'");
    $this->db->query("UPDATE building SET contractid='0',available='1' WHERE contractid='$id' AND type='rent'");
    $this->db->query("DELETE FROM payments WHERE contractid='$id' AND module='rent' AND gone='0' AND type!='expenses'");
    $this->refreshContracts();
  }

  public function markArhcive($id, $group = array(), $status)
  {
    if(!empty($group)){
      $ids = explode(',', $group);
      foreach($ids as $id){
        $this->markArhcive($id);
      }
    } else{
      $this->db->query("UPDATE rent_contracts SET archive='$status' WHERE id='$id'");
      $this->registerLog($id);
    }
  }

  public function checkAvailability($buildid, $start, $end, $rentid = 0)
  {
    $bool = $this->db->get_var("SELECT id FROM rent_contracts WHERE buildid='$buildid' AND ended='0' AND id<>$rentid AND ((startdate BETWEEN '$start' AND '$end') OR (enddate BETWEEN '$start' AND '$end'))");
    if($bool){
      return false;
    }
    return true;
  }

  public function updateBuildContract($buildid, $rentid)
  {
    //$this->db->query("UPDATE building SET contractid='$rentid',available='0' WHERE id='$buildid'");

    $newContracts = $this->db->get_results("SELECT id,buildid FROM rent_contracts WHERE buildid='$buildid' AND MONTH(startdate)=MONTH(NOW()) AND YEAR(startdate)=YEAR(NOW())");
    if($newContracts){
      foreach($newContracts as $newContract){
        $this->db->query("UPDATE building SET available='0',contractid='$newContract[id]' WHERE id='$newContract[buildid]'");
      }
    }
  }

  public function addrecord($data)
  {
    //$this->checkRentersAvailable($data['buyer']);
    return $this->db->insert('rent_contracts', $data);
  }

  public function updaterecord($data, $id)
  {
    //$this->checkRentersAvailable($data['buyer'], $id);
    return $this->db->update('rent_contracts', $data, array('id' => $id));
  }

  private function checkRentersAvailable($buyers, $contractExcept = '')
  {
    if(!empty($contractExcept)){
      $contractExcept = "AND id<>$contractExcept";
    }
    $buyers = json_decode($buyers, true);
    foreach($buyers['id'] as $key => $buyer){
      $buyerinfo = $this->auto_load_read($buyers['id'][$key], 'buyer');
      if($buyerinfo['type'] == 'person'){
        $bool = $this->db->get_var("SELECT id FROM rent_contracts WHERE JSON_CONTAINS(JSON_EXTRACT(buyer, '$.id[*]'), '\"$buyerinfo[id]\"', '$') AND ended='0' $contractExcept");
        if($bool){
          $this->showmsg(gettext('لا يمكن الأستمرار لأرتباط المستأجر ') . $buyerinfo['fullname'] . gettext(' بعقد ايجار آخر'), 0);
        }
      }
    }
  }

  public function readrecord($id, $calcTotal=false)
  {
    $contract = $this->db->get_row("SELECT * FROM rent_contracts WHERE id='$id'");
    $contract['rights'] = json_decode($this->db->get_var("SELECT content FROM contract_rights WHERE id='$contract[rightsid]'"), true);
    $contract['extrarights'] = json_decode($contract['extrarights'], true);
    $contract['meters'] = json_decode($contract['meters'], true);
    $contract['allrights'] = array_merge($this->optional($contract['extrarights']) , $this->optional($contract['rights']));
    $contract['buyer'] = json_decode($contract['buyer'], true);
    $contract['files'] = $this->db->get_results("SELECT * FROM attachments WHERE objectid='$id' AND moduleid='" . MODULE_ID . "'");
    if($contract['period'] == 'month'){
      $contract['period_title'] = gettext('شهر');
    } elseif($contract['period'] == 'day'){
      $contract['period_title'] = gettext('يوم');
    } else {
      $contract['period_title'] = gettext('عام');
    }
    if($contract['payment'] == 'day'){
      $contract['payment_title'] = gettext('يومي');
    } elseif($contract['payment'] == 'month'){
      $contract['payment_title'] = gettext('شهري');
    } elseif($contract['payment'] == 'year'){
      $contract['payment_title'] = gettext('سنوي');
    } elseif($contract['payment'] == 'midyear'){
      $contract['payment_title'] = gettext('نصف سنوي');
    } elseif($contract['payment'] == 'quartyear'){
      $contract['payment_title'] = gettext('ربع سنوي');
    }
    if(!empty($contract['buyer']['id'])){
      foreach($contract['buyer']['id'] as $key => $buyer){
        $buyername = $this->auto_load_read($contract['buyer']['id'][$key], 'buyer');
        $contract['buyer']['name'][$key] = '#' . $buyername['id'] . ' - ' . $buyername['fullname'];
        $contract['buyer']['profile'][$key] = $buyername;
        $contract['buyers'][$key] = $buyername['id'];
      }
    }
    if($calcTotal){
      $contract['alltotal'] = $this->db->get_var("SELECT SUM(`amount`) FROM `payments` WHERE `contractid`='$id' AND `module`='rent'");
      $contract['all_rentcomm'] = $this->db->get_var("SELECT SUM(`commission2`) FROM `payments` WHERE `contractid`='$id' AND `module`='rent'");
      $contract['allpaid'] = $this->db->get_var("SELECT SUM(`amount`) FROM `payments` WHERE `contractid`='$id' AND `module`='rent' AND gone='1'");
      $contract['allpaid'] += $this->db->get_var("SELECT SUM(`paidamount`) FROM `payments` WHERE `contractid`='$id' AND `module`='rent' AND gone='0'");
      $contract['remain'] = $contract['alltotal']-$contract['allpaid'];
      $contract['remain_percent'] = round(($contract['remain']/$contract['alltotal'])*100, 2);
    }
    return $contract;
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT rent_contracts.*,building.title AS build FROM rent_contracts INNER JOIN building ON(building.id=rent_contracts.buildid) $inner $where GROUP BY rent_contracts.id ORDER BY rent_contracts.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    if($results){
      $buyerMod = $this->auto_load('buyer');
      foreach($results as $key => $result){
        $results[$key]['buyer'] = json_decode($results[$key]['buyer'], true);
        if(!empty($results[$key]['buyer']['id'])){
          foreach($results[$key]['buyer']['id'] as $key2 => $buyer){
            $buyername = $buyerMod->readrecord($results[$key]['buyer']['id'][$key2]);
            $results[$key]['buyer']['name'][$key2] = '#' . $buyername['id'] . ' - ' . $buyername['fullname'];
          }
        }
      }
    }
    return $results;
  }

}
