<?php

namespace App\Support;

/**
 * Default wording of the shop's legal pages (English + Arabic). The admin can replace any page
 * under Settings → Policies; until then these texts are shown with the store's own details.
 * Lines starting with "- " are shown as bullet points.
 */
class Policies
{
    public const PAGES = [
        'terms' => ['slug' => 'terms-and-conditions', 'en' => 'Terms & Conditions', 'ar' => 'الشروط والأحكام'],
        'refund' => ['slug' => 'refund-policy', 'en' => 'No Refund & No Exchange Policy', 'ar' => 'سياسة عدم الاسترجاع وعدم الاستبدال'],
        'privacy' => ['slug' => 'privacy-policy', 'en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
        'delivery' => ['slug' => 'delivery-policy', 'en' => 'Delivery & Collection Policy', 'ar' => 'سياسة التوصيل والاستلام'],
    ];

    public const UPDATED = '2026-10-19';

    public static function keyForSlug(string $slug): ?string
    {
        foreach (self::PAGES as $key => $page) {
            if ($page['slug'] === $slug) {
                return $key;
            }
        }

        return null;
    }

    public static function title(string $key, ?string $locale = null): string
    {
        return self::PAGES[$key][($locale ?? app()->getLocale()) === 'ar' ? 'ar' : 'en'];
    }

    /** Admin-written HTML for this page, cleaned of scripts and event handlers; null = use the default. */
    public static function custom(string $key, string $locale): ?string
    {
        $html = trim((string) Settings::get("policy_{$key}_".($locale === 'ar' ? 'ar' : 'en')));
        if (strip_tags($html) === '') {
            return null;
        }
        $html = strip_tags($html, '<p><br><h2><h3><h4><ul><ol><li><strong><b><em><i><u><a><blockquote><hr>');
        $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

        return preg_replace('/href\s*=\s*(["\'])\s*(javascript|data|vbscript):[^"\']*\1/i', 'href="#"', $html);
    }

    /** @return array<int, array{0: string, 1: array<int, string>}> */
    public static function sections(string $key, string $locale): array
    {
        $ar = $locale === 'ar';
        $sections = $ar ? self::arabic()[$key] : self::english()[$key];
        $vars = [
            ':store' => $ar ? (Settings::get('store_name_ar') ?: Settings::get('store_name')) : Settings::get('store_name'),
            ':legal' => Settings::get('store_legal_name'),
            ':trn' => Settings::get('store_trn'),
            ':address' => Settings::get('store_address'),
            ':email' => Settings::get('store_email'),
            ':phone' => Settings::get('store_phone'),
            ':whatsapp' => '+'.ltrim((string) Settings::get('whatsapp_number'), '+'),
            ':emirate' => Settings::get('policy_emirate', 'Dubai'),
            ':days' => (string) Settings::get('policy_report_hours', 48),
        ];

        return array_map(fn ($s) => [strtr($s[0], $vars), array_map(fn ($l) => strtr($l, $vars), $s[1])], $sections);
    }

    private static function english(): array
    {
        return [
            'terms' => [
                ['About us', [
                    'This website is operated by :legal (":store", "we", "us"), Tax Registration Number (TRN) :trn, located at :address, United Arab Emirates.',
                    'By browsing this website, creating an account or placing an order you agree to these Terms & Conditions, our No Refund & No Exchange Policy, our Delivery & Collection Policy and our Privacy Policy. If you do not agree, please do not use the website.',
                ]],
                ['Who can buy', [
                    '- You must be at least 18 years old and legally able to enter into a contract.',
                    '- The information you give us (name, mobile number, email, address and ID details) must be true, complete and your own.',
                    '- You are responsible for keeping your password and sign-in codes secret. Tell us immediately if you think someone else has used your account.',
                ]],
                ['Products and descriptions', [
                    '- Every gold item is sold by its karat (purity) and weight in grams as shown on the product page and the tax invoice. Weights may differ slightly from the photo or the estimate shown before the item is weighed; the invoice weight is final.',
                    '- Photos are for illustration. Colour, size and finish may look different on screens, and handmade pieces can vary slightly.',
                    '- Diamond and gemstone details (carat, colour, clarity) are approximate and within normal industry tolerances unless a certificate is supplied with the item.',
                    '- Items are subject to availability. If an item turns out to be unavailable after you have paid, we will tell you and give you a full refund for that item.',
                ]],
                ['Prices, the gold rate and VAT', [
                    '- Prices of gold-rate items are calculated from the live gold rate per gram for the item\'s karat, plus the making charge, plus 5% UAE VAT. Fixed-price items have one set price.',
                    '- Because the gold rate changes during the day, the price you see can change until your payment is completed. The price is locked at the moment your payment is successfully completed.',
                    '- All prices are in UAE Dirhams (AED). Any payment fee for your chosen payment method is shown at checkout before you pay and is included in your tax invoice.',
                    '- If a price is clearly wrong because of a technical or typing error, we may cancel the order and refund you in full, even after confirmation.',
                ]],
                ['Orders', [
                    '- Your order is a request to buy. A contract is made only when your payment is completed (or, for cash on delivery, when the item is handed over and paid).',
                    '- We may refuse or cancel an order if we suspect fraud, if identity verification fails, if the item is no longer available, or if the price was wrong. In those cases you receive a full refund of anything you paid.',
                    '- Personalised, engraved, resized or made-to-order items are produced specially for you; any deposit for them cannot be refunded once production has started.',
                ]],
                ['Payment', [
                    '- Card, Apple Pay and Google Pay payments are processed by our licensed payment providers on their secure pages. We never see or store your full card number.',
                    '- Buy-now-pay-later (Tabby, Tamara) is subject to the provider\'s own terms and approval.',
                    '- Cash on delivery may be limited to a maximum order value shown at checkout.',
                    '- The card used must belong to you. We may ask for extra proof (for example a photo of the card with the middle digits covered) before releasing an order.',
                ]],
                ['Identity verification and anti-money-laundering', [
                    '- As a dealer in precious metals and stones we follow UAE anti-money-laundering and counter-terrorist-financing rules. We may ask for your Emirates ID or passport, its number and expiry, and further documents before accepting or delivering an order.',
                    '- The name on the ID must match the name on the order. We may decline, hold or cancel any transaction, and report it to the authorities where the law requires, without giving a reason.',
                    '- We keep identification and transaction records for the period required by UAE law.',
                ]],
                ['Delivery, collection and ownership', [
                    '- Orders are handed over only to the person named on the order after checking their ID and the 6-digit handover code we send by email / WhatsApp. Never share this code with anyone except our staff at the moment of handover.',
                    '- Responsibility for the item (risk) and ownership pass to you when it is handed over and the handover code is confirmed.',
                    '- Full details are in our Delivery & Collection Policy.',
                ]],
                ['No refund, no exchange', [
                    'All sales are final. Gold and jewellery are not refundable, returnable or exchangeable, except in the limited cases described in our No Refund & No Exchange Policy (wrong item, damaged on arrival, manufacturing defect, or an order we cancel).',
                ]],
                ['Chargebacks', [
                    'If you dispute a payment with your bank for an order that was delivered as described, we will provide the bank with the order record, your ID check, the handover code confirmation and delivery proof. Fraudulent chargebacks may be reported to the police.',
                ]],
                ['Website use and intellectual property', [
                    '- All content on this website (photos, designs, text, logos) belongs to :legal or its licensors and may not be copied without written permission.',
                    '- You must not misuse the website: no hacking, scraping, automated orders, fake accounts or attempts to get around prices, security or limits.',
                ]],
                ['Liability', [
                    '- We are not responsible for delays or failures caused by events outside our control (for example courier disruption, bank or payment-provider outages, government action or extreme weather).',
                    '- To the extent permitted by UAE law, our total liability for any order is limited to the amount you paid for that order. Nothing in these terms limits rights you have under UAE consumer protection law that cannot be excluded.',
                ]],
                ['Law and disputes', [
                    'These terms are governed by the laws of the United Arab Emirates as applied in the Emirate of :emirate. Disputes will be handled by the competent courts of :emirate. Please contact us first so we can try to solve any problem quickly.',
                ]],
                ['Changes and contact', [
                    'We may update these terms at any time; the version on this page at the time of your order applies to that order. Questions: :email · :phone · WhatsApp :whatsapp.',
                ]],
            ],
            'refund' => [
                ['All sales are final', [
                    'Gold and jewellery are bought and priced against the live gold market at the moment you pay, and jewellery that has left our store cannot be resold as new for hygiene and security reasons. For this reason :store does not offer refunds, returns or exchanges once an order has been paid.',
                    'This applies to all items: gold, diamond, gemstone and silver jewellery, gold coins and bars, sale and discounted items, gift cards, and personalised, engraved, resized or made-to-order pieces.',
                ]],
                ['Cancellation', [
                    '- Before payment: you can simply leave the order unpaid; it is cancelled automatically and nothing is charged.',
                    '- After payment: the order cannot be cancelled by you, because the gold is bought at the locked rate for your order.',
                    '- If we cancel an order (item unavailable, price error, failed ID verification or suspected fraud) you receive a full refund.',
                ]],
                ['When we will put it right', [
                    'Your statutory rights are not affected. We will repair, replace or — only if neither is possible — refund an item in these cases:',
                    '- You received a different item from the one you ordered.',
                    '- The item arrived damaged, or the security seal of the package was broken at handover.',
                    '- The item has a manufacturing defect (for example a faulty clasp or a stone that was loose on arrival) reported before it has been worn.',
                    '- The karat or weight is different from what is stated on your tax invoice.',
                    'Exchanges in these cases are only for the identical item. A refund, when it applies, is made to the original payment method within 7–14 working days (the timing of your bank or card provider may vary).',
                ]],
                ['How to report a problem', [
                    '- Tell us within :days hours of handover by WhatsApp (:whatsapp) or email (:email) with your order number, clear photos and, if possible, the unboxing video.',
                    '- Do not wear, alter, clean with chemicals or take the item to another jeweller; this cancels any claim.',
                    '- Bring or send the item with its invoice, box and certificate (if any) to our store for inspection. We will give you our decision within 7 working days of receiving the item.',
                ]],
                ['What is not covered', [
                    '- Change of mind, wrong size chosen, or a different price later because the gold rate moved.',
                    '- Normal wear and tear: scratches, dents, bending, fading of plating or rhodium, stretched or broken chains from pulling.',
                    '- Stones lost or damaged by knocks, and damage from perfume, chlorine, cleaning products or other chemicals.',
                    '- Items resized, repaired or altered by anyone other than :store, and lost or stolen items.',
                ]],
                ['Old gold, buy-back and paid services', [
                    'Selling your old gold to us, buy-back or trade-in is not a refund or exchange. If offered, it is done in store at our buying rate on that day after testing, minus any deductions, and needs a valid ID. Resizing, polishing and repairs are paid services and are quoted before work starts.',
                ]],
                ['Gift cards', [
                    'Gift cards cannot be refunded or exchanged for cash and are valid until the expiry date shown on the card.',
                ]],
            ],
            'privacy' => [
                ['Who we are', [
                    ':legal (TRN :trn), :address, is responsible for your personal data on this website. We process it in line with UAE Federal Decree-Law No. 45 of 2021 on the Protection of Personal Data and other applicable UAE law. Contact: :email.',
                ]],
                ['What we collect', [
                    '- Contact details: name, mobile number, email address and delivery address.',
                    '- Identity details for verification: ID type, number and expiry date, and — only if you choose to upload them — photos of the front and back of your ID.',
                    '- Order details: items, prices, invoices, payments and delivery / collection records including the handover-code confirmation.',
                    '- Payment details: handled by our payment providers. We receive only the payment result and a reference, never your full card number or CVV.',
                    '- Account details: your password (stored only in scrambled, one-way form), Google sign-in name and email if you use "Continue with Google", saved addresses, wishlist and preferences.',
                    '- Messages: WhatsApp, email and video-call requests you send us. Video calls are not recorded.',
                    '- Technical data: IP address, browser and device type, pages visited and cookies (see below). Sign-ins are recorded for security.',
                ]],
                ['Why we use it', [
                    '- To process, deliver and support your orders and issue VAT tax invoices.',
                    '- To verify your identity, protect you and us from fraud, and meet UAE anti-money-laundering, VAT and record-keeping obligations.',
                    '- To send order updates, handover codes and sign-in codes by email, SMS or WhatsApp.',
                    '- With your consent only: newsletters, offers and gold-rate alerts. You can unsubscribe at any time from the link in each message.',
                    '- To keep the website secure and improve it.',
                ]],
                ['Who we share it with', [
                    'We never sell your personal data. We share only what is needed with:',
                    '- Payment providers (for example Mettpay / PayBy, Stripe, Telr, Tabby, Tamara) to take your payment.',
                    '- Delivery couriers, to deliver your order.',
                    '- Messaging and email providers (for example Meta WhatsApp Business, Twilio, our email service) to send you messages.',
                    '- Google, if you use "Continue with Google" or view our store map.',
                    '- Government authorities, regulators, auditors or the police when the law requires it.',
                    'Some of these providers may process data outside the UAE; we only use providers that protect it with appropriate safeguards.',
                ]],
                ['How long we keep it', [
                    '- Orders, invoices and payment records: at least 5 years, as required by UAE VAT law.',
                    '- Identity verification and transaction records: for the period required by UAE anti-money-laundering law (at least 5 years).',
                    '- A saved ID for faster checkout: until you remove it in My account (it is still kept with past orders as above).',
                    '- Marketing preferences: until you unsubscribe. Abandoned carts: deleted after a few weeks.',
                ]],
                ['How we protect it', [
                    '- The whole website uses an encrypted (HTTPS) connection.',
                    '- ID photos are stored in a private area that is not reachable from the internet and can be opened only by authorised admin staff.',
                    '- Staff access is limited to the areas each person needs, with sign-in logging, automatic sign-out and optional two-step sign-in codes.',
                    'No system is 100% secure. If a breach affects your data we will inform you and the authorities as the law requires.',
                ]],
                ['Cookies', [
                    '- Essential cookies keep you signed in, remember your bag, language and dark mode, and protect forms against misuse. The website does not work without them.',
                    '- If we turn on analytics or advertising pixels (for example Google, Meta or TikTok), they help us measure visits and show relevant ads. You can block them in your browser settings.',
                ]],
                ['Your rights', [
                    'You can ask to see, correct, delete or receive a copy of your personal data, withdraw consent for marketing, or object to some processing. Email :email from the address on your account. We reply within 30 days. Data we must keep by law (for example invoices) cannot be deleted before the legal period ends.',
                ]],
                ['Children', [
                    'Our website is not intended for anyone under 18 and we do not knowingly collect their data.',
                ]],
                ['Changes', [
                    'We may update this policy. The date at the top shows the latest version.',
                ]],
            ],
            'delivery' => [
                ['Where we deliver', [
                    '- We deliver within the United Arab Emirates only. Delivery areas, fees and any free-delivery threshold are shown at checkout before you pay.',
                    '- Home delivery may be paused at busy times or on holidays; store collection is then the only option.',
                    '- Delivery times shown are estimates and start after payment and ID verification are completed.',
                ]],
                ['Handover rules', [
                    '- We hand the order only to the person named on the order, after checking an original, valid ID (Emirates ID or passport) that matches the order.',
                    '- You will receive a 6-digit handover code by email / WhatsApp when the order is on its way or ready to collect. Give it to our staff only at the moment you receive the item.',
                    '- We cannot hand over to a different person, a building reception or a hotel unless agreed with us in writing before dispatch.',
                    '- Please check that the package is sealed and undamaged before you confirm the handover. If it is not, refuse it and contact us right away.',
                ]],
                ['Missed deliveries', [
                    'If nobody is available, or the ID or handover code cannot be confirmed, the courier will return the order to our store. A new delivery may carry an extra delivery fee, or you can collect it from the store.',
                ]],
                ['Collecting from our store', [
                    '- Choose your branch at checkout. We tell you when the order is ready; bring your original ID and the handover code.',
                    '- Please collect within 14 days. If you need longer, contact us; we keep the order safely in store.',
                ]],
                ['Risk and ownership', [
                    'We are responsible for the item until handover. Responsibility and ownership pass to you once it is handed over and the handover code is confirmed.',
                ]],
                ['Questions', [
                    'WhatsApp :whatsapp · :phone · :email',
                ]],
            ],
        ];
    }

    private static function arabic(): array
    {
        return [
            'terms' => [
                ['من نحن', [
                    'يدير هذا الموقع :legal (":store" أو "نحن")، رقم التسجيل الضريبي :trn، وعنوانه :address، الإمارات العربية المتحدة.',
                    'باستخدامك لهذا الموقع أو إنشاء حساب أو تقديم طلب، فإنك توافق على هذه الشروط والأحكام وعلى سياسة عدم الاسترجاع وعدم الاستبدال وسياسة التوصيل والاستلام وسياسة الخصوصية. إذا كنت لا توافق عليها، يرجى عدم استخدام الموقع.',
                ]],
                ['من يحق له الشراء', [
                    '- يجب أن يكون عمرك 18 سنة على الأقل وأن تكون أهلاً قانونياً للتعاقد.',
                    '- يجب أن تكون المعلومات التي تقدمها (الاسم ورقم الجوال والبريد الإلكتروني والعنوان وبيانات الهوية) صحيحة وكاملة وخاصة بك.',
                    '- أنت مسؤول عن الحفاظ على سرية كلمة المرور ورموز الدخول، ويجب إبلاغنا فوراً إذا اعتقدت أن شخصاً آخر استخدم حسابك.',
                ]],
                ['المنتجات ووصفها', [
                    '- يُباع كل منتج ذهبي حسب العيار والوزن بالغرام كما هو موضح في صفحة المنتج وفي الفاتورة الضريبية. قد يختلف الوزن قليلاً عن الصورة أو التقدير قبل الوزن، ووزن الفاتورة هو المعتمد.',
                    '- الصور للتوضيح فقط، وقد يختلف اللون أو الحجم أو اللمسة النهائية على الشاشات، وقد تختلف القطع المصنوعة يدوياً اختلافاً بسيطاً.',
                    '- تفاصيل الألماس والأحجار الكريمة (القيراط واللون والنقاء) تقريبية وضمن الحدود المعتادة في هذه الصناعة ما لم تُرفق شهادة مع القطعة.',
                    '- المنتجات متاحة حسب توفرها. إذا تبيّن عدم توفر منتج بعد الدفع، سنبلغك ونرد لك قيمته كاملة.',
                ]],
                ['الأسعار وسعر الذهب وضريبة القيمة المضافة', [
                    '- تُحسب أسعار المنتجات المرتبطة بسعر الذهب من سعر الغرام المباشر لعيار القطعة، مضافاً إليه أجرة المصنعية وضريبة القيمة المضافة 5%. أما المنتجات ذات السعر الثابت فلها سعر واحد محدد.',
                    '- لأن سعر الذهب يتغير خلال اليوم، قد يتغير السعر المعروض حتى إتمام الدفع. يُثبَّت السعر لحظة إتمام الدفع بنجاح.',
                    '- جميع الأسعار بالدرهم الإماراتي. تظهر أي رسوم لطريقة الدفع المختارة قبل الدفع وتُدرج في الفاتورة الضريبية.',
                    '- إذا كان السعر خاطئاً بشكل واضح بسبب خطأ تقني أو مطبعي، يحق لنا إلغاء الطلب ورد المبلغ كاملاً حتى بعد التأكيد.',
                ]],
                ['الطلبات', [
                    '- طلبك هو رغبة في الشراء، ولا يتم العقد إلا عند إتمام الدفع (أو في حالة الدفع عند الاستلام، عند تسليم القطعة ودفع قيمتها).',
                    '- يحق لنا رفض أو إلغاء أي طلب عند الاشتباه في احتيال، أو فشل التحقق من الهوية، أو عدم توفر المنتج، أو وجود خطأ في السعر، وفي هذه الحالات نرد لك كامل المبلغ المدفوع.',
                    '- القطع المخصصة أو المنقوشة أو المعدّلة المقاس أو المصنوعة حسب الطلب تُصنع خصيصاً لك، ولا يُسترد أي عربون عنها بعد بدء التصنيع.',
                ]],
                ['الدفع', [
                    '- تتم مدفوعات البطاقات وApple Pay وGoogle Pay عبر مزودي دفع مرخصين على صفحاتهم الآمنة، ولا نطّلع على رقم بطاقتك الكامل ولا نحفظه.',
                    '- خدمات "اشترِ الآن وادفع لاحقاً" (تابي وتمارا) تخضع لشروط المزود وموافقته.',
                    '- قد يكون الدفع عند الاستلام محدوداً بحد أقصى لقيمة الطلب يظهر عند إتمام الطلب.',
                    '- يجب أن تكون البطاقة المستخدمة ملكك، وقد نطلب إثباتاً إضافياً قبل تسليم الطلب.',
                ]],
                ['التحقق من الهوية ومكافحة غسل الأموال', [
                    '- بصفتنا تاجراً في المعادن الثمينة والأحجار الكريمة، نلتزم بأنظمة دولة الإمارات لمكافحة غسل الأموال وتمويل الإرهاب، وقد نطلب الهوية الإماراتية أو جواز السفر ورقمها وتاريخ انتهائها ومستندات إضافية قبل قبول الطلب أو تسليمه.',
                    '- يجب أن يطابق الاسم في الهوية الاسم في الطلب. يحق لنا رفض أي معاملة أو تعليقها أو إلغاؤها والإبلاغ عنها للجهات المختصة عندما يتطلب القانون ذلك.',
                    '- نحتفظ بسجلات الهوية والمعاملات للمدة التي يفرضها القانون الإماراتي.',
                ]],
                ['التوصيل والاستلام وانتقال الملكية', [
                    '- لا نسلّم الطلب إلا للشخص المذكور في الطلب بعد التحقق من هويته ومن رمز التسليم المكوّن من 6 أرقام الذي نرسله بالبريد الإلكتروني أو واتساب. لا تشارك هذا الرمز مع أي شخص سوى موظفنا لحظة التسليم.',
                    '- تنتقل المسؤولية والملكية إليك عند التسليم وتأكيد رمز التسليم.',
                    '- التفاصيل الكاملة في سياسة التوصيل والاستلام.',
                ]],
                ['لا استرجاع ولا استبدال', [
                    'جميع المبيعات نهائية. الذهب والمجوهرات غير قابلة للاسترجاع أو الإرجاع أو الاستبدال، إلا في الحالات المحدودة المذكورة في سياسة عدم الاسترجاع وعدم الاستبدال (قطعة خاطئة، أو تلف عند الوصول، أو عيب في التصنيع، أو طلب نقوم نحن بإلغائه).',
                ]],
                ['الاعتراض على المدفوعات', [
                    'إذا اعترضت لدى البنك على دفعة لطلب تم تسليمه كما هو موصوف، سنزوّد البنك بسجل الطلب والتحقق من هويتك وتأكيد رمز التسليم وإثبات التسليم. وقد يُبلَّغ عن الاعتراضات الاحتيالية للشرطة.',
                ]],
                ['استخدام الموقع والملكية الفكرية', [
                    '- جميع محتويات الموقع (الصور والتصاميم والنصوص والشعارات) مملوكة لـ :legal أو للمرخِّصين لها، ولا يجوز نسخها دون إذن كتابي.',
                    '- يُمنع إساءة استخدام الموقع، بما في ذلك الاختراق أو جمع البيانات آلياً أو الطلبات الآلية أو الحسابات الوهمية أو محاولة التحايل على الأسعار أو الحماية أو الحدود.',
                ]],
                ['المسؤولية', [
                    '- لسنا مسؤولين عن التأخير أو الإخفاق الناتج عن ظروف خارجة عن إرادتنا (مثل تعطل شركات التوصيل أو البنوك أو مزودي الدفع أو الإجراءات الحكومية أو الأحوال الجوية القاسية).',
                    '- في حدود ما يسمح به القانون الإماراتي، تقتصر مسؤوليتنا الإجمالية عن أي طلب على المبلغ الذي دفعته مقابله. ولا يحد أي شيء في هذه الشروط من حقوقك بموجب قانون حماية المستهلك الإماراتي التي لا يجوز استبعادها.',
                ]],
                ['القانون وتسوية النزاعات', [
                    'تخضع هذه الشروط لقوانين دولة الإمارات العربية المتحدة كما تُطبَّق في إمارة :emirate، وتختص محاكم :emirate بالنظر في أي نزاع. يرجى التواصل معنا أولاً لنحاول حل أي مشكلة بسرعة.',
                ]],
                ['التعديلات والتواصل', [
                    'يحق لنا تحديث هذه الشروط في أي وقت، وتسري على كل طلب النسخة المنشورة وقت تقديمه. للاستفسار: :email · :phone · واتساب :whatsapp.',
                ]],
            ],
            'refund' => [
                ['جميع المبيعات نهائية', [
                    'يُشترى الذهب والمجوهرات ويُسعَّران وفق سعر سوق الذهب المباشر لحظة الدفع، ولا يمكن إعادة بيع المجوهرات التي غادرت متجرنا كقطع جديدة لأسباب صحية وأمنية. لذلك لا يقدم :store أي استرجاع أو إرجاع أو استبدال بعد دفع قيمة الطلب.',
                    'ينطبق ذلك على جميع المنتجات: مجوهرات الذهب والألماس والأحجار الكريمة والفضة، والسبائك والعملات الذهبية، والمنتجات المخفّضة، وبطاقات الهدايا، والقطع المخصصة أو المنقوشة أو المعدّلة المقاس أو المصنوعة حسب الطلب.',
                ]],
                ['الإلغاء', [
                    '- قبل الدفع: يمكنك ترك الطلب دون دفع، فيُلغى تلقائياً دون أي رسوم.',
                    '- بعد الدفع: لا يمكنك إلغاء الطلب، لأن الذهب يُشترى بالسعر المثبّت لطلبك.',
                    '- إذا ألغينا نحن الطلب (عدم توفر المنتج أو خطأ في السعر أو فشل التحقق من الهوية أو الاشتباه في احتيال) نرد لك المبلغ كاملاً.',
                ]],
                ['متى نصحّح الخطأ', [
                    'لا تتأثر حقوقك القانونية. سنقوم بإصلاح القطعة أو استبدالها — أو ردّ قيمتها فقط إذا تعذّر الإصلاح والاستبدال — في الحالات التالية:',
                    '- استلمت قطعة مختلفة عن التي طلبتها.',
                    '- وصلت القطعة تالفة، أو كان ختم الأمان على الطرد مكسوراً عند التسليم.',
                    '- وجود عيب في التصنيع (مثل قفل معطّل أو حجر غير مثبّت عند الوصول) يُبلَّغ عنه قبل ارتداء القطعة.',
                    '- اختلاف العيار أو الوزن عمّا هو مذكور في فاتورتك الضريبية.',
                    'الاستبدال في هذه الحالات يكون بالقطعة نفسها فقط. وعند استحقاق الاسترداد، يُعاد المبلغ إلى وسيلة الدفع الأصلية خلال 7 إلى 14 يوم عمل (وقد تختلف المدة حسب البنك أو مزود البطاقة).',
                ]],
                ['كيفية الإبلاغ عن مشكلة', [
                    '- أبلغنا خلال :days ساعة من التسليم عبر واتساب (:whatsapp) أو البريد الإلكتروني (:email) مع رقم الطلب وصور واضحة، وفيديو فتح الطرد إن أمكن.',
                    '- لا ترتدِ القطعة ولا تعدّلها ولا تنظفها بمواد كيميائية ولا تعرضها على صائغ آخر، فذلك يلغي أي مطالبة.',
                    '- أحضر القطعة أو أرسلها مع الفاتورة والعلبة والشهادة (إن وجدت) إلى متجرنا للفحص، وسنبلغك بقرارنا خلال 7 أيام عمل من استلامها.',
                ]],
                ['ما لا تشمله السياسة', [
                    '- تغيير الرأي، أو اختيار مقاس خاطئ، أو تغيّر السعر لاحقاً بسبب تحرك سعر الذهب.',
                    '- الاستهلاك العادي: الخدوش والانبعاجات والانحناء وبهتان الطلاء أو الروديوم والسلاسل الممتدة أو المقطوعة بسبب الشد.',
                    '- فقدان الأحجار أو تلفها بسبب الصدمات، والتلف الناتج عن العطور أو الكلور أو مواد التنظيف أو المواد الكيميائية.',
                    '- القطع التي عدّل مقاسها أو أصلحها أو غيّرها أي طرف غير :store، والقطع المفقودة أو المسروقة.',
                ]],
                ['الذهب المستعمل وإعادة الشراء والخدمات المدفوعة', [
                    'بيع ذهبك المستعمل لنا أو إعادة الشراء أو المقايضة ليس استرجاعاً ولا استبدالاً. إن توفرت هذه الخدمة فهي تتم في المتجر بسعر الشراء لدينا في ذلك اليوم بعد الفحص مع خصم أي استقطاعات، وتتطلب هوية سارية. أما تعديل المقاس والتلميع والإصلاح فهي خدمات مدفوعة يُحدد سعرها قبل البدء.',
                ]],
                ['بطاقات الهدايا', [
                    'لا يمكن استرداد قيمة بطاقات الهدايا أو استبدالها نقداً، وهي صالحة حتى تاريخ الانتهاء المذكور عليها.',
                ]],
            ],
            'privacy' => [
                ['من نحن', [
                    ':legal (الرقم الضريبي :trn)، :address، هي الجهة المسؤولة عن بياناتك الشخصية على هذا الموقع، ونعالجها وفقاً للمرسوم بقانون اتحادي رقم 45 لسنة 2021 بشأن حماية البيانات الشخصية وغيره من القوانين الإماراتية المعمول بها. للتواصل: :email.',
                ]],
                ['البيانات التي نجمعها', [
                    '- بيانات التواصل: الاسم ورقم الجوال والبريد الإلكتروني وعنوان التوصيل.',
                    '- بيانات الهوية للتحقق: نوع الهوية ورقمها وتاريخ انتهائها، وصور وجهي الهوية فقط إذا اخترت رفعها.',
                    '- بيانات الطلب: المنتجات والأسعار والفواتير والمدفوعات وسجلات التوصيل والاستلام بما فيها تأكيد رمز التسليم.',
                    '- بيانات الدفع: يتولاها مزودو الدفع، ولا نستلم سوى نتيجة الدفع ورقماً مرجعياً، ولا نطّلع أبداً على رقم بطاقتك الكامل أو رمز CVV.',
                    '- بيانات الحساب: كلمة المرور (تُحفظ بصيغة مشفرة غير قابلة للاسترجاع)، واسمك وبريدك من Google إذا استخدمت "المتابعة باستخدام Google"، والعناوين المحفوظة وقائمة الأمنيات والتفضيلات.',
                    '- الرسائل: رسائل واتساب والبريد الإلكتروني وطلبات مكالمات الفيديو. لا نسجّل مكالمات الفيديو.',
                    '- البيانات التقنية: عنوان IP ونوع المتصفح والجهاز والصفحات التي تزورها وملفات تعريف الارتباط (انظر أدناه). تُسجَّل عمليات تسجيل الدخول لأغراض الأمان.',
                ]],
                ['لماذا نستخدمها', [
                    '- لمعالجة طلباتك وتوصيلها ودعمها وإصدار فواتير ضريبة القيمة المضافة.',
                    '- للتحقق من هويتك وحمايتك وحمايتنا من الاحتيال، والوفاء بالتزامات مكافحة غسل الأموال وضريبة القيمة المضافة وحفظ السجلات في الإمارات.',
                    '- لإرسال تحديثات الطلب ورموز التسليم ورموز الدخول بالبريد الإلكتروني أو الرسائل النصية أو واتساب.',
                    '- بموافقتك فقط: النشرات والعروض وتنبيهات سعر الذهب، ويمكنك إلغاء الاشتراك في أي وقت من الرابط في كل رسالة.',
                    '- للحفاظ على أمان الموقع وتحسينه.',
                ]],
                ['مع من نشاركها', [
                    'لا نبيع بياناتك الشخصية أبداً، ونشارك فقط ما يلزم مع:',
                    '- مزودي الدفع (مثل Mettpay / PayBy وStripe وTelr وتابي وتمارا) لإتمام الدفع.',
                    '- شركات التوصيل لتوصيل طلبك.',
                    '- مزودي الرسائل والبريد الإلكتروني (مثل واتساب للأعمال من Meta وTwilio وخدمة البريد لدينا) لإرسال الرسائل.',
                    '- Google إذا استخدمت تسجيل الدخول عبر Google أو عرضت خريطة المتجر.',
                    '- الجهات الحكومية والرقابية والمدققين أو الشرطة عندما يتطلب القانون ذلك.',
                    'قد يعالج بعض هؤلاء المزودين البيانات خارج الإمارات، ونتعامل فقط مع مزودين يحمونها بضمانات مناسبة.',
                ]],
                ['مدة الاحتفاظ', [
                    '- الطلبات والفواتير وسجلات الدفع: 5 سنوات على الأقل وفق قانون ضريبة القيمة المضافة الإماراتي.',
                    '- سجلات التحقق من الهوية والمعاملات: للمدة التي يفرضها قانون مكافحة غسل الأموال الإماراتي (5 سنوات على الأقل).',
                    '- الهوية المحفوظة لتسريع الطلب: حتى تحذفها من "حسابي" (مع بقائها ضمن الطلبات السابقة كما سبق).',
                    '- تفضيلات التسويق: حتى إلغاء الاشتراك. السلال المتروكة: تُحذف بعد بضعة أسابيع.',
                ]],
                ['كيف نحميها', [
                    '- يستخدم الموقع بالكامل اتصالاً مشفراً (HTTPS).',
                    '- تُحفظ صور الهوية في مكان خاص لا يمكن الوصول إليه من الإنترنت، ولا يفتحها إلا موظفو الإدارة المخوّلون.',
                    '- صلاحيات الموظفين محصورة في الأقسام التي يحتاجها كل منهم، مع تسجيل عمليات الدخول والخروج التلقائي ورموز الدخول بخطوتين.',
                    'لا يوجد نظام آمن بنسبة 100%. إذا تأثرت بياناتك باختراق، سنبلغك ونبلغ الجهات المختصة وفق القانون.',
                ]],
                ['ملفات تعريف الارتباط', [
                    '- الملفات الأساسية تحافظ على تسجيل دخولك وتتذكر حقيبتك ولغتك والوضع الداكن، وتحمي النماذج من إساءة الاستخدام، ولا يعمل الموقع بدونها.',
                    '- إذا فعّلنا أدوات التحليل أو الإعلانات (مثل Google أو Meta أو TikTok) فهي تساعدنا في قياس الزيارات وعرض إعلانات مناسبة، ويمكنك حظرها من إعدادات المتصفح.',
                ]],
                ['حقوقك', [
                    'يمكنك طلب الاطلاع على بياناتك الشخصية أو تصحيحها أو حذفها أو الحصول على نسخة منها، أو سحب موافقتك على التسويق، أو الاعتراض على بعض المعالجة، وذلك بمراسلة :email من البريد المسجل في حسابك. نرد خلال 30 يوماً. البيانات التي يلزمنا القانون بحفظها (مثل الفواتير) لا يمكن حذفها قبل انتهاء المدة القانونية.',
                ]],
                ['الأطفال', [
                    'موقعنا غير موجّه لمن هم دون 18 عاماً، ولا نجمع بياناتهم عن علم.',
                ]],
                ['التعديلات', [
                    'قد نحدّث هذه السياسة، ويوضح التاريخ في أعلى الصفحة آخر نسخة.',
                ]],
            ],
            'delivery' => [
                ['مناطق التوصيل', [
                    '- نوصّل داخل دولة الإمارات العربية المتحدة فقط. تظهر مناطق التوصيل ورسومه وحد التوصيل المجاني (إن وجد) قبل الدفع.',
                    '- قد يتوقف التوصيل للمنازل في أوقات الذروة أو العطلات، ويكون الاستلام من المتجر هو الخيار الوحيد حينها.',
                    '- مواعيد التوصيل المعروضة تقديرية وتبدأ بعد إتمام الدفع والتحقق من الهوية.',
                ]],
                ['قواعد التسليم', [
                    '- لا نسلّم الطلب إلا للشخص المذكور في الطلب بعد التحقق من هوية أصلية سارية (الهوية الإماراتية أو جواز السفر) مطابقة للطلب.',
                    '- ستصلك رمز تسليم من 6 أرقام بالبريد الإلكتروني أو واتساب عند خروج الطلب للتوصيل أو جاهزيته للاستلام. أعطه لموظفنا فقط لحظة استلام القطعة.',
                    '- لا يمكننا التسليم لشخص آخر أو لاستقبال مبنى أو فندق إلا بموافقة كتابية مسبقة منا قبل الشحن.',
                    '- يرجى التأكد من أن الطرد مختوم وسليم قبل تأكيد التسليم، وإن لم يكن كذلك فارفض استلامه وتواصل معنا فوراً.',
                ]],
                ['تعذّر التسليم', [
                    'إذا لم يكن أحد متاحاً، أو تعذّر التحقق من الهوية أو رمز التسليم، يعيد المندوب الطلب إلى متجرنا. وقد تُفرض رسوم توصيل إضافية لإعادة التوصيل، أو يمكنك استلامه من المتجر.',
                ]],
                ['الاستلام من المتجر', [
                    '- اختر الفرع عند إتمام الطلب، وسنبلغك عندما يصبح الطلب جاهزاً. أحضر هويتك الأصلية ورمز التسليم.',
                    '- يرجى الاستلام خلال 14 يوماً، وإن احتجت وقتاً أطول تواصل معنا، فطلبك محفوظ بأمان في المتجر.',
                ]],
                ['المسؤولية والملكية', [
                    'نتحمّل مسؤولية القطعة حتى التسليم، وتنتقل المسؤولية والملكية إليك بمجرد تسليمها وتأكيد رمز التسليم.',
                ]],
                ['للاستفسار', [
                    'واتساب :whatsapp · :phone · :email',
                ]],
            ],
        ];
    }
}
