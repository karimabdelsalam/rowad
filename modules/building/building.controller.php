<?php

class building extends building_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function delattach()
  {
    if($_GET['id']){
      $this->deleteAttachFile($_GET['id']);
      $this->redirect();
    }
  }

  public function delete()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->deleteExec(0, $ids);
      $this->redirect(CPURL . '/' . Module);
    } else {
      $this->deleteExec($_GET['id']);
    }
  }

  public function report()
  {
    define('OUTPUT_PRINT_VERSION', true);
    $this->index(false, true);
  }

  public function reportdaily()
  {
    define('OUTPUT_PRINT_VERSION', true);
    $this->Smarty->assign('report_date', date('m Y'));
    $this->index(false, false);
  }

  public function view()
  {
    $this->Smarty->assign('build', $this->readrecord($_GET['id']));
    $this->Output('printable');
  }

  public function sublocs()
  {
    echo $this->getsublocs($_GET['locid']);
  }

  public function custom_fields()
  {
    $customFields = $this->getCustomFields($_GET['catid'], $_GET['buildid']);
    $this->Smarty->assign('fieldcats', $customFields);
    echo $this->Fetch();
    exit;
  }

  public function printable()
  {
    $this->Smarty->assign('build', $this->readrecord($_GET['id']));
    $this->printVersion();
  }

  public function active()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markasActive(0, $ids);
    } else{
      $this->markasActive($_GET['id']);
    }
    $this->redirect(CPURL . '/' . Module);
  }

  public function inactive()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markasinActive(0, $ids);
    } else{
      $this->markasinActive($_GET['id']);
    }
    $this->redirect(CPURL . '/' . Module);
  }

  public function search()
  {
    if(!empty($_GET['renterid'])){
      $results = $this->searchRenterBuilds($_GET['renterid']);
      echo $results;
      exit;
    }
    if(!empty($_GET['ownerid'])){
      $results = $this->searchOwnersBuilds($_GET['ownerid']);
      echo $results;
      exit;
    }
    if(!empty($_GET['buyerid'])){
      $results = $this->searchBuyersBuilds($_GET['buyerid']);
      echo $results;
      exit;
    }
    if(strlen(utf8_decode($_GET['q'])) < 1){
      //return false;
    }
    $type = (empty($_GET['type'])) ? '' : $_GET['type'];
    $free = (empty($_GET['free'])) ? '' : $_GET['free'];
    $results = $this->searchQuery($_GET['q'], $type, $free);
    echo json_encode($results);
    exit;
  }

  public function add()
  {
    if($_POST){
      if(empty($_POST['owner']['name'])){
        $this->showmsg(gettext('نسيت اختيار ملاك العقار لا يمكن المتابعة'), 0);
      }
      $data = array();
      $data['title'] = $_POST['title'];
      $data['locid'] = $_POST['locid'];
      $data['buildcat'] = $_POST['buildcat'];
      $data['citytid'] = $_POST['citytid'];
      $data['buyercat'] = $_POST['buyercat'];
      $data['calendar'] = $_POST['calendar'];
      $data['platno'] = $_POST['platno'];
      $data['planno'] = $_POST['planno'];
      $data['size'] = $_POST['size'];
      $data['zone'] = $_POST['zone'];
      $data['street'] = $_POST['street'];
      $data['floor'] = $_POST['floor'];
      $data['rooms'] = $_POST['rooms'];
      $data['furnished'] = $_POST['furnished'];
      $data['district'] = $_POST['district'];
      $data['tax'] = $_POST['tax'];
      $data['details'] = $_POST['details'];
      $data['loc_details'] = $_POST['loc_details'];
      $data['type'] = $_POST['type'];
      if($_POST['type'] == 'rent'){
        $data['bids'] = json_encode([], JSON_UNESCAPED_UNICODE);
        $data['rentvalue'] = $_POST['rentvalue'];
        $data['rent_month'] = $_POST['rent_month'];
        $data['rent_day'] = $_POST['rent_day'];
        $data['rentcomm'] = $_POST['rentcomm'];
        $data['period'] = $_POST['period'];
        $data['payment'] = $_POST['payment'];
        $data['max'] = '';
        $data['salecomm'] = '';
      } elseif($_POST['type'] == 'sale'){
        $data['bids'] = json_encode($this->cleanArray($_POST['bids'], 'amount'), JSON_UNESCAPED_UNICODE);
        $data['max'] = $_POST['max'];
        $data['salecomm'] = $_POST['salecomm'];
        $data['rent_month'] = '';
        $data['rent_day'] = '';
        $data['rentvalue'] = '';
        $data['rentcomm'] = '';
        $data['period'] = '';
        $data['payment'] = '';
      }
      if($_POST['period'] == 'month'){
        $data['payment'] = 'month';
      }
      $data['buildno'] = $_POST['buildno'];
      $data['owner'] = json_encode($this->cleanArray($_POST['owner'], 'id'), JSON_UNESCAPED_UNICODE);
      $data['plots'] = json_encode($this->cleanArray($_POST['plots'], 'no'), JSON_UNESCAPED_UNICODE);
      $data['location'] = json_encode($_POST['location'], JSON_UNESCAPED_UNICODE);
      $data['deed'] = json_encode($this->cleanArray($_POST['deed'], 'no'), JSON_UNESCAPED_UNICODE);
      $data['meters'] = json_encode($this->cleanArray($_POST['meters'], 'typeid'), JSON_UNESCAPED_UNICODE);
      if(!empty($_POST['mcost'])){
        if(empty($_POST['mcost_cycle']) || empty($_POST['mcost_start_date'])){
          $this->showmsg(gettext('نسيت اختيار دورة تحصيل إدارة الملك او تاريخ بدأ التحصيل'), 0);
        }
        $data['mcost_start_date'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['mcost_start_date']) : $_POST['mcost_start_date'];
        $data['mcost_next_date'] = $data['mcost_start_date'];
        $data['mcost_cycle'] = $_POST['mcost_cycle'];
        $data['mcost'] = $_POST['mcost'];
      } else {
        $data['mcost_start_date'] = NULL;
        $data['mcost_next_date'] = NULL;
        $data['mcost_cycle'] = '';
        $data['mcost'] = 0;
      }
      $buildid = $this->addrecord($data);
      $this->registerLog($buildid);
      $this->moveAttachments($_POST['tempid'], $buildid);
      $this->refreshContracts();
      $this->dataCustomFields($_POST['buildcat'], $_POST['cfields'], $buildid);
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->Smarty->assign('cities', $this->auto_load_list('city'));
      $this->Smarty->assign('districts', $this->auto_load_list('district'));
      $this->Smarty->assign('deedtypes', $this->auto_load_list('deedtypes'));
      $this->Smarty->assign('meterstypes', $this->auto_load_list('meter_types'));
      $this->Smarty->assign('buildcats', $this->auto_load_list('buildcats'));
      $this->Smarty->assign('buycats', $this->auto_load_list('buycats'));
      $this->Smarty->assign('locations', $this->auto_load_list('building/locations'));
      $this->Smarty->assign('borders', $this->bordersList());
      $this->enqueuejs('gmap');

      if(!empty($_GET['copyfrom'])){
        $data = $this->readrecord($_GET['copyfrom']);
        unset($data['id'], $data['files'], $data['bids'], $data['meters']);
        $this->Smarty->assign('data', $data);
      }
      if(!empty($_GET['locid'])){
        $data = $this->auto_load_read($_GET['locid'], 'building/locations');
        $data['districtid'] = $data['district'];
        $data['location'] = $data['gps_location'];
        unset($data['id'], $data['files']);
        $this->Smarty->assign('data', $data);
      }

      $this->Output('edit', $this->config['gmap_key']);
    }
  }

  public function edit()
  {
    if($_POST){
      if(empty($_POST['owner']['name'])){
        $this->showmsg(gettext('نسيت اختيار ملاك العقار لا يمكن المتابعة'), 0);
      }
      $data = array();
      $data['title'] = $_POST['title'];
      $data['locid'] = $_POST['locid'];
      $data['buildcat'] = $_POST['buildcat'];
      $data['citytid'] = $_POST['citytid'];
      $data['buyercat'] = $_POST['buyercat'];
      $data['calendar'] = $_POST['calendar'];
      $data['platno'] = $_POST['platno'];
      $data['planno'] = $_POST['planno'];
      $data['size'] = $_POST['size'];
      $data['zone'] = $_POST['zone'];
      $data['street'] = $_POST['street'];
      $data['floor'] = $_POST['floor'];
      $data['rooms'] = $_POST['rooms'];
      $data['furnished'] = $_POST['furnished'];
      $data['district'] = $_POST['district'];
      $data['tax'] = $_POST['tax'];
      $data['details'] = $_POST['details'];
      $data['loc_details'] = $_POST['loc_details'];
      $data['type'] = $_POST['type'];
      if($_POST['type'] == 'rent'){
        $data['bids'] = json_encode([], JSON_UNESCAPED_UNICODE);
        $data['rentvalue'] = $_POST['rentvalue'];
        $data['rent_month'] = $_POST['rent_month'];
        $data['rent_day'] = $_POST['rent_day'];
        $data['rentcomm'] = $_POST['rentcomm'];
        $data['period'] = $_POST['period'];
        $data['payment'] = $_POST['payment'];
        $data['max'] = '';
        $data['salecomm'] = '';
      } elseif($_POST['type'] == 'sale'){
        $data['bids'] = json_encode($this->cleanArray($_POST['bids'], 'amount'), JSON_UNESCAPED_UNICODE);
        $data['max'] = $_POST['max'];
        $data['salecomm'] = $_POST['salecomm'];
        $data['rentvalue'] = '';
        $data['rent_month'] = '';
        $data['rent_day'] = '';
        $data['rentcomm'] = '';
        $data['period'] = '';
        $data['payment'] = '';
      }
      if($_POST['period'] == 'month'){
        $data['payment'] = 'month';
      }
      $data['buildno'] = $_POST['buildno'];
      $data['owner'] = json_encode($this->cleanArray($_POST['owner'], 'id'), JSON_UNESCAPED_UNICODE);
      $data['plots'] = json_encode($this->cleanArray($_POST['plots'], 'no'), JSON_UNESCAPED_UNICODE);
      $data['location'] = json_encode($_POST['location'], JSON_UNESCAPED_UNICODE);
      $data['deed'] = json_encode($this->cleanArray($_POST['deed'], 'no'), JSON_UNESCAPED_UNICODE);
      $data['meters'] = json_encode($this->cleanArray($_POST['meters'], 'typeid'), JSON_UNESCAPED_UNICODE);
      if(!empty($_POST['mcost'])){
        if(empty($_POST['mcost_cycle']) || empty($_POST['mcost_start_date'])){
          $this->showmsg(gettext('نسيت اختيار دورة تحصيل إدارة الملك او تاريخ بدأ التحصيل'), 0);
        }
        $data['mcost_start_date'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['mcost_start_date']) : $_POST['mcost_start_date'];
        if($_POST['mcost_start_date'] != $_POST['mcost_start_date_old'] || $_POST['mcost_cycle'] != $_POST['mcost_cycle_old']){
          $data['mcost_next_date'] = $data['mcost_start_date'];
        }
        $data['mcost_cycle'] = $_POST['mcost_cycle'];
        $data['mcost'] = $_POST['mcost'];
      } else {
        $data['mcost_start_date'] = NULL;
        $data['mcost_next_date'] = NULL;
        $data['mcost_cycle'] = '';
        $data['mcost'] = 0;
      }
      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      $this->moveAttachments($_POST['tempid'], $_POST['id']);
      $this->refreshContracts();
      $this->dataCustomFields($_POST['buildcat'], $_POST['cfields'], $_POST['id'], true);
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->Smarty->assign('data', $this->readrecord($_GET['id']));
      $this->Smarty->assign('cities', $this->auto_load_list('city'));
      $this->Smarty->assign('districts', $this->auto_load_list('district'));
      $this->Smarty->assign('deedtypes', $this->auto_load_list('deedtypes'));
      $this->Smarty->assign('meterstypes', $this->auto_load_list('meter_types'));
      $this->Smarty->assign('buildcats', $this->auto_load_list('buildcats'));
      $this->Smarty->assign('buycats', $this->auto_load_list('buycats'));
      $this->Smarty->assign('locations', $this->auto_load_list('building/locations'));
      $this->Smarty->assign('borders', $this->bordersList());
      $this->enqueuejs('gmap');
      $this->Output('edit', $this->config['gmap_key']);
    }
  }

  public function index($paging = true, $alltime = false){
    $where = array();
    $inner = '';
    if($_GET['title']){
      $where[] = "building.title LIKE '%$_GET[title]%'";
    }
    if($_GET['ids']){
      $where[] = "building.id IN(" . implode(',', $_GET['ids']) . ")";
    }
    if($_GET['ownerid']){
      $where[] = "JSON_CONTAINS(JSON_EXTRACT(building.owner, '$.id[*]'), '\"$_GET[ownerid]\"', '$')";
    }
    if($_GET['type']){
      $where[] = "building.type='$_GET[type]'";
    }
    if($_GET['locid']){
      $where[] = "building.locid='$_GET[locid]'";
    }
    if($_GET['available']){
      $where[] = "building.available='" . intval($_GET['available']) . "'";
    }
    if($_GET['status']){
      $where[] = "building.active='" . intval($_GET['status']) . "'";
    }
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "building.timepost BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    $results = $this->listrecord($where, $paging, $inner);

    if($results){
      $pays_pending = $this->auto_load('pays_pending');
      $rent_contracts = $this->auto_load('rent');
      $sell_contracts = $this->auto_load('sell');
      $results[0]['total_sum']['paid'] = 0;
      $results[0]['total_sum']['remain'] = 0;
      $results[0]['total_sum']['collect'] = 0;
      foreach($results as $key => $result){
        if($result['type'] == 'rent' && !empty($result['contractid'])){
          $results[$key]['nextpayment'] = $pays_pending->rentPaymentInfo($result['contractid']);
          $results[$key]['contract_info'] = $rent_contracts->readrecord($result['contractid'], true);
          $openContracts = $this->db->get_results("SELECT startdate,enddate FROM `rent_contracts` WHERE buildid='$result[id]' AND startdate BETWEEN DATE(NOW() - INTERVAL 2 YEAR) AND DATE(NOW() + INTERVAL 5 YEAR) ORDER BY startdate ASC");
          if($openContracts){
            foreach($openContracts as $openContract){
              $results[$key]['contracts_timeline'][] = [ 'start' => $openContract['startdate'], 'end' => $openContract['enddate'] ];
            }
          }
        } elseif($result['type'] == 'sale' && !empty($result['contractid'])){
          $results[$key]['nextpayment'] = $pays_pending->salePaymentInfo($result['contractid']);
          $results[$key]['contract_info'] = $sell_contracts->readrecord($result['contractid'], true);
        }
        if(! empty($results[$key]['contract_info'])) {
            $results[0]['total_sum']['paid'] += $results[$key]['contract_info']['allpaid'];
            $results[0]['total_sum']['remain'] += $results[$key]['contract_info']['remain'];
        }
        if(! $paging) {
          $results[$key]['payments'] = $pays_pending->contractPayments($result['contractid'], $alltime);
          $payments_counter = count($results[$key]['payments']);
          if($payments_counter > 0) {
              $results[0]['total_sum']['collect'] += $results[$key]['payments'][0]['amount'];
          }
          $results[0]['max_payments'] = (empty($results[0]['max_payments']) || $payments_counter > $results[0]['max_payments']) ? $payments_counter : $results[0]['max_payments'];
        }
      }
    }

    if($_GET['ownerid']){
      $this->Smarty->assign('owner', $this->auto_load_read($_GET['ownerid'], 'owner'));
    }
    $this->Smarty->assign('locations', $this->auto_load_list('building/locations'));

    $this->Smarty->assign('results', $results);

    $this->enqueueJSLibrary('timeline');
    $this->Output();
  }

}
