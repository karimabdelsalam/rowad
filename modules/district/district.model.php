<?php

class district_model extends core
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
      $this->verifyDelete("SELECT id FROM building WHERE district='$id'");
      $this->registerLog($id, 'district', 'name');
      $this->db->query("DELETE FROM district WHERE id='$id'");
    }
  }

  public function addrecord($data)
  {
    return $this->db->insert('district', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('district', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT * FROM district WHERE id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    $wheresql = "WHERE countryid='" . $this->config['country'] . "'";
    if(!empty($where)){
      $wheresql .= ' AND ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM district $inner $wheresql ORDER BY id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
