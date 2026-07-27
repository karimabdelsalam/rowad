<?php

class bank_accounts_model extends core
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
      $this->verifyDelete("SELECT id FROM statement_in WHERE bank LIKE '%\"tobankid\":\"$id\"%'");
      $this->verifyDelete("SELECT id FROM statement_out WHERE bank LIKE '%\"frombankid\":\"$id\"%'");
      $this->verifyDelete("SELECT id FROM bankacc_transactions WHERE bankid=$id");
      $this->registerLog($id, 'bank_accounts', 'iban');
      $this->db->query("DELETE FROM bank_accounts WHERE id='$id'");
    }
  }

  public function markAdDefault($id)
  {
    $this->registerLog($id, 'bank_accounts', 'iban');
    $this->db->query("UPDATE bank_accounts SET main='0'");
    $this->db->query("UPDATE bank_accounts SET main='1' WHERE id='$id'");
  }

  public function addrecord($data)
  {
    return $this->db->insert('bank_accounts', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('bank_accounts', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT bank_accounts.*,banks.title AS bank FROM bank_accounts INNER JOIN banks ON(banks.id=bank_accounts.bankid) WHERE bank_accounts.id='$id'");
  }

  public function listTransactions($where = array(), $paging = true, $inner = '', $mainAcc = false)
  {
    if($mainAcc){
      $mainid = $this->db->get_var("SELECT id FROM bank_accounts WHERE main='1'");
      array_push($where, "bankid='$mainid'");
    }
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM bankacc_transactions $inner $where ORDER BY id DESC";
    if($paging === true){
      $sql = $this->Paging($sql);
    }
    elseif(is_numeric($paging)){
      $sql .= ' LIMIT '.$paging;
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT bank_accounts.*,banks.title AS bank FROM bank_accounts INNER JOIN banks ON(banks.id=bank_accounts.bankid) $inner $where ORDER BY bank_accounts.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
