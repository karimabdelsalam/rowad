<?php

class meter_types_model extends core
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
      $this->registerLog($id, 'meter_types', 'title');
      $this->db->query("DELETE FROM meter_types WHERE id='$id'");
    }
  }

  public function addrecord($data)
  {
    return $this->db->insert('meter_types', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('meter_types', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT * FROM meter_types WHERE id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM meter_types $inner $where ORDER BY id ASC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
