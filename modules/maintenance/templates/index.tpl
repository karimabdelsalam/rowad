<div class="widget">
    <div class="widget-head">
        <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body">
      <table class="table">
          <thead>
              <tr>
                  <th class="center">{"الاداه"|gettext}</th>
                  <th>{"مستوي التقدم"|gettext}</th>
              </tr>
          </thead>
          <tbody>
              <tr>
                  <td class="shortRight"><button href="{$CPURL}/{$cumodule.name|clean}/{$cuaction.name|clean}/?tool=dbrepair" data-bar="database-tools-bar" class="btn btn-default do-progress"><i class="fa fa-medkit"></i> {"اصلاح وتحسين قاعدة البيانات"|gettext}</button></td>
                  <td>
                      <div class="progress">
                          <div class="progress-bar progress-bar-success database-tools-bar"></div>
                      </div>
                  </td>
              </tr>
              <tr>
                  <td class="shortRight"><button href="{$CPURL}/{$cumodule.name|clean}/{$cuaction.name|clean}/?tool=backup" data-bar="backup-tools-bar" class="btn btn-default do-progress"><i class="fa fa-ambulance"></i> {"ارسال نسخة احتياطية بريدياً"|gettext}</button></td>
                  <td>
                      <div class="progress">
                          <div class="progress-bar progress-bar-success backup-tools-bar"></div>
                      </div>
                  </td>
              </tr>
          </tbody>
      </table>
  </div>
</div>
