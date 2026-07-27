<?php

class core extends systemcore
{

  public function __construct()
  {
    parent::__construct(DIR_MOD, CP_DIR_NAME);

    if(in_array(Module, array('cronjob','client'))){
      return true;
    }

    if(!empty($this->config['license_fail'])){
      define('OUTPUT_NO_SIDEBAR', true);
      $this->showmsg(gettext('انتهت صلاحية الرخصة الخاصة بالنظام. من فضلك تواصل معنا للتجديد.'), 0);
    }

    if(empty($this->config['rest_api_key'])){
      $this->save_setting('rest_api_key', $this->salt(42));
    }

    if(empty($_SESSION['userid']) && Module != 'login' && AUTO_LOADED_MODULE !== true){
      header('HTTP/1.1 401 Unauthorized', true, 401);
      if(!empty($_GET['no_header'])){
        echo 1;
        exit;
      }
      header('Location: ' . CPURL . '/login');
      exit;
    } elseif(!empty($_SESSION['userid']) && Module == 'login' && Action != 'logout'){
      header('Location: ' . CPURL . '/dashboard');
      exit;
    }
    if(empty($_SESSION['userid'])){
      return true;
    }

    if($this->config['active'] == 0 && $_SESSION['userinfo']['groupid'] != 1){
      define('OUTPUT_NO_SIDEBAR', true);
      $this->showmsg($this->config['closemsg'], 'logout');
    }
    if($_SESSION['userinfo']['active'] != 1){
      define('OUTPUT_NO_SIDEBAR', true);
      $this->showmsg(gettext('لا تملك تصريح دخول لتلك الصفحة'), 0);
    }
    if($_SESSION['userinfo']['groupid'] != GROUP_ID && Action != 'logout'){
      define('OUTPUT_NO_SIDEBAR', true);
      $this->showmsg(gettext('من فضلك اعد تسجيل الدخول كمالك او عميل'), 'logout');
    }

    /*if($_SESSION['userinfo']['groupid'] == 1 && isset($this->config['updatecore']['plan']['units_quota']) && $this->config['total_units'] > $this->config['updatecore']['plan']['units_quota'] && Action == 'add') {
      define('OUTPUT_NO_SIDEBAR', true);
      $this->showmsg(gettext('تجاوزت الحد الأقصى من عدد الوحدات التي تسمح بها خطتك.'), 0);
    }*/

    if((CP_PATH == OWNER_DIR_PATH || CP_PATH == CLIENT_DIR_PATH) && isset($this->config['updatecore']['plan']['vip_features']) && $this->config['updatecore']['plan']['vip_features'] == 0) {
      define('OUTPUT_NO_SIDEBAR', true);
      $this->showmsg(gettext('نأسف خطة اسعارك لا تدعم تلك الميزة.'), 0);
    }

    if($_SESSION['userinfo']['groupid'] == 1){
      $permSqlModule = "LEFT JOIN cp_permission ON(cp_permission.modulid=cp_module.id AND cp_permission.userid='$_SESSION[userid]' AND cp_permission.permission='1')";
      $permSqlAction = "INNER JOIN cp_permission ON(cp_permission.actionid=cp_module_action.id AND cp_permission.userid='$_SESSION[userid]' AND cp_permission.permission='1')";
      $select = ',cp_permission.modulid AS permodulid';
    } else {
      $permSqlModule = '';
      $permSqlAction = '';
      $select = '';
    }

    $menuCachePath = CACHE_DIR . '/menuCacheUser_' . $_SESSION['userid'];
    if(file_exists($menuCachePath)){
      $menu = unserialize($this->readlocalfile($menuCachePath));
    } else {
      $modules = $this->db->get_results("SELECT cp_module.* $select FROM cp_module
      $permSqlModule
      WHERE (cp_module.hidden='0' OR cp_module.hidden='-2') AND cp_module.groupid='" . $_SESSION['userinfo']['groupid'] . "'
      GROUP BY cp_module.id ORDER BY cp_module.position ASC");
      if($modules){
        foreach($modules as $module){
          if($_SESSION['userinfo']['groupid'] == 1 && empty($module['permodulid']) && $module['hidden'] == 0){
            continue;
          }
          $dataThere = false;
          $module['modules'] = $this->db->get_results("SELECT cp_module.* FROM cp_module
          $permSqlModule
          WHERE cp_module.hidden='$module[id]' AND cp_module.position > 0 AND cp_module.groupid='" . $_SESSION['userinfo']['groupid'] . "'
          GROUP BY cp_module.id ORDER BY cp_module.position ASC");
          if($module['modules']){
            foreach($module['modules'] as $key => $submodule){
              $module['modules'][$key]['actions'] = $this->db->get_results("SELECT cp_module_action.*,cp_module.hidden AS submodule,cp_module.name AS module FROM cp_module_action
              $permSqlAction
              INNER JOIN cp_module ON(cp_module.id=cp_module_action.modulid)
              WHERE cp_module_action.hidden='0' AND cp_module_action.modulid='$submodule[id]' GROUP BY cp_module_action.id ORDER BY cp_module_action.position ASC");
              if(!empty($module['modules'][$key]['actions'])){
                $dataThere = true;
              } else {
                unset($module['modules'][$key]);
              }
            }
          }
          $module['actions'] = $this->db->get_results("SELECT cp_module_action.*,cp_module.hidden AS submodule,cp_module.name AS module FROM cp_module_action
           $permSqlAction
           INNER JOIN cp_module ON(cp_module.id=cp_module_action.modulid)
           WHERE cp_module_action.hidden='0' AND cp_module_action.modulid='$module[id]' GROUP BY cp_module_action.id ORDER BY cp_module_action.position ASC");
          if(! $dataThere){
            continue;
          }
          $menu[] = $module;
        }
      }
      $this->storelocalfile($menuCachePath, serialize($menu));
    }
    if(!defined('API_ENABLED')) {
      $this->Smarty->assign('menus', $menu);
    }

    $reqmodule = $this->db->get_row("SELECT cp_module.*,cp_module_action.id AS reqActionID FROM cp_module INNER JOIN cp_module_action ON(LOWER(cp_module_action.name)='" . Action . "' AND cp_module_action.modulid=cp_module.id) WHERE LOWER(cp_module.name)='" . Module . "' AND cp_module.groupid='" . $_SESSION['userinfo']['groupid'] . "'");
    $reqaction = $this->db->get_row("SELECT * FROM cp_module_action WHERE id='$reqmodule[reqActionID]'");
    if(!defined('API_ENABLED')) {
      $this->Smarty->assign('cumodule', $reqmodule);
      $this->Smarty->assign('cuaction', $reqaction);
    }

    define('MODULE_ID', $reqmodule['id']);
    define('ACTION_ID', $reqaction['id']);

    if($reqmodule['groupid'] != $_SESSION['userinfo']['groupid']){
      define('OUTPUT_NO_SIDEBAR', true);
      $this->showmsg(gettext('لا تملك تصريح دخول لتلك الصفحة'), 0);
    }

    if($_SESSION['userinfo']['groupid'] == 1){
      if($reqaction['hidden'] == 3){
        $permission = $this->db->get_var("SELECT userid FROM cp_permission WHERE modulid='$reqmodule[id]' AND userid='$_SESSION[userid]' AND permission='1'");
        if(!$permission){
          $donothaveper = $this->db->get_var("SELECT userid FROM cp_permission WHERE modulid='$reqmodule[id]' AND userid='$_SESSION[userid]' AND permission='0'");
          if(!$donothaveper){
            $permission = true;
          }
        }
        $permSqlAction = "INNER JOIN cp_permission ON(cp_permission.actionid=cp_module_action.id AND cp_permission.userid='$_SESSION[userid]' AND cp_permission.permission='1')";
      } else{
        $permission = $this->db->get_var("SELECT userid FROM cp_permission WHERE modulid='$reqmodule[id]' AND actionid='$reqaction[id]' AND userid='$_SESSION[userid]' AND permission='1'");
      }
      if(!$permission){
        define('OUTPUT_NO_SIDEBAR', true);
        $this->showmsg(gettext('لا تملك تصريح دخول لتلك الصفحة'), 0);
      }
    } elseif(in_array($_SESSION['userinfo']['groupid'], [2, 3])){
    } else {
      define('OUTPUT_NO_SIDEBAR', true);
      $this->showmsg(gettext('لا تملك تصريح دخول لتلك الصفحة'), 0);
    }

    if(method_exists($this, 'TaskList')){
      $this->Smarty->assign('navabrnotifys', $this->notifsList(10, true));
    } else{
      $dashboard = $this->auto_load('dashboard');
      $this->Smarty->assign('navabrnotifys', $dashboard->notifsList(10, true));
    }

    $actionbtns = $this->db->get_results("SELECT cp_module_action.*,cp_module2.name AS refmodule FROM cp_module_action
     $permSqlAction
     LEFT JOIN cp_module AS cp_module2 ON(cp_module_action.refmoduleid > 0 AND cp_module2.id=cp_module_action.refmoduleid)
     WHERE (cp_module_action.hidden='1' OR cp_module_action.hidden='2') AND cp_module_action.modulid='$reqmodule[id]' GROUP BY cp_module_action.id ORDER BY cp_module_action.position ASC");
    if(!defined('API_ENABLED')) {
      $this->Smarty->assign('actionbtns', $actionbtns);
    }

    if($reqmodule['hidden'] > 0){
      $gotomenuids = $reqmodule['hidden'] . ',' . $reqmodule['id'];
    } else{
      $gotomenuids = $reqmodule['id'];
    }

    $gotomenu = [];
    if(!empty($gotomenuids)) {
      $gotomenu = $this->db->get_results("SELECT cp_module_action.*,cp_module.name AS module FROM cp_module_action
       $permSqlAction
       INNER JOIN cp_module ON(cp_module.id=cp_module_action.modulid)
       WHERE cp_module_action.hidden='0' AND cp_module_action.modulid IN($gotomenuids) GROUP BY cp_module_action.id ORDER BY cp_module_action.position ASC");
    }
    if(!defined('API_ENABLED')) {
      $this->Smarty->assign('gotomenus', $gotomenu);
    }

    $this->Smarty->assign('tempid', md5($_SESSION['userid'] . Module . Action . rand(100, 2000) . time()));
  }

  public function check_api_key()
  {
    if(defined('API_ENABLED') && API_ENABLED) {
      $apiKey = $this->readHeaderValue('API-KEY');
      if($this->config['rest_api_key'] != $apiKey){
        $this->showmsg('API key is missed or invalid', 0);
      }
    }
  }

  public function check_api_interface()
  {
    if(!empty($_GET['output']) && $_GET['output'] == 'json'){
      define('API_ENABLED', true);
      $accessToken = $this->readHeaderValue('ACCESS-TOKEN');
      if(empty($accessToken) && Module == 'login'){
        return true;
      }
      if(empty($accessToken)){
        $this->showmsg('Access token is required', 0);
      }
      $userid = $this->checkAccessToken($accessToken);
      if(empty($userid)){
        $this->showmsg('Access token is invalid', 0);
      }
      $_SESSION['userid'] = $userid;
    }
  }

  public function bootstrap()
  {
    $this->loadSettings();
    if(! empty($_SESSION['userid'])) {
      $_SESSION['userinfo'] = $this->userAuth($_SESSION['userid']);
      if($_SESSION['userinfo']['lang'] == 'ar') {
        $_SESSION['lang'] = ['title' => 'العربية', 'code' => 'ar', 'dir' => 'rtl'];
      } elseif($_SESSION['userinfo']['lang'] == 'en') {
        $_SESSION['lang'] = ['title' => 'English', 'code' => 'en', 'dir' => 'ltr'];
      }
    } elseif($this->config['lang'] == 'ar') {
      $_SESSION['lang'] = ['title' => 'العربية', 'code' => 'ar', 'dir' => 'rtl'];
    } elseif($this->config['lang'] == 'en') {
      $_SESSION['lang'] = ['title' => 'English', 'code' => 'en', 'dir' => 'ltr'];
    }
  }

  public function userAuth($userid)
  {
    $this->userinfo = $this->db->get_row("SELECT * FROM user WHERE id='$userid'");
    if($this->userinfo['groupid'] == 2) {
      $_SESSION['ownerid'] = $this->db->get_var("SELECT id FROM owner WHERE userid='$userid'");
    } elseif($this->userinfo['groupid'] == 3) {
      $_SESSION['ownerid'] = $this->db->get_var("SELECT id FROM buyer WHERE userid='$userid'");
    }
    return $this->userinfo;
  }

  public function loadSettings()
  {
    $excludes = [];
    if(defined('API_ENABLED') && API_ENABLED) {
      $excludes = ['cronjob_lstupdate', 'purchase_code', 'updatecore', 'smtp_username', 'smtp_password', 'sms_user', 'sms_pass'];
    }
    $settings = $this->db->get_results("SELECT * FROM setting");
    foreach($settings as $setting){
      if(in_array($setting['varname'], ['billing_info', 'updatecore'])){
        $config[$setting['varname']] = json_decode($setting['value'], true);
      } else {
        $config[$setting['varname']] = $setting['value'];
      }
    }
    $this->config = $config;
    if(!empty($excludes)) {
      foreach($excludes as $exclude){
        unset($config[$exclude]);
      }
      $this->api_config = $config;
    }
    define('SYS_CALENDAR', $this->config['system_calendar']);
  }

  public function formatClientName($clientInfo, $nameOnly = false)
  {
    if($clientInfo['type'] == 'person'){
      $name = $clientInfo['fname'] . ' ' . $clientInfo['fathname'] . ' ' . $clientInfo['lname'] . ' ' . $clientInfo['famname'];
    } else {
      $name = '[' . $clientInfo['company'] . '] ' . $clientInfo['fname'] . ' ' . $clientInfo['fathname'] . ' ' . $clientInfo['lname'] . ' ' . $clientInfo['famname'];
    }
    if($nameOnly) {
      return $name;
    } else {
      return '#' . $clientInfo['id'] . ' - ' . $name;
    }
  }

  public function formatOwnerName($ownerInfo, $nameOnly = false)
  {
    $name = $ownerInfo['fname'] . ' ' . $ownerInfo['fathname'] . ' ' . $ownerInfo['lname'] . ' ' . $ownerInfo['famname'];
    if($nameOnly) {
      return $name;
    } else {
      return '#' . $ownerInfo['id'] . ' - ' . $name;
    }
  }

  public function getAllClientBuilds()
  {
    $clientBuildIDs = [];
    $clientBuilds = $this->db->get_results("SELECT buildid FROM rent_contracts WHERE ended='0' AND JSON_SEARCH(buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL");
    if($clientBuilds){
      foreach($clientBuilds as $clientBuild){
        $clientBuildIDs[] = $clientBuild['buildid'];
      }
    }
    $clientBuilds = $this->db->get_results("SELECT buildid FROM sell_contracts WHERE JSON_SEARCH(buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL");
    if($clientBuilds){
      foreach($clientBuilds as $clientBuild){
        $clientBuildIDs[] = $clientBuild['buildid'];
      }
    }
    if(!empty($clientBuildIDs)) {
      return implode(',', array_unique($clientBuildIDs));
    } else {
      return '0';
    }
  }

  public function getAllClientRentContracts()
  {
    $clientContractIDs = [];
    $clientContracts = $this->db->get_results("SELECT id FROM rent_contracts WHERE JSON_SEARCH(buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL");
    if($clientContracts){
      foreach($clientContracts as $clientContract){
        $clientContractIDs[] = $clientContract['id'];
      }
    }
    if(!empty($clientBuildIDs)) {
      return implode(',', array_unique($clientContractIDs));
    } else {
      return '0';
    }
  }

  public function getAllClientSellContracts()
  {
    $clientContractIDs = [];
    $clientContracts = $this->db->get_results("SELECT id FROM sell_contracts WHERE JSON_SEARCH(buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL");
    if($clientContracts){
      foreach($clientContracts as $clientContract){
        $clientContractIDs[] = $clientContract['id'];
      }
    }
    if(!empty($clientBuildIDs)) {
      return implode(',', array_unique($clientContractIDs));
    } else {
      return '0';
    }
  }

  public static function payMethodTitle($method) {
    switch($method) {
      case 'cash':
        return gettext('نقداً');
      case 'cheque':
        return gettext('شيك');
      case 'bank':
        return gettext('تحويل بنكي');
      default:
        return 'unkown';
    }
  }

  public static function supportTypes($method) {
    switch($method) {
      case 'request':
        return gettext('طلب');
      case 'enquiry':
        return gettext('استفسار');
      case 'problem':
        return gettext('مشكلة');
      case 'suggestion':
        return gettext('اقتراح');
      default:
        return 'unkown';
    }
  }

  public function loadNationalities()
  {
    $nationalities = $this->db->get_results("SELECT * FROM country ORDER BY displayorder ASC");
    $this->Smarty->assign('nationalities', $nationalities);
  }

  public function moveAttachments($tempid, $objectid)
  {
    $this->db->query("UPDATE attachments SET moduleid='" . MODULE_ID . "',objectid='$objectid',tempid=NULL WHERE tempid='$tempid'");
  }

  public function addNotification($subject, $content, $icon, $userid)
  {
    switch($icon) {
      case 'alert':
        $iconCode = 'alert';
        break;
      case 'info':
        $iconCode = 'info';
        break;
    }

    $data = [];
    $data['userid'] = $userid;
    $data['subject'] = $subject;
    $data['content'] = $content;
    $data['icon'] = $iconCode;
    $data['read_state'] = 0;

    if($userid == 0) {
      $admins = $this->db->get_results("SELECT id FROM user WHERE groupid='1'");
      foreach($admins as $admin) {
        $data['userid'] = $admin['id'];
        $this->db->insert('notifications', $data);
      }
    } else {
      $this->db->insert('notifications', $data);
    }
  }

  public function verifyDelete($query, $reason = false)
  {
    $tables = array(
      'building' => gettext('الوحدات'),
      'buyer' => gettext('العملاء'),
      'bankacc_transactions' => gettext('حركة الحسابات البنكية'),
      'bank_accounts' => gettext('البنوك'),
      'payments' => gettext('المبالغ المالية'),
      'rent_contracts' => gettext('عقود الإيجارات'),
      'sell_contracts' => gettext('عقود البيع'),
      'statement_in' => gettext('سندات القبض'),
      'statement_out' => gettext('سندات الصرف'),
      'transactions' => gettext('المستحقات المالية'),
      'letters' => gettext('الخطابات'),
      'invoices' => gettext('الفواتير')
    );
    $bool = $this->db->get_var($query);
    if($bool){
      if($reason !== false){
        $this->showmsg($reason, 0);
      }
      foreach($tables as $table => $reason){
        if(strpos($query, 'FROM ' . $table)){
          $this->showmsg(gettext('لا يمكن تنفيذ طلبك لأرتباط ذلك السجل بسجل آخر في وحدة') . ' ' . $reason, 0);
        }
      }
      $this->showmsg(gettext('لا يمكن تنفيذ عملية الحذف لاستخدام تلك البيانات في عملبات ادخال اخرى'), 0);
    }
  }

  public function bordersList()
  {
    return $this->db->get_results("SELECT * FROM `building_borders`");
  }

  public function processDimensions($type, $width, $no, $unit = false)
  {
    $border = $this->db->get_row("SELECT * FROM `building_borders` WHERE border='$type'");
    if(empty($border)){
      return gettext('غير معروف');
    }
    if(strpos($border['attrs'], 'W') !== false || strpos($border['attrs'], 'N') !== false){
      $string = $border['title'].' (' . $width . ')' . (($unit) ? ' '.gettext('م') : '');
    }
    elseif(strpos($border['attrs'], 'N') !== false){
      $string = $border['title'].' (' . $no . ')' . (($unit) ? ' '.gettext('م') : '');
    }
    else{
      $string = $border['title'];
    }
    return $string;
  }

  public function registerLog($objectid=0, $titletbl = '', $titlecol = '')
  {
    if(Module == 'login' && Action == 'index'){
      define('MODULE_ID', 79);
      define('ACTION_ID', 608);
    }
    if(empty($_SESSION['userid'])){
      return false;
    }
    $data = array();
    $data['moduleid'] = MODULE_ID;
    $data['actionid'] = ACTION_ID;
    $data['objectid'] = $objectid;
    $data['userid'] = $_SESSION['userid'];
    if(!empty($titletbl)){
      $data['title'] = $this->db->get_var("SELECT `$titlecol` FROM `$titletbl` WHERE id='$objectid'");
      $this->db->query("DELETE FROM cp_logs WHERE objectid='$objectid' AND moduleid='" . MODULE_ID . "'");
    }
    return $this->db->insert('cp_logs', $data);
  }

  function refreshContracts() {
    $paymentmodule = $this->auto_load('payments');

    $expireContracts = $this->db->get_results("SELECT id,buildid FROM rent_contracts WHERE DATE(NOW())>enddate");
    if($expireContracts){
      foreach($expireContracts as $expireContract){
        $this->db->query("UPDATE building SET available='1',contractid='0' WHERE id='$expireContract[buildid]' AND contractid='$expireContract[id]'");
      }
    }

    $newContracts = $this->db->get_results("SELECT id,buildid FROM rent_contracts WHERE DATE(NOW())>=startdate AND DATE(NOW())<enddate ORDER BY startdate ASC");
    if($newContracts){
      foreach($newContracts as $newContract){
        $this->db->query("UPDATE building SET available='0',contractid='$newContract[id]' WHERE id='$newContract[buildid]'");
      }
    }

    $buildManCosts = $this->db->get_results("SELECT id,mcost,mcost_next_date,mcost_start_date,mcost_cycle,calendar FROM building WHERE mcost_next_date<=DATE(NOW()) AND active='1'");
    if($buildManCosts){
      foreach($buildManCosts as $buildManCost){
        $next_date = $paymentmodule->buildManCostPeriod($buildManCost);
        $this->db->update('building', [ 'mcost_next_date' => $next_date ], [ 'id' => $buildManCost['id'] ]);
      }
    }

  }

  private function readSubModules($moduleid, $permSqlModule)
  {
    $submodules = $this->db->get_results("SELECT cp_module.id FROM cp_module
     $permSqlModule
     WHERE cp_module.hidden='$moduleid' GROUP BY cp_module.id");
    $ids = array(0 => $moduleid);
    if($submodules){
      foreach($submodules as $submodule){
        $ids[] = $submodule['id'];
      }
    }
    $ids = implode(',', $ids);
    return $ids;
  }

  private function checkAccessToken($access_token)
  {
    $userid = $this->db->get_var("SELECT userid FROM user_access_token WHERE token='$access_token'");
    if(! $userid) {
      return false;
    }
    return $userid;
  }

  public function deleteUser($userid)
  {
    if(empty($userid)) return false;
    $this->db->query("DELETE FROM user WHERE id='$userid'");
    $this->db->query("DELETE FROM login_activity WHERE userid='$userid'");
    $this->db->query("DELETE FROM reset_codes WHERE userid='$userid'");
  }

}
