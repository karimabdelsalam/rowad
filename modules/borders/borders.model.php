<?php

class borders_model extends core
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
      $this->registerLog($id, 'building_borders', 'title');
      $this->db->query("DELETE FROM building_borders WHERE id='$id'");
    }
  }

  public function addrecord($data)
  {
    $id = $this->db->insert('building_borders', $data);
    $this->db->update('building_borders', array('border' => $id), array('id' => $id));
    return $id;
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('building_borders', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT * FROM building_borders WHERE id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM building_borders $inner $where ORDER BY id ASC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
