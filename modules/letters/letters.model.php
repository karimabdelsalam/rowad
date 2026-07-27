<?php

class letters_model extends core
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
      $this->registerLog($id, 'letters', 'id');
      $this->db->query("DELETE FROM letters WHERE id='$id'");
    }
  }

  public function makeBuildingBusy($buildid)
  {
    $contractid = $this->db->get_var("SELECT contractid FROM building WHERE id='$buildid'");
    $this->db->query("UPDATE building SET available='1' WHERE id='$buildid'");
    $this->db->query("UPDATE rent_contracts SET ended='1' WHERE id='$contractid'");
  }

  public function readTypes()
  {
    return $this->db->get_results('SELECT * FROM letters_type');
  }

  public function addrecord($data)
  {
    return $this->db->insert('letters', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('letters', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    $letter = $this->db->get_row("SELECT * FROM letters WHERE id='$id'");
    $letter['official'] = json_decode($letter['official'], true);
    $letter['cancel'] = json_decode($letter['cancel'], true);
    $letter['rentraise'] = json_decode($letter['rentraise'], true);
    $letter['review'] = json_decode($letter['review'], true);
    if(in_array($letter['type'], array('cancel', 'rentraise', 'finish'))){
      $letter['renter'] = $this->auto_load_read($letter['renterid'], 'buyer');
      $letter['build'] = $this->db->get_var("SELECT title FROM building WHERE id='$letter[buildid]'");
    }
    if(!empty($letter['review']['city'])){
      $letter['review']['city_name'] = $this->db->get_var("SELECT name FROM city WHERE id='" . $letter['review']['city'] . "'");
    }
    if(!empty($letter['review']['district'])){
      $letter['review']['district_name'] = $this->db->get_var("SELECT name FROM district WHERE id='" . $letter['review']['district'] . "'");
    }
    return $letter;
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT letters.*,letters_type.title AS type_title FROM letters
    INNER JOIN letters_type ON(letters_type.name=letters.type)
    $inner $where ORDER BY letters.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $letters = array();
    $results = $this->db->get_results($sql);
    if($results){
      $counter = 0;
      foreach($results as $result){
        $letters[$counter] = $result;
        $letters[$counter]['official'] = json_decode($result['official'], true);
        $letters[$counter]['review'] = json_decode($result['review'], true);
        if(in_array($result['type'], array('cancel', 'rentraise', 'finish'))){
          $letters[$counter]['renter'] = $this->auto_load_read($result['renterid'], 'buyer');
          $letters[$counter]['build'] = $this->db->get_var("SELECT title FROM building WHERE id='$result[buildid]'");
        }
        $counter++;
      }
    }
    return $letters;
  }

}
