<?php

use Orchestra\Testbench\TestCase;
use StudentAffairsUwm\Shibboleth\Tests\Stubs\Setup;
use App\User;
use StudentAffairsUwm\Shibboleth\Controllers\ShibbolethController;

class ShibbolethControllerTest extends TestCase
{
    use Setup;

    public function test_creates_user()
    {
        $getJeff = function(){
            return User::where('email', 'jeff@example.org')->first();
        };

        $this->assertEmpty($getJeff());

        User::create([
            'name' => 'jeff',
            'email' => 'jeff@example.org',
            'password' => 'password',
        ]);

        $this->assertInstanceOf(User::class, $getJeff());

        (new ShibbolethController)->idpAuthenticate();

        $this->assertSame('100000001', $getJeff()->student_id);
    }

    public function test_handles_backwards_compatability_config()
    {
        $backup = config('shibboleth.user');
        config(['shibboleth.user' => null]);

        config([
            'shibboleth.idp_login_email'       => 'mail',
            'shibboleth.idp_login_name'        => 'displayName',
            'shibboleth.idp_login_first_name'  => 'givenName',
            'shibboleth.idp_login_last_name'   => 'sn',
            'shibboleth.idp_login_entitlement' => 'entitlement',
        ]);

        $getJeff = function(){
            return User::where('email', 'jeff@example.org')->first();
        };

        $this->assertEmpty($getJeff());

        (new ShibbolethController)->idpAuthenticate();

        $this->assertSame('Puckett', $getJeff()->last_name);

        config(['shibboleth.user' => $backup]);
    }
}
