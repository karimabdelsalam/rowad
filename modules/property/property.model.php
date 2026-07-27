<?php

namespace ownerarea;

class property_model extends \core
{

  public function __construct()
  {
    parent::__construct();
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

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    $where[] = "JSON_SEARCH(building.owner, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL";
    if(empty($where)){
      $where = '';
    } else {
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT building_locs.*,COUNT(building_locs.id) AS unitsCount,city.name AS city_name,district.name AS district_name,building_cats.title AS buildcat_name FROM building_locs
    LEFT JOIN building ON(building.locid=building_locs.id)
    LEFT JOIN city ON(city.id=building_locs.citytid)
    LEFT JOIN district ON(district.id=building_locs.district)
    LEFT JOIN building_cats ON(building_cats.id=building.buildcat)
    $inner $where GROUP BY building_locs.id ORDER BY building_locs.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
