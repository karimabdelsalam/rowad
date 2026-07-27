<?php

class logs_model extends core
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
      $this->db->query("DELETE FROM cp_logs WHERE id='$id'");
    }
  }

  public function getSubModules($moduleid)
  {
    $allModules = [$moduleid];
    $results = $this->db->get_results("SELECT id FROM cp_module WHERE hidden='$moduleid'");
    if($results) {
      foreach($results as $result){
        $allModules[] = $result['id'];
      }
    }
    return implode(',', $allModules);
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT cp_logs.*,cp_module.name AS module,cp_module.title AS modtitle,cp_module_action.name AS action,cp_module_action.title AS actiontitle,user.name AS manager
    ,cp_module_action2.name AS editaction,cp_module_action3.name AS indexaction FROM cp_logs $inner
    INNER JOIN cp_module ON(cp_module.id=cp_logs.moduleid)
    INNER JOIN cp_module_action ON(cp_module_action.id=cp_logs.actionid)
    LEFT JOIN cp_module_action AS cp_module_action2 ON(cp_module_action2.modulid=cp_logs.moduleid AND cp_module_action2.name='edit')
    LEFT JOIN cp_module_action AS cp_module_action3 ON(cp_module_action3.modulid=cp_logs.moduleid AND (cp_module_action3.name='index' OR cp_module_action3.name!='add') AND cp_module_action3.hidden='0')
    INNER JOIN user ON(user.id=cp_logs.userid)
    $where GROUP BY cp_logs.id ORDER BY cp_logs.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
