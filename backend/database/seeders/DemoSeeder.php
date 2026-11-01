<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Enums\OrderStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\VipLevel;
use App\Models\Category;
use App\Models\ExternalStore;
use App\Models\ManualOrderField;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRoles();
        $this->seedSettings();
        $this->seedUsers();
        $this->seedExternalStores();
        $this->seedCategories();
        $this->seedProducts();
        $this->seedSampleOrders();
    }

    private function seedRoles(): void
    {
        foreach (['admin', 'moderator', 'user'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function seedSettings(): void
    {
        $defaults = [
            // VIP limits
            ['key' => 'vip1_withdrawal_limit', 'value' => '1000', 'group' => 'vip', 'type' => 'float', 'description' => 'Max withdrawal amount per transaction for VIP1 users'],
            ['key' => 'vip2_withdrawal_limit', 'value' => '2000', 'group' => 'vip', 'type' => 'float', 'description' => 'Max withdrawal amount per transaction for VIP2 users'],

            // VIP fees
            ['key' => 'vip1_fee_percent', 'value' => '3', 'group' => 'vip', 'type' => 'float', 'description' => 'Withdrawal fee % for VIP1'],
            ['key' => 'vip2_fee_percent', 'value' => '1.5', 'group' => 'vip', 'type' => 'float', 'description' => 'Withdrawal fee % for VIP2'],
            ['key' => 'regular_fee_percent', 'value' => '5', 'group' => 'vip', 'type' => 'float', 'description' => 'Withdrawal fee % for regular users'],

            // VIP upgrade pricing
            ['key' => 'vip1_upgrade_price', 'value' => '100', 'group' => 'vip', 'type' => 'float', 'description' => 'Price to upgrade to VIP1'],
            ['key' => 'vip2_upgrade_price', 'value' => '300', 'group' => 'vip', 'type' => 'float', 'description' => 'Price to upgrade to VIP2'],

            // Payment gateway (mock credentials)
            ['key' => 'binance_pay_key', 'value' => 'MOCK_BINANCE_KEY', 'group' => 'payment', 'type' => 'string', 'description' => 'Binance Pay API key'],
            ['key' => 'binance_pay_secret', 'value' => 'MOCK_BINANCE_SECRET', 'group' => 'payment', 'type' => 'string', 'description' => 'Binance Pay API secret'],
            ['key' => 'usdt_wallet_address', 'value' => '0xMOCKUSDTWALLETADDRESS', 'group' => 'payment', 'type' => 'string', 'description' => 'USDT BEP-20 shared deposit wallet'],
            ['key' => 'oranos_markup_percent', 'value' => '20', 'group' => 'payment', 'type' => 'float', 'description' => 'Oranos markup percentage applied to base prices'],
        ];

        foreach ($defaults as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }

        // Seed demo legal content (English)
        Setting::set('legal_terms_en', 'Terms of Service

By using Marketly, you agree to the following terms. Marketly is a digital marketplace that facilitates the purchase of digital products, software licenses, and online services. All transactions are final once the product is delivered. You are responsible for ensuring that your purchase complies with local laws and regulations in your jurisdiction. Refunds are processed at our discretion for undelivered or defective products only. We reserve the right to suspend accounts that violate these terms or engage in fraudulent activity. All prices are listed in USD unless otherwise stated. By completing a purchase, you confirm that you are at least 18 years of age or have parental consent to use this platform.', 'legal');

        Setting::set('legal_privacy_en', 'Privacy Policy

Marketly is committed to protecting your privacy. We collect minimal personal information necessary to process your orders, including your name, email address, and payment details. Payment information is processed securely through Binance Pay and is never stored on our servers. We may use your email address to send order confirmations and account-related notifications. We do not sell or share your personal data with third parties for marketing purposes. Cookies are used to maintain session state and improve your browsing experience. You have the right to request access to or deletion of your personal data at any time by contacting our support team.', 'legal');

        Setting::set('legal_refund_en', 'Refund Policy

Refunds at Marketly are evaluated on a case-by-case basis. Digital products that have been delivered and accessed are generally non-refundable. If you experience a technical issue or have not received your order within 24 hours, please contact our support team with your order details. Approved refunds are processed to your Marketly wallet balance within 3–5 business days. Chargebacks initiated through your payment provider without prior contact may result in account suspension. VIP membership upgrades and one-time fee payments are non-refundable once activated. We reserve the right to deny refund requests that appear fraudulent or abusive.', 'legal');

        // Seed demo legal content (Arabic)
        Setting::set('legal_terms_ar', 'شروط الخدمة

باستخدامك لـ Marketly، فإنك توافق على الشروط التالية. Marketly هو سوق رقمي يسهّل شراء المنتجات الرقمية وتراخيص البرامج والخدمات عبر الإنترنت. جميع المعاملات نهائية بمجرد تسليم المنتج. أنت مسؤول عن التأكد من أن عملية الشراء الخاصة بك تتوافق مع القوانين واللوائح المحلية في ولايتك القضائية. تتم معالجة المبالغ المستردة حسب تقديرنا للمنتجات غير المسلمة أو المعيبة فقط. نحتفظ بالحق في تعليق الحسابات التي تنتهك هذه الشروط أو تنخرط في أنشطة احتيالية. يتم عرض جميع الأسعار بالدولار الأمريكي ما لم يُذكر خلاف ذلك. بإتمام عملية الشراء، فإنك تؤكد أن عمرك 18 عاماً على الأقل أو أن لديك موافقة الوالدين على استخدام هذه المنصة.', 'legal');

        Setting::set('legal_privacy_ar', 'سياسة الخصوصية

تلتزم Marketly بحماية خصوصيتك. نجمع الحد الأدنى من المعلومات الشخصية اللازمة لمعالجة طلباتك، بما في ذلك اسمك وعنوان بريدك الإلكتروني وتفاصيل الدفع. تتم معالجة معلومات الدفع بشكل آمن عبر Binance Pay ولا يتم تخزينها أبداً على خوادمنا. قد نستخدم عنوان بريدك الإلكتروني لإرسال تأكيدات الطلبات وإشعارات الحساب. لا نبيع أو نشارك بياناتك الشخصية مع أطراف ثالثة لأغراض التسويق. تُستخدم ملفات تعريف الارتباط للحفاظ على حالة الجلسة وتحسين تجربة التصفح الخاصة بك. لديك الحق في طلب الوصول إلى بياناتك الشخصية أو حذفها في أي وقت عن طريق الاتصال بفريق الدعم لدينا.', 'legal');

        Setting::set('legal_refund_ar', 'سياسة الاسترداد

يتم تقييم المبالغ المستردة في Marketly على أساس كل حالة على حدة. المنتجات الرقمية التي تم تسليمها والوصول إليها غير قابلة للاسترداد بشكل عام. إذا واجهت مشكلة تقنية أو لم تستلم طلبك خلال 24 ساعة، يرجى الاتصال بفريق الدعم لدينا مع تفاصيل طلبك. تتم معالجة المبالغ المستردة المعتمدة على رصيد محفظتك في Marketly خلال 3-5 أيام عمل. قد يؤدي بدء استرداد المبالغ من خلال مزود الدفع الخاص بك دون اتصال مسبق إلى تعليق الحساب. ترقيات عضوية VIP والمدفوعات لمرة واحدة غير قابلة للاسترداد بمجرد تنشيطها. نحتفظ بالحق في رفض طلبات الاسترداد التي تبدو احتيالية أو مسيئة.', 'legal');
    }

    private function seedUsers(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@demo.test'],
            [
                'name' => 'Site Admin',
                'password' => Hash::make('password'),
                'vip_level' => VipLevel::Vip2,
                'balance' => 10000,
            ]
        );
        $admin->assignRole('admin');

        $mod = User::updateOrCreate(
            ['email' => 'mod@demo.test'],
            [
                'name' => 'Site Moderator',
                'password' => Hash::make('password'),
                'vip_level' => VipLevel::Vip1,
                'balance' => 2000,
            ]
        );
        $mod->assignRole('moderator');

        User::updateOrCreate(
            ['email' => 'user@demo.test'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'vip_level' => VipLevel::None,
                'balance' => 500,
            ]
        );

        User::updateOrCreate(
            ['email' => 'vip1@demo.test'],
            [
                'name' => 'VIP1 Demo',
                'password' => Hash::make('password'),
                'vip_level' => VipLevel::Vip1,
                'balance' => 1500,
            ]
        );

        User::updateOrCreate(
            ['email' => 'vip2@demo.test'],
            [
                'name' => 'VIP2 Demo',
                'password' => Hash::make('password'),
                'vip_level' => VipLevel::Vip2,
                'balance' => 5000,
            ]
        );
    }

    private function seedExternalStores(): void
    {
        ExternalStore::updateOrCreate(['name' => 'GameStore API'], [
            'url' => 'https://api.gamestore.example.com',
            'api_key' => 'mock-key-1',
            'type' => 'api',
            'is_active' => true,
            'notes' => 'Reseller API for game keys',
        ]);
        ExternalStore::updateOrCreate(['name' => 'CardHub Manual'], [
            'url' => 'https://cardhub.example.com',
            'api_key' => null,
            'type' => 'manual',
            'is_active' => true,
            'notes' => 'Manually fulfilled card store',
        ]);
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Games', 'name_ar' => 'الألعاب', 'type' => CategoryType::Auto, 'icon' => 'gamepad', 'sort_order' => 1, 'description' => 'Game keys, accounts, and in-game items', 'description_ar' => 'مفاتيح الألعاب والحسابات والعناصر داخل الألعاب'],
            ['name' => 'Chat Applications', 'name_ar' => 'تطبيقات الدردشة', 'type' => CategoryType::Auto, 'icon' => 'message', 'sort_order' => 2, 'description' => 'Premium subscriptions for chat and messaging apps', 'description_ar' => 'اشتراكات مميزة لتطبيقات الدردشة والرسائل'],
            ['name' => 'Cards', 'name_ar' => 'البطاقات', 'type' => CategoryType::Auto, 'icon' => 'credit-card', 'sort_order' => 3, 'description' => 'Gift and prepaid cards', 'description_ar' => 'البطاقات الهدية والبطاقات مسبقة الدفع'],
            ['name' => 'Balance Top-ups', 'name_ar' => 'شحن الرصيد', 'type' => CategoryType::Auto, 'icon' => 'wallet', 'sort_order' => 4, 'description' => 'Mobile balance and top-ups', 'description_ar' => 'شحن رصيد الهاتف وإعادة الشحن'],
            ['name' => 'Design Programs', 'name_ar' => 'برامج التصميم', 'type' => CategoryType::Auto, 'icon' => 'design', 'sort_order' => 5, 'description' => 'Software licenses for design tools', 'description_ar' => 'تراخيص برامج التصميم'],
            ['name' => 'Screen Subscriptions', 'name_ar' => 'اشتراكات البث', 'type' => CategoryType::Auto, 'icon' => 'monitor', 'sort_order' => 6, 'description' => 'Streaming service subscriptions', 'description_ar' => 'اشتراكات خدمات البث'],
            ['name' => 'VPN Subscriptions', 'name_ar' => 'اشتراكات VPN', 'type' => CategoryType::Auto, 'icon' => 'server', 'sort_order' => 7, 'description' => 'VPN access plans', 'description_ar' => 'خطط الوصول إلى VPN'],
            ['name' => 'Account Verification', 'name_ar' => 'التحقق من الحسابات', 'type' => CategoryType::Manual, 'icon' => 'check-circle', 'sort_order' => 8, 'description' => 'Manual account verification services', 'description_ar' => 'خدمات التحقق اليدوية من الحسابات'],
            ['name' => 'Artificial Intelligence', 'name_ar' => 'الذكاء الاصطناعي', 'type' => CategoryType::Auto, 'icon' => 'cpu', 'sort_order' => 9, 'description' => 'AI tool subscriptions', 'description_ar' => 'اشتراكات أدوات الذكاء الاصطناعي'],
            ['name' => 'Manual Charging & Store Offers', 'name_ar' => 'الشحن اليدوي وعروض المتجر', 'type' => CategoryType::Manual, 'icon' => 'handshake', 'sort_order' => 10, 'description' => 'Custom store offers and manual charging', 'description_ar' => 'عروض المتجر المخصصة والشحن اليدوي'],
            ['name' => 'Social Media Services', 'name_ar' => 'خدمات التواصل الاجتماعي', 'type' => CategoryType::Manual, 'icon' => 'share', 'sort_order' => 11, 'description' => 'Telegram, Facebook, Twitter and other social media services', 'description_ar' => 'تيليجرام وفيسبوك وتويتر وخدمات التواصل الاجتماعي الأخرى'],
        ];

        foreach ($categories as $data) {
            Category::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                $data
            );
        }

        // Add manual-order fields for the social media category
        $social = Category::where('slug', 'social-media-services')->first();
        if ($social) {
            $fields = [
                ['key' => 'platform', 'label' => 'Platform', 'label_ar' => 'المنصة', 'type' => 'select', 'required' => true, 'options' => ['telegram', 'facebook', 'twitter', 'instagram', 'youtube'], 'sort_order' => 1, 'placeholder' => 'Select platform'],
                ['key' => 'service_type', 'label' => 'Service Type', 'label_ar' => 'نوع الخدمة', 'type' => 'select', 'required' => true, 'options' => ['members', 'followers', 'likes', 'views', 'reactions'], 'sort_order' => 2, 'placeholder' => 'Select service'],
                ['key' => 'link', 'label' => 'Profile / Channel Link', 'label_ar' => 'رابط الملف الشخصي', 'type' => 'text', 'required' => true, 'sort_order' => 3, 'placeholder' => 'https://t.me/...'],
                ['key' => 'quantity', 'label' => 'Quantity', 'label_ar' => 'الكمية', 'type' => 'number', 'required' => true, 'sort_order' => 4, 'placeholder' => 'e.g. 1000'],
                ['key' => 'notes', 'label' => 'Additional Notes', 'label_ar' => 'ملاحظات إضافية', 'type' => 'textarea', 'required' => false, 'sort_order' => 5, 'placeholder' => 'Any special instructions'],
            ];
            foreach ($fields as $field) {
                ManualOrderField::updateOrCreate(
                    ['category_id' => $social->id, 'key' => $field['key']],
                    $field
                );
            }
        }

        // Manual fields for Account Verification
        $verify = Category::where('slug', 'account-verification')->first();
        if ($verify) {
            $fields = [
                ['key' => 'service', 'label' => 'Verification Service', 'label_ar' => 'خدمة التحقق', 'type' => 'select', 'required' => true, 'options' => ['facebook_blue', 'instagram_blue', 'twitter_blue', 'youtube_monetization', 'telegram_premium'], 'sort_order' => 1, 'placeholder' => 'Select service'],
                ['key' => 'account_link', 'label' => 'Account Link / Username', 'label_ar' => 'رابط الحساب', 'type' => 'text', 'required' => true, 'sort_order' => 2, 'placeholder' => '@username or profile URL'],
                ['key' => 'documents_info', 'label' => 'Document Description', 'label_ar' => 'وصف المستند', 'type' => 'textarea', 'required' => true, 'sort_order' => 3, 'placeholder' => 'Describe the documents you can provide'],
            ];
            foreach ($fields as $field) {
                ManualOrderField::updateOrCreate(
                    ['category_id' => $verify->id, 'key' => $field['key']],
                    $field
                );
            }
        }

        // Form schema for Manual Charging & Store Offers
        $manualCharging = Category::where('slug', 'manual-charging-store-offers')->first();
        if ($manualCharging) {
            $manualCharging->update([
                'form_schema' => [
                    ['key' => 'store_type', 'label' => 'Store Type', 'label_ar' => 'نوع المتجر', 'type' => 'select', 'required' => true, 'options' => ['Product Reseller', 'Digital Service', 'Custom Order']],
                    ['key' => 'profile_link', 'label' => 'Profile / Order Link', 'label_ar' => 'الرابط / ملف الطلب', 'type' => 'text', 'required' => true, 'options' => []],
                    ['key' => 'order_description', 'label' => 'Order Description', 'label_ar' => 'وصف الطلب', 'type' => 'textarea', 'required' => false, 'options' => []],
                ],
            ]);
        }
    }

    private function seedProducts(): void
    {
        $store = ExternalStore::where('name', 'GameStore API')->first();
        $categories = Category::pluck('id', 'slug');

        $products = [
            // Games
            [
                'category' => 'games',
                'name' => 'Steam Gift Card $50',
                'name_ar' => 'بطاقة هدايا ستيم 50$',
                'price' => 50.00,
                'stock' => 100,
                'store' => $store?->id,
                'description' => 'Redeemable on Steam for any game, DLC, software, or in-game item of your choice. The code is delivered instantly to your Marketly inbox after checkout — no waiting, no shipping.',
                'description_ar' => 'قابلة للاستخدام على ستيم لأي لعبة أو محتوى إضافي أو برنامج أو عنصر داخل اللعبة. يتم تسليم الكود فوراً إلى صندوق بريدك في Marketly بعد الدفع — بدون انتظار أو شحن.',
            ],
            [
                'category' => 'games',
                'name' => 'PlayStation Plus 3 Months',
                'name_ar' => 'بلايستيشن بلس 3 أشهر',
                'price' => 25.00,
                'stock' => 50,
                'store' => null,
                'description' => 'Three months of PlayStation Plus Essential — online multiplayer, two free monthly games, exclusive discounts, and 100 GB of cloud storage. Digital code delivered to your account in minutes.',
                'description_ar' => 'ثلاثة أشهر من بلايستيشن بلس Essential — اللعب الجماعي عبر الإنترنت، لعبتان مجانيتان شهرياً، خصومات حصرية، و100 جيجابايت من التخزين السحابي. كود رقمي يُسلَّم لحسابك خلال دقائق.',
            ],
            [
                'category' => 'games',
                'name' => 'Xbox Game Pass Ultimate 1 Month',
                'name_ar' => 'إكس بوكس غيم باس ألتميت شهر واحد',
                'price' => 14.99,
                'stock' => 80,
                'store' => null,
                'description' => 'One month of Xbox Game Pass Ultimate. Stream and download over 100 high-quality games on console, PC, and cloud. Includes EA Play membership and Xbox Live Gold.',
                'description_ar' => 'شهر واحد من إكس بوكس غيم باس ألتميت. استمتع بأكثر من 100 لعبة عالية الجودة على وحدة التحكم والكمبيوتر والسحابة. يشمل عضوية EA Play وXbox Live Gold.',
            ],

            // Chat
            [
                'category' => 'chat-applications',
                'name' => 'Telegram Premium 3 Months',
                'name_ar' => 'تيليجرام بريميوم 3 أشهر',
                'price' => 12.99,
                'stock' => 200,
                'store' => null,
                'description' => 'Three months of Telegram Premium for any account. Get exclusive stickers, faster downloads, animated profile photos, 4 GB file uploads, and an ad-free experience.',
                'description_ar' => 'ثلاثة أشهر من تيليجرام بريميوم لأي حساب. احصل على ملصقات حصرية، تنزيلات أسرع، صور شخصية متحركة، رفع ملفات حتى 4 جيجابايت، وتجربة بدون إعلانات.',
            ],
            [
                'category' => 'chat-applications',
                'name' => 'WhatsApp Business API Setup',
                'name_ar' => 'إعداد واجهة واتساب للأعمال',
                'price' => 49.00,
                'stock' => 30,
                'store' => null,
                'description' => 'Full WhatsApp Business API onboarding — verified business account, automated greeting messages, and 1,000 free conversational sessions per month. Delivered within 24 hours.',
                'description_ar' => 'إعداد كامل لواجهة واتساب للأعمال — حساب أعمال موثق، رسائل ترحيب تلقائية، و1000 جلسة محادثة مجانية شهرياً. يتم التسليم خلال 24 ساعة.',
            ],

            // Cards
            [
                'category' => 'cards',
                'name' => 'Amazon Gift Card $25',
                'name_ar' => 'بطاقة هدايا أمازون 25$',
                'price' => 25.00,
                'stock' => 200,
                'store' => $store?->id,
                'description' => 'Official Amazon.com gift card redeemable across millions of products — no fees, no expiration date. Perfect for birthdays, holidays, or treating yourself.',
                'description_ar' => 'بطاقة هدايا أمازون الرسمية القابلة للاستخدام على ملايين المنتجات — بدون رسوم وبدون تاريخ انتهاء. مثالية لأعياد الميلاد والعطلات أو لمكافأة نفسك.',
            ],
            [
                'category' => 'cards',
                'name' => 'iTunes Gift Card $50',
                'name_ar' => 'بطاقة هدايا آيتونز 50$',
                'price' => 50.00,
                'stock' => 150,
                'store' => null,
                'description' => 'Apple App Store & iTunes gift card. Buy apps, games, music, movies, iCloud storage, and subscriptions for any Apple ID. Code delivered instantly.',
                'description_ar' => 'بطاقة هدايا متجر آبل وآيتونز. اشترِ التطبيقات والألعاب والموسيقى والأفلام وتخزين iCloud والاشتراكات لأي حساب Apple. الكود يُسلَّم فوراً.',
            ],

            // Balance
            [
                'category' => 'balance-top-ups',
                'name' => 'Mobile Top-up $20',
                'name_ar' => 'شحن رصيد الهاتف 20$',
                'price' => 20.00,
                'stock' => 500,
                'store' => null,
                'description' => 'Instant mobile airtime top-up for major carriers. Add $20 to your prepaid balance in seconds — supports AT&T, T-Mobile, Verizon, and 200+ carriers worldwide.',
                'description_ar' => 'شحن فوري لرصيد الهاتف لجميع شركات الاتصالات الكبرى. أضف 20$ إلى رصيدك المدفوع مسبقاً في ثوانٍ — يدعم AT&T وT-Mobile وVerizon وأكثر من 200 شركة حول العالم.',
            ],

            // Design
            [
                'category' => 'design-programs',
                'name' => 'Adobe Creative Cloud 1 Month',
                'name_ar' => 'أدوبي كريتيف كلاود شهر واحد',
                'price' => 54.99,
                'stock' => 40,
                'store' => null,
                'description' => 'One month of Adobe Creative Cloud — Photoshop, Illustrator, Premiere Pro, After Effects, and 20+ apps. Cloud storage, fonts, and portfolio access included.',
                'description_ar' => 'شهر واحد من أدوبي كريتيف كلاود — فوتوشوب وإليستريتور وبريمير برو وأفتر إفكتس وأكثر من 20 تطبيقاً. يشمل التخزين السحابي والخطوط والوصول إلى بورتفوليو.',
            ],
            [
                'category' => 'design-programs',
                'name' => 'Figma Professional 1 Year',
                'name_ar' => 'فيغما بروفيشنال سنة واحدة',
                'price' => 180.00,
                'stock' => 25,
                'store' => null,
                'description' => 'A full year of Figma Professional for one designer. Unlimited Figma files, advanced prototyping, team libraries, FigJam boards, and priority support.',
                'description_ar' => 'سنة كاملة من فيغما بروفيشنال لمصمم واحد. ملفات فيغما غير محدودة، نماذج أولية متقدمة، مكتبات الفريق، لوحات FigJam، ودعم ذو أولوية.',
            ],

            // Streaming
            [
                'category' => 'screen-subscriptions',
                'name' => 'Netflix Premium 1 Month',
                'name_ar' => 'نتفلكس بريميوم شهر واحد',
                'price' => 17.99,
                'stock' => 100,
                'store' => null,
                'description' => 'One month of Netflix Premium — 4K Ultra HD streaming on 4 screens simultaneously. Watch thousands of TV shows, movies, and Netflix originals.',
                'description_ar' => 'شهر واحد من نتفلكس بريميوم — بث بدقة 4K Ultra HD على 4 شاشات في وقت واحد. شاهد آلاف المسلسلات والأفلام وأعمال نتفلكس الأصلية.',
            ],
            [
                'category' => 'screen-subscriptions',
                'name' => 'Spotify Premium 6 Months',
                'name_ar' => 'سبوتيفاي بريميوم 6 أشهر',
                'price' => 59.94,
                'stock' => 80,
                'store' => null,
                'description' => 'Six months of Spotify Premium Individual. Ad-free music, offline listening, unlimited skips, and high-quality audio streaming on any device.',
                'description_ar' => 'ستة أشهر من سبوتيفاي بريميوم فردي. موسيقى بدون إعلانات، استماع بدون اتصال، تخطي غير محدود، وبث صوتي عالي الجودة على أي جهاز.',
            ],

            // VPN
            [
                'category' => 'vpn-subscriptions',
                'name' => 'NordVPN 1 Year',
                'name_ar' => 'نورد في بي إن سنة واحدة',
                'price' => 59.99,
                'stock' => 60,
                'store' => null,
                'description' => 'A full year of NordVPN — secure browsing, 5,500+ servers in 60 countries, military-grade encryption, malware protection, and support for 6 devices.',
                'description_ar' => 'سنة كاملة من نورد في بي إن — تصفح آمن، أكثر من 5500 خادم في 60 دولة، تشفير عسكري، حماية من البرامج الضارة، ودعم حتى 6 أجهزة.',
            ],

            // Account Verification (manual)
            [
                'category' => 'account-verification',
                'name' => 'Social Media Verification',
                'name_ar' => 'توثيق حسابات التواصل الاجتماعي',
                'price' => 49.00,
                'stock' => 999,
                'type' => CategoryType::Manual,
                'description' => 'Manual social media verification service. Submit your account and required documents — our team completes the verification badge process within 3-7 business days.',
                'description_ar' => 'خدمة توثيق يدوي لحسابات التواصل الاجتماعي. أرسل حسابك والوثائق المطلوبة — فريقنا يكمل عملية التوثيق خلال 3-7 أيام عمل.',
            ],

            // AI
            [
                'category' => 'artificial-intelligence',
                'name' => 'ChatGPT Plus 1 Month',
                'name_ar' => 'شات جي بي تي بلس شهر واحد',
                'price' => 20.00,
                'stock' => 100,
                'store' => null,
                'description' => 'One month of ChatGPT Plus — GPT-4 access, faster response times, priority access during peak hours, and advanced data analysis with DALL·E image generation.',
                'description_ar' => 'شهر واحد من شات جي بي تي بلس — وصول إلى GPT-4، أوقات استجابة أسرع، أولوية الوصول في أوقات الذروة، وتحليل متقدم للبيانات مع توليد الصور بـ DALL·E.',
            ],
            [
                'category' => 'artificial-intelligence',
                'name' => 'Midjourney Pro 1 Month',
                'name_ar' => 'ميدجورني برو شهر واحد',
                'price' => 30.00,
                'stock' => 80,
                'store' => null,
                'description' => 'One month of Midjourney Pro plan — 30 fast GPU hours per month, stealth image generation, and access to the latest AI image models on Discord.',
                'description_ar' => 'شهر واحد من خطة ميدجورني برو — 30 ساعة GPU سريعة شهرياً، توليد صور في وضع التخفي، والوصول إلى أحدث نماذج الذكاء الاصطناعي للصور على ديسكورد.',
            ],

            // Manual Charging
            [
                'category' => 'manual-charging-store-offers',
                'name' => 'Custom Store Offer',
                'name_ar' => 'عرض متجر مخصص',
                'price' => 25.00,
                'stock' => 999,
                'type' => CategoryType::Manual,
                'description' => 'Need something custom? Tell us what you want to purchase or top up, and our team will manually handle the order within 24 hours. Minimum order $25.',
                'description_ar' => 'تحتاج شيئاً مخصصاً؟ أخبرنا بما تريد شراءه أو شحنه، وسيقوم فريقنا بمعالجة الطلب يدوياً خلال 24 ساعة. الحد الأدنى للطلب 25$.',
            ],

            // Social Media Services (manual)
            [
                'category' => 'social-media-services',
                'name' => 'Telegram Members 1000',
                'name_ar' => '1000 عضو تيليجرام',
                'price' => 15.00,
                'stock' => 999,
                'type' => CategoryType::Manual,
                'description' => 'Add 1,000 real, active members to your Telegram channel or group. Gradual delivery to keep your growth looking organic. Non-drop guarantee included.',
                'description_ar' => 'أضف 1000 عضو نشط وحقيقي إلى قناتك أو مجموعتك في تيليجرام. تسليم تدريجي لجعل النمو يبدو طبيعياً. ضمان عدم النقصان.',
            ],
            [
                'category' => 'social-media-services',
                'name' => 'Facebook Page Likes 1000',
                'name_ar' => '1000 إعجاب لصفحة فيسبوك',
                'price' => 25.00,
                'stock' => 999,
                'type' => CategoryType::Manual,
                'description' => 'Boost your Facebook page credibility with 1,000 high-quality page likes from real-looking profiles. Delivered over 3-5 days for natural-looking growth.',
                'description_ar' => 'عزز مصداقية صفحتك على فيسبوك بـ 1000 إعجاب عالي الجودة من ملفات شخصية تبدو حقيقية. يتم التسليم خلال 3-5 أيام لنمو طبيعي.',
            ],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(
                ['slug' => Str::slug($p['name'])],
                [
                    'category_id' => $categories[$p['category']] ?? null,
                    'name' => $p['name'],
                    'name_ar' => $p['name_ar'] ?? null,
                    'description' => $p['description'],
                    'description_ar' => $p['description_ar'] ?? null,
                    'price' => $p['price'],
                    'stock' => $p['stock'],
                    'type' => $p['type'] ?? CategoryType::Auto,
                    'is_active' => true,
                    'external_store_id' => $p['store'] ?? null,
                    'image_url' => $p['image_url'] ?? null,
                ]
            );
        }
    }

    private function seedSampleOrders(): void
    {
        $vip2 = User::where('email', 'vip2@demo.test')->first();
        $vip1 = User::where('email', 'vip1@demo.test')->first();
        $user = User::where('email', 'user@demo.test')->first();
        $spotify = Product::where('slug', 'spotify-premium-6-months')->first();
        $telegram = Product::where('slug', 'telegram-premium-3-months')->first();
        $telegramMembers = Product::where('slug', 'telegram-members-1000')->first();

        if ($vip2 && $spotify) {
            $order = Order::create([
                'user_id' => $vip2->id,
                'status' => OrderStatus::Completed,
                'subtotal' => $spotify->price,
                'fee' => 0,
                'total' => $spotify->price,
                'payment_method' => 'cash_wallet',
                'payment_ref' => 'demo-spotify-1',
            ]);
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $spotify->id,
                'quantity' => 1,
                'unit_price' => $spotify->price,
            ]);
        }

        if ($vip1 && $telegram) {
            $order = Order::create([
                'user_id' => $vip1->id,
                'status' => OrderStatus::Processing,
                'subtotal' => $telegram->price,
                'fee' => 0,
                'total' => $telegram->price,
                'payment_method' => 'binance_pay',
                'payment_ref' => 'demo-tg-1',
            ]);
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $telegram->id,
                'quantity' => 1,
                'unit_price' => $telegram->price,
            ]);
        }

        if ($user && $telegramMembers) {
            $order = Order::create([
                'user_id' => $user->id,
                'status' => OrderStatus::Pending,
                'subtotal' => $telegramMembers->price,
                'fee' => 0,
                'total' => $telegramMembers->price,
                'payment_method' => 'cash_wallet',
            ]);
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $telegramMembers->id,
                'quantity' => 1,
                'unit_price' => $telegramMembers->price,
                'payload' => [
                    'platform' => 'telegram',
                    'service_type' => 'members',
                    'link' => 'https://t.me/demo_channel',
                    'quantity' => '1000',
                ],
            ]);
        }

        // Sample transactions
        if ($vip2) {
            Transaction::create([
                'user_id' => $vip2->id,
                'type' => TransactionType::Deposit,
                'amount' => 1000,
                'fee' => 0,
                'status' => TransactionStatus::Approved,
                'method' => 'binance_pay',
                'gateway_ref' => 'demo-deposit-1',
            ]);
        }
        if ($vip1) {
            Transaction::create([
                'user_id' => $vip1->id,
                'type' => TransactionType::Withdrawal,
                'amount' => 500,
                'fee' => 15,
                'status' => TransactionStatus::Pending,
                'method' => 'usdt',
            ]);
        }
    }
}
