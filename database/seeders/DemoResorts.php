<?php

namespace Database\Seeders;

/**
 * Demo resort layouts for DemoSeeder (Step 1.1): amenities, departments, and each property's
 * room types, cottage types and cottages. A cottage's `rooms` lists the room type code of each room.
 */
final class DemoResorts
{
    /**
     * @var list<array{string, string, string}> name, icon, category
     */
    public const array AMENITIES = [
        ['Air conditioning', 'bi-snow', 'in_room'],
        ['Wi-Fi', 'bi-wifi', 'in_room'],
        ['Smart TV', 'bi-tv', 'in_room'],
        ['Mini-bar', 'bi-cup-straw', 'in_room'],
        ['Tea & coffee', 'bi-cup-hot', 'in_room'],
        ['In-room safe', 'bi-safe', 'in_room'],
        ['Hot water', 'bi-droplet', 'bathroom'],
        ['Bathtub', 'bi-water', 'bathroom'],
        ['Toiletries', 'bi-basket', 'bathroom'],
        ['Balcony', 'bi-door-open', 'outdoor'],
        ['Sea view', 'bi-water', 'outdoor'],
        ['Private garden', 'bi-flower1', 'outdoor'],
        ['BBQ area', 'bi-fire', 'outdoor'],
        ['Room service', 'bi-bell', 'service'],
        ['Daily housekeeping', 'bi-stars', 'service'],
    ];

    /**
     * @var list<array{string, string, string}> code, name, description
     */
    public const array DEPARTMENTS = [
        ['FO', 'Front Office', 'Reception, reservations, concierge and guest relations'],
        ['HK', 'Housekeeping', 'Rooms, public areas and laundry'],
        ['FBS', 'F&B Service', 'Restaurants, bars and room service'],
        ['FBP', 'F&B Production', 'Kitchens and bakery'],
        ['ENG', 'Maintenance & Engineering', 'Buildings, utilities and grounds'],
        ['ADM', 'Administration & General', 'Management and general administration'],
        ['SM', 'Sales & Marketing', 'Sales, marketing and corporate accounts'],
        ['ACC', 'Accounts', 'Finance, purchasing payments and audit'],
        ['HR', 'Human Resources', 'Recruitment, training and payroll administration'],
        ['SEC', 'Security', 'Security and safety'],
        ['STR', 'Stores', 'Main store and receiving'],
    ];

    /**
     * @return array{roomTypes: array<string, array<string, mixed>>, cottageTypes: array<string, array<string, mixed>>, cottages: list<array<string, mixed>>}
     */
    public static function rodela(): array
    {
        return [
            'roomTypes' => [
                'HS' => self::roomType('Honeymoon Suite', '1 king', 2, 2, 0, 2, 40, 1, ['Air conditioning', 'Wi-Fi', 'Smart TV', 'Mini-bar', 'Bathtub', 'Toiletries', 'Sea view', 'Balcony']),
                'DK' => self::roomType('Deluxe King', '1 king', 2, 2, 1, 3, 32, 2, ['Air conditioning', 'Wi-Fi', 'Smart TV', 'Tea & coffee', 'Hot water', 'Toiletries', 'Balcony']),
                'TW' => self::roomType('Twin', '2 single', 2, 2, 1, 3, 28, 3, ['Air conditioning', 'Wi-Fi', 'Smart TV', 'Hot water', 'Toiletries']),
                'FS' => self::roomType('Family Suite', '1 king + 2 single', 2, 4, 2, 5, 48, 4, ['Air conditioning', 'Wi-Fi', 'Smart TV', 'Mini-bar', 'In-room safe', 'Hot water', 'Toiletries']),
            ],
            'cottageTypes' => [
                'HC' => self::cottageType('Honeymoon Cottage', 1, 2, 'A private beachfront cottage for two.', 1, ['Sea view', 'Room service', 'Daily housekeeping']),
                'GC' => self::cottageType('Garden Cottage', 2, 6, 'Two bedrooms around a shaded garden, rented by the room or whole.', 2, ['Private garden', 'Daily housekeeping']),
                'FV' => self::cottageType('Family Villa', 3, 11, 'A three-bedroom villa with a lounge, booked whole.', 3, ['Private garden', 'BBQ area', 'Room service', 'Daily housekeeping']),
            ],
            'cottages' => [
                self::cottage('C01', 'Coral', 'HC', 'Beachfront', 'both', '101', ['HS']),
                self::cottage('C02', 'Pearl', 'HC', 'Beachfront', 'both', '201', ['HS']),
                self::cottage('C03', 'Seashell', 'HC', 'Beachfront', 'both', '301', ['HS']),
                self::cottage('C04', 'Palm', 'GC', 'Garden', 'both', '401', ['DK', 'TW']),
                self::cottage('C05', 'Casuarina', 'GC', 'Garden', 'both', '501', ['DK', 'TW']),
                self::cottage('C06', 'Hibiscus', 'GC', 'Garden', 'rooms_only', '601', ['DK', 'DK']),
                self::cottage('C07', 'Lagoon Villa', 'FV', 'Lagoon', 'whole_only', '701', ['FS', 'DK', 'TW']),
                self::cottage('C08', 'Sunset Villa', 'FV', 'Lagoon', 'whole_only', '801', ['FS', 'DK', 'TW']),
            ],
        ];
    }

    /**
     * @return array{roomTypes: array<string, array<string, mixed>>, cottageTypes: array<string, array<string, mixed>>, cottages: list<array<string, mixed>>}
     */
    public static function greenValley(): array
    {
        return [
            'roomTypes' => [
                'DK' => self::roomType('Deluxe King', '1 king', 2, 2, 1, 3, 30, 1, ['Air conditioning', 'Wi-Fi', 'Hot water', 'Toiletries']),
                'TW' => self::roomType('Twin', '2 single', 2, 2, 1, 3, 26, 2, ['Air conditioning', 'Wi-Fi', 'Hot water']),
            ],
            'cottageTypes' => [
                'LC' => self::cottageType('Lake Cottage', 1, 3, 'A single-room cottage by the lake.', 1, ['Balcony', 'Daily housekeeping']),
                'FL' => self::cottageType('Forest Lodge', 2, 6, 'A two-room lodge in the forest.', 2, ['BBQ area', 'Daily housekeeping']),
                'FAM' => self::cottageType('Family Lodge', 4, 12, 'A four-bedroom lodge for large families, booked whole.', 3, ['Private garden', 'BBQ area', 'Daily housekeeping']),
            ],
            'cottages' => [
                self::cottage('L01', 'Kingfisher', 'LC', 'Lakeside', 'both', '101', ['DK']),
                self::cottage('L02', 'Heron', 'LC', 'Lakeside', 'both', '102', ['DK']),
                self::cottage('L03', 'Egret', 'LC', 'Lakeside', 'both', '103', ['TW']),
                self::cottage('F01', 'Bamboo Lodge', 'FL', 'Forest', 'both', '201', ['DK', 'TW']),
                self::cottage('F02', 'Family Lodge', 'FAM', 'Forest', 'whole_only', '301', ['DK', 'DK', 'TW', 'TW']),
            ],
        ];
    }

    /**
     * @param  list<string>  $amenities
     * @return array<string, mixed>
     */
    private static function roomType(string $name, string $beds, int $base, int $adults, int $children, int $max, int $size, int $order, array $amenities): array
    {
        return [
            'name' => $name, 'bed_configuration' => $beds, 'base_occupancy' => $base, 'max_adults' => $adults,
            'max_children' => $children, 'max_occupancy' => $max, 'size_sqm' => (string) $size, 'sort_order' => $order,
            'description' => null, 'amenity_ids' => $amenities,
        ];
    }

    /**
     * @param  list<string>  $amenities
     * @return array<string, mixed>
     */
    private static function cottageType(string $name, int $bedrooms, int $max, string $description, int $order, array $amenities): array
    {
        return ['name' => $name, 'bedrooms' => $bedrooms, 'max_occupancy' => $max, 'description' => $description, 'sort_order' => $order, 'amenity_ids' => $amenities];
    }

    /**
     * @param  list<string>  $rooms  room type code of each room
     * @return array<string, mixed>
     */
    private static function cottage(string $code, string $name, string $type, string $zone, string $mode, string $firstRoom, array $rooms): array
    {
        return ['code' => $code, 'name' => $name, 'type' => $type, 'zone' => $zone, 'booking_mode' => $mode, 'first_room' => $firstRoom, 'rooms' => $rooms];
    }
}
