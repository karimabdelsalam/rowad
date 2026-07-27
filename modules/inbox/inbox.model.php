<?php

class inbox_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function markasRead($id, $group = array())
  {
    if(!empty($group)){
      $this->db->query("UPDATE inbox SET isread='1' WHERE id IN($group)");
    } else{
      $this->db->query("UPDATE inbox SET isread='1' WHERE id='$id'");
    }
  }

  public function markasunRead($id, $group = array())
  {
    if(!empty($group)){
      $this->db->query("UPDATE inbox SET isread='0' WHERE id IN($group)");
    } else{
      $this->db->query("UPDATE inbox SET isread='0' WHERE id='$id'");
    }
  }

  public function deleteExec($id, $group = '')
  {
    if(!empty($group)){
      $this->db->query("DELETE FROM inbox WHERE id IN($group)");
    } else{
      $this->db->query("DELETE FROM inbox WHERE id='$_GET[id]'");
    }
  }

  public function readInboxMsg($id)
  {
    return $this->db->get_row("SELECT * FROM inbox WHERE id='$id'");
  }

  public function getInboxMsgs($where = array(), $paging = true)
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM inbox $where ORDER BY id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
