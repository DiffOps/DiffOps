<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => $this->faker->word().'.'.$this->faker->word(),
            'entity_type' => $this->faker->randomElement([
                'pull_request',
                'risk_assessment',
                'repository',
                'user',
            ]),
            'entity_id' => (string) Str::uuid(),
            'payload' => [
                'sample' => $this->faker->sentence(),
            ],
        ];
    }

    /**
     * Mark the log as system-initiated (no user).
     */
    public function system(): static
    {
        return $this->state(fn () => ['user_id' => null]);
    }
}
