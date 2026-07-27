<!-- Widget -->
<div class="widget overflow-hidden">
    <h4 class="innerAll bg-gray border-bottom margin-bottom-none"><i class="fa fa-shield"></i> {"محاولات تسجيل الدخول"|gettext} </h4>
    <div class="widget-body">
        <div class="innerB">
            <ul class="chat media-list border-list">
            {foreach from=$login_activities item=login_activity}
                <li class="media">
                    {$MEDIA_PATH}
                    {if $login_activity.type eq "mobile"}
                    <img class="media-object thumb pull-left" src="{$image_path}/phone.png" title="{"جوال"|gettext}" data-toggle="tooltip" alt="" width="50" />
                    {else}
                    <img class="media-object thumb pull-left" src="{$image_path}/pc.png" title="{"جهاز كمبيوتر"|gettext}" data-toggle="tooltip" alt="" width="50" />
                    {/if}
                    <div class="media-body">
                        <div class="btn-group btn-group-xs pull-right arfont">
                          <button class="btn btn-default">{$login_activity.login_time|Period}</button>
                        </div>
                        <h5 class="media-heading" style="margin-bottom: 14px">
                            <span title="{"اي بي المستخدم"|gettext}" data-toggle="tooltip" style="font-family:"Courier";font-size: 13px;font-weight: bold">{$login_activity.ip}</span>
                        </h5>
                        <p class="margin-none ltr">
                            <i class="fa fa-desktop" title="{$login_activity.os}" data-toggle="tooltip"></i>&nbsp;
                            <i class="fa fa-internet-explorer" title="{$login_activity.browser}" data-toggle="tooltip"></i>&nbsp;
                            <i class="fa fa-globe" title="{$login_activity.country}" data-toggle="tooltip"></i>&nbsp;
                            <i class="fa fa-street-view" title="{$login_activity.city}" data-toggle="tooltip"></i>&nbsp;
                            <a href="https://www.google.com/maps?q=loc:{$login_activity.lat},{$login_activity.lng}" target="_blank">
                                <i class="fa fa-map-marker" title="{"عرض على الخريطة"|gettext}" data-toggle="tooltip"></i>
                            </a>
                        </p>
                    </div>
                </li>
            {foreachelse}
                <li class="innerTB half text-center">{"لا يوجد محاولات دخول مسجلة"|gettext}</li>
            {/foreach}
            </ul>
        </div>
        <a href="{$CPURL}/{$cumodule.name|clean}/index/?do=secure_logout" class="btn btn-sm btn-danger"><i class="fa fa-user-secret fa-fw"></i>{"تسجيل الخروج من جميع الأجهزة"|gettext}</a>
    </div>
</div>
<!-- //Widget -->
