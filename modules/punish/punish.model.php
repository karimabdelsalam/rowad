<?php

class punish_model extends core
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
      $this->db->query("DELETE FROM punish WHERE id='$id'");
    }
  }

  public function addrecord($data)
  {
    return $this->db->insert('punish', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('punish', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT punish.*,employees.fname,employees.lname,employees.fathname,employees.famname FROM punish INNER JOIN employees ON(employees.id=punish.employeeid) WHERE punish.id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT punish.*,employees.fname,employees.lname,employees.fathname,employees.famname,employees.salary FROM punish INNER JOIN employees ON(employees.id=punish.employeeid) $inner $where ORDER BY punish.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
