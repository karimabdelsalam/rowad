<?php

class letters extends letters_model
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
      $this->redirect(CPURL . '/' . Module);
    } else{
      $this->deleteExec($_GET['id']);
    }
  }

  public function printable()
  {
    $this->turnTempArabic();
    require LIB_DIR . '/I18N/Arabic.php';
    $Arabic = new I18N_Arabic('Numbers');
    $letter = $this->readrecord($_GET['id']);
    $buyermodule = $this->auto_load('buyer');
    $buildmodule = $this->auto_load('building');
    $rentmodule = $this->auto_load('rent');

    switch($letter['type']){
      case 'official':
        $replaces = array('content' => $letter['official']['content']);
        if($letter['official']['sendto'] == 'person'){
          $replaces['toname'] = $letter['official']['sendto_name'] . ' ' . $letter['official']['sendto_title'];
        } else{
          $replaces['toname'] = gettext('إلى من يهمه الأمر');
        }
        $this->processDoc($replaces, 7);
        break;
      case 'cancel':
        $renter = $buyermodule->readrecord($letter['renterid']);
        $building = $buildmodule->readrecord($letter['buildid']);
        $contract = $rentmodule->readrecord($building['contractid']);
        $replaces = array('renter' => $renter['fullname'], 'nationality' => $renter['national'], 'id_number' => $renter['idnumber'], 'build_type' => $building['buildcat_name'], 'next_payment' => (($contract['calendar'] == 2) ? uCal::g2u($contract['nextpaymentdate']) : $contract['nextpaymentdate']),);
        if($letter['cancel']['sendto'] == 'person'){
          $replaces['toname'] = $letter['cancel']['sendto_desc'] . ': ' . $letter['cancel']['sendto_name'] . ' ' . $letter['cancel']['sendto_title'];
        } else{
          $replaces['toname'] = gettext('إلى من يهمه الأمر');
        }
        $this->processDoc($replaces, 10);
        break;
      case 'rentraise':
        $renter = $buyermodule->readrecord($letter['renterid']);
        $building = $buildmodule->readrecord($letter['buildid']);
        $contract = $rentmodule->readrecord($building['contractid']);
        $replaces = array(
          'date' => (($letter['calendar'] == 2) ? uCal::g2u($letter['postdate']) : $letter['postdate']),
          'renter' => $renter['fullname'],
          'build' => $building['title'],
          'contract_no' => $building['contractid'],
          'new_rent' => $letter['rentraise']['amount'],
          'new_rent_text' => $Arabic->int2str($letter['rentraise']['amount']),
          'end_date' => (($contract['calendar'] == 2) ? uCal::g2u($contract['enddate']) : $contract['enddate']),
          );
        $this->processDoc($replaces, 6);
        break;
      case 'finish':
        $renter = $buyermodule->readrecord($letter['renterid']);
        $building = $buildmodule->readrecord($letter['buildid']);
        $contract = $rentmodule->readrecord($building['contractid']);
        $replaces = array(
          'date' => (($letter['calendar'] == 2) ? uCal::g2u($letter['postdate']) : $letter['postdate']),
          'renter' => $renter['fullname'],
          'build' => $building['title'],
          'contract_no' => $building['contractid'],
          'end_date' => (($contract['calendar'] == 2) ? uCal::g2u($contract['enddate']) : $contract['enddate']),
          );
        $this->processDoc($replaces, 5);
        break;
      case 'review':
        $replaces = array(
          'name' => $letter['review']['provider'],
          'provider' => ($letter['review']['provider_title'] == 'agent') ? gettext('وكيل شرعي لـ') . $letter['review']['agent_name'] : gettext('المالك'),
          'desc' => $letter['review']['about'],
          'city' => $letter['review']['city_name'],
          'plotno' => $letter['review']['plotno'],
          'planno' => $letter['review']['planno'],
          'district' => $letter['review']['district_name'],
          'size' => $letter['review']['size'],
          'amount' => $letter['review']['amount'],
          'amount_text' => $Arabic->int2str($letter['review']['amount']),
          'north_long' => $letter['review']['plot']['north_length'] . ' ' . gettext('م'),
          'south_long' => $letter['review']['plot']['south_length'] . ' ' . gettext('م'),
          'west_long' => $letter['review']['plot']['west_length'] . ' ' . gettext('م'),
          'east_long' => $letter['review']['plot']['east_length'] . ' ' . gettext('م'),
          'north_type' => $this->processDimensions($letter['review']['plot']['north_type'], $letter['review']['plot']['north_width'], $letter['review']['plot']['north_plotno'], true),
          'south_type' => $this->processDimensions($letter['review']['plot']['south_type'], $letter['review']['plot']['south_width'], $letter['review']['plot']['south_plotno'], true),
          'west_type' => $this->processDimensions($letter['review']['plot']['west_type'], $letter['review']['plot']['west_width'], $letter['review']['plot']['west_plotno'], true),
          'east_type' => $this->processDimensions($letter['review']['plot']['east_type'], $letter['review']['plot']['east_width'], $letter['review']['plot']['east_plotno'], true),
          );
        $this->processDoc($replaces, 11);
        break;
    }
  }

  public function add()
  {
    if($_POST){
      $data = array();
      $data['type'] = $_POST['type'];
      $data['renterid'] = $_POST['renterid'];
      $data['buildid'] = $_POST['buildid'];
      $data['calendar'] = $_POST['calendar'];
      $data['postdate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['postdate']) : $_POST['postdate'];
      switch($_POST['type']){
        case 'official':
          $data['official'] = json_encode($_POST['official'], JSON_UNESCAPED_UNICODE);
          $data['cancel'] = $data['rentraise'] = $data['review'] = '';
          $data['buildid'] = $data['renterid'] = 0;
          break;
        case 'cancel':
          $data['cancel'] = json_encode($_POST['cancel'], JSON_UNESCAPED_UNICODE);
          $data['official'] = $data['rentraise'] = $data['review'] = '';
          break;
        case 'rentraise':
          $data['rentraise'] = json_encode($_POST['rentraise'], JSON_UNESCAPED_UNICODE);
          $data['cancel'] = $data['official'] = $data['review'] = '';
          break;
        case 'finish':
          $data['official'] = $data['cancel'] = $data['rentraise'] = $data['review'] = '';
          break;
        case 'review':
          $data['review'] = json_encode($_POST['review'], JSON_UNESCAPED_UNICODE);
          $data['cancel'] = $data['rentraise'] = $data['official'] = '';
          $data['buildid'] = $data['renterid'] = 0;
          break;
      }
      $letterid = $this->addrecord($data);
      $this->registerLog($letterid);

      if($_POST['type'] == 'cancel'){
        $this->sendNotify('finishBuildRenter', array(), array('buildid' => $data['buildid']));
        $this->sendNotify('finishBuildOwner', array(), array('buildid' => $data['buildid']));
        //$this->makeBuildingBusy($data['buildid']);
      }

      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $default = array('official' => array('sendto' => 'random'), 'cancel' => array('sendto' => 'random'), 'review' => array('provider_title' => 'owner'),);
      $this->Smarty->assign('data', $default);
      $this->Smarty->assign('types', $this->readTypes());
      $this->Smarty->assign('cities', $this->auto_load_list('city'));
      $this->Smarty->assign('districts', $this->auto_load_list('district'));
      $this->Smarty->assign('borders', $this->bordersList());
      $this->Output('edit');
    }
  }

  public function edit()
  {
    if($_POST){
      $data = array();
      $data['type'] = $_POST['type'];
      $data['renterid'] = $_POST['renterid'];
      $data['buildid'] = $_POST['buildid'];
      $data['calendar'] = $_POST['calendar'];
      $data['postdate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['postdate']) : $_POST['postdate'];
      switch($_POST['type']){
        case 'official':
          $data['official'] = json_encode($_POST['official'], JSON_UNESCAPED_UNICODE);
          $data['cancel'] = $data['rentraise'] = $data['review'] = '';
          $data['buildid'] = $data['renterid'] = 0;
          break;
        case 'cancel':
          $data['cancel'] = json_encode($_POST['cancel'], JSON_UNESCAPED_UNICODE);
          $data['official'] = $data['rentraise'] = $data['review'] = '';
          break;
        case 'rentraise':
          $data['rentraise'] = json_encode($_POST['rentraise'], JSON_UNESCAPED_UNICODE);
          $data['cancel'] = $data['official'] = $data['review'] = '';
          break;
        case 'finish':
          $data['official'] = $data['cancel'] = $data['rentraise'] = $data['review'] = '';
          break;
        case 'review':
          $data['review'] = json_encode($_POST['review'], JSON_UNESCAPED_UNICODE);
          $data['cancel'] = $data['rentraise'] = $data['official'] = '';
          $data['buildid'] = $data['renterid'] = 0;
          break;
      }
      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->Smarty->assign('types', $this->readTypes());
      $this->Smarty->assign('cities', $this->auto_load_list('city'));
      $this->Smarty->assign('districts', $this->auto_load_list('district'));
      $this->Smarty->assign('borders', $this->bordersList());
      $this->Smarty->assign('data', $this->readrecord($_GET['id']));
      $this->Output();
    }
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "letters.postdate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    if($_GET['type']){
      $where[] = "letters.type='$_GET[type]'";
    }
    if(!empty($_GET['renterid'])){
      $where[] = "letters.renterid='$_GET[renterid]'";
      $renter = $this->auto_load_read($_GET['renterid'], 'buyer');
      $this->Smarty->assign('renter', $renter);
    }
    if(!empty($_GET['buildid'])){
      $where[] = "letters.buildid='$_GET[buildid]'";
      $this->Smarty->assign('build', $this->db->get_var("SELECT title FROM building WHERE id='$_GET[buildid]'"));
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Smarty->assign('types', $this->readTypes());
    $this->Output();
  }

}
