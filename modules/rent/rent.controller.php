<?php

use chillerlan\QRCode\QRCode;

class rent extends rent_model
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

  public function invoice()
  {
    if($_GET['id']){
      $this->redirect(CPURL.'/invoices/create/?rentid='.$_GET['id']);
    }
  }

  public function invoiceall()
  {
    if($_GET['id']){
      $this->redirect(CPURL.'/invoices/create/?with=expenses&rentid='.$_GET['id']);
    }
  }

  public function archiveit()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markArhcive(0, $ids, 1);
      $this->redirect(CPURL . '/' . Module);
    } else{
      $this->markArhcive($_GET['id'], '', 1);
      $this->redirect(CPURL . '/' . Module);
    }
  }

  public function unarchive()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markArhcive(0, $ids, 0);
      $this->redirect(CPURL . '/' . Module);
    } else{
      $this->markArhcive($_GET['id'], '', 0);
      $this->redirect(CPURL . '/' . Module);
    }
  }

  public function end()
  {
    $this->markAsEnded($_GET['id']);
    $this->redirect(CPURL . '/' . Module);
  }

  public function cancel()
  {
    $this->markAsCancelled($_GET['id']);
    $this->redirect(CPURL . '/' . Module);
  }

  public function printable()
  {
    $this->turnTempArabic();
    require LIB_DIR . '/I18N/Arabic.php';
    require(LIB_DIR.'/qrcode/autoload.php');

    $Arabic = new I18N_Arabic('Numbers');
    $contract = $this->readrecord($_GET['id'], true);
    $daynum = date('w', $contract['postdate']);
    $buyermodule = $this->auto_load('buyer');
    $buildmodule = $this->auto_load('building');
    $ownermodule = $this->auto_load('owner');

    $buyers = '';
    foreach($contract['buyers'] as $buyerid){
      $buyer = $buyermodule->readrecord($buyerid);
      $buyers .= '<tr>';
      $buyers .= '<td>' . $buyer['fullname'] . '</td>';
      if($buyer['type'] != 'person'){
        $buyers .= '<td></td>';
        $buyers .= '<td style="text-align:center">' . $buyer['com_idnumber'] . '</td>';
        $buyers .= '<td style="text-align:center">' . $buyer['com_mobile'] . '</td>';
        $buyers .= '</tr><tr><td>'.gettext('ويمثلها').': ' . $buyer['person'] . '</td>';
      }
      $buyers .= '<td style="text-align:center">' . $buyer['national'] . '</td>';
      $buyers .= '<td style="text-align:center">' . $buyer['idnumber'] . '</td>';
      $buyers .= '<td style="text-align:center">' . $buyer['mobile'] . '</td>';
      $buyers .= '</tr>';
    }

    $building = $buildmodule->readrecord($contract['buildid']);

    $owners = '';
    foreach($building['owners'] as $ownerid){
      $ownerinfo = $ownermodule->readrecord($ownerid);
      $owners .= '<tr>';
      $owners .= '<td>' . $ownerinfo['fullname'] . '</td>';
      $owners .= '<td style="text-align:center">' . $ownerinfo['national'] . '</td>';
      $owners .= '<td style="text-align:center">' . $ownerinfo['idnumber'] . '</td>';
      $owners .= '<td style="text-align:center">' . $ownerinfo['mobile'] . '</td>';
      $owners .= '</tr>';
    }

    /*$owners = array();
    foreach($building['owners'] as $ownerid){
      $owner = $ownermodule->readrecord($ownerid);
      $owners[] = $owner['fullname'];
    }
    $owners = implode(gettext(' و '), $owners);*/

    $meters = '';
    foreach($building['meters']['no'] as $key => $meterno){
      $meters .= '<tr>';
      $meters .= '<td><p style="text-align:center">' . $building['meters']['type_name'][$key] . '</p></td>';
      $meters .= '<td><p style="text-align:center">' . $building['meters']['owner_type_name'][$key] . '</p></td>';
      $meters .= '<td><p dir="LTR" style="text-align:center">' . $building['meters']['no'][$key] . '</p></td>';
      $meters .= '<td><p dir="LTR" style="text-align:center">' . $contract['meters'][$meterno] . '</p></td>';
      $meters .= '<td><p dir="LTR" style="text-align:center">' . $building['meters']['pay_no'][$key] . '</p></td>';
      $meters .= '<td><p dir="LTR" style="text-align:center">' . $building['meters']['pay_code'][$key] . '</p></td>';
      $meters .= '</tr>';
    }

    $meters2 = '';
    $meters2 .= '<tr>';
    $meters2 .= '<td><p dir="LTR" style="text-align:center">' . $building['meters']['no'][0] . '</p></td>';
    $meters2 .= '<td><p dir="LTR" style="text-align:center">' . $building['meters']['no'][1] . '</p></td>';
    $meters2 .= '<td><p dir="LTR" style="text-align:center">' . $contract['meters'][$building['meters']['no'][0]] . '</p></td>';
    $meters2 .= '<td><p dir="LTR" style="text-align:center">' . $contract['meters'][$building['meters']['no'][1]] . '</p></td>';
    $meters2 .= '<td><p dir="LTR" style="text-align:center">' . $building['meters']['pay_no'][0] . '</p></td>';
    $meters2 .= '<td><p dir="LTR" style="text-align:center">' . $building['meters']['pay_no'][1] . '</p></td>';
    $meters2 .= '</tr>';

    $deeds = '';
    foreach($building['deed']['no'] as $key => $deedno){
      $deeds .= '<tr>';
      $deeds .= '<td><p style="text-align:center">' . $building['deed']['type_name'][$key] . '</p></td>';
      $deeds .= '<td><p style="text-align:center">' . $building['deed']['no'][$key] . '</p></td>';
      $deeds .= '<td><p dir="LTR" style="text-align:center">' . $building['deed']['source'][$key] . '</p></td>';
      $deeds .= '<td><p dir="LTR" style="text-align:center">' . $building['deed']['date'][$key] . '</p></td>';
      $deeds .= '</tr>';
    }

    $blockReplaces = array('owners' => $owners, 'buyers' => $buyers, 'meters' => $meters, 'meters2' => $meters2, 'deeds' => $deeds, 'rights' => (empty($contract['allrights'])) ? '' : ('<li>' . implode('</li><li>', $contract['allrights']) . '</li>'));
    $replaces = array(
      'serial' => $contract['id'],
      'number' => $contract['id'],
      'date' => ($contract['calendar'] == 2) ? uCal::g2u($contract['postdate']) : $contract['postdate'],
      'day' => uCal::$l['ar'][$daynum],
      'title' => $building['title'],
      'parent_build' => $building['parent_build'],
      'address' => $building['loc_details'],
      'city' => $building['city_name'],
      'district' => $building['district'],
      'type' => $building['type_name'],
      'category' => $building['buildcat_name'],
      'desc' => $building['details'],
      'buildno' => $building['buildno'],
      'planno' => $building['planno'],
      'platno' => $building['platno'],
      'size' => (empty($building['size']))? '--' : $building['size'] . gettext('م2'),
      'zone' => $building['zone'],
      'street' => $building['street'],
      'floor' => (empty($building['floor']))? '--' : $building['floor'],
      'rooms' => (empty($building['rooms']))? '--' : $building['rooms'],
      'furnished' => $building['furnished_text'],
      'period' => $contract['periodnum'] . ' ' . $contract['period_title'],
      'startdate' => ($contract['calendar'] == 2) ? uCal::g2u($contract['startdate']) : $contract['startdate'],
      'enddate' => ($contract['calendar'] == 2) ? uCal::g2u($contract['enddate']) : $contract['enddate'],
      'purpose' => $contract['usein'], 'tax' => $contract['tax'],
      'rent' => $contract['rentvalue'] - $contract['tax'],
      'total_rent' => $contract['rentvalue'],
      'total_rent_text' => $Arabic->int2str($contract['rentvalue']),
      'all_total_rent' => $contract['alltotal'],
      'all_total_rent_text' => $Arabic->int2str($contract['alltotal']),
      'pay_method' => $contract['payment_title'],
      'remain_amount' => $contract['alltotal'] - $contract['allpaid'],
      'paid' => $contract['allpaid'],
      'paid_text' => $Arabic->int2str($contract['allpaid']),
      'pay_comm' => $contract['platno'],
      'commission' => $contract['all_rentcomm'],
      'commission_text' => $Arabic->int2str($contract['all_rentcomm']),
      'qrcode_owner' => '<images src="'.(new QRCode)->render(CPURL.'/client/?secret='.$this->encodeHash('type=rent&id='.$contract['id'].'&person=owner&time='.TIMENOW)).'" alt="QR Code" />',
      'qrcode_customer' => '<images src="'.(new QRCode)->render(CPURL.'/client/?secret='.$this->encodeHash('type=rent&id='.$contract['id'].'&person=customer&time='.TIMENOW)).'" alt="QR Code" />'
    );
    $this->processDoc($replaces, 8, $blockReplaces);
  }

  public function add()
  {
    if($_POST){
      if(empty($_POST['buyer']['id'][1])){
        $this->showmsg(gettext('نسيت اختيار مستأجرين العقار لا يمكن المتابعة'), 0);
      }
      $data = array();
      if($_POST['calendar'] == 2){
        $data['postdate'] = uCal::u2g($_POST['postdate']);
        $data['startdate'] = uCal::u2g($_POST['startdate']);
        $data['enddate'] = uCal::u2g($_POST['enddate']);
        $data['nextpaymentdate'] = uCal::u2g($_POST['nextpaymentdate']);
      } else{
        $data['postdate'] = $_POST['postdate'];
        $data['startdate'] = $_POST['startdate'];
        $data['enddate'] = $_POST['enddate'];
        $data['nextpaymentdate'] = $_POST['nextpaymentdate'];
      }
      $freeBool = $this->checkAvailability($_POST['buildid'], $data['startdate'], $data['enddate']);
      if($freeBool === false){
        $this->showmsg(gettext('العقار غير متاح في ذلك التاريخ'), 0);
      }
      $data['buildid'] = $_POST['buildid'];
      $data['buyer'] = json_encode($this->cleanArray($_POST['buyer'], 'id'), JSON_UNESCAPED_UNICODE);
      $data['period'] = $_POST['period'];
      $data['periodnum'] = $_POST['periodnum'];
      $data['yearlyrent'] = $_POST['yearlyrent'];
      $data['rentvalue'] = $_POST['rentvalue'];
      $data['tax_percent'] = $_POST['buildTax'];
      $data['tax'] = $_POST['tax'];
      $data['nextpaymentmoney'] = $_POST['nextpaymentmoney'];
      $data['rightsid'] = $_POST['rightsid'];
      if($_POST['period'] == 'month'){
        $data['payment'] = 'month';
      } elseif($_POST['period'] == 'day'){
        $data['payment'] = 'day';
      } else {
        $data['payment'] = $_POST['payment'];
      }
      $data['usein'] = $_POST['usein'];
      $data['calendar'] = $_POST['calendar'];
      $data['commission'] = (empty($_POST['commission'])) ? 0 : $_POST['commission'];
      $data['comm_cycle'] = $_POST['comm_cycle'];
      $data['notes'] = $_POST['notes'];
      $data['extrarights'] = json_encode($this->cleanArray($_POST['extrarights']), JSON_UNESCAPED_UNICODE);
      $data['meters'] = json_encode($_POST['meters'], JSON_UNESCAPED_UNICODE);

      $rentid = $this->addrecord($data);
      $this->registerLog($rentid);
      $this->moveAttachments($_POST['tempid'], $rentid);
      //$this->updateBuildContract($_POST['buildid'], $rentid);
      $this->refreshContracts();

      $payments = $this->auto_load('payments');
      $payments->rentContractSetup($rentid, $data);

      $newContract = $this->readrecord($rentid);
      $this->sendNotify('newRentContract', array(), $newContract);
      $this->sendNotify('ownerBuildRented', array(), $newContract);

      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      if(empty($_GET['id'])){
        $this->Output('choose_build');
        exit;
      }
      $build = $this->auto_load_read($_GET['id'], 'building');
      if($build['active'] == 0){
        $this->showmsg(gettext('لا يمكن اضافة عقد جديد لعقار غير نشط'), 0);
      }
      $this->Smarty->assign('samples', $this->auto_load_list('sample'));
      $this->Smarty->assign('rights', $this->auto_load_list('rights'));
      $this->Smarty->assign('build', $build);
      $this->Output('edit');
    }
  }

  public function edit()
  {
    if($_POST){
      if($_POST['endedContract'] == 1){
        $this->showmsg(gettext('لا يمكن التعديل على عقد منتهي'), 0);
      }
      if(empty($_POST['buyer']['id'])){
        $this->showmsg(gettext('نسيت اختيار مستأجرين العقار لا يمكن المتابعة'), 0);
      }
      $data = array();
      if($_POST['calendar'] == 2){
        $data['postdate'] = uCal::u2g($_POST['postdate']);
        $data['startdate'] = uCal::u2g($_POST['startdate']);
        $data['enddate'] = uCal::u2g($_POST['enddate']);
        $data['nextpaymentdate'] = uCal::u2g($_POST['nextpaymentdate']);
      } else{
        $data['postdate'] = $_POST['postdate'];
        $data['startdate'] = $_POST['startdate'];
        $data['enddate'] = $_POST['enddate'];
        $data['nextpaymentdate'] = $_POST['nextpaymentdate'];
      }
      $freeBool = $this->checkAvailability($_POST['buildid'], $data['startdate'], $data['enddate'], $_POST['id']);
      if($freeBool === false){
        $this->showmsg(gettext('العقار غير متاح في ذلك التاريخ'), 0);
      }
      $data['buyer'] = json_encode($this->cleanArray($_POST['buyer'], 'id'), JSON_UNESCAPED_UNICODE);
      $data['buildid'] = $_POST['buildid'];
      $data['period'] = $_POST['period'];
      $data['periodnum'] = $_POST['periodnum'];
      $data['yearlyrent'] = $_POST['yearlyrent'];
      $data['rentvalue'] = $_POST['rentvalue'];
      $data['tax_percent'] = $_POST['buildTax'];
      $data['tax'] = $_POST['tax'];
      $data['nextpaymentmoney'] = $_POST['nextpaymentmoney'];
      $data['rightsid'] = $_POST['rightsid'];
      if($_POST['period'] == 'month'){
        $data['payment'] = 'month';
      } elseif($_POST['period'] == 'day'){
        $data['payment'] = 'day';
      } else {
        $data['payment'] = $_POST['payment'];
      }
      $data['usein'] = $_POST['usein'];
      $data['calendar'] = $_POST['calendar'];
      $data['commission'] = (empty($_POST['commission'])) ? 0 : $_POST['commission'];
      $data['comm_cycle'] = $_POST['comm_cycle'];
      $data['notes'] = $_POST['notes'];
      $data['extrarights'] = json_encode($this->cleanArray($_POST['extrarights']), JSON_UNESCAPED_UNICODE);
      $data['meters'] = json_encode($_POST['meters'], JSON_UNESCAPED_UNICODE);

      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      $this->moveAttachments($_POST['tempid'], $_POST['id']);

      if($_POST['dimremoved'] == 1){
        $payments = $this->auto_load('payments');
        $payments->checkEditStatus($_POST['id'], 'rent');
        $payments->rentContractSetup($_POST['id'], $data);
      }

      //$this->updateBuildContract($_POST['buildid'], $_POST['id']);
      $this->refreshContracts();

      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $pays_pending = $this->auto_load('pays_pending');
      $contract = $this->readrecord($_GET['id']);
      $this->Smarty->assign('data', $contract);
      $this->Smarty->assign('samples', $this->auto_load_list('sample'));
      $this->Smarty->assign('rights', $this->auto_load_list('rights'));
      $this->Smarty->assign('build', $this->auto_load_read($contract['buildid'], 'building'));
      $this->Smarty->assign('nextpayment', $pays_pending->rentPaymentInfo($_GET['id']));
      $this->Output();
    }
  }

  public function archive()
  {
    $this->index();
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['id']){
      $where[] = "rent_contracts.buildid='$_GET[id]'";
      $this->Smarty->assign('build', $this->auto_load_read($_GET['id'], 'building'));
    }
    if(Action == 'archive'){
      $where[] = "rent_contracts.archive='1'";
    } else{
      $where[] = "rent_contracts.archive='0'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output('index');
  }

}
