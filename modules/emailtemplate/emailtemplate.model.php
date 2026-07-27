<?php

class emailtemplate_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function markasActive($id, $group = array())
  {
    if(!empty($group)){
      $this->db->query("UPDATE email_templates SET active='1' WHERE id IN($group)");
    } else{
      $this->db->query("UPDATE email_templates SET active='1' WHERE id='$id'");
    }
  }

  public function markasInactive($id, $group = array())
  {
    if(!empty($group)){
      $this->db->query("UPDATE email_templates SET active='0' WHERE id IN($group)");
    } else{
      $this->db->query("UPDATE email_templates SET active='0' WHERE id='$id'");
    }
  }

  public function saveTemplate($data, $id)
  {
    return $this->db->update('email_templates', $data, array('id' => $id));
  }

  public function readTemplate($id)
  {
    return $this->db->get_row("SELECT * FROM email_templates WHERE id='$id'");
  }

  public function listTemplate()
  {
    $sql = $this->Paging("SELECT * FROM email_templates ORDER BY id DESC");
    $results = $this->db->get_results($sql);
    return $results;
  }

}