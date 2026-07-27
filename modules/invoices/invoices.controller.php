<?php

use chillerlan\QRCode\QRCode;
use Salla\ZATCA\GenerateQrCode;
use Salla\ZATCA\Tags\InvoiceDate;
use Salla\ZATCA\Tags\InvoiceTaxAmount;
use Salla\ZATCA\Tags\InvoiceTotalAmount;
use Salla\ZATCA\Tags\Seller;
use Salla\ZATCA\Tags\TaxNumber;

class invoices extends invoices_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function printable()
  {
    if($_GET['id']){
      $this->printInvoice($_GET['id'], 12);
    }
  }

  public function taxbill()
  {
    if($_GET['id']){
      $this->printInvoice($_GET['id'], 13);
    }
  }

  public function create()
  {
    if($_GET['id']){
      $this->printInvoice($_GET['id'], 12);
    } elseif($_POST){
      parse_str(urldecode(htmlspecialchars_decode($_POST['query'])), $params);
      unset($params['route']);

      if(empty($_POST['name']) && empty($_POST['buyerid'])){
        $this->showmsg(gettext('من فضلك اختر العميل او قم بإدخال اسمه'), 0);
      }

      $data = array();
      $data['query'] = serialize($params);
      $data['name'] = $_POST['name'];
      $data['buyerid'] = $_POST['buyerid'];
      $data['calendar'] = $_POST['calendar'];
      $data['paid'] = $_POST['paid'];
      $data['notes'] = $_POST['notes'];
      if($_POST['calendar'] == 2){
        $data['created_date'] = uCal::u2g($_POST['created_date']);
      } else{
        $data['created_date'] = $_POST['created_date'];
      }

      $payment_ids = $this->getRelatedPayments($params);

      if(empty($payment_ids)){
        $this->showmsg(gettext('لم يتم العثور على اي مدفوعات متأخرة'), 0);
      }

      /*$createdBefore = $this->db->get_var("SELECT id FROM `payments` WHERE id IN($payment_ids) AND invoiceid > 0");
      if($createdBefore){
        $this->showmsg(gettext('الدفعة رقم'). ' #'.$createdBefore.gettext(' تم بالفعل انشاء فاتورة لها من قبل.'), 0);
      }*/

      $totals = $this->db->get_row("SELECT SUM(`amount`) AS totalamounts, SUM(`tax`) AS totaltaxs, SUM(`paidamount`) AS paidamounts FROM `payments` WHERE id IN($payment_ids)");
      $amount_to_pay = $totals['totalamounts']-$totals['paidamounts'];
      if($_POST['paid'] < $amount_to_pay){
        //$this->showmsg(gettext('المبلغ المدفوع أقل من إجمالي الفاتورة'), 0);
      }

      $items = [];
      $payments = $this->db->get_results("SELECT payments.amount,payments.commission2,payments.statementid,payments.tax,payments.type,payment_types.title AS paymenttype
        ,payments.paydate,payments.calendar FROM `payments`
        INNER JOIN payment_types ON(payment_types.name=payments.type)
        WHERE payments.id IN($payment_ids)");
      foreach($payments as $payment){
        $description = '';
        if(!empty($payment['reason'])){
          $description = $payment['reason'];
        } elseif($payment['type'] == 'expenses' && !empty($payment['statementid'])){
          $description = $this->db->get_var("SELECT reason FROM `statement_out` WHERE id='$payment[statementid]'");
        } elseif($payment['type'] != 'expenses'){
          $description = ($payment['calendar'] == 2) ? uCal::g2u($payment['paydate']) : $payment['paydate'];
        }
        $items[] = [
          'amount' => $payment['amount'],
          'tax' => $payment['tax'],
          'commission' => $payment['commission2'],
          'title' => $payment['paymenttype'],
          'description' => $description,
        ];
      }

      $data['items'] = serialize($items);
      $data['total'] = $totals['totalamounts'];
      $data['totaltaxs'] = $totals['totaltaxs'];
      $data['paidamounts'] = $totals['paidamounts'];

      $invoiceid = $this->createInvoice($data);
      $this->registerLog($invoiceid);

      if($invoiceid){
        $this->db->query("UPDATE `payments` SET invoiceid='$invoiceid',paidamount=paidamount+$_POST[paid] WHERE id IN($payment_ids)");
      }

      $this->showmsg(CPURL . '/' . Module . '/printable/?id=' . $invoiceid, 1);
    } else {
      $fullname = $this->tryGetFullName();

      $amount_to_pay = 0;
      $payment_ids = $this->getRelatedPayments($_GET);
      if(!empty($payment_ids)){
        $totals = $this->db->get_row("SELECT SUM(`amount`) AS totalamounts, SUM(`tax`) AS totaltaxs, SUM(`paidamount`) AS paidamounts FROM `payments` WHERE id IN($payment_ids)");
        $amount_to_pay = $totals['totalamounts']-$totals['paidamounts'];
      }

      $this->Smarty->assign('data', [ 'query' => $_SERVER['QUERY_STRING'], 'paidto' => $fullname, 'amount_to_pay' => $amount_to_pay ]);
      $this->Output();
    }
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "created_date BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    if($_GET['serial']){
      $where[] = "id='$_GET[serial]'";
    }
    if($_GET['fullname']){
      $where[] = "name LIKE '%$_GET[fullname]%'";
    }

    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

  private function printInvoice($invoiceid, $templateID){
    $this->turnTempArabic();
    $invoice = $this->readInvoice($invoiceid);
    $invoice['items'] = unserialize($invoice['items']);

    $items = '';
    if($templateID == 12) {
      foreach($invoice['items'] as $item){
        $items .= '<tr>';
        $items .= '<td><p style="text-align:right">' . $item['title']. '</p></td>';
        $items .= '<td><p style="text-align:right">' . $item['description']. '</p></td>';
        $items .= '<td><p style="text-align:center">' . $item['tax']. '</p></td>';
        $items .= '<td><p style="text-align:center">' . $item['commission']. '</p></td>';
        $items .= '<td><p style="text-align:center">' . $item['amount']. '</p></td>';
        $items .= '</tr>';
      }
    } elseif($templateID == 13) {
      foreach($invoice['items'] as $item){
        $items .= '<tr>';
        $items .= '<td><p style="text-align:center">' . number_format($item['amount'], 2) . '</p></td>';
        $items .= '<td><p style="text-align:center">' . number_format($item['tax'], 2) . '</p></td>';
        $items .= '<td><p style="text-align:center">' . (($item['tax']/($item['amount']-$item['tax']))*100) . '%</p></td>';
        $items .= '<td><p style="text-align:center">0.00</p></td>';
        $items .= '<td><p style="text-align:center">' . number_format($item['amount']-$item['tax'], 2) . '</p></td>';
        $items .= '<td><p style="text-align:center">1</p></td>';
        $items .= '<td><p style="text-align:center">' . number_format($item['amount']-$item['tax'], 2) . '</p></td>';
        $items .= '<td><p style="text-align:right">' . $item['title'] . ' ' . $item['description'] . '</p></td>';

        $items .= '</tr>';
      }
    }

    require(LIB_DIR.'/qrcode/autoload.php');
    require(LIB_DIR . '/zacta/autoload.php');

    $zacatString = GenerateQrCode::fromArray([
      new Seller($this->config['en_sitename']), // seller name
      new TaxNumber($this->config['billing_info']['taxno']), // seller tax number
      new InvoiceDate(date("c", strtotime($invoice['created_date'] . ' ' . date($invoice['inserted_time'], 'H:i:s')))), // invoice date as Zulu ISO8601 @see https://en.wikipedia.org/wiki/ISO_8601
      new InvoiceTotalAmount(number_format($invoice['total'], 2, '.', '')), // invoice total amount
      new InvoiceTaxAmount(number_format($invoice['totaltaxs'], 2, '.', '')) // invoice tax amount
    ])->toBase64();

    $liveLink = CPURL.'/client/?secret='.$this->encodeHash('type=invoice&id='.$invoice['id'].'&time='.TIMENOW);

    $blockReplaces = array('items' => $items);
    $replaces = array(
      'fullname' => $invoice['name'],
      'clientno' => '',
      'created_date' => ($invoice['calendar'] == 2) ? uCal::g2u($invoice['created_date']) : $invoice['created_date'],
      'no' => $invoice['id'],
      'total' => number_format(($invoice['total']-$invoice['totaltaxs']), 2).' '.$this->config['currency'],
      'taxs' => number_format($invoice['totaltaxs'], 2).' '.$this->config['currency'],
      'total_wtaxes' => number_format($invoice['total'], 2).' '.$this->config['currency'],
      'paid' => number_format($invoice['paidamounts'], 2).' '.$this->config['currency'],
      'collected' => number_format($invoice['paid'], 2).' '.$this->config['currency'],
      'remain' => number_format(($invoice['total']-($invoice['paid']+$invoice['paidamounts'])), 2).' '.$this->config['currency'],
      'notes' => $invoice['notes'],
      'seller.billing.*' => $this->config['billing_info'],
      'customer.billing.*' => [],
      'customer_country' => '',
      'seller_country' => $this->db->get_var("SELECT name FROM country WHERE id='".$this->config['country']."'"),
      'qrcode' => '<img src="'.(new QRCode)->render($zacatString).'" alt="QR Code" width="200" height="200" style="border: 2px solid #ccc;" />',
    );
    if(!empty($invoice['buyerid'])) {
      $buyermodule = $this->auto_load('buyer');
      $buyer = $buyermodule->readrecord($invoice['buyerid']);
      $replaces['fullname'] = $buyer['fullname'];
      $replaces['customer_country'] = $buyer['national'];
      $replaces['customer.billing.*'] = $buyer['billing_info'];
    }
    $this->processDoc($replaces, $templateID, $blockReplaces);
  }

  private function getRelatedPayments($params){
    $payment_ids = '';
    if(!empty($params['ids'])){
      $payment_ids = $params['ids'];
    } elseif(!empty($params['payid'])){
      $payment_ids = $params['payid'];
    } elseif(!empty($params['rentid'])){
      $where = " AND type!='expenses'";
      if(!empty($params['with']) && $params['with'] == 'expenses'){
        $where = '';
      }
      $getIDS = $this->db->get_results("SELECT id FROM payments WHERE contractid='$params[rentid]' AND gone='0' AND paydate<DATE(NOW()) AND module='rent' $where");
      if($getIDS){
        $payment_ids = [];
        foreach($getIDS as $getID){
          $payment_ids[] = $getID['id'];
        }
        $payment_ids = implode(',', $payment_ids);
      }
    } elseif(!empty($params['sellid'])){
      $getIDS = $this->db->get_results("SELECT id FROM payments WHERE contractid='$params[sellid]' AND gone='0' AND paydate<DATE(NOW()) AND module='sell'");
      if($getIDS){
        $payment_ids = [];
        foreach($getIDS as $getID){
          $payment_ids[] = $getID['id'];
        }
        $payment_ids = implode(',', $payment_ids);
      }
    }
    return $payment_ids;
  }

  private function tryGetFullName(){
    $payment_ids = '';
    $contractid = 0;
    if(!empty($_GET['ids'])){
      $payment_ids = $_GET['ids'];
    } elseif(!empty($_GET['payid'])){
      $payment_ids = $_GET['payid'];
    }
    if(!empty($payment_ids)){
      $contractid = $this->db->get_results("SELECT DISTINCT(`contractid`) AS contractid FROM `payments` WHERE id IN($payment_ids) AND module='rent'");
      if(count($contractid) == 1){
        $contractid = $contractid[0]['contractid'];
      }
    }
    if(!empty($_GET['rentid'])){
      $contractid = $_GET['rentid'];
    }
    if(empty($contractid)) return '';

    $buyer = json_decode($this->db->get_var("SELECT buyer FROM `rent_contracts` WHERE id='$contractid'"), true);
    $buyername = $this->auto_load_read($buyer['id'][0], 'buyer');
    return ['id' => $buyer['id'][0], 'fullname' => $buyername['fullname']];
  }

}
