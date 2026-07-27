<?php

class banks_model extends core
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
      $this->verifyDelete("SELECT id FROM statement_in WHERE cheque LIKE '%\"bankid\":\"$id\"%' OR bank LIKE '%\"frombankid\":\"$id\"%'");
      $this->verifyDelete("SELECT id FROM statement_out WHERE cheque LIKE '%\"bankid\":\"$id\"%' OR bank LIKE '%\"tobankid\":\"$id\"%'");
      $this->verifyDelete("SELECT id FROM bank_accounts WHERE bankid='$id'");
      $this->registerLog($id, 'banks', 'title');
      $this->db->query("DELETE FROM banks WHERE id='$id'");
    }
  }

  public function addrecord($data)
  {
    return $this->db->insert('banks', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('banks', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT * FROM banks WHERE id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM banks $inner $where ORDER BY id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
