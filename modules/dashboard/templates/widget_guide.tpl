<div class="how-steps">
  <h4 class="heading mt-10 mb-10 f-600"><i class="fa fa-question-circle"></i> {"كيف تبدأ استخدام النظام"|gettext}</h4>
  <div class="filter-bar innerAll inner-2x">
    <div class="flex-column">
      <div class="flex-center mb-10"><span class="number-plate">1</span>
        <h4 class="{if !empty($config.address1)}lined-text{/if} f-400">
          <a href="{$CPURL}/home/settings">{"مراجعة اعدادات النظام و معلومات الشركة"|gettext}</a>
        </h4>
      </div>
      <div class="flex-center mb-10"><span class="number-plate">2</span>
        <h4 class="{if $stats.officeOwners gt 0}lined-text{/if} f-400">
          <a href="{$CPURL}/home/owners">{"تعديل بيانات مالك او شركاء المنشأة"|gettext}</a>
        </h4>
      </div>
      <div class="flex-center mb-10"><span class="number-plate">3</span>
        <h4 class="{if $stats.owners gt 0}lined-text{/if} f-400">
          <a href="{$CPURL}/owner/add">{"اضافة مالك عقار"|gettext}</a>
        </h4>
      </div>
      <div class="flex-center mb-10"><span class="number-plate">4</span>
        <h4 class="{if $stats.builds gt 0}lined-text{/if} f-400">
          <a href="{$CPURL}/building/add">{"انشاء وحدة عقارية"|gettext}</a>
        </h4>
      </div>
      <div class="flex-center mb-10"><span class="number-plate">5</span>
        <h4 class="{if $stats.renters gt 0}lined-text{/if} f-400">
          <a href="{$CPURL}/buyer/add">{"اضافة عميل جديد"|gettext}</a>
        </h4>
      </div>
      <div class="flex-center mb-10"><span class="number-plate">6</span>
        <h4 class="{if $stats.contracts gt 0}lined-text{/if} f-400">
          <a href="{$CPURL}/rent/add">{"انشاء عقد ايجار"|gettext}</a>
        </h4>
      </div>
    </div>
  </div>
</div>
