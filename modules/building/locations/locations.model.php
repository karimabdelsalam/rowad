<?php

class locations_model extends core
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
      $this->registerLog($id, 'building_locs', 'location');
      $this->db->query("DELETE FROM building_locs WHERE id='$id'");
    }
  }

  public function searchQuery($q)
  {
    $results = $this->db->get_results($this->Paging("SELECT id,location FROM building_locs WHERE location LIKE '%$q%'"));
    $search = array();
    $search['results'] = array();
    if($results){
      foreach($results as $result){
        $search['results'][] = array('id' => $result['id'], 'text' => '#' . $result['id'] . ' - ' . $result['location']);
      }
    }
    $search['pagination'] = ['more' => $_GET['callpage'] >= $this->paging['pages']  ? false : true];
    return $search;
  }

  public function addrecord($data)
  {
    return $this->db->insert('building_locs', $data);
  }

  public function updaterecord($data, $id)
  {
    $this->db->update('building_locs', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    $location = $this->db->get_row("SELECT * FROM building_locs WHERE id='$id'");
    $location['gps_location'] = json_decode($location['gps_location'], true);
    $location['files'] = $this->db->get_results("SELECT * FROM attachments WHERE objectid='$id' AND moduleid='" . MODULE_ID . "'");

    return $location;
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT building_locs.*,COUNT(building_locs.id) AS unitsCount,city.name AS city_name,district.name AS district_name,building_cats.title AS buildcat_name FROM building_locs
    LEFT JOIN building ON(building.locid=building_locs.id)
    LEFT JOIN city ON(city.id=building_locs.citytid)
    LEFT JOIN district ON(district.id=building_locs.district)
    LEFT JOIN building_cats ON(building_cats.id=building_locs.buildcat)
    $inner $where GROUP BY building_locs.id ORDER BY building_locs.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
