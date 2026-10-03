<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'personnel_code' => 'P-'.strtoupper(Str::random(6)),
            'full_name' => $this->faker->name('male'),
            'national_id' => $this->faker->unique()->numerify('##########'),
            'department' => $this->faker->randomElement([
                'اپراتوری', 'تعمیرات مکانیک', 'تعمیرات برق', 'کنترل کیفیت', 'HSE', 'مدیریت', 'لجستیک',
            ]),
            'contract_type' => $this->faker->randomElement(['employee', 'employee', 'employee', 'contractor', 'visitor']),
            'avatar_url' => null,
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
