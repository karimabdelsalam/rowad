<?php

class task_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function markasDone($id, $group = array())
  {
    if(!empty($group)){
      $this->db->query("UPDATE task SET done='1' WHERE id IN($group)");
    } else{
      $this->db->query("UPDATE task SET done='1' WHERE id='$id'");
    }
  }

  public function markasUndone($id, $group = array())
  {
    if(!empty($group)){
      $this->db->query("UPDATE task SET done='0' WHERE id IN($group)");
    } else{
      $this->db->query("UPDATE task SET done='0' WHERE id='$id'");
    }
  }

  public function deleteExec($id, $group = '')
  {
    if(!empty($group)){
      $this->db->query("DELETE FROM task WHERE id IN($group)");
    } else{
      $this->db->query("DELETE FROM task WHERE id='$id'");
    }
  }

  public function addTask($data)
  {
    return $this->db->insert('task', $data);
  }

  public function saveTask($data, $id)
  {
    return $this->db->update('task', $data, array('id' => $id));
  }

  public function readTask($id)
  {
    return $this->db->get_row("SELECT * FROM task WHERE id='$id'");
  }

  public function listTask($where = array(), $paging = true)
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT task.*,user.name AS manager FROM task
    INNER JOIN user ON(user.id=task.tomanagerid)
    $where ORDER BY task.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
