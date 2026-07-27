<?php

namespace ownerarea;

class reports extends \ownerarea\reports_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function view()
  {
    $data = $this->readrecord($_GET['id']);
    header('Location: ' . MEDIAURL . '/' . $data['file']);
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['title']){
      $where[] = "building_reports.title LIKE '%$_GET[title]%'";
    }
    if($_GET['buildid']){
      $where[] = "building_reports.buildid='$_GET[buildid]'";
    }
    if($_GET['catid']){
      $where[] = "building_reports.catid='$_GET[catid]'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Smarty->assign('categories', $this->getCategories());
    $this->Output();
  }

}
