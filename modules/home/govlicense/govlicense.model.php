<?php

class govlicense_model extends core
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
      $this->registerLog($id, 'gov_licenses', 'title');
      $this->db->query("DELETE FROM gov_licenses WHERE id='$id'");
    }
  }

  public function addrecord($data)
  {
    return $this->db->insert('gov_licenses', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('gov_licenses', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT * FROM gov_licenses WHERE id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM gov_licenses $inner $where ORDER BY id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
