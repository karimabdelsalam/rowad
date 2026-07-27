<?php

namespace clientarea;

class rent_model extends \core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function hasAuthContract($id)
  {
    return $this->db->get_var("SELECT rent_contracts.id FROM rent_contracts
      WHERE JSON_SEARCH(rent_contracts.buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL AND rent_contracts.id = $id");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    $where[] = "JSON_SEARCH(rent_contracts.buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL";
    $where[] = "rent_contracts.archive='0'";
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT rent_contracts.*,building.owner,building.title AS build FROM rent_contracts INNER JOIN building ON(building.id=rent_contracts.buildid) $inner $where GROUP BY rent_contracts.id ORDER BY rent_contracts.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    if($results){
      $owner = $this->auto_load('owner');
      foreach($results as $key => $result){
        $results[$key]['owner'] = json_decode($results[$key]['owner'], true);
        if(!empty($results[$key]['owner']['id'])){
          foreach($results[$key]['owner']['id'] as $key2 => $buyer){
            $buyername = $owner->readrecord($results[$key]['owner']['id'][$key2]);
            $results[$key]['owner']['name'][$key2] = $buyername['fullname'];
          }
        }
      }
    }
    return $results;
  }

}
