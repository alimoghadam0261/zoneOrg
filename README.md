# ZONE — سامانه جغرافیانگاشت، مدیریت محدوده و کنترل دسترسی بلادرنگ

سامانه **ZONE** برای شرکت تعمیرات نیروگاهی ایران: تعریف محدوده‌های جغرافیایی (Geofence) روی نقشه، دریافت پینگ GPS پرسنل از طریق API، ارزیابی بلادرنگ ورود/خروج و قوانین دسترسی، و نمایش زنده رویدادها و هشدارها برای اپراتور امنیت.

- **پشته فنی:** Laravel 12 · Livewire 3 · Tailwind CSS 3 (RTL) · Leaflet + leaflet-draw · MySQL 8 · Sanctum
- **زبان رابط:** فارسی (راست‌چین) با فونت وزیرمتن و حالت تاریک
- **داده‌های نمونه:** ۱۰۰ پرسنل، ۱۰۰ دستگاه، ۵ منطقه، ۴۰۰ پینگ و رویدادهای تاریخی

---

## پیش‌نیازها

| ابزار | نسخه آزمایش‌شده |
|---|---|
| PHP | 8.4 |
| Composer | 2.x |
| Node.js / npm | 24 / 10+ |
| MySQL | 8.x (مثال: WAMP) |

---

## راه‌اندازی

```bash
# ۱) وابستگی‌های PHP
composer install

# ۲) پیکربندی
cp .env.example .env
php artisan key:generate
# سپس اطلاعات دیتابیس را در .env تنظیم کنید (پایین)

# ۳) دیتابیس نمونه
mysql -uroot -e "CREATE DATABASE zone_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate:fresh --seed

# ۴) دارایی‌های فرانت‌اند (Tailwind/Leaflet/Vite)
npm install
npm run build

# ۵) اجرا
php artisan serve          # http://127.0.0.1:8000
```

بخش‌های کلیدی `.env`:

```dotenv
APP_NAME=ZONE
APP_LOCALE=fa
APP_TIMEZONE=Asia/Tehran

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zone_db
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=file

# چیدمان موتور جغرافیایی
ZONE_RETENTION_DAYS=7          # نگهداری پینگ‌های خام
ZONE_MIN_ACCURACY_METERS=25    # آستانه دقت GPS برای تحریک هشدار
ZONE_CONFIRM_PINGS=2           # پینگ متوالی برای تأیید نقض (debounce)
ZONE_OFFLINE_AFTER_MINUTES=5   # آستانه وضعیت آفلاین
ZONE_PING_MAX_PER_SECOND=20    # محدودیت نرخ API هر توکن
```

> برای شروع سریع، لاگین نمونه از پیش ساخته شده است؛ نیازی به ساخت دستی کاربر نیست.

### ورود نمونه

| فیلد | مقدار |
|---|---|
| ایمیل | `admin@zone.local` |
| گذرواژه | `password` |

### توکن نمونه دستگاه (برای API)

```
zone_dev_token_00000000000000000000000001
```

> توکن به‌صورت hash_sha256 روی «نخستین» دستگاه ذخیره می‌شود؛ سایر توکن‌های دیده‌شده در خروجی سیدر یکتایند.

---

## صفحات سامانه

| مسیر | توضیح |
|---|---|
| `/login` | ورود مدیر/اپراتور |
| `/monitoring` | داشبورد زنده امنیت: مارکر پرسنل، فید هشدار، آلارم صوتی، فیلتر وضعیت |
| `/zones` | سازنده مناطق: رسم چندضلعی/مستطیل/دایره، ویرایش، قوانین دسترسی |
| `/events` | گزارش رویدادها و هشدارها با فیلتر و بستن رویداد |
| `/people` | دایرکتوری پرسنل و وضعیت لحظه‌ای |
| `/devices` | دستگاه‌ها، توکن‌ها و آخرین پینگ |

---

## API تلمتری (نسخه ۱)

### `POST /api/v1/telemetry/ping`

دریافت یک رفع‌موقعیت (GPS fix) از اپ موبایل یا تگ GPS.

**هدرها:**

```
Authorization: Bearer <SANCTUM_TOKEN>
Accept: application/json
Content-Type: application/json
```

**فیلدها:**

| فیلد | الزامی | توضیح |
|---|---|---|
| `lat` | ✓ | عرض جغرافیایی، −۹۰ تا ۹۰ |
| `lng` | ✓ | طول جغرافیایی، −۱۸۰ تا ۱۸۰ |
| `accuracy` | ✓ | دقت GPS به متر (صفر تا ۱۰۰۰۰۰) |
| `speed` | – | سرعت به متر/ثانیه (۰ تا ۵۰۰) |
| `heading` | – | زاویه حرکت، ۰ تا ۳۶۰ |
| `battery` | – | شارژ باتری، ۰ تا ۱۰۰ |
| `captured_at` | – | زمان ثبت پینگ؛ پیش‌فرض `now()` |

**نمونه درخواست:**

```bash
curl -X POST http://127.0.0.1:8000/api/v1/telemetry/ping \
  -H "Authorization: Bearer zone_dev_token_00000000000000000000000001" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"lat": 35.6895, "lng": 51.3890, "accuracy": 8, "battery": 76}'
```

**پاسخ موفق (۲۰۰):**

```json
{
  "success": true,
  "message": "موقعیت با موفقیت ثبت شد.",
  "data": {
    "ping_id": 1234,
    "zones": [
      {"id": 3, "name": "سوله سوخت", "code": "SOULEH_01", "severity": "critical", "color": "#ef4444", "access": "deny", "type": "polygon"}
    ],
    "inside_any_zone": true,
    "violation": true,
    "severity": "critical",
    "events": [
      {"id": 501, "type": "violation_entered", "severity": "critical", "zone": "سوله سوخت"}
    ]
  }
}
```

**خطاها:**

- `۴۰۱` — توکن نامعتبر، غیرفعال یا متعلق به دستگاه حذف‌شده
- `۴۲۲` — اعتبارسنجی ناموفق با پیام‌های فارسی (مثال: «عرض جغرافیایی (lat) الزامی است.»)
- `۴۲۹` — بیش از حد مجاز (`ZONE_PING_MAX_PER_SECOND` = ۲۰ درخواست در ثانیه برای هر توکن)

---

## موتور جغرافیایی

1. **فاز ۱ — BBox:** با هر پینگ، ستون‌های `min_/max_lat/lng` مناطق فعال (کش‌شده در `zone.index.active_v1`) به‌عنوان پیش‌فیلتر استفاده می‌شوند.
2. **فاز ۲ — محاسبه دقیق:** `ray-casting` برای چندضلعی/مستطیل و `haversine` برای دایره (نقطه + شعاع).
3. **قوانین دسترسی** بر اساس مشخص‌بودن، با اولویت:
   `پرسنل (۳۰)` ← `واحد سازمانی (۲۰)` ← `نوع قرارداد (۱۰)` ← `پیش‌فرض/ wildcard (۰)`
4. **Debounce تأیید نقض:** نقض با `ZONE_CONFIRM_PINGS` (پیش‌فرض ۲) پینگ متوالی در منطقه ممنوع تأیید می‌شود؛ شمارش از لحظه ورود آغاز می‌گردد. رویدادها: `entered`، `exited`، `violation_entered`، `violation_lingering` (با فاصله حداقل `ZONE_LINGERING_INTERVAL`).
5. **کیفیت موقعیت:** پینگی با دقت بدتر از `ZONE_MIN_ACCURACY_METERS` (۲۵ متر) نقض تحریک نمی‌کند.
6. **حضور:** آخرین پینگ قدیمی‌تر از `ZONE_OFFLINE_AFTER_MINUTES` (۵ دقیقه) ← «آفلاین».

---

## دستورات عملیاتی

```bash
# شبیه‌سازی ترافیک تلمتری (نمایش زنده داشبورد)
php artisan zone:simulate --once --devices=100
php artisan zone:simulate --count=25 --interval=2 --devices=100

# پاک‌سازی پینگ‌های قدیمی (خارج از دوره نگهداری)
php artisan zone:purge-old-pings              # با ZONE_RETENTION_DAYS
php artisan zone:purge-old-pings --days=3     # دوره دلخواه
php artisan zone:purge-old-pings --dry-run    # فقط گزارش

# زمان‌بندی: اجرای روزانه ساعت ۰۰:۱۵ (منطقه زمانی APP_TIMEZONE) در routes/console.php
php artisan schedule:work                     # در محیط توسعه
```

---

## تست

```bash
XDEBUG_MODE=off php artisan test
```

**۵۷ تست · ۱۶۵ assertion — همه سبز** (موتور هندسی، قوانین دسترسی، API، پاک‌سازی، کامپوننت‌های Livewire، صفحات)

---

## ساختار کلیدی پروژه

```
app/Services/Geofencing/   GeoJson · Geometry · ZoneIndex · AccessRuleResolver
                           ZoneEvaluator · PingProcessor · PingResult
app/Livewire/              ZoneBuilder · LiveDashboard · EventLogTable
                           PeopleDirectory · DeviceManager
app/Http/                  Api/V1/TelemetryPingController + StorePingRequest
                           middleware AuthenticateDevice (Sanctum bearer)
app/Console/Commands/      zone:purge-old-pings · zone:simulate
resources/js/              zone-builder.js · live-map.js (پل Leaflet ↔ Livewire)
resources/views/           قالب RTL + کامپوننت‌های Blade معادل
config/zone.php            نگهداری، debounce، حضور، لایه‌های نقشه
database/seeders/          ZoneSeeder · DatabaseSeeder (۱۰۰ پرسنل/دستگاه)
tests/                     Feature + Unit (۵۷ تست)
```

---

## نکته‌ها

- **لایه‌های نقشه:** خیابان (OSM) · ماهواره‌ای (Google imagery) · صنعتی تیره · روشن — از منوی «نقشه» روی هر صفحه قابل تعویض است.
- **کش مناطق:** پس از هر ذخیره/ویرایش/تغییر وضعیت منطقه، کش خودکار پاک می‌شود؛ برای سید مجدد ابتدا `php artisan cache:clear`.
- **آلارم صوتی:** در داشبورد زنده با دکمه «هشدار صوتی» روشن/خاموش می‌شود (وضعیت در `localStorage`).
- **منطقه زمانی:** `APP_TIMEZONE=Asia/Tehran` تا زمان‌های مطلق ثبت‌شده با ساعت محلی یکسان باشد.
