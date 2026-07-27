<?php

class sample_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function backupRestore($id, $group = array())
  {
    if(!empty($group)){
      $ids = explode(',', $group);
      foreach($ids as $id){
        $this->backupRestore($id);
      }
    } else{
      $this->registerLog($id);
      $this->db->query("UPDATE print_samples SET `content`=`backup` WHERE id='$id'");
    }
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('print_samples', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    $template = $this->getrecord($id);
    $html = '';
    if($template['header'] == 1){
      $html .= $this->clearSlashes($this->db->get_var("SELECT content FROM print_samples WHERE id='1'"));
    }
    $html .= $this->clearSlashes($template['content']);
    if($template['footer'] == 1){
      $html .= $this->clearSlashes($this->db->get_var("SELECT content FROM print_samples WHERE id='2'"));
    }
    return $html;
  }

  public function getrecord($id)
  {
    return $this->db->get_row("SELECT * FROM print_samples WHERE id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM print_samples $inner $where ORDER BY id ASC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}