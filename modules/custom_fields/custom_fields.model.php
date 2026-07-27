<?php

class custom_fields_model extends core
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
      $this->verifyDelete("SELECT id FROM custom_fields_data WHERE field_id='$id'");
      $this->registerLog($id, 'custom_fields', 'title');
      $this->db->query("DELETE FROM custom_fields WHERE id='$id'");
    }
  }

  public function addrecord($data)
  {
    return $this->db->insert('custom_fields', $data);
  }

  public function updaterecord($data, $id)
  {
    $this->db->update('custom_fields', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT * FROM custom_fields WHERE id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT custom_fields.*,custom_fields_cats.title AS category FROM custom_fields
    INNER JOIN custom_fields_cats ON(custom_fields_cats.id=custom_fields.catid)
    $inner $where GROUP BY custom_fields.id ORDER BY custom_fields.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    return $this->db->get_results($sql);
  }

}
