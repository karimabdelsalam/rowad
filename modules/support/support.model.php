<?php

class support_model extends core
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
    } else {
      $this->registerLog($id, 'support', 'subject');
      $this->db->query("DELETE FROM support WHERE id='$id'");
    }
  }

  public function markasRead($id, $group = array())
  {
    if(!empty($group)){
      $this->db->query("UPDATE support SET read_state='1' WHERE id IN($group)");
    } else{
      $this->db->query("UPDATE support SET read_state='1' WHERE id='$id'");
    }
  }

  public function markasunRead($id, $group = '')
  {
    if(!empty($group)){
      $this->db->query("UPDATE support SET read_state='0' WHERE id IN($group)");
    } else {
      $this->db->query("UPDATE support SET read_state='0' WHERE id='$id'");
    }
  }
  
  public function addrecord($data)
  {
    return $this->db->insert('support', $data);
  }

  public function updaterecord($data, $id)
  {
    $this->db->update('support', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT support.*,building.title AS buildTitle,owner.fname AS ofname,owner.fathname AS ofathname,buyer.fname AS bfname,buyer.fathname AS bfathname FROM support
    INNER JOIN building ON(building.id=support.buildid)
    LEFT JOIN owner ON(owner.id=support.ownerid)
    LEFT JOIN buyer ON(buyer.id=support.clientid) WHERE support.id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)) {
      $where = '';
    } else {
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT support.*,building.title AS buildTitle,owner.fname AS ofname,owner.fathname AS ofathname,buyer.fname AS bfname,buyer.fathname AS bfathname FROM support
    INNER JOIN building ON(building.id=support.buildid)
    LEFT JOIN owner ON(owner.id=support.ownerid)
    LEFT JOIN buyer ON(buyer.id=support.clientid)
    $inner $where ORDER BY support.id DESC";
    if($paging) {
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
