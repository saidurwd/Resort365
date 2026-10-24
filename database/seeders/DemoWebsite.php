<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Attachments\Attachments;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Modules\Guest\Contracts\GuestRegistry;
use Modules\Guest\DTOs\GuestDetails;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\Property;
use Modules\Property\Models\RoomType;
use Modules\Rates\Models\Promotion;
use Modules\Rates\Models\RatePlan;
use Modules\Reservation\Contracts\BookingEngine;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Website\Actions\SaveWebsiteSite;
use Modules\Website\Actions\SaveWebsiteUnits;
use Modules\Website\Enums\PayChoice;
use Modules\Website\Enums\SiteTheme;
use Modules\Website\Models\WebsiteBooking;
use Modules\Website\Models\WebsiteSite;
use Spatie\MediaLibrary\HasMedia;

/**
 * Public website demo (Step 8.2a): Rodela's site, published in English and Bangla at http://rodela.resort365.test (`/` opens it),
 * with placeholder photos (soft gradients drawn with GD; there are no real photos) for the site, the room types and the cottage
 * types, the family villas and honeymoon cottages featured, and the Long stay promotion on the Offers page (MONSOON20 and
 * EARLYBIRD stay private), and one booking made on the website (Farhana Akter, a Deluxe King in 25 days, tentative). Green Valley has a site in English that is not published (open it as a preview when signed in).
 */
final class DemoWebsite
{
    /** Gradient colours of the placeholder photos: sea, garden, sunset, sand. */
    private const array PALETTES = [[[24, 92, 128], [120, 186, 196]], [[46, 107, 59], [170, 204, 140]], [[196, 85, 44], [244, 196, 120]], [[138, 106, 47], [236, 220, 180]]];

    public static function seed(Tenant $rodela, int $resort, Tenant $greenValley, int $valley): void
    {
        app(TenantContext::class)->run($rodela, function () use ($resort): void {
            $property = Property::query()->findOrFail($resort);
            $site = SaveWebsiteSite::make()->handle(WebsiteSite::query()->where('property_id', $resort)->first(), $resort, [
                'slug' => 'rodela', 'is_published' => true, 'theme' => SiteTheme::Forest->value, 'name' => 'Rodela Eco Resort',
                'phone' => '+880 1711 000000', 'whatsapp' => '+880 1711 000000', 'email' => 'stay@rodelaresort.com', 'address' => $property->address ?? "Marine Drive, Inani, Cox's Bazar",
                'map_url' => 'https://maps.google.com/?q=Inani+Beach+Cox%27s+Bazar', 'facebook_url' => 'https://facebook.com/rodelaresort', 'instagram_url' => 'https://instagram.com/rodelaresort',
                'tagline' => ['en' => 'Eco cottages between the hills and the sea', 'bn' => 'পাহাড় আর সমুদ্রের মাঝে পরিবেশবান্ধব কটেজ'],
                'intro_title' => ['en' => "Welcome to Cox's Bazar's quiet side", 'bn' => 'কক্সবাজারের নিরিবিলি প্রান্তে স্বাগতম'],
                'intro_body' => [
                    'en' => "Rodela sits on Marine Drive, a short walk from Inani beach. Our cottages are built from local bamboo and timber, shaded by a garden we grow ourselves.\nWake up to the sound of the sea, eat what the morning's fishermen brought in, and watch the sun set from your own veranda.",
                    'bn' => "রোদেলা মেরিন ড্রাইভের পাশে, ইনানী সৈকত থেকে অল্প হাঁটার পথে। আমাদের কটেজগুলো স্থানীয় বাঁশ ও কাঠে তৈরি, নিজেদের বাগানের ছায়ায় ঘেরা।\nসমুদ্রের শব্দে ঘুম ভাঙুক, সকালের জেলেদের আনা মাছে হোক খাবার, আর নিজের বারান্দা থেকে দেখুন সূর্যাস্ত।",
                ],
                'highlights' => ['en' => "Five minutes to Inani beach\nOrganic kitchen garden\nSea-view honeymoon cottages\nFamily villas with a BBQ area", 'bn' => "ইনানী সৈকত পাঁচ মিনিটের পথ\nজৈব সবজির বাগান\nসমুদ্রমুখী হানিমুন কটেজ\nবারবিকিউ এলাকাসহ ফ্যামিলি ভিলা"],
                'dining_intro' => ['en' => 'Fresh fish from Inani, vegetables from our garden and Bangladeshi home cooking, served in the Main Restaurant or by the pool.', 'bn' => 'ইনানীর তাজা মাছ, আমাদের বাগানের সবজি আর দেশি ঘরোয়া রান্না: মূল রেস্তোরাঁয় বা পুলের পাশে।'],
                'house_rules' => ['en' => "Quiet hours from 11 pm to 7 am\nNo smoking inside the cottages\nPets are welcome by arrangement\nPlease show a photo ID at check-in", 'bn' => "রাত ১১টা থেকে সকাল ৭টা পর্যন্ত নীরবতা\nকটেজের ভেতরে ধূমপান নিষেধ\nআগে জানালে পোষা প্রাণী আনা যাবে\nচেক-ইনের সময় ছবিসহ পরিচয়পত্র দেখান"],
                'seo_title' => ['en' => "Rodela Eco Resort · Cottages by Inani beach, Cox's Bazar", 'bn' => 'রোদেলা ইকো রিসোর্ট · ইনানী সৈকতের পাশে কটেজ'],
                'seo_description' => ['en' => "Bamboo cottages, family villas and sea-view honeymoon suites on Marine Drive, five minutes from Inani beach in Cox's Bazar.", 'bn' => 'কক্সবাজারের মেরিন ড্রাইভে, ইনানী সৈকতের কাছে বাঁশের কটেজ, ফ্যামিলি ভিলা ও হানিমুন স্যুট।'],
            ]);

            self::photos($site, 3, ['Inani beach', 'The garden', 'Sunset from the veranda'], 0);

            $units = [];

            foreach (RoomType::query()->where('property_id', $resort)->orderBy('sort_order')->get() as $index => $type) {
                self::photos($type, 2, [$type->name, $type->name], $index);
                $units['room_type:'.$type->id] = ['sort_order' => 10 + $index] + match ($type->code) {
                    'HS' => ['headline' => ['en' => 'A king bed, a bathtub and the sea outside', 'bn' => 'কিং বেড, বাথটাব আর জানালার বাইরে সমুদ্র']],
                    'FS' => ['headline' => ['en' => 'Room for the whole family', 'bn' => 'পুরো পরিবারের জন্য জায়গা']],
                    default => [],
                };
            }

            foreach (CottageType::query()->where('property_id', $resort)->orderBy('sort_order')->get() as $index => $type) {
                self::photos($type, 2, [$type->name, $type->name], $index + 1);
                $units['cottage_type:'.$type->id] = ['sort_order' => $index] + match ($type->code) {
                    'HC' => ['is_featured' => true, 'headline' => ['en' => 'Just the two of you, steps from the beach', 'bn' => 'শুধু আপনারা দুজন, সৈকত থেকে কয়েক পা দূরে']],
                    'FV' => ['is_featured' => true, 'headline' => ['en' => 'Three bedrooms, a lounge and your own garden', 'bn' => 'তিনটি শোবার ঘর, বসার ঘর আর নিজস্ব বাগান'],
                        'description' => ['bn' => 'বসার ঘরসহ তিন শোবার ঘরের ভিলা, পুরোটা একসঙ্গে ভাড়া দেওয়া হয়। বড় পরিবার বা বন্ধুদের দলের জন্য উপযুক্ত।']],
                    'GC' => ['is_featured' => true, 'headline' => ['en' => 'Two bedrooms around a shaded garden', 'bn' => 'ছায়াঘেরা বাগানের চারপাশে দুটি শোবার ঘর']],
                    default => [],
                };
            }

            SaveWebsiteUnits::make()->handle($site, $units);
            Promotion::query()->where('property_id', $resort)->where('name', 'Long stay')->update(['show_on_website' => true]);

            self::booking($site);
        });

        app(TenantContext::class)->run($greenValley, function () use ($valley): void {
            SaveWebsiteSite::make()->handle(WebsiteSite::query()->where('property_id', $valley)->first(), $valley, [
                'slug' => 'green-valley', 'is_published' => false, 'theme' => SiteTheme::Ocean->value, 'name' => 'Green Valley Resort',
                'tagline' => ['en' => 'Tea gardens and quiet hills'], 'intro_title' => ['en' => 'Coming soon'],
            ]);
        });
    }

    /**
     * One booking made on the website (Step 8.2b): a Deluxe King for two in 25 days, tentative, the guest chose to pay the deposit.
     */
    private static function booking(WebsiteSite $site): void
    {
        if (WebsiteBooking::query()->where('website_site_id', $site->id)->exists()) {
            return;
        }

        $engine = app(BookingEngine::class);
        $plan = RatePlan::query()->where('property_id', $site->property_id)->where('code', 'RO')->firstOrFail();
        $type = RoomType::query()->where('property_id', $site->property_id)->where('code', 'DK')->firstOrFail();
        [$checkIn, $checkOut] = [CarbonImmutable::today()->addDays(25), CarbonImmutable::today()->addDays(27)];
        $guest = app(GuestRegistry::class)->register(new GuestDetails('Farhana', 'Akter', '+8801712345678', 'farhana.akter@example.com', nationalityCode: 'BD'));

        $items = $engine->resolveItems($site->property_id, $plan->id, $checkIn, $checkOut, [['type' => 'room', 'room_type_id' => $type->id, 'adults' => 2]], channel: 'online');
        $reservation = $engine->book(new NewReservation($site->property_id, $checkIn, $checkOut, $items, $guest->id, ReservationSource::Website, specialRequests: 'A quiet room, please.'));

        WebsiteBooking::query()->create([
            'property_id' => $site->property_id, 'website_site_id' => $site->id, 'reservation_id' => $reservation->id, 'reservation_code' => $reservation->code,
            'pay_choice' => PayChoice::Deposit, 'amount_due_now' => $reservation->depositRequired, 'currency_code' => $reservation->currencyCode,
            'email' => 'farhana.akter@example.com', 'ip_address' => '203.0.113.10',
        ]);
    }

    /**
     * Adds placeholder gallery photos to a record that has none (soft two-colour gradients, 1600 × 1067 JPEG).
     *
     * @param  list<string>  $captions
     */
    private static function photos(HasMedia $record, int $count, array $captions, int $palette): void
    {
        // Seeding again adds no more photos.
        if ($record->getMedia(Attachments::PHOTOS)->isNotEmpty()) {
            return;
        }

        foreach (range(0, $count - 1) as $n) {
            [$from, $to] = self::PALETTES[($palette + $n) % count(self::PALETTES)];
            $path = tempnam(sys_get_temp_dir(), 'r365photo').'.jpg';
            self::gradient($path, $from, $to, $n);

            $record->addMedia($path)->usingName($captions[$n] ?? 'Photo')->usingFileName('photo-'.($n + 1).'.jpg')
                ->withCustomProperties(['caption' => $captions[$n] ?? 'Photo'])->toMediaCollection(Attachments::PHOTOS);
        }
    }

    /**
     * @param  array{int, int, int}  $from
     * @param  array{int, int, int}  $to
     */
    private static function gradient(string $path, array $from, array $to, int $variant): void
    {
        [$width, $height] = [1600, 1067];
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            $t = $y / $height;
            $colour = (int) imagecolorallocate($image, (int) ($from[0] + ($to[0] - $from[0]) * $t), (int) ($from[1] + ($to[1] - $from[1]) * $t), (int) ($from[2] + ($to[2] - $from[2]) * $t));
            imageline($image, 0, $y, $width, $y, $colour);
        }

        // A low sun or hill shape, so the photos are not all alike.
        $glow = (int) imagecolorallocatealpha($image, 255, 240, 200, 80);
        imagefilledellipse($image, 300 + 500 * $variant, 600, 520, 520, $glow);
        $land = (int) imagecolorallocatealpha($image, 20, 50, 30, 60);
        imagefilledellipse($image, $width / 2, $height + 250, $width + 600, 700, $land);

        imagejpeg($image, $path, 82);
        imagedestroy($image);
    }
}
