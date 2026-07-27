<?php

class rights_model extends core
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
      $this->verifyDelete("SELECT id FROM rent_contracts WHERE rightsid='$id'");
      $this->verifyDelete("SELECT id FROM sell_contracts WHERE rightsid='$id'");
      $this->registerLog($id, 'contract_rights', 'title');
      $this->db->query("DELETE FROM contract_rights WHERE id='$id'");
    }
  }

  public function addrecord($data)
  {
    return $this->db->insert('contract_rights', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('contract_rights', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    $rights = $this->db->get_row("SELECT * FROM contract_rights WHERE id='$id'");
    $rights['content'] = json_decode($rights['content'], true);
    return $rights;
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM contract_rights $inner $where ORDER BY id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
