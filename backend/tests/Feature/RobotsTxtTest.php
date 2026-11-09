<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotsTxtTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_lets_crawlers_in_by_default(): void
    {
        // no boards opted out → base rules only
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame("User-agent: *\nAllow: /\n", $response->getContent());
    }

    public function test_robots_txt_disallows_open_boards_the_owner_hid_from_search(): void
    {
        Project::factory()->create(['slug' => 'alpha', 'visibility' => 'public', 'search_indexable' => false]);
        Project::factory()->create(['slug' => 'beta', 'visibility' => 'public', 'search_indexable' => false]);
        Project::factory()->create(['slug' => 'gamma', 'visibility' => 'public', 'search_indexable' => true]);

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Disallow: /alpha', $body);
        $this->assertStringContainsString('Disallow: /beta', $body);
        // the indexable board stays out of robots.txt
        $this->assertStringNotContainsString('Disallow: /gamma', $body);
    }

    public function test_robots_txt_does_not_expose_closed_board_slugs(): void
    {
        // closed boards are 403 to guests anyway; listing their slugs in robots.txt would leak them
        Project::factory()->create(['slug' => 'secret-project', 'visibility' => 'private', 'search_indexable' => false]);
        Project::factory()->create(['slug' => 'another-secret', 'visibility' => 'private', 'search_indexable' => true]);

        $body = $this->get('/robots.txt')->getContent();

        $this->assertStringNotContainsString('secret-project', $body);
        $this->assertStringNotContainsString('another-secret', $body);
    }

    public function test_robots_txt_is_stable_regardless_of_board_insert_order(): void
    {
        // insert in non-alphabetical order; robots.txt sorts, so crawlers see a deterministic list
        Project::factory()->create(['slug' => 'zulu', 'visibility' => 'public', 'search_indexable' => false]);
        Project::factory()->create(['slug' => 'alpha', 'visibility' => 'public', 'search_indexable' => false]);

        $body = $this->get('/robots.txt')->getContent();

        $this->assertSame(
            "User-agent: *\nAllow: /\nDisallow: /alpha\nDisallow: /zulu\n",
            $body,
        );
    }
}
