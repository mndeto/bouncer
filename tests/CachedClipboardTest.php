<?php

use Silber\Bouncer\CachedClipboard;

use Mockery as m;
use Illuminate\Cache\ArrayStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class CachedClipboardTest extends BaseTestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function test_it_caches_abilities()
    {
        $cache = new ArrayStore;

        $bouncer = $this->bouncer($user = User::create())->cache($cache);

        $bouncer->allow($user)->to('ban-users');

        $this->assertEquals(['ban-users'], $this->getAbliities($cache, $user));

        $bouncer->allow($user)->to('create-users');

        $this->assertEquals(['ban-users'], $this->getAbliities($cache, $user));
    }

    public function test_it_caches_empty_abilities()
    {
        $user = User::create();

        $clipboard = m::mock(CachedClipboard::class.'[getFreshAbilities]', [new ArrayStore]);
        $clipboard->shouldReceive('getFreshAbilities')->once()->andReturn(new Collection);

        $this->assertInstanceOf(Collection::class, $clipboard->getAbilities($user));
        $this->assertInstanceOf(Collection::class, $clipboard->getAbilities($user));
    }

    public function test_it_caches_roles()
    {
        $bouncer = $this->bouncer($user = User::create())->cache(new ArrayStore);

        $bouncer->assign('editor')->to($user);

        $this->assertTrue($bouncer->is($user)->an('editor'));

        $bouncer->assign('moderator')->to($user);

        $this->assertFalse($bouncer->is($user)->a('moderator'));
    }

    public function test_it_can_refresh_the_cache()
    {
        $cache = new ArrayStore;

        $bouncer = $this->bouncer($user = User::create())->cache($cache);

        $bouncer->allow($user)->to('create-posts');
        $bouncer->assign('editor')->to($user);
        $bouncer->allow('editor')->to('delete-posts');

        $this->assertEquals(['create-posts', 'delete-posts'], $this->getAbliities($cache, $user));

        $bouncer->disallow('editor')->to('delete-posts');
        $bouncer->allow('editor')->to('edit-posts');

        $this->assertEquals(['create-posts', 'delete-posts'], $this->getAbliities($cache, $user));

        $bouncer->refresh();

        $this->assertEquals(['create-posts', 'edit-posts'], $this->getAbliities($cache, $user));
    }

    public function test_it_can_refresh_the_cache_only_for_one_user()
    {
        $user1 = User::create();
        $user2 = User::create();

        $cache = new ArrayStore;

        $bouncer = $this->bouncer($user = User::create())->cache($cache);

        $bouncer->allow('admin')->to('ban-users');
        $bouncer->assign('admin')->to($user1);
        $bouncer->assign('admin')->to($user2);

        $this->assertEquals(['ban-users'], $this->getAbliities($cache, $user1));
        $this->assertEquals(['ban-users'], $this->getAbliities($cache, $user2));

        $bouncer->disallow('admin')->to('ban-users');
        $bouncer->refreshFor($user1);

        $this->assertEquals([], $this->getAbliities($cache, $user1));
        $this->assertEquals(['ban-users'], $this->getAbliities($cache, $user2));
    }

    /**
     * Get the user's abilities from the given cache instance through the clipboard.
     *
     * @param  \Illuminate\Cache\ArrayStore  $cache
     * @param  \Illuminate\Database\Eloquent\Model  $user
     * @return array
     */
    protected function getAbliities(ArrayStore $cache, Model $user)
    {
        $clipboard = new CachedClipboard($cache);

        $abilities = $clipboard->getAbilities($user)->pluck('name');

        return $abilities->sort()->values()->all();
    }

    /**
     * Get the user's roles from the given cache instance through the clipboard.
     *
     * @param  \Illuminate\Cache\ArrayStore  $cache
     * @param  \Illuminate\Database\Eloquent\Model  $user
     * @return array
     */
    protected function getRoles(ArrayStore $cache, Model $user)
    {
        $clipboard = new CachedClipboard($cache);

        return $clipboard->getRoles($user)->all();
    }
}
