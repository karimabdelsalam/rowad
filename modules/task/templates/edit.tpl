<form action="" class="form-horizontal margin-none form-ajax" method="post">
<input type="hidden" name="id" value="{$smarty.get.id}" />
    <div class="widget">
        <div class="widget-head">
            <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean}</h4>
        </div>
        <div class="widget-body innerAll">
            <div class="row innerLR">
                <div class="col-md-8">
                    <div class="form-group no-border-space">
                        <label class="col-md-3 control-label">{"التوجيه إلي"|gettext}</label>
                        <div class="col-md-6">
                            <select class="form-control" name="tomanagerid">
                            {foreach from=$managers item=manager}
                                <option value="{$manager.id}" {if $manager.id eq $data.tomanagerid}selected="selected"{/if}>{$manager.name|clean}</option>
                            {/foreach}
                            </select>
                        </div>
                    </div>
                    <div class="form-group no-border-space">
                        <label class="col-md-3 control-label">{"المهمه"|gettext}</label>
                        <div class="col-md-8">
                            <textarea class="form-control" name="content" rows="8">{$data.content|clean}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="separator"></div>
            <div class="innerAll border-top">
                <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ التغييرات"|gettext}</button>
                <button type="reset" class="btn btn-default"><i class="fa fa-times"></i> {"استرجاع"|gettext}</button>
            </div>
        </div>
    </div>
</form>
