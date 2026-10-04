<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\PrinterConnection;
use Modules\Restaurant\Enums\PrinterType;
use Modules\Restaurant\Models\Printer;

/**
 * @extends Factory<Printer>
 */
class PrinterFactory extends Factory
{
    use ResolvesProperty;

    protected $model = Printer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'name' => 'Kitchen printer',
            'type' => PrinterType::Kot,
            'connection' => PrinterConnection::Browser,
            'paper_width_mm' => 80,
            'is_active' => true,
        ];
    }
}
