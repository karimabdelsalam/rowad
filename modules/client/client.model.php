<?php

class client_model extends core
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
      $this->verifyDelete("SELECT id FROM payments WHERE contractid='$id' AND gone='1' AND module='sell'");
      $this->registerLog($id, 'sell_contracts', 'id');
      $this->db->query("DELETE FROM sell_contracts WHERE id='$id'");
      $payments = $this->db->get_results("SELECT id FROM payments WHERE contractid='$id' AND module='sell'");
      if($payments){
        foreach($payments as $payment){
          $this->db->query("DELETE FROM payments WHERE id='$payment[id]'");
          $this->db->query("DELETE FROM transactions WHERE paymentid='$payment[id]'");
        }
      }
      $this->db->query("UPDATE building SET contractid='0',available='1' WHERE contractid='$id' AND type='sale'");

      $buyer = $this->db->get_row("SELECT userid FROM buyer WHERE id='$id'");
      $this->deleteUser($buyer['userid']);
    }
  }

  public function markArhcive($id, $group = array(), $status)
  {
    if(!empty($group)){
      $ids = explode(',', $group);
      foreach($ids as $id){
        $this->markArhcive($id);
      }
    } else{
      $this->db->query("UPDATE sell_contracts SET archive='$status' WHERE id='$id'");
      $this->registerLog($id);
    }
  }

  public function searchQuery($q)
  {
    $results = $this->db->get_results($this->Paging("SELECT id,title FROM citizens WHERE (title LIKE '%$q%' OR id='$q')"));
    $search = array();
    $search['results'] = array();
    if($results){
      foreach($results as $result){
        $search['results'][] = array('id' => $result['id'], 'text' => '#' . $result['id'] . ' - ' . $result['title']);
      }
    }
    $search['pagination'] = ['more' => $_GET['callpage'] >= $this->paging['pages']  ? false : true];
    return $search;
  }

  public function updateBuildContract($buildid, $sellid)
  {
    $this->db->query("UPDATE building SET contractid='$sellid',available='0' WHERE id='$buildid'");
  }

  public function addrecord($data)
  {
    return $this->db->insert('sell_contracts', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('sell_contracts', $data, array('id' => $id));
  }

  public function readrecord($id, $calcTotal=false)
  {
    $contract = $this->db->get_row("SELECT * FROM sell_contracts WHERE id='$id'");
    $contract['once'] = json_decode($contract['once'], true);
    $contract['installment'] = json_decode($contract['installment'], true);
    $contract['parts'] = json_decode($contract['parts'], true);
    $contract['rights'] = json_decode($this->db->get_var("SELECT content FROM contract_rights WHERE id='$contract[rightsid]'"), true);
    $contract['extrarights'] = json_decode($contract['extrarights'], true);
    $contract['files'] = $this->db->get_results("SELECT * FROM attachments WHERE objectid='$id' AND moduleid='" . MODULE_ID . "'");
    $contract['allrights'] = array_merge($this->optional($contract['extrarights']) , $this->optional($contract['rights']));
    $contract['buyer'] = json_decode($contract['buyer'], true);
    if($contract['paytype'] == 'once'){
      $contract['paytypename'] = gettext('تسديد كامل المبلغ');
    } elseif($contract['paytype'] == 'parts'){
      $contract['paytypename'] = gettext('تسديد المبلغ على دفعات');
    } elseif($contract['paytype'] == 'installment'){
      $contract['paytypename'] = gettext('تقسيط المبلغ');
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
      $contract['alltotal'] = $this->db->get_var("SELECT SUM(`amount`) FROM `payments` WHERE `contractid`='$id' AND `module`='sell'");
      $contract['all_rentcomm'] = $this->db->get_var("SELECT SUM(`commission2`) FROM `payments` WHERE `contractid`='$id' AND `module`='sell'");
      $contract['allpaid'] = $this->db->get_var("SELECT SUM(`amount`) FROM `payments` WHERE `contractid`='$id' AND `module`='sell' AND gone='1'");
      $contract['allpaid'] += $this->db->get_var("SELECT SUM(`paidamount`) FROM `payments` WHERE `contractid`='$id' AND `module`='sell' AND gone='0'");
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
    $sql = "SELECT sell_contracts.*,building.title AS build FROM sell_contracts INNER JOIN building ON(building.id=sell_contracts.buildid) $inner $where ORDER BY sell_contracts.id DESC";
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
