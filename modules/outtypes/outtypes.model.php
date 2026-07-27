<?php

class outtypes_model extends core
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
      $this->verifyDelete("SELECT id FROM statement_out WHERE typeid='$id'");
      $this->registerLog($id, 'outgoings_types', 'title');
      $this->db->query("DELETE FROM outgoings_types WHERE id='$id'");
    }
  }

  public function addrecord($data)
  {
    return $this->db->insert('outgoings_types', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('outgoings_types', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT * FROM outgoings_types WHERE id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM outgoings_types $inner $where ORDER BY id ASC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
