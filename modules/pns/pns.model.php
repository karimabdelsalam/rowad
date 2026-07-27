<?php

class pns_model extends core
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
      $this->db->query("DELETE FROM notifications WHERE id='$id'");
    }
  }

  public function addrecord($data)
  {
    return $this->db->insert('device_tokens', $data);
  }

  public function updaterecord($data, $id)
  {
    $this->db->update('device_tokens', $data, ['id' => $id]);
  }

  public function readrecord($id)
  {
    return $this->db->get_row("SELECT * FROM notifications WHERE id='$id'");
  }

  public function checkAdded($token, $platform)
  {
    $token = md5($token);
    return $this->db->get_var("SELECT id FROM device_tokens WHERE token_md5='$token' AND platform='$platform'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    $where[] = "userid='$_SESSION[userid]'";
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM notifications $inner $where ORDER BY id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
