{if empty($smarty.request.no_header)}
<!-- Widget -->
<div class="widget overflow-hidden">
    <h4 class="innerAll bg-gray border-bottom margin-bottom-none"><i class="fa fa-bookmark"></i> {if empty($title)}{"ملاحظات"|gettext}{else}{$title}{/if}</h4>
    <div class="widget-body">
        <div class="innerB">
            <ul class="chat media-list border-list" id="myNotesWidget">
{/if}
            {foreach from=$notes item=note}
                <li class="media">
                    <img class="media-object thumb pull-left" src="{$CPURL}/home/resize/50/50/{$note.picture|clean}/" alt="" width="50" />
                    <div class="media-body">
                        <div class="btn-group btn-group-xs pull-right arfont">
                          <button class="btn btn-default">{$note.timepost|Period}</button>
                          <button class="btn btn-danger delete-ajax" data-parent="li" href="{$CPURL}/{$cumodule.name|clean}/index/?note={$note.id}">
                          <i class="fa fa-trash-o"></i> {"حذف"|gettext}</button>
                        </div>
                        <h5 class="media-heading">{$note.name|clean}</h5>
                        <p class="margin-none">{$note.content|nl2br}</p>
                    </div>
                </li>
            {foreachelse}
                <li class="innerTB half text-center">{"ابدأ الآن في اضافة ملاحظاتك"|gettext}</li>
            {/foreach}
{if empty($smarty.request.no_header)}
            </ul>
        </div>
        <button href="{$CPURL}/{$cumodule.name|clean}/index/?do=newnote" title="{"اضافة ملاحظة جديدة"|gettext}" class="btn btn-sm btn-success open-ajax"><i class="fa fa-plus fa-fw"></i> {"اضافة ملاحظة جديدة"|gettext}</button>
    </div>
</div>
<!-- //Widget -->
{/if}