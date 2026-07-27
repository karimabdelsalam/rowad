<!-- Widget -->
<a name="notifications"></a>
<div class="widget overflow-hidden">
    <h4 class="innerAll bg-gray border-bottom margin-bottom-none"><i class="fa fa-bell"></i> {"التنبيهات"|gettext}</h4>
    <div class="widget-body">
        <div class="{if empty($notifications)}innerB{/if}">
            <ul class="chat media-list border-list" id="myNotifysWidget">
            {foreach from=$notifications item=notify}
                <li class="media" style="overflow: inherit">
                    <img class="media-object thumb pull-left" src="{$image_path}/{$notify.icon}.png" alt="" width="50" />
                    <div class="media-body" style="overflow: inherit">
                        <div class="btn-group btn-group-xs pull-right arfont">
                          <button class="btn btn-default">{$notify.created_time|Period}</button>
                          <button class="btn btn-default delete-ajax" data-parent="li" href="{$CPURL}/{$cumodule.name|clean}/index/?notification={$notify.id}">
                          <i class="fa fa-eye" title="{"جعلها مقرؤة"|gettext}" data-toggle="tooltip" data-placement="bottom"></i></button>
                        </div>
                        <h5 class="media-heading">{if $notify.read_state eq 0}<i class="fa fa-circle"></i>{/if} {$notify.subject|clean}</h5>
                        <p class="margin-none">{$notify.content|nl2br}</p>
                    </div>
                </li>
            {foreachelse}
                <li class="innerTB half text-center">{"صندوق التنبيهات فارغ"|gettext}</li>
            {/foreach}
            </ul>
            {if !empty($notifications)}
                <a href="{$CPURL}/{$cumodule.name}/index/?do=mark_all_read" class="btn btn-block btn-default instantReq" style="margin-top: 15px" data-load="{"جارى المعالجة"|gettext}" data-success="{"تم تنفيذ طلبك بنجاح"|gettext}"><i class="fa fa-check-circle"></i> {"تعيين كل التنبيهات مقروءة"|gettext}</a>
            {/if}
        </div>
    </div>
</div>
<!-- //Widget -->
