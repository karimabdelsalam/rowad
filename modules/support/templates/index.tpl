<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2">{"حالة الطلب"|gettext}</label>
      <div class="col-md-2">
        <select class="form-control" name="state">
          <option value="">{"غير محدد"|gettext}</option>
          <option value="seen" {if $smarty.get.state eq "seen"}selected="selected"{/if}>{"مغلق"|gettext}</option>
          <option value="unseen" {if $smarty.get.state eq "unseen"}selected="selected"{/if}>{"مفتوح"|gettext}</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"عنوان الطلب"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="subject" value="{$smarty.get.subject}" class="form-control" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"نوع الطلب"|gettext}</label>
      <div class="col-md-2">
        <select class="form-control" name="type">
          <option value="">{"غير محدد"|gettext}</option>
          <option value="request" {if $smarty.get.type eq "request"}selected="selected"{/if}>{"request"|coregate:"supportTypes"}</option>
          <option value="enquiry" {if $smarty.get.type eq "enquiry"}selected="selected"{/if}>{"enquiry"|coregate:"supportTypes"}</option>
          <option value="problem" {if $smarty.get.type eq "problem"}selected="selected"{/if}>{"problem"|coregate:"supportTypes"}</option>
          <option value="suggestion" {if $smarty.get.type eq "suggestion"}selected="selected"{/if}>{"suggestion"|coregate:"supportTypes"}</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"كود الطلب"|gettext}</label>
      <div class="col-md-2">
        <input type="text" name="code" value="{$smarty.get.code}" class="form-control" />
      </div>
    </div>
    <div class="row col-md-12">
      <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> {"بحث وتنقيح"|gettext}</button>
    </div>
    <div class="clearfix"></div>
  </form>
</div>
<div class="widget widget-body-white">
  <div class="widget-head">
    <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
  </div>
  <form action="{$CPURL}/{$cumodule.name}/" method="get">
    <div class="widget-body">
      <table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
        <thead>
          <tr>
            <th style="width: 1%;" class="uniformjs">
              <input type="checkbox" class="checkAll" />
            </th>
            <th class="center" style="width:80px">{"م"|gettext}</th>
            <th class="center" style="width:120px">{"الكود"|gettext}</th>
            <th class="center" style="width:80px">{"الحالة"|gettext}</th>
            <th>{"العنوان"|gettext}</th>
            <th class="center no-print" style="width: 150px;"></th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$results item=result}
            <tr class="selectable">
              <td class="center uniformjs">
                <input type="checkbox" name="ids[]" value="{$result.id}" />
              </td>
              <td class="center">{$result.id}</td>
              <td class="center"><span class="label label-primary">{$result.code}</span></td>
              <td class="center">{if $result.read_state eq 1}<i class="fa fa-circle" style="color: #cccccc"></i>{else}<i class="fa fa-circle"></i>{/if}</td>
              <td>
                <a href="{$CPURL}/{$cumodule.name}/show/?id={$result.id}"><strong>{$result.subject}</strong></a>
                  {if !empty($result.file)}<i class="fa fa-paperclip"></i>{/if}
              </td>
              <td class="text-left center no-print">
                {include file="core/templates/module_actions.tpl"}
              </td>
            </tr>
          {foreachelse}
            <tr class="warning"><td class="center" colspan="20">{"لا يوجد بيانات متاحة للعرض الآن"|gettext}</td></tr>
            {/foreach}
        </tbody>
      </table>
      <div class="pull-left checkboxs_actions hide-2">
        {include file="core/templates/module_multi_actions.tpl"}
      </div>
      {include file="core/templates/paging.tpl"}
      <div class="clearfix"></div>
    </div>
  </form>
</div>
