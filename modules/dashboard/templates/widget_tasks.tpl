<!-- Widget -->
<div class="widget overflow-hidden">
    <h4 class="innerAll bg-gray border-bottom margin-bottom-none"><i class="fa fa-tasks"></i> {"المهام"|gettext}</h4>
    <div class="widget-body">
        <div class="innerB">
            <ul class="chat media-list border-list">
            {foreach from=$tasks item=task}
                <li class="media">
                    <img class="media-object thumb pull-left" src="{$CPURL}/home/resize/50/50/{$task.picture|clean}/" alt="" width="50" />
                    <div class="media-body">
                        <div class="btn-group btn-group-xs pull-right arfont">
                          <button class="btn btn-default">{$task.timepost|Period}</button>
                          <button class="btn btn-success delete-ajax" data-parent="li" href="{$CPURL}/{$cumodule.name|clean}/index/?done={$task.id}">
                          <i class="fa fa-check-circle-o"></i> {"إنهاء"|gettext}</button>
                        </div>
                        <h5 class="media-heading">{$task.name|clean}</h5>
                        <p class="margin-none">{$task.content|nl2br}</p>
                    </div>
                </li>
            {foreachelse}
                <li class="innerTB half text-center">{"لا مهام اليوم !"|gettext}</li>
            {/foreach}
            </ul>
        </div>
    </div>
</div>
<!-- //Widget -->