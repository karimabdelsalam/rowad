<?php

namespace ownerarea;

class units_model extends \core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    $where[] = "JSON_SEARCH(building.owner, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL";
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
        $results[$key]['owner'] = json_decode($results[$key]['owner'], true);
        if(!empty($results[$key]['owner']['id'])){
          foreach($results[$key]['owner']['id'] as $key2 => $owner){
            $ownername = $this->db->get_row("SELECT id,fname,lname,fathname,famname,mobile,idnumber FROM owner WHERE id='" . $results[$key]['owner']['id'][$key2] . "'");
            $results[$key]['owner']['name'][$key2] = '#' . $ownername['id'] . ' - ' . $ownername['fname'] . ' ' . $ownername['fathname'] . ' ' . $ownername['lname'] . ' ' . $ownername['famname'];
            $ownername['fullname'] = $ownername['fname'] . ' ' . $ownername['fathname'] . ' ' . $ownername['lname'] . ' ' . $ownername['famname'];
            $results[$key]['ownerinfo'][$key2] = $ownername;
          }
        }
        $results[$key]['location'] = json_decode($results[$key]['location'], true);
        if($result['type'] == 'rent'){
          $lastContract = $this->db->get_var("SELECT enddate FROM rent_contracts WHERE buildid='$result[id]' AND ended='0' AND enddate>NOW() ORDER BY enddate DESC LIMIT 1");
          if($lastContract){
            $results[$key]['freein'] = $lastContract;
          }
        }
      }
    }
    return $results;
  }

}
