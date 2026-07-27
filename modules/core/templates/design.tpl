<!DOCTYPE html>
<!--[if lt IE 7]> <html class="ie lt-ie9 lt-ie8 lt-ie7 sidebar sidebar-collapse"> <![endif]-->
<!--[if IE 7]>    <html class="ie lt-ie9 lt-ie8 sidebar sidebar-collapse"> <![endif]-->
<!--[if IE 8]>    <html class="ie lt-ie9 sidebar sidebar-collapse"> <![endif]-->
<!--[if gt IE 8]> <html class="ie sidebar sidebar-collapse"> <![endif]-->
<!--[if !IE]><!-->
<html class="sidebar sidebar-collapse">
  <!-- <![endif]-->
  <head>
    <title>{$cumodule.title|gettext|clean} | {$cuaction.title|gettext|clean}</title>
    <!-- Meta -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="{$MEDIAURL}/{$config.favicon}">
    <!--[if lt IE 9]><link rel="stylesheet" href="{$css_path}/bootstrap.min.css" /><![endif]-->

    {if $smarty.session.lang.dir eq "rtl"}
      {if $config.style eq "classic"}
      <link rel="stylesheet" href="{$css_path}/style.rtl.css?version={$smarty.const.VERSION}" />
      {else}
      <link rel="stylesheet" href="{$css_path}/style_modern.rtl.css?version={$smarty.const.VERSION}" />
      {/if}
    {else}
      {if $config.style eq "classic"}
        <link rel="stylesheet" href="{$css_path}/style.css?version={$smarty.const.VERSION}" />
      {else}
        <link rel="stylesheet" href="{$css_path}/style_modern.css?version={$smarty.const.VERSION}" />
      {/if}
    {/if}
    <link rel="stylesheet" href="{$css_path}/font-awesome.min.css?version={$smarty.const.VERSION}" />
    {foreach from=$enqueuecss item=cssfile}
      <link rel="stylesheet" href="{$css_path}/{$cssfile|clean}.css?version={$smarty.const.VERSION}" />
    {/foreach}
    {if $smarty.session.lang.dir eq "ltr"}
      <link rel="stylesheet" href="{$css_path}/ltr.css?version={$smarty.const.VERSION}" />
    {/if}
    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
    <![endif]-->
    <script src="{$js_path}/jquery.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/jquery-migrate.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/modernizr.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/less.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/excanvas.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/ie.prototype.polyfill.js?version={$smarty.const.VERSION}"></script>
    <script>
      if (/*@cc_on!@*/ false && document.documentMode === 10)
      {
        document.documentElement.className += ' ie ie10';
      }
    </script>
  </head>
  <body class="">
    <!-- Main Container Fluid -->
    <div class="container-fluid">
      <!-- Sidebar Menu -->
      <div id="menu" class="sidebar-white hidden-print hidden-xs">
        <div id="sidebar-collapse-wrapper">
          <div id="brandWrapper">
            <a href="{$CPURL}" class="display-block-inline pull-left logo">
              <img src="{$CPURL}/home/resize/32/33/{$config.logo}/" alt="">
            </a>
            <a href="{$CPURL}">
              <span class="text">{if $smarty.session.lang.dir eq "rtl"}{$config.sitename|clean}{else}{$config.en_sitename|clean}{/if}</span>
            </a>
          </div>
          <div id="logoWrapper">
            <div id="logo">
              <a href="{$CPURL}" class="btn btn-sm btn-inverse"><i class="fa fa-fw fa-home"></i></a>
              {if $smarty.session.userinfo.groupid eq 1}
              <a href="{$CPURL}/dashboard/index#tasks" class="btn btn-sm btn-inverse"><i class="fa fa-fw fa-tasks"></i><span class="badge pull-right badge-primary">{$navabrnotifys|@count}</span></a>
              <a href="{$CPURL}/manager/logs/index" class="btn btn-sm btn-inverse"><i class="fa fa-fw fa-book"></i><span class="badge pull-right badge-primary"></span></a>
              {/if}
              <a href="{$CPURL}/login/logout" class="btn btn-sm btn-inverse pull-right"><i class="fa fa-fw fa-sign-out"></i></a>
            </div>
          </div>
          <ul class="menu list-unstyled">
            {foreach from=$menus item=menu}
              <li class="hasSubmenu {if $smarty.session.userinfo.groupid neq 1}active{/if}">
                {if $smarty.session.userinfo.groupid eq 1}
                <a href="#sidebar-nav-{$menu.name|clean}" class="glyphicons {$menu.icon|clean}" data-toggle="collapse">
                  <i></i>
                  <span>{$menu.title|gettext|clean}</span>
                </a>
                {else}
                <a href="#sidebar-nav-cp-name" class="text-center padding-none">
                  <span>{"مرحبا,"|gettext} {$smarty.session.userinfo.name}</span>
                </a>
                    {if $smarty.session.adminid gt 0}
                      <p class="bg-danger text-center padding-none m-0 p-5"><a href="{$CPURL}/dashboard/masklogout">{"الرجوع الى لوحة تحكم المدراء"|gettext}</a></p>
                    {/if}
                {/if}
                <ul id="sidebar-nav-{$menu.name|clean}" class="{if $smarty.session.userinfo.groupid eq 1}collapse{/if}">
                    {foreach from=$menu.actions item=action}
                      {if $action.submodule neq 0}
                        <li {if $cuaction.id eq $action.id}class="activeMenu"{/if}><a href="{$CPURL}/{$action.module|clean}/{$action.name|clean}"><i class="fa fa-fw fa-{$action.icon|clean}"></i> {$action.title|gettext|clean}</a></li>
                      {else}
                        <li {if $cuaction.id eq $action.id}class="activeMenu"{/if}><a href="{$CPURL}/{$menu.name|clean}/{$action.name|clean}"><i class="fa fa-fw fa-{$action.icon|clean}"></i> {$action.title|gettext|clean}</a></li>
                      {/if}
                    {/foreach}
                    {foreach from=$menu.modules item=module}
                      {if count($module.actions) eq 1}
                        {foreach from=$module.actions item=action}
                          {if $action.submodule neq 0}
                            <li {if $cuaction.id eq $action.id}class="activeMenu"{/if}>
                              <a href="{$CPURL}/{$action.module|clean}/{$action.name|clean}"><i class="fa fa-fw fa-{$module.icon|clean}"></i> {$module.title|gettext|clean}
                                    {if !empty($module.info)} <i class="fa fa-info-circle side-menu-info" title="{$module.info|gettext}" data-toggle="tooltip"></i>{/if}
                              </a></li>
                          {else}
                            <li {if $cuaction.id eq $action.id}class="activeMenu"{/if}><a href="{$CPURL}/{$module.name|clean}/{$action.name|clean}"><i class="fa fa-fw fa-{$module.icon|clean}"></i> {$module.title|gettext|clean}</a></li>
                          {/if}
                        {/foreach}
                      {else}
                      <li class="hasSubmenu">
                        <a href="#sidebar-subnav-{$module.id|clean}" data-toggle="collapse">
                          <i class="fa fa-fw fa-{$module.icon|clean}"></i>
                          {$module.title|gettext|clean}
                        </a>
                        <ul id="sidebar-subnav-{$module.id|clean}" class="collapse">
                          {foreach from=$module.actions item=action}
                            {if $action.submodule neq 0}
                              <li {if $cuaction.id eq $action.id}class="activeMenu activeSubMenu"{/if}><a href="{$CPURL}/{$action.module|clean}/{$action.name|clean}"><i class="fa fa-fw fa-{$action.icon|clean}"></i> {$action.title|gettext|clean}</a></li>
                            {else}
                              <li {if $cuaction.id eq $action.id}class="activeMenu activeSubMenu"{/if}><a href="{$CPURL}/{$module.name|clean}/{$action.name|clean}"><i class="fa fa-fw fa-{$action.icon|clean}"></i> {$action.title|gettext|clean}</a></li>
                            {/if}
                          {/foreach}
                        </ul>
                      </li>
                      {/if}
                    {/foreach}
                </ul>
              </li>
            {/foreach}
          </ul>
        </div>
      </div>
      <!-- // Sidebar Menu END -->
      <!-- Content -->
      <div id="content">
        <nav class="navbar hidden-print main " role="navigation">
          <div class="smartiocp-loading"><img src="{$image_path}/loading.gif" alt="" /></div>
          {if $smarty.session.userinfo.groupid eq 1}
          <div class="navbar-header pull-left">
            <div class="user-action user-action-btn-navbar pull-left border-right">
              <button class="btn btn-sm btn-navbar btn-inverse btn-stroke"><i class="fa fa-bars fa-2x"></i>
              </button>
            </div>
          </div>
          {/if}
          <ul class="main pull-right">
            <li class="dropdown notif notifications hidden-xs">
              <a href="" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-bullhorn"></i> <span class="label label-danger">{$navabrnotifys|@count}</span></a>
              <ul class="dropdown-menu chat media-list pull-right">
                {foreach from=$navabrnotifys item=notif}
                  <li class="media arfont">
                    <img class="media-object thumb pull-left" src="{$image_path}/{$notif.icon}.png" alt="" width="50" />
                    <div class="media-body">
                      <span class="label label-default pull-right">{$notif.created_time|Period}</span>
                      <h5 class="media-heading">{$notif.subject|clean}</h5>
                      <p class="margin-none">{$notif.content}</p>
                    </div>
                  </li>
                {foreachelse}
                  <li class="innerTB half text-center">{"صندوق التنبيهات فارغ"|gettext}</li>
                {/foreach}
                  <li><a href="{$CPURL}/dashboard/#notifications" class="btn btn-primary"><i class="fa fa-list"></i> <span>{"عرض جميع التنبيهات"|gettext}</span></a></li>
              </ul>
            </li>
            <li class="dropdown username">
              <a href="" class="dropdown-toggle" data-toggle="dropdown">
                <img src="{$image_path}/lang.png" class="img-circle" width="30" /> <span class="mdText">{"لغة النظام"|gettext}</span>
                <span class="caret"></span>
              </a>
              <ul class="dropdown-menu pull-right">
                <li><a href="{$CPURL}/dashboard/lang/?code=en" class="glyphicons show_lines"><i></i> English</a></li>
                <li><a href="{$CPURL}/dashboard/lang/?code=ar" class="glyphicons show_lines no-ajaxify"><i></i>اللغة العربية</a>
                </li>
              </ul>
            </li>
            <li class="dropdown username">
              <a href="" class="dropdown-toggle" data-toggle="dropdown">
                <img src="{$CPURL}/home/resize/30/30/{$smarty.session.userinfo.picture|clean}/" class="img-circle" width="30" /> <span class="mdText">{$smarty.session.userinfo.name|clean}</span>
                <span class="caret"></span>
              </a>
              <ul class="dropdown-menu pull-right">
                {if $smarty.session.userinfo.groupid eq 1}
                <li><a href="{$SITEURL}/{$smarty.const.OWNER_DIR_PATH}" class="glyphicons briefcase"><i></i> {"لوحة تحكم المالك"|gettext}</a></li>
                <li><a href="{$SITEURL}/{$smarty.const.CLIENT_DIR_PATH}" class="glyphicons home"><i></i> {"لوحة تحكم العملاء"|gettext}</a></li>
                {/if}
                <li><a href="{$CPURL}/dashboard/myaccount" class="glyphicons user"><i></i> {"تعديل حسابي"|gettext}</a></li>
                <li><a href="{$CPURL}/login/logout" class="glyphicons lock no-ajaxify"><i></i>{"تسجيل الخروج"|gettext}</a>
                </li>
              </ul>
            </li>
          </ul>
          <div class="navbar-collapse">
            <ul class="nav navbar-nav">
                {foreach from=$menus item=submenus}
                {foreach from=$submenus.modules item=menu}
                  {if $menu.shortcut eq 1}
                  <li class="dropdown">
                    <a data-toggle="dropdown" class="dropdown-toggle" href="">{$menu.title|gettext|clean} <span class="caret"></span></a>
                    <ul class="dropdown-menu">
                      {foreach from=$menu.actions item=action}
                        {if $action.submodule neq 0}
                          <li><a href="{$CPURL}/{$action.module|clean}/{$action.name|clean}">{$action.title|gettext|clean}</a></li>
                          {else}
                          <li><a href="{$CPURL}/{$menu.name|clean}/{$action.name|clean}">{$action.title|gettext|clean}</a></li>
                          {/if}
                        {/foreach}
                    </ul>
                  </li>
                {/if}
              {/foreach}
              {/foreach}
            </ul>
          </div>
        </nav>
        <!-- // END navbar -->
        <h3 class="margin-none">
          {$cumodule.title|gettext|clean}
          {foreach from=$gotomenus item=gotomenu}
            {if ($gotomenu.icon eq "plus-circle" OR $gotomenu.icon eq "edit" OR $gotomenu.name eq "index" OR $gotomenu.name eq "multiadd") AND $cuaction.name neq $gotomenu.name}
            <a href="{$CPURL}/{$gotomenu.module|clean}/{$gotomenu.name|clean}{if $gotomenu.param neq ""}?{$gotomenu.param}={$smarty.get[$gotomenu['param']]}{/if}" class="btn btn-add {if $gotomenu.ajax eq 1}open-ajax{/if}"><i class="fa fa-fw fa-{$gotomenu.icon|clean}"></i> {$gotomenu.title|gettext|clean}</a>
            {/if}
          {/foreach}
        </h3>
        <div class="clearfix"></div>
        <div class="innerLR">
          {$template_output}
        </div>
        <!-- // Content END -->
        <div class="clearfix"></div>
        <!-- // Sidebar menu & content wrapper END -->
      </div>
    </div>

    <!-- Blueimp Gallery -->
    <div id="blueimp-gallery" class="blueimp-gallery blueimp-gallery-controls">
      <div class="slides"></div>
      <h3 class="title"></h3>
      <a class="prev no-ajaxify">‹</a>
      <a class="next no-ajaxify">›</a>
      <a class="close no-ajaxify">×</a>
      <a class="play-pause no-ajaxify"></a>
      <ol class="indicator"></ol>
    </div>
    <!-- // Main Container Fluid END -->
    <div class="modal fade" id="open-quick-form">
      <div class="modal-dialog">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <div class="open-quick-body innerAll"></div>
      </div>
    </div>
    <div class="modal fade" id="open-quick-form-spare">
      <div class="modal-dialog">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <div class="open-quick-body-spare innerAll"></div>
      </div>
    </div>
    <div class="modal fade" id="open-ajax-form">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
            <h4 class="modal-title"></h4>
          </div>
          <div class="open-ajax-body innerAll"></div>
        </div>
      </div>
    </div>
    <div id="choiceModal" class="modal fade" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title"></h4>
          </div>
          <div class="modal-body innerAll">
            <p style="padding: 40px 10px"></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">{"لا"|gettext}</button>
            <button type="button" class="btn btn-primary choiceModalYes" data-dismiss="modal">{"نعم"|gettext}</button>
          </div>
        </div><!-- /.modal-content -->
      </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->
    <a href="" class="quick-form-trigger quick-form"></a>
    <a href="{$CPURL}/login" class="ajax-login-form-btn quick-form"></a>
    <!-- Global -->
    <link rel="stylesheet" href="{$css_path}/shared.css?version={$smarty.const.VERSION}" />
    <script src="{$js_path}/bootstrap.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/jquery.ui.widget.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/jquery.nicescroll.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/bootstrap-switch.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/daterangepicker.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/bootstrap-timepicker.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/breakpoints.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/jquery-labelauty.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/jquery.fancybox.pack.js?version={$smarty.const.VERSION}"></script>
    {if $smarty.session.lang.dir eq "rtl"}
    <script src="{$js_path}/select2.rtl.js?version={$smarty.const.VERSION}"></script>
    {else}
    <script src="{$js_path}/select2.js?version={$smarty.const.VERSION}"></script>
    {/if}
    <script src="{$js_path}/pace.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/ajax.from.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/jquery.validate.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/jquery.blueimp-gallery.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/ckeditor/ckeditor.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/ckeditor/adapters/jquery.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/calendar/jquery.plugin.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/calendar/jquery.calendars.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/calendar/jquery.calendars.plus.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/calendar/jquery.calendars.ummalqura.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/calendar/jquery.calendars.picker.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/calendar/jquery.calendars-ar.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/calendar/jquery.calendars.picker-ar.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/calendar/jquery.calendars.ummalqura-ar.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/calendar/moment.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/calendar/moment-hijri.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/sms_counter.min.js?version={$smarty.const.VERSION}"></script>
    {$localization}
    <script src="{$js_path}/init.js?version={$smarty.const.VERSION}"></script>
    {foreach from=$enqueuejs item=jsfile}
    <script src="{$js_path}/{$jsfile|clean}.js?version={$smarty.const.VERSION}"></script>
    {/foreach}
    {if !empty($config.csscode)}<style>{$config.csscode}</style>{/if}
    {$customJavascript}
  </body>
</html>
