<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_project(): void
    {
        $this->artisan('project:create', ['name' => 'My Product', '--origin' => ['https://app.example.com', 'http://localhost:*']])
            ->assertSuccessful();

        $project = Project::where('slug', 'my-product')->firstOrFail();
        $this->assertSame(['Баг', 'Идея'], $project->categories()->pluck('name')->all());
        $this->assertTrue($project->allowsOrigin('http://localhost:5174'));
        $this->assertFalse($project->allowsOrigin('https://evil.example.org'));

        $this->artisan('project:create', ['name' => 'Dup', '--slug' => 'my-product', '--origin' => ['https://a.example.com']])->assertFailed();
        $this->artisan('project:create', ['name' => 'No origin'])->assertFailed();
    }

    public function test_creates_admin_with_generated_password_and_resets_it(): void
    {
        Project::factory()->create(['slug' => 'demo']);

        $this->artisan('admin:create', ['email' => 'Anna@Example.com', '--project' => ['demo']])->assertSuccessful();
        $admin = User::where('email', 'anna@example.com')->firstOrFail();
        $this->assertTrue($admin->managesProject(Project::firstOrFail()));
        $oldHash = $admin->password;

        $this->artisan('admin:create', ['email' => 'anna@example.com', '--project' => ['demo']])->assertFailed();
        $this->artisan('admin:create', ['email' => 'bob@example.com', '--project' => ['missing']])->assertFailed();

        $this->artisan('admin:password', ['email' => 'anna@example.com'])->assertSuccessful();
        $this->assertNotSame($oldHash, $admin->refresh()->password);
        $this->assertFalse(Hash::check('', $admin->password));
    }
}
