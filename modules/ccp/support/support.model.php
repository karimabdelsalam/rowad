<?php

namespace clientarea;

class support_model extends \core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function addrecord($data)
  {
    return $this->db->insert('support', $data);
  }

  public function updaterecord($data, $id)
  {
    $this->db->update('support', $data, array('id' => $id));
  }

  public function clientBuilds()
  {
    return $this->db->get_results("SELECT building.id,building.title,building_locs.location FROM building LEFT JOIN building_locs ON(building_locs.id=building.locid) WHERE building.id IN(".$this->getAllClientBuilds().")");
  }

}
