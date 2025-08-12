<?php

use Orchestra\Testbench\TestCase;
use StudentAffairsUwm\Shibboleth\Tests\Stubs\Setup;
use StudentAffairsUwm\Shibboleth\Entitlement;
use App\User;

class EntitlementTest extends TestCase
{
    use Setup;

    
    /**
     * @expectedException InvalidArgumentException
     */
    public function testCanInvalidateFindInStringParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        Entitlement::findInString(1);
    }

    public function testCanFindEntitlementsInString()
    {
        $sudoer = new Entitlement;
        $sudoer->name = 'urn:mace:uark.edu:ADGroups:walton:Groups:linux02_sudoers';
        $sudoer->save();

        $bomgar = new Entitlement;
        $bomgar->name = 'urn:mace:uark.edu:ADGroups:Computing Services:Bomgar Groups:Bomgar-WCOB';
        $bomgar->save();

        $expected = collect([$sudoer, $bomgar])->toArray();

        $absent = new Entitlement;
        $absent->name = 'urn:mace:uark.edu:ADGroups:Something Somesuch:Entitlement-User-Does-Not-Have';
        $absent->save();

        $entitlementString = 'urn:mace:dir:entitlement:common-lib-terms;urn:mace:uark.edu:ADGroups:Computing Services:Bomgar Groups:Bomgar-WCOB;urn:mace:uark.edu:ADGroups:Walton College:Security Groups:Old Security Groups:WCOB-TechCenter;urn:mace:uark.edu:ADGroups:Exchange Resource Units:UITS (University IT Services):UITS: TechPartners;urn:mace:uark.edu:ADGroups:Walton College:Security Groups:WCOB-Intranet;urn:mace:uark.edu:ADGroups:walton:Groups:linux02_sudoers;urn:mace:uark.edu:ADGroups:Walton College:Security Groups:WCOB-Users;urn:mace:uark.edu:ADGroups:Exchange Resource Units:WCOB (Walton College):WCOB: Conference Team';

        $entitlements = Entitlement::findInString($entitlementString)->toArray();

        // sqlite returns different order
        sort($entitlements);

        $this->assertEquals($expected, $entitlements);
    }

    public function test_checks_for_current_entitlement()
    {
        // $_SERVER fixture set in
        // StudentAffairsUwm\Shibboleth\Tests\Stubs\Setup::setUp();

        $entitlement = 'urn:mace:uark.edu:ADGroups:Computing Services:Something:Somesuch-WCOB';

        $this->assertTrue(Entitlement::has($entitlement));
    }

    public function test_checks_for_missing_entitlement()
    {
        // $_SERVER fixture set in
        // StudentAffairsUwm\Shibboleth\Tests\Stubs\Setup::setUp();

        $entitlement = 'urn:mace:uark.edu:ADGroups:Computing Services:Nothing:Nonesuch-WCOB';

        $this->assertFalse(Entitlement::has($entitlement));
    }

    public function test_checks_for_complete_entitlement()
    {
        // sub-string is missing trailing 'B'
        $entitlement = 'urn:mace:uark.edu:ADGroups:Computing Services:Something:Somesuch-WCO';

        $this->assertFalse(Entitlement::has($entitlement));
    }
}
