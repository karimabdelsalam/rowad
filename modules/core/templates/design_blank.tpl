<!DOCTYPE html>
<!--[if lt IE 7]> <html class="ie lt-ie9 lt-ie8 lt-ie7 "> <![endif]-->
<!--[if IE 7]>    <html class="ie lt-ie9 lt-ie8 "> <![endif]-->
<!--[if IE 8]>    <html class="ie lt-ie9 "> <![endif]-->
<!--[if gt IE 8]> <html class="ie "> <![endif]-->
<!--[if !IE]><!-->
<html class="">
  <!-- <![endif]-->
  <head>
    <title>{if $smarty.session.lang.dir eq "rtl"}{$config.sitename|clean}{else}{$config.en_sitename|clean}{/if} | {$pagetitle}</title>
    <!-- Meta -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimum-scale=1.0, maximum-scale=1.0">
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
    <link rel="shortcut icon" type="image/x-icon" href="{$MEDIAURL}/{$config.favicon}">
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
  <body class="loginWrapper">
    <div class="container-fluid menu-hidden">
      <div id="content" style="padding-top:0;">
        <div class="container">
        {$template_output}
        </div>
      </div>
    </div>
    <!-- Global -->
    <link rel="stylesheet" href="{$css_path}/shared.css?version={$smarty.const.VERSION}" />
    <script src="{$js_path}/bootstrap.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/jquery.ui.widget.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/jquery.nicescroll.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/bootstrap-switch.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/breakpoints.js?version={$smarty.const.VERSION}"></script>
    {if $smarty.session.lang.dir eq "rtl"}
      <script src="{$js_path}/select2.rtl.js?version={$smarty.const.VERSION}"></script>
    {else}
      <script src="{$js_path}/select2.js?version={$smarty.const.VERSION}"></script>
    {/if}
    <script src="{$js_path}/pace.min.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/ajax.from.js?version={$smarty.const.VERSION}"></script>
    <script src="{$js_path}/jquery.validate.min.js?version={$smarty.const.VERSION}"></script>
    {$localization}
    <script src="{$js_path}/init.js?version={$smarty.const.VERSION}"></script>
    {foreach from=$enqueuejs item=jsfile}
      <script src="{$js_path}/{$jsfile|clean}.js?version={$smarty.const.VERSION}"></script>
    {/foreach}
    {if !empty($config.csscode)}<style>{$config.csscode}</style>{/if}
    {$customJavascript}
  </body>
</html>
