<?php

namespace clientarea;

class units_model extends \core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    $where[] = "building.id IN(".$this->getAllClientBuilds().")";
    if(empty($where)){
      $where = '';
    } else {
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT building.*,building_locs.location AS m_location FROM building LEFT JOIN building_locs ON(building_locs.id=building.locid) $inner $where ORDER BY building.id DESC";
    if($paging){
      $sql = $this->Paging($sql, 10);
    }
    $results = $this->db->get_results($sql);
    if($results){
      foreach($results as $key => $result){
        $ownerMod = $this->auto_load('owner');
        $results[$key]['owner'] = json_decode($results[$key]['owner'], true);
        if(!empty($results[$key]['owner']['id'])){
          foreach($results[$key]['owner']['id'] as $key2 => $owner){
            $ownername = $ownerMod->readrecord($results[$key]['owner']['id'][$key2]);
            $results[$key]['owner']['name'][$key2] = $ownername['fullname'];
            $results[$key]['ownerinfo'][$key2] = $ownername;
          }
        }
        $results[$key]['location'] = json_decode($results[$key]['location'], true);
      }
    }
    return $results;
  }

}
