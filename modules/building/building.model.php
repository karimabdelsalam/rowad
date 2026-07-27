<?php

class building_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function deleteAttachFile($id)
  {
    $fileinfo = $this->db->get_row("SELECT * FROM attachments WHERE id='$id'");
    @unlink(ROOT_DIR . '/' . UPLOAD_DIR . '/' . $fileinfo['path']);
    $this->registerLog($_GET['id'], 'attachments', 'name');
    $this->db->query("DELETE FROM attachments WHERE id='$id'");
  }

  public function deleteExec($id, $group = '')
  {
    if(!empty($group)){
      $ids = explode(',', $group);
      foreach($ids as $id){
        $this->deleteExec($id);
      }
    } else{
      $this->verifyDelete("SELECT id FROM payments WHERE buildid='$id' AND gone='1'");
      $this->verifyDelete("SELECT id FROM transactions WHERE buildid='$id' AND gone='1'");
      $this->verifyDelete("SELECT id FROM rent_contracts WHERE buildid='$id'");
      $this->verifyDelete("SELECT id FROM sell_contracts WHERE buildid='$id'");
      $this->verifyDelete("SELECT id FROM statement_in WHERE buildid='$id'");
      $this->verifyDelete("SELECT id FROM statement_out WHERE buildid='$id'");
      $this->verifyDelete("SELECT id FROM letters WHERE buildid='$id'");
      $this->registerLog($id, 'building', 'title');
      $this->db->query("DELETE FROM building WHERE id='$id'");
      $this->db->query("DELETE FROM payments WHERE buildid='$id'");
      $this->db->query("DELETE FROM rent_contracts WHERE buildid='$id'");
      $this->db->query("DELETE FROM sell_contracts WHERE buildid='$id'");
      $this->db->query("DELETE FROM statement_in WHERE buildid='$id'");
      $this->db->query("DELETE FROM statement_out WHERE buildid='$id'");
      $this->db->query("DELETE FROM transactions WHERE buildid='$id'");
      $this->db->query("DELETE FROM attachments WHERE objectid='$id' AND moduleid='" . MODULE_ID . "'");
    }
  }

  public function markasActive($id, $group = array())
  {
    if(!empty($group)){
      $ids = explode(',', $group);
      foreach($ids as $id){
        $this->markasActive($id);
      }
    } else{
      $this->registerLog($id);
      $this->db->query("UPDATE building SET active='1' WHERE id='$id'");
    }
  }

  public function markasinActive($id, $group = array())
  {
    if(!empty($group)){
      $ids = explode(',', $group);
      foreach($ids as $id){
        $this->markasinActive($id);
      }
    } else{
      $this->registerLog($id);
      $this->db->query("UPDATE building SET active='0' WHERE id='$id'");
    }
  }

  public function getsublocs($locid)
  {
    $html = '<option value=""></option>';
    $results = $this->db->get_results("SELECT id,title FROM building WHERE locid='$locid' AND active='1'");
    if($results){
      foreach($results as $result){
        $html .= '<option value="' . $result['id'] . '">' . '#' . $result['id'] . ' - ' . $result['title'] . '</option>';
      }
    }
    return $html;
  }

  public function getCustomFields($catid = 0, $buildid = 0)
  {
    if(empty($catid)) {
      $catid = $this->db->get_var("SELECT buildcat FROM building WHERE id='$buildid'");
    }
    $fields = [];
    $loop = 0;
    $cats = $this->db->get_results("SELECT id,title FROM custom_fields_cats WHERE build_catid='$catid'");
    if($cats){
      foreach($cats as $cat) {
        $fields[$loop] = $cat;
        $inputs = $this->db->get_results("SELECT * FROM custom_fields WHERE catid='$cat[id]'");
        if($inputs) {
          $loop2 = 0;
          foreach($inputs as $input) {
            $fields[$loop]['fields'][$loop2] = $input;
            $inputVal = $buildid ? $this->db->get_var("SELECT data FROM custom_fields_data WHERE field_id='$input[id]' AND build_id='$buildid'") : '';
            $fields[$loop]['fields'][$loop2]['value'] = $inputVal ?: '';
            $loop2++;
          }
          $fields[$loop]['splitno'] = ceil($loop2/3);
        } else {
          $fields[$loop]['fields'] = $inputs;
        }
        $loop++;
      }
    }
    return $fields;
  }

  function dataCustomFields($catid, $fieldsData, $buildid, $update = false) {
    $fids = [0];
    $fields = $this->db->get_results("SELECT custom_fields.* FROM custom_fields
       INNER JOIN custom_fields_cats ON(custom_fields_cats.id = custom_fields.catid)
       WHERE custom_fields_cats.build_catid='$catid'");
    if($fields) {
      foreach($fields as $field) {
        $data = '';
        if(isset($fieldsData[$field['id']])){
          $data = $fieldsData[$field['id']];
        }
        if($update) {
          $bool = $this->db->get_var("SELECT id FROM custom_fields_data WHERE build_id='$buildid' AND field_id='$field[id]'");
          if($bool) {
            $this->db->update('custom_fields_data', [ 'data' => $data ], ['id' => $bool]);
            $fids[] = $bool;
          } else {
            $fids[] = $this->db->insert('custom_fields_data', [ 'build_id' => $buildid, 'field_id' => $field['id'], 'data' => $data ]);
          }
        } else {
          $fids[] = $this->db->insert('custom_fields_data', [ 'build_id' => $buildid, 'field_id' => $field['id'], 'data' => $data ]);
        }
      }
    }
    if($update) {
      $this->db->query("DELETE FROM custom_fields_data WHERE build_id='$buildid' AND id NOT IN(".implode(',', $fids).")");
    }
  }

  public function searchRenterBuilds($renterid)
  {
    $html = '';
    $results = $this->db->get_results("SELECT building.id,building.title FROM building INNER JOIN rent_contracts ON(rent_contracts.buildid=building.id) WHERE JSON_CONTAINS(JSON_EXTRACT(rent_contracts.buyer, '$.id[*]'), '\"$renterid\"', '$') AND building.active='1'");
    if($results){
      foreach($results as $result){
        $html .= '<option value="' . $result['id'] . '">' . '#' . $result['id'] . ' - ' . $result['title'] . '</option>';
      }
    }
    return $html;
  }

  public function searchBuyersBuilds($buyerid)
  {
    $html = '';
    $results = $this->db->get_results("SELECT building.id,building.title FROM building INNER JOIN sell_contracts ON(sell_contracts.buildid=building.id) WHERE JSON_CONTAINS(JSON_EXTRACT(sell_contracts.buyer, '$.id[*]'), '\"$buyerid\"', '$') AND building.active='1'");
    if($results){
      foreach($results as $result){
        $html .= '<option value="' . $result['id'] . '">' . '#' . $result['id'] . ' - ' . $result['title'] . '</option>';
      }
    }
    return $html;
  }

  public function searchOwnersBuilds($ownerid)
  {
    $html = '';
    $results = $this->db->get_results("SELECT id,title FROM building WHERE JSON_CONTAINS(JSON_EXTRACT(owner, '$.id[*]'), '\"$ownerid\"', '$') AND active='1'");
    if($results){
      foreach($results as $result){
        $html .= '<option value="' . $result['id'] . '">' . '#' . $result['id'] . ' - ' . $result['title'] . '</option>';
      }
    }
    return $html;
  }

  public function searchQuery($q, $type = '', $free = '')
  {
    if($type == 'rent'){
      $type = "AND type='rent'";
    } elseif($type == 'sell'){
      $type = "AND type='sale'";
    }
    if(!empty($free)){
      $free = "AND available='1'";
    }
    $results = $this->db->get_results($this->Paging("SELECT building.id,building.title,building.type,building_locs.location FROM building
    LEFT JOIN building_locs ON(building_locs.id=building.locid)
    WHERE (building.title LIKE '%$q%' OR building_locs.location LIKE '%$q%' OR building.id='$q') AND building.active='1' $type $free GROUP BY building.id"));
    $search = array();
    $search['results'] = array();
    if($results){
      foreach($results as $result) {
        if($result['type'] == 'rent'){
          $lastContract = $this->db->get_var("SELECT enddate FROM rent_contracts WHERE buildid='$result[id]' AND enddate>NOW() ORDER BY enddate DESC LIMIT 1");
          if($lastContract){
            $result['title'] .= ' ['.gettext('متاح بعد').' ' . (($this->config['system_calendar'] == 2) ? uCal::g2u($lastContract) : $lastContract) . ']';
          }
        }
        $search['results'][] = array('id' => $result['id'], 'text' => '#' . $result['id'] . ' - ' . $result['location'] . ' ' . $result['title']);
      }
    }
    $search['pagination'] = ['more' => $_GET['callpage'] >= $this->paging['pages']  ? false : true];
    return $search;
  }

  public function addrecord($data)
  {
    return $this->db->insert('building', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('building', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    $building = $this->db->get_row("SELECT building.*,city.name AS city_name,district.name AS district_name,buyer_cats.title AS buyercat_name,building_cats.title AS buildcat_name FROM building
    LEFT JOIN city ON(city.id=building.citytid)
    LEFT JOIN district ON(district.id=building.district)
    LEFT JOIN building_cats ON(building_cats.id=building.buildcat)
    LEFT JOIN buyer_cats ON(buyer_cats.id=building.buyercat)
    WHERE building.id='$id'");
    $building['files'] = $this->db->get_results("SELECT * FROM attachments WHERE objectid='$id' AND moduleid='" . MODULE_ID . "'");
    $building['owner'] = json_decode($building['owner'], true);
    if(!empty($building['owner']['id'])){
      foreach($building['owner']['id'] as $key => $owner){
        $ownername = $this->db->get_row("SELECT id,fname,lname,fathname,famname,mobile,idnumber FROM owner WHERE id='" . $building['owner']['id'][$key] . "'");
        $building['owner']['name'][$key] = '#' . $ownername['id'] . ' - ' . $ownername['fname'] . ' ' . $ownername['fathname'] . ' ' . $ownername['lname'] . ' ' . $ownername['famname'];
        $building['owner']['fullname'][$key] = $ownername['fname'] . ' ' . $ownername['fathname'] . ' ' . $ownername['lname'] . ' ' . $ownername['famname'];
        $building['owner']['mobile'][$key] = $ownername['mobile'];
        $building['owner']['idnumber'][$key] = $ownername['idnumber'];
        $building['owners'][$key] = $ownername['id'];
      }
    }
    $building['plots'] = json_decode($building['plots'], true);
    $building['location'] = json_decode($building['location'], true);
    $building['deed'] = json_decode($building['deed'], true);
    if(!empty($building['deed']['typeid'])){
      foreach($building['deed']['typeid'] as $key => $typeid){
        $building['deed']['type_name'][$key] = $this->db->get_var("SELECT title FROM deed_types WHERE id='$typeid'");
      }
    }
    $building['bids'] = json_decode($building['bids'], true);
    $building['meters'] = json_decode($building['meters'], true);
    if(!empty($building['meters']['typeid'])){
      foreach($building['meters']['typeid'] as $key => $typeid){
        $building['meters']['type_name'][$key] = $this->db->get_var("SELECT title FROM meter_types WHERE id='$typeid'");
        $building['meters']['owner_type_name'][$key] = ($building['meters']['owner_type'][$key] == 'private') ? gettext('خاص') : gettext('مشترك') ;
      }
    }
    $building['typeid'] = $building['type'];
    $building['districtid'] = $building['district'];
    $building['district'] = $building['district_name'];
    $building['taxes'] = 0;
    $building['parent_build'] = '';
    if(!empty($building['locid'])){
      $building['parent_build'] = $this->db->get_var("SELECT title FROM building WHERE id='$building[locid]'");
    }
    if($building['tax'] == 'habit'){
      $building['taxes'] = ($this->config['habit_tax_status'] == 1) ? $this->config['habit_tax_value'] : 0;
      $building['type_name'] = gettext('سكني');
    } elseif($building['tax'] == 'comm'){
      $building['taxes'] = ($this->config['comm_tax_status'] == 1) ? $this->config['comm_tax_value'] : 0;
      $building['type_name'] = gettext('تجاري');
    }

    if($building['furnished'] == 0){
      $building['furnished_text'] = '--';
    } elseif($building['furnished'] == 1){
      $building['furnished_text'] = gettext('نعم');
    } elseif($building['furnished'] == 2){
      $building['furnished_text'] = gettext('لا');
    }
    return $building;
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT building.*,building_locs.location AS m_location FROM building LEFT JOIN building_locs ON(building_locs.id=building.locid) $inner $where ORDER BY building.id DESC";
    if($paging){
      $sql = $this->Paging($sql, 10);
    }
    $results = $this->db->get_results($sql);
    if($results){
      foreach($results as $key => $result){
        $results[$key]['owner'] = json_decode($results[$key]['owner'], true);
        if(!empty($results[$key]['owner']['id'])){
          foreach($results[$key]['owner']['id'] as $key2 => $owner){
            $ownername = $this->db->get_row("SELECT id,fname,lname,fathname,famname FROM owner WHERE id='" . $results[$key]['owner']['id'][$key2] . "'");
            $results[$key]['owner']['name'][$key2] = '#' . $ownername['id'] . ' - ' . $ownername['fname'] . ' ' . $ownername['fathname'] . ' ' . $ownername['lname'] . ' ' . $ownername['famname'];
            $results[$key]['ownerinfo'][$key2] = $ownername;
          }
        }
        $results[$key]['location'] = json_decode($results[$key]['location'], true);
        if($result['type'] == 'rent'){
          $lastContract = $this->db->get_var("SELECT enddate FROM rent_contracts WHERE buildid='$result[id]' AND ended='0' AND enddate>NOW() ORDER BY enddate DESC LIMIT 1");
          if($lastContract){
            $results[$key]['freein'] = $lastContract;
          }
        }
      }
    }
    return $results;
  }

}
