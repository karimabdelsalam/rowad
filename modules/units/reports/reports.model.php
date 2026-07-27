<?php

namespace ownerarea;

class reports_model extends \core
{

  public function __construct()
  {
    parent::__construct();
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
    $where[] = "JSON_SEARCH(building.owner, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL";
    if(empty($where)){
      $where = '';
    } else {
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT building_reports.*,building_reports_cats.name FROM building_reports
    LEFT JOIN building ON(building.id=building_reports.buildid)
    LEFT JOIN building_reports_cats ON(building_reports_cats.id=building_reports.catid)
    $inner $where GROUP BY building_reports.id ORDER BY building_reports.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
