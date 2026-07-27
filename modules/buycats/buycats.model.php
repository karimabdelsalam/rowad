<?php

class buycats_model extends core
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
      $this->verifyDelete("SELECT id FROM building WHERE buyercat='$id'");
      $this->registerLog($id, 'buyer_cats', 'title');
      $this->db->query("DELETE FROM buyer_cats WHERE id='$id'");
    }
  }

  public function addrecord($data)
  {
    return $this->db->insert('buyer_cats', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('buyer_cats', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT * FROM buyer_cats WHERE id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM buyer_cats $inner $where ORDER BY id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
