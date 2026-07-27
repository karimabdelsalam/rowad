<?php

class employees_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function deleteAttachFile($id)
  {
    $fileinfo = $this->db->get_row("SELECT * FROM attachments WHERE id='$id'");
    @unlink(ROOT_DIR . '/' . UPLOAD_DIR . '/' . $fileinfo['path']);
    $this->registerLog($_GET['id'], 'attachments', 'name');
    $this->db->query("DELETE FROM attachments WHERE id='$id'");
  }

  public function deleteExec($id, $group = '')
  {
    if(!empty($group)){
      $ids = explode(',', $group);
      foreach($ids as $id){
        $this->deleteExec($id);
      }
    } else{
      $this->verifyDelete("SELECT id FROM statement_in WHERE from_id='$id' AND from_type='employee'");
      $this->verifyDelete("SELECT id FROM statement_out WHERE from_id='$id' AND from_type='employee'");
      $this->registerLog($id, 'employees', 'id');
      $this->db->query("DELETE FROM employees WHERE id='$id'");
      $this->db->query("DELETE FROM punish WHERE employeeid='$id'");
      $this->db->query("DELETE FROM attachments WHERE objectid='$id' AND moduleid='" . MODULE_ID . "'");
    }
  }

  public function searchQuery($q)
  {
    $results = $this->db->get_results($this->Paging("SELECT id,fname,lname,fathname,famname FROM employees WHERE (fname LIKE '%$q%' OR lname LIKE '%$q%' OR fathname LIKE '%$q%' OR famname LIKE '%$q%' OR idnum='$q')"));
    $search = array();
    $search['results'] = array();
    if($results){
      foreach($results as $result){
        $search['results'][] = array('id' => $result['id'], 'text' => '#' . $result['id'] . ' - ' . $result['fname'] . ' ' . $result['fathname'] . ' ' . $result['lname'] . ' ' . $result['famname']);
      }
    }
    $search['pagination'] = ['more' => $_GET['callpage'] >= $this->paging['pages']  ? false : true];
    return $search;
  }

  public function addrecord($data)
  {
    return $this->db->insert('employees', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('employees', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    $employee = $this->db->get_row("SELECT employees.*,country.name AS country FROM employees LEFT JOIN country ON(country.id=employees.nationality) WHERE employees.id='$id'");
    $employee['files'] = $this->db->get_results("SELECT * FROM attachments WHERE objectid='$id' AND moduleid='" . MODULE_ID . "'");
    $employee['idexpire'] = ($this->config['salary_calendar'] == 1) ? $employee['idexpire'] : uCal::g2u($employee['idexpire']);
    return $employee;
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT employees.*,country.name AS country FROM employees LEFT JOIN country ON(country.id=employees.nationality) $inner $where ORDER BY employees.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
