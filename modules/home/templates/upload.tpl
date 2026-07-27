<div class="widget">
    <div class="widget-head">
        <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body">
        <div id="dropzone">
            <form action="{$CPURL}/{$cumodule.name|clean}/{$cuaction.name|clean}" class="dropzone">
                <div class="fallback">
                    <input name="file" type="file" multiple />
                </div>
            </form>
        </div>
    </div>
</div>
