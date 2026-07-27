<?php

class manager_model extends core
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
      $isSuper = $this->db->query("SELECT super FROM user WHERE id='$id'");
      if($isSuper == 1){
        $this->showmsg(gettext('لا يمكن حذف حساب المدير الأساسي بالنظام.'), 0);
      }
      $this->registerLog($id, 'user', 'name');
      $this->db->query("DELETE FROM user WHERE id='$id'");
      $this->db->query("DELETE FROM cp_permission WHERE userid='$id'");
    }
  }

  public function markasActive($id, $group = array())
  {
    if(!empty($group)){
      $ids = explode(',', $group);
      foreach($ids as $id){
        $this->markasActive($id);
      }
    } else{
      $this->registerLog($id);
      $this->db->query("UPDATE user SET active='1' WHERE id='$id'");
    }
  }

  public function markasinActive($id, $group = array())
  {
    if(!empty($group)){
      $ids = explode(',', $group);
      foreach($ids as $id){
        $this->markasinActive($id);
      }
    } else{
      $this->registerLog($id);
      $this->db->query("UPDATE user SET active='0' WHERE id='$id'");
    }
  }

  public function updateManager($data, $userid)
  {
    $bool = $this->db->get_var("SELECT id FROM user WHERE (username='$data[username]' OR email='$data[email]') AND id!='$userid'");
    if($bool){
      $this->showmsg(gettext('اسم المستخدم او البريد الإلكتروتي مستخدمين من قبل.'), 0);
    }
    $this->db->update('user', $data, array('id' => $userid));
  }

  public function updatePassword($newpass, $userid)
  {
    $this->db->update('user', ['password' => $newpass], array('id' => $userid));
  }

  public function addNewManager($data)
  {
    $bool = $this->db->get_var("SELECT id FROM user WHERE username='$data[username]' OR email='$data[email]'");
    if($bool) {
      $this->showmsg(gettext('اسم المستخدم او البريد الإلكتروتي مستخدمين من قبل.'), 0);
    }
    if(empty($data['picture'])) {
      $data['picture'] = 'user_img/default.png';
    }
    $userid = $this->db->insert('user', $data);
    return $userid;
  }

  public function getUserInfo($userid)
  {
    return $this->db->get_row("SELECT * FROM user WHERE id='$userid'", 'ARRAY_A');
  }

  public function readrecord($userid)
  {
    return $this->db->get_row("SELECT * FROM user WHERE id='$userid'", 'ARRAY_A');
  }

  public function loadUserPermissions($userid)
  {
    $modules = $this->db->get_results("SELECT id,name,title FROM cp_module WHERE groupid='1' AND hidden>-1 ORDER BY position ASC", 'ARRAY_A');
    if($modules){
      foreach($modules as $module){
        $module['actions'] = $this->db->get_results("SELECT cp_module_action.id,cp_module_action.name,cp_module_action.title,cp_permission.permission FROM cp_module_action
         LEFT JOIN cp_permission ON(cp_permission.actionid=cp_module_action.id AND cp_permission.userid='$userid')
         WHERE cp_module_action.modulid='$module[id]' AND cp_module_action.hidden<3 GROUP BY cp_module_action.id ORDER BY cp_module_action.position ASC", 'ARRAY_A');
        $permission[] = $module;
      }
      return $permission;
    }
  }

  public function updateAdminPermission($userid)
  {
    $menuCachePath = CACHE_DIR . '/menuCacheUser_' . $userid;
    if(file_exists($menuCachePath)){
      unlink($menuCachePath);
    }
    $this->db->delete('cp_permission', array('userid' => $userid));
    $modules = $this->db->get_results("SELECT id,name FROM cp_module WHERE groupid='1' AND hidden>-1", 'ARRAY_A');
    if($modules){
      foreach($modules as $module){
        $actions = $this->db->get_results("SELECT id,name FROM cp_module_action WHERE modulid='$module[id]'", 'ARRAY_A');
        if($actions){
          foreach($actions as $action){
            if($_POST[$module[name] . '_' . $action[name]]){
              $permission = 1;
            } else{
              $permission = 0;
            }
            $this->db->insert('cp_permission', array('userid' => $userid, 'modulid' => $module['id'], 'actionid' => $action['id'], 'permission' => $permission));
          }
        }
      }
    }
  }

  public function getManagers($where = array(), $paging = true)
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'AND ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM user WHERE groupid='1' $where ORDER BY id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
