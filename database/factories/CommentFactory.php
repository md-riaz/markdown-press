<?php
namespace Database\Factories;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
class CommentFactory extends Factory {
    public function definition(): array {
        $email = $this->faker->email();
        return [
            'post_id'      => Post::factory(),
            'guest_name'   => $this->faker->name(),
            'guest_email'  => $email,
            'body'         => $this->faker->paragraph(),
            'status'       => 'approved',
            'gravatar_hash'=> md5(strtolower(trim($email))),
        ];
    }
}
