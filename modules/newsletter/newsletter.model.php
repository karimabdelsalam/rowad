<?php

class newsletter_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function queueUserPlans($groupids, $data)
  {
    if(empty($groupids)) return;
    $groupids = implode(',', $groupids);
    $emails = $this->db->get_results("SELECT email FROM user WHERE groupid='2' AND planid IN($groupids)");
    if($emails){
      foreach($emails as $email){
        $data['receiver'] = $email['email'];
        $this->queueNewMail($data);
      }
    }
  }

  public function queueUserEmails($groupids, $data)
  {
    if(empty($groupids)) return;
    $groupids = implode(',', $groupids);
    $emails = $this->db->get_results("SELECT email FROM user WHERE groupid IN($groupids)");
    if($emails){
      foreach($emails as $email){
        $data['receiver'] = $email['email'];
        $this->queueNewMail($data);
      }
    }
  }

  public function queueMaillist($data)
  {
    $emails = $this->db->get_results("SELECT email FROM email_list");
    if($emails){
      foreach($emails as $email){
        $data['receiver'] = $email['email'];
        $this->queueNewMail($data);
      }
    }
  }

  public function queueMailTable($table)
  {
    $emails = $this->db->get_results("SELECT email FROM `$table`");
    if($emails){
      foreach($emails as $email){
        $data['receiver'] = $email['email'];
        $this->queueNewMail($data);
      }
    }
  }

  public function queueNewMail($data)
  {
    return $this->db->insert('email_queue', $data);
  }

  public function saveEmails($data)
  {
    foreach($data as $email){
      $bool = $this->db->get_var("SELECT id FROM email_list WHERE email='$email'");
      if(!$bool){
        $this->db->insert('email_list', array('email' => $email, 'timepost' => TIMENOW));
      }
    }
  }

  public function listUserGroup()
  {
    return $this->db->get_results("SELECT * FROM user_group ORDER BY id ASC");
  }

  public function deleteExec($id, $group = '')
  {
    if(!empty($group)){
      $this->db->query("DELETE FROM email_list WHERE id IN($group)");
    } else{
      $this->db->query("DELETE FROM email_list WHERE id='$id'");
    }
  }

  public function listEmail($where = array(), $paging = true)
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM email_list $where ORDER BY id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
