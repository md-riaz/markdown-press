<?php
namespace Database\Factories;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PostFactory extends Factory {
    public function definition(): array {
        $title = $this->faker->sentence(rand(5,10));
        $md = "# {$title}\n\n" . implode("\n\n", $this->faker->paragraphs(rand(4,8)));
        return [
            'user_id'          => User::factory(),
            'title'            => $title,
            'slug'             => Str::slug($title).'-'.uniqid(),
            'content_markdown' => $md,
            'meta_description' => $this->faker->sentence(rand(15,25)),
            'status'           => 'published',
            'published_at'     => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }
    public function published(): static { return $this->state(['status'=>'published','published_at'=>now()]); }
    public function draft(): static { return $this->state(['status'=>'draft','published_at'=>null]); }
}
