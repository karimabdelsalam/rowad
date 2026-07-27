<?php

class locations extends locations_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function delete()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->deleteExec(0, $ids);
      $this->redirect(CPURL . '/' . Module . '/index/');
    } else{
      $this->deleteExec($_GET['id']);
    }
  }

  public function search()
  {
    if(strlen(utf8_decode($_GET['q'])) < 1){
      //return false;
    }
    $results = $this->searchQuery($_GET['q']);
    echo json_encode($results);
  }

  public function report()
  {
    define('OUTPUT_PRINT_VERSION', true);
    $_GET['locid'] = $_GET['id'];
    $builds = $this->auto_load('building');
    $builds->index(false, true);
  }

  public function reportdaily()
  {
    define('OUTPUT_PRINT_VERSION', true);
    $this->Smarty->assign('report_date', date('m Y'));
    $_GET['locid'] = $_GET['id'];
    $builds = $this->auto_load('building');
    $builds->index(false, false);
  }

  public function add()
  {
    if($_POST){
      $data = array();
      $data['location'] = $_POST['location'];
      $data['gps_location'] = json_encode($_POST['gps_location'], JSON_UNESCAPED_UNICODE);
      $data['buildcat'] = $_POST['buildcat'];
      $data['citytid'] = $_POST['citytid'];
      $data['platno'] = $_POST['platno'];
      $data['planno'] = $_POST['planno'];
      $data['buildno'] = $_POST['buildno'];
      $data['size'] = $_POST['size'];
      $data['zone'] = $_POST['zone'];
      $data['street'] = $_POST['street'];
      $data['floors'] = $_POST['floors'];
      $data['district'] = $_POST['district'];
      $data['loc_details'] = $_POST['loc_details'];

      $newid = $this->addrecord($data);
      $this->registerLog($newid);

      $this->moveAttachments($_POST['tempid'], $newid);

      if($_POST['quick_form']){
        $this->showmsg(json_encode(array('id' => $newid, 'title' => $_POST['location'], 'element' => $_POST['elementID'])), 'AutoAdder');
      }
      $this->showmsg(CPURL . '/' . Module . '/index/', 1);
    } else{
      $this->Smarty->assign('buildcats', $this->auto_load_list('buildcats'));
      $this->Smarty->assign('cities', $this->auto_load_list('city'));
      $this->Smarty->assign('districts', $this->auto_load_list('district'));
      $this->Smarty->assign('deedtypes', $this->auto_load_list('deedtypes'));
      $this->enqueuejs('gmap');

      $this->Output('edit', $this->config['gmap_key']);
    }
  }

  public function edit()
  {
    if($_POST){
      $data = array();
      $data['location'] = $_POST['location'];
      $data['gps_location'] = json_encode($_POST['gps_location'], JSON_UNESCAPED_UNICODE);
      $data['buildcat'] = $_POST['buildcat'];
      $data['citytid'] = $_POST['citytid'];
      $data['platno'] = $_POST['platno'];
      $data['planno'] = $_POST['planno'];
      $data['buildno'] = $_POST['buildno'];
      $data['size'] = $_POST['size'];
      $data['zone'] = $_POST['zone'];
      $data['street'] = $_POST['street'];
      $data['floors'] = $_POST['floors'];
      $data['district'] = $_POST['district'];
      $data['loc_details'] = $_POST['loc_details'];

      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      $this->moveAttachments($_POST['tempid'], $_POST['id']);

      $this->showmsg(CPURL . '/' . Module . '/index/', 1);
    } else{
      $this->Smarty->assign('data', $this->readrecord($_GET['id']));
      $this->Smarty->assign('buildcats', $this->auto_load_list('buildcats'));
      $this->Smarty->assign('cities', $this->auto_load_list('city'));
      $this->Smarty->assign('districts', $this->auto_load_list('district'));
      $this->Smarty->assign('deedtypes', $this->auto_load_list('deedtypes'));
      $this->enqueuejs('gmap');

      $this->Output('edit', $this->config['gmap_key']);
    }
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['title']){
      $where[] = "building_locs.location='$_GET[title]'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
