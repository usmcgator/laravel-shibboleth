<?php

namespace StudentAffairsUwm\Shibboleth\Controllers;

use Illuminate\Auth\GenericUser;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Illuminate\Console\AppNamespaceDetectorTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use StudentAffairsUwm\Shibboleth\ConfigurationBackwardsCompatabilityMapper;

use OneLogin\Saml2\Auth as OneLogin_Saml2_Auth;
use OneLogin\Saml2\Error as OneLogin_Saml2_Error;
use OneLogin\Saml2\Utils;
use Illuminate\Support\Str;

class ShibbolethController extends Controller
{
    /**
     * Service Provider
     * @var Shibalike\SP
     */
    private $sp;

    /**
     * Identity Provider
     * @var Shibalike\IdP
     */
    private $idp;

    /**
     * Configuration
     * @var Shibalike\Config
     */
    private $config;

    /**
     * Constructor
     */
    public function __construct(?GenericUser $user = null)
    {

        if (config('shibboleth.emulate_idp') === true) {

            $this->config         = new \Shibalike\Config();
            $this->config->idpUrl = '/emulated/idp';

            $stateManager = $this->getStateManager();

            $this->sp = new \Shibalike\SP($stateManager, $this->config);
            $this->sp->initLazySession();

            $this->idp = new \Shibalike\IdP($stateManager, $this->getAttrStore(), $this->config);
        }

        $this->user = $user;
    }

    /**
     * Create the session, send the user away to the IDP
     * for authentication.
     */
    public function login()
    {
        if (config('shibboleth.emulate_idp') === true) {
            return redirect()->action([self::class, 'emulateLogin'], [
                'target' => action([self::class, 'idpAuthenticate'])
            ]);
        }
        return Redirect::to(
            URL::to('/') . $this->getLoginURL() . '?target=' . action([self::class, 'idpAuthenticate'])
        );
    }

    /**
     * Setup authentication based on returned server variables
     * from the IdP.
     */
    public function idpAuthenticate(Request $request)
    {
        if (empty(config('shibboleth.user'))) {
            ConfigurationBackwardsCompatabilityMapper::map();
        }

        foreach (config('shibboleth.user') as $local => $server) {
            $map[$local] = $this->getServerVariable($request, $server);
        }
        // dd('local: ' . $local . ' Server: ' .$server);
        if (empty($map[config('shibboleth.authfield')])) {
            return abort(403, 'Unauthorized');
        }

        $userClass = config('auth.providers.users.model', 'App\User');

        $authField = config('shibboleth.authfield');
        $identifier = $map[$authField] ?? null;

        if (empty($identifier)) {
            return abort(403, 'Unauthorized because of authfield!');
        }

        $userClass = config('auth.providers.users.model', \App\Models\User::class);

        $user = $userClass::where($authField, $identifier)->first();

        if (!$user) {
            if (!config('shibboleth.add_new_users', true)) {
                return abort(403, 'Unauthorized');
            }
            $user = new $userClass();
            if (empty($user->password)) {
                $user->password = bcrypt(Str::random(40));
            }
            $user->{$authField} = $identifier;
        }

        if (config('shibboleth.emulate_idp') !== true) {
            $user->fill($map);
            $user->save();
        }

        // Trust the IdP: no password involved
        \Auth::login($user, true);

        Session::regenerate();

        $route = config('shibboleth.authenticated');

        return redirect()->intended($route);
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        //  Session::flush();
        // Invalidate session
        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (config('shibboleth.emulate_idp') == true) {
            return Redirect::to(action('\\' . __CLASS__ . '@emulateLogout'));
        }

        return Redirect::to(url('/') . $this->getLogoutURL());
    }

    /**
     * Emulate a login via Shibalike
     */

    public function emulateLogin(Request $request)
    {
        $from = $request->input('target') ?? $this->getServerVariable($request, 'HTTP_REFERER');

        $this->sp->makeAuthRequest($from);
        $this->sp->redirect();
    }
    /**
     * Emulate a logout via Shibalike
     */
    public function emulateLogout(Request $request)
    {
        $this->sp->logout();
        auth()->logout();

        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/emulated/login');
    }

    /**
     * Emulate the 'authentication' via Shibalike
     */
    public function emulateIdp(Request $request)
    {
        $data = [];

        if ($request->input('username') !== null) {
            $username = ($request->input('username') === $request->input('password')) ?
                $request->input('username') : '';

            $userAttrs = $this->idp->fetchAttrs($username);

            if ($userAttrs) {
                $this->idp->markAsAuthenticated($username);
                $this->idp->redirect("/shibboleth-authenticate");
            }

            $data['error'] = 'Incorrect username and/or password';
        }

        return $this->viewOrRedirect(config('shibboleth.emulate_idp_login_view'));
    }

    /**
     * Function to get an attribute store for Shibalike
     */
    private function getAttrStore()
    {
        return new \Shibalike\Attr\Store\ArrayStore(config('shibboleth.emulate_idp_users'));
    }

    /**
     * Gets a state manager for Shibalike
     */
    private function getStateManager()
    {
        $session = \UserlandSession\SessionBuilder::instance()
            ->setSavePath(sys_get_temp_dir())
            ->setName('SHIBALIKE_BASIC')
            ->build();

        return new \Shibalike\StateManager\UserlandSession($session);
    }

    /**
     * Wrapper function for getting server variables.
     * Since Shibalike injects $_SERVER variables Laravel
     * doesn't pick them up. So depending on if we are
     * using the emulated IdP or a real one, we use the
     * appropriate function.
     */

    private function getServerVariable(Request $request, string $variableName): ?string
    {
        // Emulated IdP via Shibalike
        if (config('shibboleth.emulate_idp') === true) {
            return $_SERVER[$variableName] ?? null;
        }

        // Local Shibboleth SP with serialized session attributes
        if (config('shibboleth.sp_type') === 'local_shib') {
            $serialized = $request->session()->get('shibAttributes');

            if ($serialized) {
                $attributes = @unserialize($serialized);

                if (is_array($attributes) && isset($attributes[$variableName])) {
                    return is_array($attributes[$variableName])
                        ? ($attributes[$variableName][0] ?? null)
                        : $attributes[$variableName];
                }
            }

            return null;
        }

        // Default: use Laravel's server() method
        $value = $request->server($variableName);

        return !empty($value)
            ? $value
            : $request->server('REDIRECT_' . $variableName);
    }

    // These are helpers to provide backwards compatibility with the apache only version of this library
    private function getLoginURL()
    {
        if (config('shibboleth.sp_type')) {
            return config('shibboleth.' . config('shibboleth.sp_type') . '.idp_login');
        } else {
            return config('shibboleth.idp_login');
        }
    }

    private function getLogoutURL()
    {
        if (config('shibboleth.sp_type')) {
            return config('shibboleth.' . config('shibboleth.sp_type') . '.idp_logout');
        } else {
            return config('shibboleth.idp_logout');
        }
    }

    private function getLocalSettings()
    {
        $localSettings = config('shibboleth.local_settings');
        // check if the assertionConsumerService is a fqdn and if not, set it based on the current request host
        if (isset($localSettings['sp']['assertionConsumerService']['url'])) {
            $acsUrl = $localSettings['sp']['assertionConsumerService']['url'];
            if (!preg_match('/^https?:\/\//', $acsUrl)) {
                $localSettings['sp']['assertionConsumerService']['url'] = url($acsUrl);
            }
            return $localSettings;
        } else {
            return abort(500, 'Assertion Consumer Service URL is not configured.');
        }
    }

    /*
     * Simple function that allows configuration variables
     * to be either names of views, or redirect routes.
     */
    private function viewOrRedirect($view)
    {
        return (View::exists($view)) ? view($view) : Redirect::to($view);
    }

    public function localSPLogin()
    {
        $localSettings = $this->getLocalSettings();
        $auth = new OneLogin_Saml2_Auth($localSettings);
        $auth->login(null, array(), false, false, false, false);
    }

    public function localSPLogout()
    {
        $localSettings = $this->getLocalSettings();
        $auth = new OneLogin_Saml2_Auth($localSettings);
        $auth->logout();
    }

    public function localSPACS()
    {
        $localSettings = $this->getLocalSettings();
        $auth = new OneLogin_Saml2_Auth($localSettings);
        Utils::setProxyVars(true);
        $auth->processResponse();

        $errors = $auth->getErrors();

        if (!empty($errors)) {
            return array('error' => $errors, 'last_error_reason' => $auth->getLastErrorReason());
        }

        if (!$auth->isAuthenticated()) {
            return array('error' => 'Could not authenticate', 'last_error_reason' => $auth->getLastErrorReason());
        }

        Request::session()->flash("shibAttributes", serialize(array_merge(["nameId" => $auth->getNameId()], $auth->getAttributes())));

        return Redirect::action('\\' . __CLASS__ . '@idpAuthenticate');
    }

    public function localSPMetadata()
    {
        $localSettings = $this->getLocalSettings();
        $auth = new OneLogin_Saml2_Auth($localSettings);
        $settings = $auth->getSettings();
        $metadata = $settings->getSPMetadata();
        $errors = $settings->validateMetadata($metadata);

        if (empty($errors)) {
            return response($metadata, 200, ['Content-Type' => 'text/xml']);
        } else {

            throw new InvalidArgumentException(
                'Invalid SP metadata: ' . implode(', ', $errors),
                OneLogin_Saml2_Error::METADATA_SP_INVALID
            );
        }
    }
}
