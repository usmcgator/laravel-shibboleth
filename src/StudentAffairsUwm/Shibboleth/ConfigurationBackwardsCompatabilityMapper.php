<?php

namespace StudentAffairsUwm\Shibboleth;

class ConfigurationBackwardsCompatabilityMapper
{
    public static function map()
    {
        foreach ([
            'entitlement'     => 'idp_login_entitlement',
            'user.email'      => 'idp_login_email',
            'user.name'       => 'idp_login_name',
            'user.first_name' => 'idp_login_first_name',
            'user.last_name'  => 'idp_login_last_name',
        ] as $new => $old) {
            config(["shibboleth.$new" => config("shibboleth.$old")]);
        }
    }
}
