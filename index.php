<?php

require('include/config.php');

const FRONT_DIR_MOD = ROOT_DIR . '/frontend';

const ADMIN_DIR_PATH = 'adminarea';
const OWNER_DIR_PATH = 'ownerarea';
const CLIENT_DIR_PATH = 'clientarea';

session_name(COOKIEPREFIX);
session_start();

if (!empty($_REQUEST['route'])) {
  $_REQUEST['route'] = trim($_REQUEST['route'], '/');
} elseif (!empty($argv[1])) {
  $_REQUEST['route'] = $argv[1];
}

$route = explode('/', strtolower(str_replace(array('./', '../'), '', $_REQUEST['route'])));
$module = 'dashboard';
$pathmodule = 'dashboard';
$action = 'index';
$namespace = '';

switch ($route[0]) {
  case OWNER_DIR_PATH:
    define('CP_PATH', $route[0]);
    define('GROUP_ID', 2);
    $modFolder = DIR_MOD;
    $namespace = OWNER_DIR_PATH . '\\';
    break;
  case CLIENT_DIR_PATH:
    define('CP_PATH', $route[0]);
    define('GROUP_ID', 3);
    $modFolder = DIR_MOD;
    $namespace = CLIENT_DIR_PATH . '\\';
    break;
  case ADMIN_DIR_PATH:
    define('CP_PATH', $route[0]);
    define('GROUP_ID', 1);
    $modFolder = DIR_MOD;
    break;
  default:
    if(!empty($_SESSION['userid'])) {
      header('Location: ' . ADMIN_DIR_PATH);
    }
    define('CP_PATH', '');
    $modFolder = FRONT_DIR_MOD;
    $module = 'home';
    $pathmodule = 'home';
    $action = 'index';
    $namespace = 'frontend\\';
    array_unshift($route, 'frontend');
    break;
}

if (!empty($route[1])) {
  $module = $route[1];
  $pathmodule = $route[1];
}

if (!empty($_REQUEST['Action'])) {
  $action = $_REQUEST['Action'];
} elseif (!empty($route[2])) {
  $action = $route[2];
}

if ((!empty($route[3]) || (!empty($route[2]) && !empty($_REQUEST['Action']))) && !in_array($action, array('resize', 'recover', 'emailupdate', 'activation'))) {
  $module = $route[2];
  $pathmodule = $route[1] . '/' . $route[2];
  $action = !empty($_REQUEST['Action']) ? $_REQUEST['Action'] : $route[3];
}

define('Module', $pathmodule);
define('Action', $action);

if (file_exists($modFolder . '/' . Module . '/' . $module . '.controller.php')) {
  require(INC_DIR . '/systemcore.function.php');
  require($modFolder . '/core/core.function.php');
  include($modFolder . '/' . Module . '/' . $module . '.model.php');
  include($modFolder . '/' . Module . '/' . $module . '.controller.php');

  if(class_exists($namespace.$module)) {
    $controller = $namespace.$module;
  } else {
    $controller = $module;
  }
  $controller = new $controller();
  $method = Action;

  if (method_exists($controller, $method)) {
    $_REQUEST = array_map(array($controller, 'SecureInputs'), $_REQUEST);
    $_POST = array_map(array($controller, 'SecureInputs'), $_POST);
    $_GET = array_map(array($controller, 'SecureInputs'), $_GET);
    if (in_array($method, array('resize', 'recover', 'emailupdate', 'activation'))) {
      unset($route[0]);
      unset($route[1]);
      unset($route[2]);
      call_user_func_array(array($controller, $method), $route);
    } else {
      $controller->$method();
    }
  } else {
    die('Page is not found');
  }
} else {
  die('Page is not found');
}

function dd($object) {
  if(is_array($object) || is_object($object)) {
    header('Content-Type: application/json');
    echo json_encode($object);
  } else {
    echo $object;
  }
  exit;
}
