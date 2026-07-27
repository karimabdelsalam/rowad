<?php

class reports_model extends core
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
      $this->verifyDelete("SELECT id FROM building WHERE locid='$id'");
      $this->registerLog($id, 'building_reports', 'location');
      $this->db->query("DELETE FROM building_reports WHERE id='$id'");
    }
  }

  public function addrecord($data)
  {
    return $this->db->insert('building_reports', $data);
  }

  public function updaterecord($data, $id)
  {
    $this->db->update('building_reports', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT * FROM building_reports WHERE id='$id'");
  }

  public function getCategories()
  {
    return $this->db->get_results("SELECT * FROM building_reports_cats ORDER BY id ASC");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT building_reports.*,building_reports_cats.name FROM building_reports
    LEFT JOIN building_reports_cats ON(building_reports_cats.id=building_reports.catid)
    $inner $where GROUP BY building_reports.id ORDER BY building_reports.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
