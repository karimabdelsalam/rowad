<?php

class categories_model extends core
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
      $this->verifyDelete("SELECT id FROM custom_fields WHERE catid='$id'");
      $this->registerLog($id, 'custom_fields_cats', 'title');
      $this->db->query("DELETE FROM custom_fields_cats WHERE id='$id'");
    }
  }

  public function addrecord($data)
  {
    return $this->db->insert('custom_fields_cats', $data);
  }

  public function updaterecord($data, $id)
  {
    $this->db->update('custom_fields_cats', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT * FROM custom_fields_cats WHERE id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT custom_fields_cats.*,building_cats.title AS buildCat,COUNT(custom_fields.id) AS fieldsCount FROM custom_fields_cats
    LEFT JOIN building_cats ON(custom_fields_cats.build_catid=building_cats.id)
    LEFT JOIN custom_fields ON(custom_fields.catid=custom_fields_cats.id)
    $inner $where GROUP BY custom_fields_cats.id ORDER BY custom_fields_cats.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    return $this->db->get_results($sql);
  }

}
