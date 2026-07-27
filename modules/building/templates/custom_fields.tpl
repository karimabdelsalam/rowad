{foreach $fieldcats as $fieldcat}
<div class="row innerLR">
  <h4 class="innerAll bg-container margin-none"><i class="fa fa-bars"></i> {$fieldcat.title}</h4>
  <div class="separator"></div>
  <div class="col-md-4">
    {foreach from=$fieldcat.fields key=loop item=field name=op}
    <div class="form-group">
      <label class="col-md-3 control-label">{$field.title}</label>
      {if $field.type eq "checkbox"}
        <div class="col-md-6">
          <input class="ckeckonoff" name="cfields[{$field.id}]" value="{$field.value}" type="checkbox" />
        </div>
      {else}
        <div class="col-md-6 {if !empty($field.suffix)}input-group{/if}">
          <input class="form-control" name="cfields[{$field.id}]" value="{$field.value}" placeholder="{$field.description}" type="text" />
            {if !empty($field.suffix)}<span class="input-group-addon">{$field.suffix}</span>{/if}
        </div>
      {/if}
    </div>
    {if ($loop+1) % $fieldcat.splitno eq 0}</div><div class="col-md-4">{/if}
    {/foreach}
  </div>
</div>
{foreachelse}
  <div class="alert alert-info text-center">
      {"لا يوجد حقول إضافية لهذه الفئة من العقارات"|gettext} <a href="{$CPURL}/custom_fields/categories/add" class="btn btn-default"><i class="fa fa-plus-circle"> {"اضغط هنا لإنشاء حقول إضافية"|gettext}</i></a>
  </div>
{/foreach}
