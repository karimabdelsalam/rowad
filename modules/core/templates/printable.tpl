<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>{$cuaction.title|gettext|clean}</title>
</head>

{literal}
<style type="text/css">
body{
direction:rtl;
font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
font-size: 13px;
font-weight: bold;
}
span{
font-family: Arial;
}
table{
direction:rtl;
width: 800px;
border-collapse: collapse;
margin:auto;
}
th{
font-size: 15px;
width:25%;
padding: 3px;
border:1px solid #000;
}
td{
padding: 3px;
border:1px solid #000;
}
.center{
text-align:center;
}
.right{
text-align:right;
}
.no-print, .uniformjs, .no-print *, .uniformjs *, button, input
{
  display: none !important;
}
.chartGraphs{width: 600px;margin:auto;text-align: center;}
</style>
{/literal}

<body>

{$template_output}

<script>
setTimeout(function () {
  {if $smarty.const.DEBUG_MODE neq "localhost"}window.print();{/if}
}, 2000);
</script>
{$localization}
{foreach from=$enqueuecss item=cssfile}
  <link rel="stylesheet" href="{$css_path}/{$cssfile|clean}.css?version={$smarty.const.VERSION}" />
{/foreach}
{foreach from=$enqueuejs item=jsfile}
  <script src="{$js_path}/{$jsfile|clean}.js?version={$smarty.const.VERSION}"></script>
{/foreach}
{$customJavascript}
</body>
</html>
