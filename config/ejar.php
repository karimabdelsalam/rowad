<?php

/*
 * Values Ejar's ECRS API accepts for property and unit registration. The specification shows one example
 * per field (al_riyadh, building, residential_families, apartment, furnish_new, north, finished, hojjat);
 * the rest follow Ejar's own naming. Kept here so a value Ejar names differently can be corrected in one
 * place after the first real registration, without touching the forms or the payloads.
 */
return [
    'regions' => [
        'al_riyadh' => 'منطقة الرياض',
        'makkah' => 'منطقة مكة المكرمة',
        'al_madinah' => 'منطقة المدينة المنورة',
        'al_qassim' => 'منطقة القصيم',
        'eastern_province' => 'المنطقة الشرقية',
        'asir' => 'منطقة عسير',
        'tabuk' => 'منطقة تبوك',
        'hail' => 'منطقة حائل',
        'northern_borders' => 'منطقة الحدود الشمالية',
        'jazan' => 'منطقة جازان',
        'najran' => 'منطقة نجران',
        'al_bahah' => 'منطقة الباحة',
        'al_jawf' => 'منطقة الجوف',
    ],

    // city name (as offices write it) => region, to preselect the region
    'city_regions' => [
        'الرياض' => 'al_riyadh', 'الخرج' => 'al_riyadh', 'الدرعية' => 'al_riyadh', 'المجمعة' => 'al_riyadh', 'الدوادمي' => 'al_riyadh',
        'مكة' => 'makkah', 'جدة' => 'makkah', 'الطائف' => 'makkah', 'رابغ' => 'makkah', 'القنفذة' => 'makkah',
        'المدينة' => 'al_madinah', 'ينبع' => 'al_madinah', 'العلا' => 'al_madinah',
        'بريدة' => 'al_qassim', 'عنيزة' => 'al_qassim', 'الرس' => 'al_qassim',
        'الدمام' => 'eastern_province', 'الخبر' => 'eastern_province', 'الظهران' => 'eastern_province', 'الأحساء' => 'eastern_province',
        'الهفوف' => 'eastern_province', 'القطيف' => 'eastern_province', 'الجبيل' => 'eastern_province', 'حفر الباطن' => 'eastern_province',
        'أبها' => 'asir', 'خميس مشيط' => 'asir', 'بيشة' => 'asir', 'تبوك' => 'tabuk', 'حائل' => 'hail', 'عرعر' => 'northern_borders',
        'جازان' => 'jazan', 'جيزان' => 'jazan', 'نجران' => 'najran', 'الباحة' => 'al_bahah', 'سكاكا' => 'al_jawf', 'الجوف' => 'al_jawf',
    ],

    'ownership_document_types' => [
        'electronic_title_deed' => 'صك ملكية إلكتروني',
        'title_deed' => 'صك ملكية (ورقي)',
        'hojjat' => 'حجة استحكام',
        'real_estate_registry' => 'سجل عقاري',
        'other' => 'وثيقة أخرى',
    ],

    // documents Ejar reads electronically; every other type needs a scan of the document
    'electronic_documents' => ['electronic_title_deed', 'real_estate_registry'],

    'property_types' => [
        'building' => 'عمارة',
        'villa' => 'فيلا',
        'tower' => 'برج',
        'compound' => 'مجمع سكني',
        'duplex' => 'دوبلكس',
        'rest_house' => 'استراحة',
    ],

    'usages' => [
        'residential_families' => 'سكني — عائلات',
        'residential_singles' => 'سكني — أفراد',
    ],

    'unit_types' => [
        'apartment' => 'شقة',
        'villa' => 'فيلا',
        'duplex' => 'دوبلكس',
        'floor' => 'دور',
        'studio' => 'استوديو',
        'room' => 'غرفة',
        'townhouse' => 'تاون هاوس',
        'annex' => 'ملحق',
    ],

    'furnish_types' => [
        'furnish_new' => 'مفروشة — أثاث جديد',
        'furnish_used' => 'مفروشة — أثاث مستعمل',
    ],

    'directions' => [
        'north' => 'شمال', 'south' => 'جنوب', 'east' => 'شرق', 'west' => 'غرب',
        'north_east' => 'شمال شرق', 'north_west' => 'شمال غرب', 'south_east' => 'جنوب شرق', 'south_west' => 'جنوب غرب',
    ],

    'finishings' => [
        'finished' => 'مشطبة',
        'semi_finished' => 'نصف تشطيب',
        'not_finished' => 'غير مشطبة',
    ],

    // property-level facilities (counts)
    'facilities' => [
        'parking_spaces' => 'مواقف السيارات',
        'elevators' => 'المصاعد',
        'security_entries' => 'مداخل أمنية',
        'security_service' => 'حراسة أمنية',
        'gyms_fitness_centers' => 'نوادٍ رياضية',
        'swimming_pool' => 'مسابح',
        'children_playground' => 'ملاعب أطفال',
        'grocery_store' => 'بقالات',
        'laundry' => 'مغاسل',
        'banquet_hall' => 'قاعات مناسبات',
        'cafeteria' => 'كافتيريا',
        'baby_nursery' => 'حضانة أطفال',
        'games_room' => 'غرف ألعاب',
        'football_yard' => 'ملاعب كرة قدم',
        'volleyball_court' => 'ملاعب طائرة',
        'tennis_court' => 'ملاعب تنس',
        'basketball_court' => 'ملاعب سلة',
        'transfer_service' => 'خدمة نقل',
    ],

    // unit amenities (counts) and fixtures (yes/no)
    'amenities' => [
        'bedrooms' => 'غرف النوم',
        'bathrooms_full' => 'دورات المياه',
        'halls' => 'الصالات',
        'majles' => 'المجالس',
        'kitchen' => 'المطابخ',
        'storeroom' => 'المستودعات',
        'maid_room' => 'غرف الخادمة',
        'backyard' => 'الأحواش',
        'central_ac' => 'مكيفات مركزية',
        'split_unit' => 'مكيفات سبليت',
        'desert_cooler' => 'مكيفات صحراوية',
    ],
    'fixtures' => [
        'is_kitchen_sink_installed' => 'مغسلة المطبخ مركبة',
        'is_cabinet_installed' => 'خزائن المطبخ مركبة',
    ],
];
