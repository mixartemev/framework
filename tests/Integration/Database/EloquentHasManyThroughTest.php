<?php

namespace Illuminate\Tests\Integration\Database\EloquentHasManyThroughTest;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Tests\Integration\Database\DatabaseTestCase;

/**
 * @group integration
 */
class EloquentHasManyThroughTest extends DatabaseTestCase
{
    public function setUp()
    {
        parent::setUp();

        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('slug')->nullable();
            $table->integer('team_id')->nullable();
            $table->string('name');
        });

        Schema::create('teams', function ($table) {
            $table->increments('id');
            $table->integer('owner_id')->nullable();
            $table->string('owner_slug')->nullable();
        });
    }

    /**
     * @test
     */
    public function basic_create_and_retrieve()
    {
        $user = User::create(['name' => str_random()]);

        $team1 = Team::create(['owner_id' => $user->id]);
        $team2 = Team::create(['owner_id' => $user->id]);

        $mate1 = User::create(['name' => str_random(), 'team_id' => $team1->id]);
        $mate2 = User::create(['name' => str_random(), 'team_id' => $team2->id]);

        User::create(['name' => str_random()]);

        $this->assertEquals([$mate1->id, $mate2->id], $user->teamMates->pluck('id')->toArray());
        $this->assertEquals([$user->id], User::has('teamMates')->pluck('id')->toArray());
    }

    public function test_global_scope_columns()
    {
        $user = User::create(['name' => str_random()]);

        $team1 = Team::create(['owner_id' => $user->id]);

        User::create(['name' => str_random(), 'team_id' => $team1->id]);

        $teamMates = $user->teamMatesWithGlobalScope;

        $this->assertEquals(['id' => 2, 'owner_id' => 1], $teamMates[0]->getAttributes());
    }

    public function test_has_self()
    {
        $user = User::create(['name' => str_random()]);

        $team = Team::create(['owner_id' => $user->id]);

        User::create(['name' => str_random(), 'team_id' => $team->id]);

        $users = User::has('teamMates')->get();

        $this->assertEquals(1, $users->count());
    }

    public function test_has_self_custom_owner_key()
    {
        $user = User::create(['slug' => str_random(), 'name' => str_random()]);

        $team = Team::create(['owner_slug' => $user->slug]);

        User::create(['name' => str_random(), 'team_id' => $team->id]);

        $users = User::has('teamMatesBySlug')->get();

        $this->assertEquals(1, $users->count());
    }
}

class User extends Model
{
    public $table = 'users';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function teamMates()
    {
        return $this->hasManyThrough(self::class, Team::class, 'owner_id', 'team_id');
    }

    public function teamMatesBySlug()
    {
        return $this->hasManyThrough(self::class, Team::class, 'owner_slug', 'team_id', 'slug');
    }

    public function teamMatesWithGlobalScope()
    {
        return $this->hasManyThrough(UserWithGlobalScope::class, Team::class, 'owner_id', 'team_id');
    }
}

class UserWithGlobalScope extends Model
{
    public $table = 'users';
    public $timestamps = false;
    protected $guarded = ['id'];

    public static function boot()
    {
        parent::boot();

        static::addGlobalScope(function ($query) {
            $query->select('users.id');
        });
    }
}

class Team extends Model
{
    public $table = 'teams';
    public $timestamps = false;
    protected $guarded = ['id'];
}
