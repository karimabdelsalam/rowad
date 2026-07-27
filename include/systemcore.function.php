<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class systemcore
{
  public $db;
  public $Smarty;
  public $config;
  public $api_config;
  public $reqmodule;
  public $paging;
  public $enqueuejs = array();
  public $enqueuecss = array();
  public $returnnoexit = false;
  public $templatedir;
  public $pathurl;
  public $userinfo;
  public $curl_status;
  private $moduleCache;
  private $lastModuleCache;
  public $updateServer;
  public $version = 5.6;//Always update version in build.core.php

  public function __construct($templatedir, $pathurl = '')
  {
    $this->templatedir = $templatedir;
    require_once(LIB_DIR . '/db/ez_sql_core.php');
    require_once(LIB_DIR . '/db/ez_sql_mysqli.php');
    require_once(LIB_DIR . '/Smarty/Smarty.class.php');
    require_once(LIB_DIR . '/HijriCalendar.class.php');

    global $db_config;
    $this->db = new ezSQL_mysqli();
    $this->db->hide_errors();
    $this->db->quick_connect($db_config['dbuser'], $db_config['dbpass'], $db_config['dbname'], $db_config['dbhost'], '', $db_config['charset']);

    if(!empty($this->db->last_error)){
      die($this->db->last_error);
    }

    if(DEBUG_MODE){
      $this->db->show_errors();
    } else {
      $this->db->hide_errors();
    }

    if(DEBUG_MODE === true || DEBUG_MODE == 'debug'){
      $this->updateServer = 'https://smartiolabs.com/update/phantom_property_manager';
    } else{
      $this->updateServer = 'https://smartiolabs.com/update/phantom_property_manager';
    }

    $live = $this->db->get_var("SELECT COUNT(varname) FROM setting");
    if(empty($live)){
      header('Location: ./install');
      exit;
    }

    $this->check_api_interface();
    $this->bootstrap();
    $this->check_api_key();

    date_default_timezone_set($this->config['timezone']);

    $origin_dtz = new DateTimeZone($this->config['timezone']);
    $offset = new DateTime('now', $origin_dtz);
    $offset = $origin_dtz->getOffset($offset)/3600;
    if($offset < 0){
      $this->db->query("SET time_zone = '$offset:00';");
    } else {
      $this->db->query("SET time_zone = '+$offset:00';");
    }

    define('BASEURL', $this->config['siteurl']);
    define('SITEURL', $this->config['siteurl']);
    define('MEDIAURL', SITEURL . '/' . UPLOAD_DIR);
    define('CPURL', SITEURL.'/'.CP_PATH);
    define('VERSION', $this->config['version']);
    define('DATENOW', date('Y-m-d'));
    define('TIMENOW', time());
    define('DOMAIN', str_replace('www.', '', parse_url(SITEURL, PHP_URL_HOST)));

    if($_SESSION['lang']['code'] == 'en' && !empty($this->config['lang_file_en'])){
      $lang_code = 'en';
      $lang_locale = 'en_US';
      $lang_file = $this->config['lang_file_en'];
    } elseif($_SESSION['lang']['code'] == 'ar' && !empty($this->config['lang_file_ar'])){
      $lang_code = 'ar';
      $lang_locale = 'ar_SA';
      $lang_file = $this->config['lang_file_ar'];
    } else {
      $lang_code = 'ar';
      $lang_locale = 'ar_SA';
      putenv('LANG=' . $lang_code);
      setlocale(LC_ALL, $lang_locale . '.UTF-8');
      $lang_code = '';
    }

    if(! empty($lang_code) && ! defined('NATVIE_LANGUAGE')){
      $textdomain = str_replace('.mo', '', basename($lang_file));
      $lang_file_source = ROOT_DIR . '/' .UPLOAD_DIR . '/' . $lang_file;
      $lang_file_path = ROOT_DIR . '/language/' . $lang_code . '/LC_MESSAGES/' . $textdomain . '.mo';

      if(file_exists($lang_file_source)){
        if(! file_exists($lang_file_path)){
          $this->delDir(ROOT_DIR . '/language/' . $lang_code . '/LC_MESSAGES/', true);
          copy($lang_file_source, $lang_file_path);
        }
        putenv('LANG=' . $lang_code);
        setlocale(LC_ALL, $lang_locale . '.UTF-8');
        bindtextdomain($textdomain, ROOT_DIR . '/language');
        bind_textdomain_codeset($textdomain, 'UTF-8');
        textdomain($textdomain);
      }
    }

    $this->checkUpgrade();
    $this->runCronJob();

    $this->pathurl = SITEURL;
    $this->Smarty = new Smarty();
    $this->Smarty->setTemplateDir($this->templatedir . '/');
    $this->Smarty->setCompileDir(ROOT_DIR . '/' . UPLOAD_DIR . '/cache');
    if(DEBUG_MODE && DEBUG_MODE == 'localhost'){
      $this->Smarty->clearCompiledTemplate();
      $this->Smarty->clearAllCache();
    }
    $this->Smarty->assign('js_path', $this->pathurl . '/assets/js');
    $this->Smarty->assign('css_path', $this->pathurl . '/assets/css');
    $this->Smarty->assign('image_path', $this->pathurl . '/assets/images');

    if(defined('API_ENABLED') && API_ENABLED) {
      $this->Smarty->assign('site_config', $this->api_config);
    }
    $this->Smarty->assign('config', $this->config);
    $this->Smarty->assign('SITEURL', SITEURL);
    $this->Smarty->assign('BASEURL', BASEURL);
    $this->Smarty->assign('MEDIAURL', MEDIAURL);
    $this->Smarty->assign('CPURL', CPURL);
  }

  public function checkUpgrade()
  {
    $this->config['version'] = str_replace(',', '.', $this->config['version']);
    if($this->version > $this->config['version']){
      require(INC_DIR . '/upgrade.core.php');
    }
  }

  public function siteModules()
  {
    return $this->db->get_results("SELECT * FROM module ORDER BY `title` ASC");
  }

  public function runCronJob()
  {
    if(TIMENOW > ($this->config['cronjob_lstupdate'] + 60)){
      $this->save_setting('cronjob_lstupdate', TIMENOW);
      $this->config['cronjob_lstupdate'] = TIMENOW;
      $this->silentRequest(CPURL . '/cronjob');
    }
  }

  public function enqueueJSLibrary($library)
  {
    switch($library){
      case 'charts':
        $this->enqueueJS('Chart.min');
        $this->enqueueJS('chartjs-plugin-datalabels');
        //$this->enqueueJS('chartjs-plugin-colorschemes');
        $this->enqueueJS('Chart.defaults');
        break;
      case 'timeline':
        $this->enqueueCSS('jquery.timeline.min');
        $this->enqueueJS('jquery.timeline.min');
        $this->enqueueJS('jquery.timeline.init');
        break;
    }
  }

  public function enqueueJS($jsfile)
  {
    $this->enqueuejs[] = $jsfile;
  }

  public function enqueueCSS($cssfile)
  {
    $this->enqueuecss[] = $cssfile;
  }

  public function debug($object)
  {
    echo json_encode($object);
    exit;
  }

  public function log($message, $level = 'error')
  {
    if($level == 'debug' && DEBUG_MODE != 'debug'){
      return true;
    }
    if(DEBUG_MODE){
      if(is_array($message)){
        $message = json_encode($message);
      }
      $message = date('d/m/y H:i:s') . ' : ' . $message;
      $message .= "\n==============================================";
      $message .= "\n";
      error_log($message, 3, ERROR_LOG_FILE);
    }
  }

  public function auto_load($module, $modulepath = false, $namespace = false)
  {
    define('AUTO_LOADED_MODULE', true);
    $route = explode('/', $module);
    if(empty($route[1])) {
      $controller = $module;
    } else {
      $controller = $route[1];
    }
    if($namespace) {
      $controllerName = $namespace . '\\' . $controller;
    } else {
      $controllerName = $controller;
    }
    if($modulepath === false){
      $modulepath = $this->templatedir;
    }
    include_once($modulepath . '/' . $module . '/' . $controller . '.model.php');
    include_once($modulepath . '/' . $module . '/' . $controller . '.controller.php');
    $$module = new $controllerName();
    return $$module;
  }

  public function auto_load_list($module, $modulepath = false)
  {
    if($this->lastModuleCache == $module){
      return $this->moduleCache->listrecord(array(), false);
    } else{
      $this->lastModuleCache = $module;
      $this->moduleCache = $this->auto_load($module, $modulepath);
      return $this->moduleCache->listrecord(array(), false);
    }
  }

  public function auto_load_read($objectid, $module, $modulepath = false)
  {
    if($this->lastModuleCache == $module){
      return $this->moduleCache->readrecord($objectid);
    } else{
      $this->lastModuleCache = $module;
      $this->moduleCache = $this->auto_load($module, $modulepath);
      return $this->moduleCache->readrecord($objectid);
    }
  }

  function Fetch($module = '', $action = '')
  {
    if(empty($module)){
      return $this->Smarty->fetch(Module . '/templates/' . Action . '.tpl');
    } elseif(!empty($action)){
      return $this->Smarty->fetch($module . '/templates/' . $action . '.tpl');
    } else{
      return $this->Smarty->fetch(Module . '/templates/' . $module . '.tpl');
    }
  }

  function Output($tpl = '', $params = '')
  {
    if(defined('API_ENABLED') && API_ENABLED){
      $allVars = $this->Smarty->getTemplateVars();
      unset($allVars['config']);
      $this->json(array('status' => 1, 'message' => '', 'result' => $allVars));
    }
    if(defined('OUTPUT_PRINT_VERSION') && OUTPUT_PRINT_VERSION === true){
      $this->printVersion();
      exit;
    }

    $this->Smarty->assign('enqueuejs', $this->enqueuejs);
    $this->Smarty->assign('enqueuecss', $this->enqueuecss);
    if(empty($tpl)) {
      $action_tpl_path = Module . '/templates/' . Action . '.tpl';
    } else {
      $action_tpl_path = Module . '/templates/' . $tpl . '.tpl';
    }

    if(file_exists($this->templatedir . '/' . Module . '/' . basename(Module) . '.javascript.php')){
      include($this->templatedir . '/' . Module . '/' . basename(Module) . '.javascript.php');
      $customJavascript = ob_get_contents();
      ob_end_clean();
      ob_start();
    } else {
      $customJavascript = '';
    }

    include(DIR_MOD . '/core/localization.php');
    $localization = ob_get_contents();
    ob_end_clean();
    ob_start();

    if(isset($_GET['exportotf']) && $_GET['exportotf'] == 'excel'){
      $template_output = $this->Smarty->fetch($action_tpl_path);
      $html = '';
      preg_match('/<table(.*)<\/table>/s', $template_output, $match);
      if(!empty($match[0])){
        $html = $match[0];
      }

      require LIB_DIR . "/vendor/autoload.php";
      $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
      $spreadsheet = $reader->loadFromString($html);
      $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment; filename="export.xlsx"');
      $writer->save("php://output");
      exit;
    }
    if(isset($_GET['printotf']) && $_GET['printotf'] == 'all'){
      $template_output = $this->Smarty->fetch($action_tpl_path);
      $html = '';
      $supportJS = false;
      preg_match('/<!-- Chart Graph -->(.*)<!-- Chart Graph -->/s', $template_output, $match);
      if(!empty($match[0])){
        $supportJS = true;
        $html .= $match[0];
      }
      preg_match('/<table(.*)<\/table>/s', $template_output, $match);
      if(!empty($match[0])){
        $html .= $match[0];
      }

      $this->Smarty->assign('template_output', $html);
      if($supportJS){
        $this->enqueuejs = [];
        $this->enqueuecss = [];
        $this->enqueueJS('jquery.min');
        $this->enqueueJSLibrary('charts');
        $this->Smarty->assign('localization', $localization);
        $this->Smarty->assign('customJavascript', $customJavascript);
        $this->Smarty->assign('enqueuejs', $this->enqueuejs);
        $this->Smarty->assign('enqueuecss', $this->enqueuecss);
      }
      $this->Smarty->display('core/templates/printable.tpl');
      ob_end_flush();
      exit;
    } elseif(defined('OUTPUT_NO_SIDEBAR') && OUTPUT_NO_SIDEBAR === true){
      $template_output = $this->Smarty->fetch($action_tpl_path);
      $this->Smarty->assign('localization', $localization);
      $this->Smarty->assign('customJavascript', $customJavascript);
      $this->Smarty->assign('template_output', $template_output);
      $this->Smarty->display('core/templates/design_blank.tpl');
    } elseif(empty($_REQUEST['no_header'])){
      $template_output = $this->Smarty->fetch($action_tpl_path);
      $this->Smarty->assign('localization', $localization);
      $this->Smarty->assign('customJavascript', $customJavascript);
      $this->Smarty->assign('template_output', $template_output);
      $this->Smarty->display('core/templates/design.tpl');
    } else{
      $this->Smarty->display($action_tpl_path);
      echo $customJavascript;
    }
    ob_end_flush();
  }

  function printVersion($tpl = '', $params = '')
  {
    if(empty($tpl)) {
      $action_tpl_path = Module . '/templates/' . Action . '.tpl';
    } else {
      $action_tpl_path = Module . '/templates/' . $tpl . '.tpl';
    }
    $template_output = $this->Smarty->fetch($action_tpl_path);
    $this->Smarty->assign('template_output', $template_output);
    $this->Smarty->display('core/templates/printable.tpl');
    ob_end_flush();
  }

  function json($object) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($object, JSON_UNESCAPED_UNICODE);
    exit;
  }

  function showmsg($message, $case, $parameter='')
  {
    if(defined('API_ENABLED') && API_ENABLED){
      if(is_array($message)){
        $this->json(array('status' => $case, 'message' => '', 'result' => $message));
      } else {
        $this->json(array('status' => $case, 'message' => $message, 'result' => []));
      }
    } elseif($this->returnnoexit === true) {
      return array('status' => $case, 'message' => $message);
    } elseif($_REQUEST['quick_form'] && !empty($case)){
      echo json_encode(array('status' => 'close-quick-form', 'message' => $message, 'parameter' => $parameter));
    } elseif($_REQUEST['no_header']) {
      echo json_encode(array('status' => $case, 'message' => $message, 'parameter' => $parameter));
    } else {
      $this->Smarty->assign('case', $case);
      $this->Smarty->assign('pagetitle', gettext('رسالة النظام'));
      $this->Smarty->assign('message', $message);
      $this->Smarty->assign('parameter', $parameter);
      $template_output = $this->Smarty->fetch('core/templates/showmsg.tpl');
      $this->Smarty->assign('template_output', $template_output);
      if(OUTPUT_NO_SIDEBAR === true){
        $this->Smarty->display('core/templates/design_blank.tpl');
      } else{
        $this->Smarty->display('core/templates/design.tpl');
      }
    }
    die();
  }

  function redirect($location = '')
  {
    if(defined('API_ENABLED') && API_ENABLED){
      $this->json(array('status' => 1, 'message' => 'successful request', 'result' => []));
    }
    if($_REQUEST['no_header']){
      echo 1;
      die();
    }
    if(empty($location)){
      $location = $_SERVER['HTTP_REFERER'];
    }
    $this->Smarty->assign('pagetitle', 'Redirect');
    $this->Smarty->assign('location', $location);
    $template_output = $this->Smarty->fetch('core/templates/redirect.tpl');
    $this->Smarty->assign('template_output', $template_output);
    if(OUTPUT_NO_SIDEBAR === true){
      $this->Smarty->display('core/templates/design_blank.tpl');
    } else{
      $this->Smarty->display('core/templates/design.tpl');
    }
    die();
  }

  function save_setting($setting, $value)
  {
    $this->db->query("UPDATE `setting` SET `value`='$value' WHERE `varname`='$setting'");
  }

  function add_setting($setting, $value)
  {
    $this->db->insert('setting', array('value' => $value, 'varname' => $setting));
  }

  function vgettext($text)
  {
    return $text;
  }

  function checkExist($inputs)
  {
    foreach($inputs as $input){
      if(empty($_REQUEST[$input])){
        switch($input){
          case 'username':
            $this->showmsg(gettext('حقل اسم المستخدم الزامي'), 0);
            break;
          case 'email':
            $this->showmsg(gettext('حقل البريد الإلكتروني الزامي'), 0);
            break;
          case 'password':
            $this->showmsg(gettext('حقل كلمة المرور الزامي'), 0);
            break;
          default:
            $this->showmsg(gettext('من فضلك ادخل جميع الحقول المطلوبة'), 0);
            break;
        }

      }
    }
  }

  function optional($object)
  {
    if(empty($object)){
      return [];
    } else {
      return $object;
    }
  }

  function removeSelectStat($sql, $replace)
  {
    return preg_replace('/(?<=SELECT)(.*)(?=FROM)/i', ' '.$replace.' ', $sql);
  }

  function Paging($sql, $limit=false)
  {
    if(defined('DISABLE_PAGING') && DISABLE_PAGING){
      return $sql;
    }
    if(isset($_GET['printotf']) && $_GET['printotf'] == 'all'){
      return $sql;
    }
    if(defined('PAGING_LIMIT')){
      $limit = PAGING_LIMIT;
      if(!empty($_REQUEST['homepage'])) $currentpage = $_REQUEST['homepage']; else $currentpage = 1;
    } else{
      if(isset($_REQUEST['perpage'])) $limit = $_REQUEST['perpage'];
      elseif(! $limit) $limit = 50;
      if(isset($_REQUEST['callpage'])) $currentpage = $_REQUEST['callpage']; else $currentpage = 1;
    }

    if(preg_match('/UNION ALL/i', $sql, $match)){
      $this->db->get_results($sql);
      $count = $this->db->num_rows;
    } else{
      if(preg_match('/group by ([a-zA-Z0-9`*(),._\n\r]+)\s?/i', $sql, $match)){
        $cselect = 'DISTINCT(' . $match[1] . ')';
        $countsql = preg_replace('/group by ([a-zA-Z0-9`*(),._\n\r\s]+)\s?/i', '', $sql);
      } else{
        $cselect = '*';
        $countsql = $sql;
      }
      $countsql = preg_replace('/select ([a-zA-Z0-9`*(),.\'_\n\r\s]+) from/i', 'SELECT COUNT(' . $cselect . ') FROM', $countsql);
      $count = $this->db->get_var($countsql);
    }
    $pages = $count / $limit;
    $pages = ceil($pages);

    if($currentpage < $pages) $this->paging['stillmore'] = 1; else{
      $currentpage = $pages;
      $this->paging['stillmore'] = 0;
    }
    if($currentpage == 1){
      $this->paging['previous'] = 0;
      $this->paging['next'] = $currentpage + 1;
    } elseif($currentpage == $pages){
      $this->paging['previous'] = $currentpage - 1;
      $this->paging['next'] = 0;
    } else{
      $this->paging['previous'] = $currentpage - 1;
      $this->paging['next'] = $currentpage + 1;
    }
    $this->paging['start'] = $currentpage - 4;
    if($this->paging['start'] < 1){
      $this->paging['start'] = 0;
    }
    $this->paging['result'] = $count;
    $this->paging['pages'] = $pages;
    $this->paging['perpage'] = $limit;
    $this->paging['callpage'] = $currentpage;
    if(defined('PAGING_LIMIT')){
      if(preg_match('/\/page([0-9]+)\/?/', $_SERVER['REQUEST_URI'], $matches)){
        $this->paging['pageurl'] = preg_replace('/\/page([0-9]+)\/?/', '/page{pagenum}/', $_SERVER['REQUEST_URI']);
      } else{
        $this->paging['pageurl'] = rtrim($_SERVER['REQUEST_URI'], '/');
        if(strpos($this->paging['pageurl'], '?')){
          $this->paging['pageurl'] = str_replace('?', 'page{pagenum}/?', $this->paging['pageurl']);
        } else{
          $this->paging['pageurl'] .= '/page{pagenum}/';
        }
      }
    } else{
      $this->paging['pageurl'] = preg_replace('/(\?|&)callpage=([0-9]+)/', '', $_SERVER['REQUEST_URI']);
      if(strpos($this->paging['pageurl'], '?')){
        $this->paging['pageurl'] .= '&callpage=';
      } else{
        $this->paging['pageurl'] .= '?callpage=';
      }
    }
    $http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
    $this->paging['pageurl'] = $http . $_SERVER['HTTP_HOST'] . $this->paging['pageurl'];

    $this->Smarty->assign('paging', $this->paging);

    if($currentpage > 0) $currentpage--;
    $from = $currentpage * $limit;
    if($count == 0) return $sql;
    return $sql . " LIMIT $from,$limit";
  }

  public function salt($lenght)
  {
    $salt = '';
    $alph = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'A', 'a', 'B', 'b', 'C', 'c', 'D', 'd', 'E', 'e', 'F', 'f', 'G', 'g', 'H', 'h', 'I', 'i', 'J', 'K', 'k', 'L', 'l', 'M', 'm', 'N', 'n', 'O', 'o', 'P', 'p', 'Q', 'q', 'R', 'r', 'S', 's', 'T', 't', 'U', 'u', 'V', 'v', 'W', 'w', 'X', 'x', 'Y', 'y', 'Z', 'z');
    for($i = 0; $i < $lenght; $i++){
      $randc = rand(0, count($alph)-1);
      $salt .= $alph[$randc];
    }
    return $salt;
  }

  public function AlphaSalt($lenght)
  {
    $salt = '';
    $alph = array('A', 'a', 'B', 'b', 'C', 'c', 'D', 'd', 'E', 'e', 'F', 'f', 'G', 'g', 'H', 'h', 'I', 'i', 'J', 'K', 'k', 'L', 'l', 'M', 'm', 'N', 'n', 'O', 'o', 'P', 'p', 'Q', 'q', 'R', 'r', 'S', 's', 'T', 't', 'U', 'u', 'V', 'v', 'W', 'w', 'X', 'x', 'Y', 'y', 'Z', 'z');
    for($i = 0; $i < $lenght; $i++){
      $randc = rand(0, count($alph)-1);
      $salt .= $alph[$randc];
    }
    return $salt;
  }

  public function numSalt($lenght)
  {
    $salt = '';
    $alph = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
    for($i = 0; $i < $lenght; $i++){
      $randc = rand(0, count($alph)-1);
      $salt .= $alph[$randc];
    }
    return $salt;
  }

  public function string_cut($string, $num)
  {
    $word = '';
    $explode = preg_split(' ', $string);
    for($i = 0; $i < $num; $i++){
      $word .= $explode[$i] . ' ';
    }
    return $word;
  }

  public function clearSlashes($string)
  {
    return stripslashes(htmlspecialchars_decode($string));
  }

  public function IsEmail($email)
  {
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
      return false;
    }
    return true;
  }

  public function auto_links_con($text)
  {
    $text = str_replace("http://www.", "www.", $text);
    $text = str_replace("www.", "http://www.", $text);
    $text = preg_replace("/([\w]+:\/\/[\w-?&;#~=\.\/\@]+[\w\/])/i", "<a target=\"_blank\" href=\"$0\">$0</a>", $text);
    $text = preg_replace("/([\w-?&;#~=\.\/]+\@(\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,3}|[0-9]{1,3})(\]?))/i", "<a href=\"mailto:$0\">$0</a>", $text);
    return $text;
  }

  public function queryBuilder($query, $args)
  {
    if(!empty($args['inner'])){
      $inner = implode(' ', $args['inner']);
      $query .= $inner;
    }
    if(!empty($args['where'])){
      $where = ' WHERE ' . implode(' AND ', $args['where']);
      $query .= $where;
    }
    if(!empty($args['group'])){
      $query .= ' GROUP BY ' . $args['group'];
    }
    if(!empty($args['order'])){
      $query .= ' ORDER BY ' . $args['order'];
    }
    if($args['limit'] > 0){
      $query .= ' LIMIT 0,' . $args['limit'];
    } elseif($args['limit'] !== false){
      $query = $this->Paging($query);
    }
    return $query;
  }

  public function readlocalfile($path)
  {
    $content = '';
    if(function_exists('file_get_contents')){
      $content = file_get_contents($path);
    } elseif(function_exists('fopen') && function_exists('stream_get_contents')){
      $handler = fopen($path, 'rb');
      $content = stream_get_contents($handler);
      fclose($handler);
    } elseif(function_exists('readfile')){
      $content = readfile($path);
    } else{
      error_log('Server closes all remote reading functions fopen(), readfile(), file_get_contents() and CURL library !');
      $this->showmsg('Server closes all remote reading functions fopen(), readfile(), file_get_contents() and CURL library !', 0);
    }
    return $content;
  }

  public function storelocalfile($path, $contents)
  {
    if(function_exists('fopen')){
      $handle = fopen($path, 'w');
      fwrite($handle, $contents);
      fclose($handle);
    } elseif(function_exists('file_put_contents')){
      file_put_contents($path, $contents);
    } else{
      error_log('Server closes all saving functions fopen(), file_put_contents() !');
      $this->showmsg('Server closes all saving functions fopen(), file_put_contents() !', 0);
    }
  }

  public function deleteDir($dirpath)
  {
    if(!file_exists($dirpath)){
      return;
    }
    $it = new RecursiveDirectoryIterator($dirpath, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file){
      if($file->isDir()){
        @rmdir($file->getRealPath());
      } else{
        @unlink($file->getRealPath());
      }
    }
    rmdir($dirpath);
  }

  function getDirectorySize($path){
    $bytestotal = 0;
    $path = realpath($path);
    if($path!==false && $path!='' && file_exists($path)){
      foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object){
        $bytestotal += $object->getSize();
      }
    }
    return round($bytestotal/1000000, 1);
  }

  public function curl($url, $method = 'get', $params = 0)
  {
    if(function_exists('curl_init')){
      $ch = curl_init();
      if($method == 'post'){
        curl_setopt($ch, CURLOPT_POST, true);
        if(!empty($params)){
          curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }
      } elseif(!empty($params)){
        if(strpos($url, '?')){
          $url .= '&' . http_build_execute($params);
        } else{
          $url .= '?' . http_build_execute($params);
        }
      }
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.6 (KHTML, like Gecko) Chrome/16.0.897.0 Safari/535.6');
      curl_setopt($ch, CURLOPT_REFERER, 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
      curl_setopt($ch, CURLOPT_TIMEOUT, 40);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_HEADER, FALSE);
      $result = curl_exec($ch);
      $this->curl_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      return $result;
    } else{
      $this->readlocalfile($url);
    }
  }

  public function readHeaderValue($headerParam)
  {
    $header_value = '';
    if(!function_exists('apache_request_headers') && !function_exists('getallheaders')){
      function apache_request_headers()
      {
        $arh = array();
        $rx_http = '/\AHTTP_/';
        foreach($_SERVER as $key => $val){
          if(preg_match($rx_http, $key)){
            $arh_key = preg_replace($rx_http, '', $key);
            $rx_matches = array();
            $rx_matches = explode('_', $arh_key);
            if(count($rx_matches) > 0 and strlen($arh_key) > 2){
              foreach($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst($ak_val);
              $arh_key = implode('-', $rx_matches);
            }
            $arh[$arh_key] = $val;
          }
        }
        return $arh;
      }
    }
    if(function_exists('getallheaders')){
      foreach(getallheaders() as $name => $value){
        if(strtolower($name) == strtolower($headerParam)){
          $header_value = $value;
        }
      }
    } elseif(function_exists('apache_request_headers')){
      foreach(apache_request_headers() as $name => $value){
        if(strtolower($name) == strtolower($headerParam)){
          $header_value = $value;
        }
      }
    }
    if(empty($header_value) && !empty($_REQUEST[$headerParam])){
      return $_REQUEST[$headerParam];
    }
    return $header_value;
  }

  public function processDoc($replacements, $templateid, $blockReplaces = false)
  {
    $daynum = date('w');

    $template = $this->auto_load_read($templateid, 'sample');
    $template = str_replace('{$}', $this->config['currency'], $template);
    $template = str_replace('{org_arname}', $this->config['sitename'], $template);
    $template = str_replace('{org_enname}', $this->config['en_sitename'], $template);
    $template = str_replace('{org_logo}', MEDIAURL . '/' . $this->config['logo'], $template);
    $template = str_replace('{org_city}', $this->config['address1'], $template);
    $template = str_replace('{org_address}', $this->config['address2'], $template);
    $template = str_replace('{org_mobile}', $this->config['mobile'], $template);
    $template = str_replace('{org_phone}', $this->config['phone'], $template);
    $template = str_replace('{org_fax}', $this->config['fax'], $template);
    $template = str_replace('{org_email}', $this->config['email'], $template);
    $template = str_replace('{today}', uCal::$l['ar'][$daynum], $template);
    $template = str_replace('{today_date}', date('d-m-Y'), $template);
    if(!empty($replacements)){
      if(!empty($replacements['number'])){
        $replacements['serial'] = $replacements['number'];
        $replacements['number'] = gettext('رقــم المستند').': ' . $replacements['number'];
      } else{
        $replacements['number'] = '';
      }
      if(!empty($replacements['date'])){
        $replacements['doc_date'] = gettext('تاريخ المستند').': ' . $replacements['date'];
      } else {
        $replacements['doc_date'] = '';
      }
      foreach($replacements as $search => $replace){
        if(strpos($search, '*') !== false) {
          preg_match_all('/'.str_replace('*', '([A-Za-z0-9]+)', $search).'/', $template, $matches);
          foreach($matches[0] as $key => $match) {
            $replaceValue = (empty($replace[$matches[1][$key]])) ? '' : $replace[$matches[1][$key]];
            $template = str_replace('{' . $match . '}', $replaceValue, $template);
          }
        } else {
          $template = str_replace('{' . $search . '}', $replace, $template);
        }
      }
    }
    if(!empty($blockReplaces)){
      foreach($blockReplaces as $search => $replace){
        $result = preg_replace('/<!--{' . $search . '}-->(.*)<!--{' . $search . '}-->/is', $replace, $template);
        if(!is_null($result)){
          $template = $result;
        }
      }
    }
    $template = '<html><head><style>td{max-height:35px}@media print {.pagebreak { page-break-before: always; }}</style></head><body dir="rtl" lang="ar-SA">' . $template . '</body></html>';
    echo $template;
    exit;
    $randFileName = TEMP_DIR . '/' . md5(uniqid() . time() . '_' . $_SESSION['user_id']);
    $this->storelocalfile($randFileName, $template);
    exec('prince --page-size=A3 --page-margin=0 "' . $randFileName . '" -o "' . $randFileName . '.pdf"');
    //exec('"C:\Program Files (x86)\LibreOffice 5\program\soffice.exe" --headless --norestore --writer --convert-to pdf --outdir '.TEMP_DIR.' "'.$randFileName.'"');
    header('Content-type: application/pdf');
    echo $this->readlocalfile($randFileName . '.pdf');
    @unlink($randFileName);
    @unlink($randFileName . '.pdf');
  }

  public function turnTempArabic()
  {
    define('NATVIE_LANGUAGE', true);
    textdomain('ar');
  }

  public function sendNotify($action, $args, $payload = array())
  {
    define('NOTIFICATION_ENABLED', true);
    include_once(DIR_MOD . '/notification/notification.model.php');
    include_once(DIR_MOD . '/notification/notification.controller.php');
    $notify = new notification($args, $payload);
    $notify->$action();
  }

  function getUserIP()
  {
    if(DEBUG_MODE === true || DEBUG_MODE == 'localhost'){
      return '24.48.0.1';
    }
    // Get real visitor IP behind CloudFlare network
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
      $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
      $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    $client  = @$_SERVER['HTTP_CLIENT_IP'];
    $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
    $remote  = $_SERVER['REMOTE_ADDR'];

    if(filter_var($client, FILTER_VALIDATE_IP)){
      $ip = $client;
    } elseif(filter_var($forward, FILTER_VALIDATE_IP)){
      $ip = $forward;
    } else{
      $ip = $remote;
    }

    return $ip;
  }

  public function encodeHash($params)
  {
    return urlencode(openssl_encrypt(($params), 'aes128', SECRET_KEY, false, substr(md5(SECRET_KEY), 0, 16)));
  }

  public function decodeHash($secret)
  {
    return openssl_decrypt($secret, 'aes128', SECRET_KEY, false, substr(md5(SECRET_KEY), 0, 16));
  }

  public function fixMobileNumber($number)
  {
    $number = preg_replace('/^0/', '', $number);
    if(!empty($this->config['phone_code']) && ! preg_match('/^'.$this->config['phone_code'].'/', $number)){
      $number = $this->config['phone_code'] . $number;
    }
    return $number;
  }

  public function sendSMS($to, $message)
  {
    $to = $this->fixMobileNumber($to);
    if(DEBUG_MODE){
      $this->log('SMS to '.$to.' text: '.$message);
    }
    if(DEBUG_MODE == 'localhost'){
      return true;
    }
    if(empty($this->config['sms_sender']) || empty($this->config['sms_user']) || empty($this->config['sms_pass'])){
      return false;
    }
    include_once(LIB_DIR . '/class.sms.php');
    $platformSettings = array('provider' => $this->config['sms_provider'], 'api_key' => $this->config['sms_user'], 'secret_key' => $this->config['sms_pass'], 'sender' => $this->config['sms_sender']);
    sms::send($to, $message, $platformSettings);
  }

  public function getBalanceSMS()
  {
    include_once(LIB_DIR . '/class.sms.php');
    include_once(LIB_DIR . '/class.sms_balance.php');
    $platformSettings = array('provider' => $this->config['sms_provider'], 'api_key' => $this->config['sms_user'], 'secret_key' => $this->config['sms_pass'], 'sender' => $this->config['sms_sender']);
    $response = sms_balance::check($platformSettings);
    if($response === false){
      return gettext('فشل في الربط مع مزود الخدمة');
    } elseif($response === true){
      return gettext('تم الأتصال بنجاح');
    } else{
      return $response;
    }
  }

  public function sendEmail($to, $subject, $message, $from = '', $attach = false)
  {
    include_once(LIB_DIR . '/class.phpmailer.php');
    $sname = "=?UTF-8?B?" . base64_encode($this->config['sitename']) . "?=\n";
    $smail = (empty($from)) ? $this->config['email'] : $from;
    $rname = "=?UTF-8?B?" . base64_encode($to) . "?=\n";
    $rmail = $to;
    $subject = "=?UTF-8?B?" . base64_encode($subject) . "?=\n";
    $body = htmlspecialchars_decode($message);
    $mail = new PHPMailer();
    if($this->config['mail_sendtype'] == 'smtp'){
      include_once(LIB_DIR . '/class.smtp.php');
      $mail->IsSMTP();
      $mail->Host = $this->config['smtp_server'];
      $mail->SMTPAuth = true;
      $mail->Port = $this->config['smtp_port'];
      $mail->Username = $this->config['smtp_username'];
      $mail->Password = $this->config['smtp_password'];
    } else{
      $mail->IsMail();
    }
    $mail->AddReplyTo($smail, $sname);
    $mail->AddAddress($rmail, $rname);
    $mail->From = $smail;
    $mail->FromName = $sname;
    $mail->Subject = $subject;
    $mail->MsgHTML($body);
    if($attach !== false){
      $mail->AddAttachment($attach);
    }
    $mail->IsHTML(true);
    if(DEBUG_MODE == 'localhost'){
      //$this->log($body);
      return true;
    }
    $mail->Send();
  }

  public function cleanArray($array, $checkindex = false)
  {
    if($checkindex !== false){
      if(empty($array[$checkindex])){
        return $array;
      }
      $arrKeys = array_keys($array);
      foreach($array[$checkindex] as $key => $value){
        if(empty($array[$checkindex][$key])){
          foreach($arrKeys as $arrKey){
            unset($array[$arrKey][$key]);
          }
        }
      }
      foreach($arrKeys as $arrKey){
        $array[$arrKey] = array_values($array[$arrKey]);
      }
    } elseif(!empty($array)){
      foreach($array as $key => $value){
        if(empty($array[$key])){
          unset($array[$key]);
        }
      }
      $array = array_values($array);
    }
    return $array;
  }

  public function ProtectFlood($action, $duration)
  {
    if(isset ($_SESSION['lastdo'][$action])){
      $now = time();
      $diff = $_SESSION['lastdo'][$action] - $now;
      if($now < $_SESSION['lastdo'][$action]){
        $text = 'You can not do a new action unless after ' . date('s', $diff) . ' sec';
        echo "<script>alert('$text');</script>";
        exit;
      } else{
        $_SESSION['lastdo'][$action] = time() + $duration;
      }
    } else{
      $_SESSION['lastdo'][$action] = time() + $duration;
    }
  }

  function jsredirect($redirect)
  {
    echo '<script>window.location="' . $redirect . '"</script>';
  }

  function printImage($filePath)
  {
    $contents = $this->readlocalfile($filePath);

    $expires = 14*24*60*60;
    header("Content-Type: " . mime_content_type($filePath));
    header("Content-Length: " . strlen($contents));
    header("Cache-Control: public");
    header("Pragma: public");
    header('Expires: ' . gmdate('D, d M Y H:i:s', TIMENOW + $expires) . ' GMT');

    echo $contents;
    exit;
  }

  function MultiUpload($name, $folder = '', $type = '')
  {
    $uploads = array();
    for($i = 0; $i < count($_FILES[$name]['tmp_name']); $i++){
      if(!empty($_FILES[$name]['tmp_name'][$i])){
        $file = array('name' => $_FILES[$name]['name'][$i], 'size' => $_FILES[$name]['size'][$i], 'type' => $_FILES[$name]['size'][$i], 'tmp_name' => $_FILES[$name]['tmp_name'][$i]);
        $upload = $this->Upload_File($file, $folder, $type, false);
        if($upload !== false){
          $uploads[] = $upload;
        }
      }
    }
    return $uploads;
  }

  function Upload_File($FILES, $folder = '', $type = 'image', $showerror = true, $testonly = false, $index = false)
  {
    if(isset($this->config['updatecore']['plan']['filesize_quota']) && $this->config['total_file_size'] > $this->config['updatecore']['plan']['filesize_quota']) {
      $this->showmsg(gettext('تجاوزت الحد الأقصى من مساحة الملفات التي تسمح بها خطتك.'), 0);
    }
    if($index === false){
      $file['name'] = strtolower($FILES['name']);
      $file['size'] = $FILES['size'] / 1000000;
      $file['type'] = $FILES['type'];
      $tmpName = $FILES['tmp_name'];
    } else{
      $file['name'] = strtolower($FILES['name'][$index]);
      $file['size'] = $FILES['size'][$index] / 1000000;
      $file['type'] = $FILES['type'][$index];
      $tmpName = $FILES['tmp_name'][$index];
    }
    $ext = strtolower(substr($file['name'], strrpos($file['name'], '.') + 1));
    $valid = 1;
    if($type == 'image'){
      $max_size = 10;
      $valid_exe = array('jpg', 'jpeg', 'png', 'gif', 'tiff', 'ico');
    } elseif($type == 'cert'){
      $max_size = 10;
      $valid_exe = array('cert', 'pem', 'ca', 'pfx', 'cer', 'p12', 'csr');
    } elseif($type == 'video'){
      $max_size = 50;
      $valid_exe = array('avi', 'mpeg', 'flv', '3gp', 'mpg', 'mp4', 'caf', 'mov');
    } elseif($type == 'all'){
      $max_size = 20;
      $valid_exe = array('jpg', 'jpeg', 'png', 'ico', 'gif', 'tiff', 'doc', 'docx', 'pdf', 'zip', 'txt', 'avi', 'mpeg', 'flv', '3gp', 'mpg', 'mp4', 'caf', 'mov', 'csv', 'vcf', 'mo');
    } elseif(is_array($type)){
      $max_size = 20;
      $valid_exe = $type;
    } else {
      $this->showmsg('you passed a wrong parameter type to function Upload_File()', 0);
    }
    if(!in_array($ext, $valid_exe)){
      $valid = 0;
      $error = gettext('امتداد الملف').' ' . $file['name'] . ' '.gettext('غير مسموح به');
    }
    if($file['size'] > $max_size){
      $valid = 0;
      $error = gettext('الملف').' ' . $file['name'] . ' '.gettext('تعدى الحجم المسموح به');
    }
    if($valid == 1 && $testonly){
      return true;
    } elseif($valid == 1){
      if(!file_exists(ROOT_DIR . '/' . UPLOAD_DIR . '/' . $folder)){
        mkdir(ROOT_DIR . '/' . UPLOAD_DIR . '/' . $folder, 0777, true);
      }
      $secname = $folder . '/' . md5(rand(10000, 50000) . time()) . '.' . $ext;
      $target_path = ROOT_DIR . '/' . UPLOAD_DIR . '/' . $secname;
      move_uploaded_file($tmpName, $target_path);
      if(file_exists($target_path)){
        return $secname;
      } else{
        if($showerror){
          $this->showmsg('unknow error while uploading proccess. please try again later', 0);
        } else{
          return false;
        }
      }
    } else{
      if($showerror){
        $this->showmsg($error, 0);
      } else{
        return false;
      }
    }
  }

  function silentRequest($url)
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_exec($ch);
    curl_close($ch);
  }

  function SEO($string)
  {
    $code_entities_match = array('-', '--', '&quot;', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '_', '+', '{', '}', '|', ':', '"', '<', '>', '?', '[', ']', '\\', ';', "'", ',', '.', '/', '*', '+', '~', '`', '=');
    return preg_replace('/\s+/', '_', str_replace($code_entities_match, ' ', mb_strtolower($string, 'UTF-8')));
  }

  function SecureInputs($value)
  {
    if(!is_numeric($value)){
      if(is_array($value)){
        foreach($value AS $key => $v){
          if(is_array($v)) $value[$key] = $this->SecureInputs($v); else{
            $value[$key] = htmlspecialchars(trim($v), ENT_QUOTES);
          }
        }
      } else{
        $value = htmlspecialchars(trim($value), ENT_QUOTES);
      }
    }
    return $value;
  }

  function resetCache()
  {
    $this->delDir(CACHE_DIR, false);
  }

  function delDir($dir, $filesonly = false)
  {
    $structure = glob(rtrim($dir, "/") . '/*');
    if(is_array($structure)){
      foreach($structure as $file){
        if(is_dir($file)){
          $this->delDir($file);
        } elseif(is_file($file)){
          @unlink($file);
        }
      }
    }
    if($filesonly === false){
      @rmdir($dir);
    }
  }

}
