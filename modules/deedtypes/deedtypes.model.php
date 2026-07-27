<?php

class deedtypes_model extends core
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
      $this->registerLog($id, 'deed_types', 'title');
      $this->db->query("DELETE FROM deed_types WHERE id='$id'");
    }
  }

  public function addrecord($data)
  {
    return $this->db->insert('deed_types', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('deed_types', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT * FROM deed_types WHERE id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM deed_types $inner $where ORDER BY id ASC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
