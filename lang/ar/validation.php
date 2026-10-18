<?php

// Common rules in Arabic; anything not listed falls back to English.
return [
    'required' => 'حقل :attribute مطلوب.',
    'required_if' => 'حقل :attribute مطلوب.',
    'required_without' => 'حقل :attribute مطلوب.',
    'email' => 'يجب أن يكون :attribute بريدًا إلكترونيًا صحيحًا.',
    'unique' => ':attribute مستخدم من قبل.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.',
    'digits' => 'يجب أن يتكون :attribute من :digits أرقام.',
    'regex' => 'صيغة :attribute غير صحيحة.',
    'exists' => 'القيمة المختارة لـ :attribute غير صالحة.',
    'in' => 'القيمة المختارة لـ :attribute غير صالحة.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'between' => ['numeric' => 'يجب أن تكون قيمة :attribute بين :min و :max.'],
    'min' => ['string' => 'يجب ألا يقل :attribute عن :min أحرف.', 'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.'],
    'max' => ['string' => 'يجب ألا يزيد :attribute عن :max حرفًا.', 'numeric' => 'يجب ألا تزيد قيمة :attribute عن :max.', 'file' => 'يجب ألا يزيد حجم :attribute عن :max كيلوبايت.', 'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عناصر.'],
    'password' => [
        'letters' => 'يجب أن تحتوي :attribute على حرف واحد على الأقل.',
        'numbers' => 'يجب أن تحتوي :attribute على رقم واحد على الأقل.',
        'mixed' => 'يجب أن تحتوي :attribute على حرف كبير وحرف صغير على الأقل.',
        'uncompromised' => 'ظهرت :attribute هذه في تسريب بيانات. يرجى اختيار كلمة مرور أخرى.',
    ],
    'custom' => [
        'accept_terms' => ['accepted' => 'يرجى تأكيد موافقتك على الشروط والأحكام وسياسة عدم الاسترجاع وعدم الاستبدال.'],
    ],
    'attributes' => [
        'name' => 'الاسم', 'email' => 'البريد الإلكتروني', 'phone' => 'رقم الجوال', 'password' => 'كلمة المرور',
        'line1' => 'العنوان', 'city' => 'المدينة', 'zone_id' => 'الإمارة', 'buyer_trn' => 'الرقم الضريبي',
        'payment_method' => 'طريقة الدفع', 'pickup_branch_id' => 'فرع الاستلام', 'body' => 'النص', 'rating' => 'التقييم',
    ],
];
