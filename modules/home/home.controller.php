<?php

class home extends home_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function resize($width, $height, $folder, $file = '')
  {
    if(empty($file)){
      $file = urldecode($folder);
    } else{
      $file = urldecode($folder . '/' . $file);
    }
    $thumb_filepath = '/' . UPLOAD_DIR . '/thumbcache/' . $width . '_' . $height . '_' . preg_replace('/[(a-zA-Z)]\//', '', $file);
    $_filepath = ROOT_DIR . '/' . UPLOAD_DIR . '/' . $file;
    if(file_exists(ROOT_DIR . '/' . $thumb_filepath)){
      $this->printImage(ROOT_DIR . '/' . $thumb_filepath);
    } elseif(file_exists($_filepath)){
      include_once(LIB_DIR . '/PhpThumb/ThumbLib.inc.php');
      $thumb = PhpThumbFactory::create($_filepath);
      $thumb->resize($width, $height)->save(ROOT_DIR . '/' . $thumb_filepath);
      $this->printImage(ROOT_DIR . '/' . $thumb_filepath);
    } else{
      die('file not found');
    }
  }

  public function removefile()
  {
    $this->delFileUploader($_GET['file']);
    $this->redirect();
  }

  public function delattach()
  {
    if($_GET['id']){
      $this->deleteAttachment($_GET['id']);
      $this->redirect();
    }
  }

  public function attach()
  {
    if(!empty($_FILES['file']['tmp_name'])){
      $file = $this->Upload_File($_FILES['file'], 'files', 'all');
      $fileid = $this->addAttachment($file, $_POST['title'], $_POST['tempid']);
      $html = '<li><i class="trashbtn fa fa-trash-o pull-right delete-ajax" href="' . CPURL . '/home/delattach?id=' . $fileid . '" data-parent="li"></i><a href="' . MEDIAURL . '/' . $file . '" target="_blank"><i class="fa fa-file-o"></i> ' . $_POST['title'] . ' <span>' . DATENOW . '</span></a></li>';
      $this->showmsg($html, 'uploadedfile');
    } else{
      $this->Output();
    }
  }

  public function smsbalance()
  {
    echo $this->getBalanceSMS();
  }

  public function upload()
  {
    if(!empty($_FILES['file']['tmp_name'])){
      $file = $this->Upload_File($_FILES['file'], 'files', 'all');
      $fildID = $this->addFileUploader($file, $_FILES['file']['name']);
      echo BASEURL . '/' . UPLOAD_DIR . '/' . $file;
    } else {
      $mediafiles = array();
      $storeFolder = ROOT_DIR . '/' . UPLOAD_DIR . '/';
      $files = $this->getUploaderFiles();
      if($files){
        foreach($files as $file){
          $obj['name'] = $file['name'];
          $obj['path'] = BASEURL . '/' . UPLOAD_DIR . '/' . $file['path'];
          $obj['size'] = filesize($storeFolder . $file['path']);
          $mediafiles[] = $obj;
        }
      }
      $this->enqueueCSS('dropzone');
      $this->enqueueJS('dropzone.min');
      $this->Output('', array('mediafiles' => $mediafiles));
    }
  }

  public function owners()
  {
    if($_POST){
      if(empty($_POST['fname'])){
        $this->showmsg(gettext('لا يمكن حفظ البيانات بدون إضافة بيانات لأي مالك'), 0);
      }
      foreach($_POST['fname'] as $key => $value){
        if(empty($_FILES['idcopy']['tmp_name'][$key])){
          continue;
        }
        $this->Upload_File($_FILES['idcopy'], 'company', 'image', true, true, $key);
      }
      $this->resetOwners();
      foreach($_POST['fname'] as $key => $value){
        if(empty($_POST['fname'][$key])){
          continue;
        }
        $data = array();
        $data['fname'] = $_POST['fname'][$key];
        $data['lname'] = $_POST['lname'][$key];
        $data['fathname'] = $_POST['fathname'][$key];
        $data['famname'] = $_POST['famname'][$key];
        $data['email'] = $_POST['email'][$key];
        $data['address'] = $_POST['address'][$key];
        $data['nationality'] = $_POST['nationality'][$key];
        $data['idtype'] = $_POST['idtype'][$key];
        $data['idsource'] = $_POST['idsource'][$key];
        if(empty($_FILES['idcopy']['tmp_name'][$key])){
          $data['idcopy'] = $_POST['idcopy'][$key];
        } else{
          $data['idcopy'] = $this->Upload_File($_FILES['idcopy'], 'company', 'image', false, false, $key);
        }
        $data['work'] = $_POST['work'][$key];
        $data['homephone'] = $_POST['homephone'][$key];
        $data['mobile'] = $_POST['mobile'][$key];
        $data['workphone'] = $_POST['workphone'][$key];
        $data['city'] = $_POST['city'][$key];
        $data['share'] = $_POST['share'][$key];
        $data['postal'] = $_POST['postal'][$key];
        $data['zipcode'] = $_POST['zipcode'][$key];
        $this->updateOwners($data);
      }
      $this->registerLog(0);
      $this->showmsg(CPURL . '/' . Module . '/' . Action, 1);
    } else{
      $this->loadNationalities();
      $this->Smarty->assign('data', $this->readOwners());
      $this->Smarty->assign('cities', $this->auto_load_list('city'));
      $this->Output();
    }
  }

  public function serial()
  {
    $tables = array('buyer', 'owner', 'building', 'sell_contracts', 'rent_contracts', 'statement_in', 'statement_out', 'letters', 'invoices');
    if($_POST){
      foreach($tables as $table){
        $currentIncreament = $this->db->get_var("SELECT `AUTO_INCREMENT`
        FROM  INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND   TABLE_NAME   = '$table';");
        if($currentIncreament > $_POST['serial'][$table]){
          $this->showmsg(gettext('لا يمكن تعيين احد اعدادات الترقيم برقم اقل من الحالي'), 0);
        }
        if($_POST['serial'][$table] > $currentIncreament){
          $this->db->query("ALTER TABLE `$table` AUTO_INCREMENT = " . $_POST['serial'][$table]);
        }
      }
      $this->showmsg(CPURL . '/' . Module . '/' . Action, 1);
    } else{
      $serials = array();
      foreach($tables as $table){
        $serials[$table] = $this->db->get_var("SELECT `AUTO_INCREMENT`
        FROM  INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND   TABLE_NAME   = '$table';");
      }
      $this->Smarty->assign('serial', $serials);
      $this->Output();
    }
  }

  public function settings()
  {
    if($_POST){
      $data = array();
      $reload = false;
      foreach($_POST as $key => $value){
        if($key == 'billing_info'){
          $data[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
          $data[$key] = $value;
        }
      }
      if(!empty($_FILES['favicon']['tmp_name'])){
        $reload = true;
        $data['favicon'] = $this->Upload_File($_FILES['favicon'], 'settings', 'image');
      } else{
        unset($data['favicon']);
      }
      if(!empty($_FILES['logo']['tmp_name'])){
        $reload = true;
        $data['logo'] = $this->Upload_File($_FILES['logo'], 'settings', 'image');
      } else{
        unset($data['logo']);
      }
      if(!empty($_FILES['lang_file_ar']['tmp_name'])){
        $reload = true;
        $data['lang_file_ar'] = $this->Upload_File($_FILES['lang_file_ar'], 'settings', 'all');
      } else{
        unset($data['lang_file_ar']);
      }
      if(!empty($_FILES['lang_file_en']['tmp_name'])){
        $reload = true;
        $data['lang_file_en'] = $this->Upload_File($_FILES['lang_file_en'], 'settings', 'all');
      } else{
        unset($data['lang_file_en']);
      }
      unset($data['no_header']);
      $this->updateSetting($data);
      $this->showmsg(CPURL . '/' . Module . '/' . Action, 1);
    } else{
      $this->loadNationalities();
      $this->Output();
    }
  }

  public function index()
  {
    $this->Output();
  }

}
