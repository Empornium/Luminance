<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;
use Luminance\Entities\Client;
use Luminance\Entities\ClientUserAgent;
use Luminance\Entities\ClientAccept;
use Luminance\Entities\ClientScreen;
use Luminance\Entities\User;
use Luminance\Errors\UserError;

class Secretary extends Service {

    public $ipChanged = null;
    public $info;
    public $userAgentString;

    protected static $useServices = [
        'crypto'   => 'Crypto',
        'settings' => 'Settings',
        'flasher'  => 'Flasher',
        'db'       => 'DB',
        'auth'     => 'Auth',
        'repos'    => 'Repos',
    ];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->server = $this->master->server;
        if (!$this->master->request->cli) {
            if (array_key_exists('HTTP_USER_AGENT', $this->server)) {
                $this->info = $this->parseUserAgent($this->server['HTTP_USER_AGENT']);
                $this->userAgentString = $this->server['HTTP_USER_AGENT'];
            } else {
                $this->info = ['platform'=>null, 'browser'=>null, 'version'=>null];
                $this->userAgentString = '';
            }
        }
    }

    public function getExternalToken($ident, $action = '') {
        $token = $this->crypto->generateAuthToken('token', $ident, $action);
        return $token;
    }

    public function checkExternalToken($token, $ident, $action = '', $duration = 86400) {
        if ($this->crypto->checkAuthToken('token', $token, $ident, $action, $duration)) {
            return true;
        }
        throw new UserError('Authorization token expired or invalid');
    }

    public function getToken($action = '') {
        if (is_null($this->CID)) {
            throw new UserError('CID cookie not found or invalid');
        }
        $token = $this->crypto->generateAuthToken('token', $this->CID, $action);
        return $token;
    }

    public function checkToken($token, $action = '', $duration = 86400, $redirect = null) {
        if (is_null($this->CID)) {
            throw new UserError('CID cookie not found or invalid', null, $redirect);
        }
        if ($this->crypto->checkAuthToken('token', $token, $this->CID, $action, $duration)) {
            return true;
        }
        throw new UserError('Authorization token expired or invalid', null, $redirect);
    }

    public function checkClient() {
        $this->repos->ips->checkBanned($this->request->ip);
        $client = null;
        if (array_key_exists('cid', $this->request->cookie)) {
            $CID = $this->crypto->decrypt($this->request->cookie['cid'], 'cookie');
            if (!empty($CID)) {
                $client = $this->repos->clients->getByCID($CID);
            }
        }
        if ($client instanceof Client) {
            $this->timecheckClient($client);
        } else {
            $client = $this->createClient();
        }

        # Ensure global CID is set and cookie is set/updated
        $this->CID = $client->CID;
        $this->request->setCookie('cid', $this->crypto->encrypt($client->CID, 'cookie'), time()+(60*60*24)*28, false);
        $this->request->client = $client;
    }

    protected function createClient() {
        $CID = $this->crypto->randomBytes(8);
        $ip = $this->request->ip;
        $client = $this->repos->clients->new($CID, $ip);
        $this->updateClient($client);
        $this->repos->clients->save($client);
        return $client;
    }

    public function timecheckClient(Client $client) {
        $compDate = new \DateTime('2 minutes ago');

        if (!($this->request->ip->ID === $client->IPID)) {
            $client->IPID = $this->request->ip->ID;
            $this->ipChanged = true;
        } else {
            $this->ipChanged = false;
        }

        if ($client->Updated <= $compDate || $this->ipChanged) {
            $this->updateClient($client);
            $this->repos->clients->save($client);
        }
        return $client;
    }

    public function parseUserAgent($userAgentString) {
        # This just wraps the function from the provided library
        $info = ['platform'=>null, 'browser'=>null, 'version'=>null];
        $info = array_merge($info, \donatj\UserAgent\parse_user_agent($userAgentString));
        return $info;
    }

    public function httpUserAgent() {
        $userAgentString = (array_key_exists('HTTP_USER_AGENT', $this->server)) ? $this->server['HTTP_USER_AGENT'] : '';
        $userAgent = $this->repos->clientUseragents->getByString($userAgentString);
        if (!($userAgent instanceof ClientUserAgent)) {
            $userAgent = new ClientUserAgent();
            $userAgent->String = $userAgentString;
            $userAgent->Platform = $this->info['platform'];
            $userAgent->Browser = $this->info['browser'];
            $userAgent->Version = $this->info['version'];
            $this->repos->clientUseragents->save($userAgent);
        }
        return $userAgent;
    }

    public function getAcceptHeaders() {
        $headers = [];
        $headers['string'] = (array_key_exists('HTTP_ACCEPT', $this->server)) ? $this->server['HTTP_ACCEPT'] : null;
        $headers['charset'] = (array_key_exists('HTTP_ACCEPT_CHARSET', $this->server)) ? $this->server['HTTP_ACCEPT_CHARSET'] : null;
        $headers['encoding'] = (array_key_exists('HTTP_ACCEPT_ENCODING', $this->server)) ? $this->server['HTTP_ACCEPT_ENCODING'] : null;
        $headers['language'] = (array_key_exists('HTTP_ACCEPT_LANGUAGE', $this->server)) ? $this->server['HTTP_ACCEPT_LANGUAGE'] : null;
        return $headers;
    }

    public function httpAccept() {
        $headers = $this->getAcceptHeaders();
        $accept = $this->repos->clientAccepts->getByValues($headers['string'], $headers['charset'], $headers['encoding'], $headers['language']);
        if (!($accept instanceof ClientAccept)) {
            $accept = new ClientAccept();
            $accept->Accept = $headers['string'];
            $accept->AcceptCharset = $headers['charset'];
            $accept->AcceptEncoding = $headers['encoding'];
            $accept->AcceptLanguage = $headers['language'];
            $this->repos->clientAccepts->save($accept);
        }
        return $accept;
    }

    public function updateClientInfo($options = []) {
        if (!empty($options)) {
            $screen = $this->repos->clientScreens->getByValues($options['width'], $options['height'], $options['colordepth']);
            if (!($screen instanceof ClientScreen)) {
                $screen = new ClientScreen();
                $screen->Width = $options['width'];
                $screen->Height = $options['height'];
                $screen->ColorDepth = $options['colordepth'];
                $this->repos->clientScreens->save($screen);
            }
            $this->request->client->ClientScreenID = $screen->ID;
            $this->request->client->TimezoneOffset = $options['timezoneoffset'];
        }
        if (!empty($this->request->tls_ver)) {
            $this->request->client->TLSVersion = $this->request->tls_ver;
        }
        if (!empty($this->request->http_ver)) {
            $this->request->client->HTTPVersion = $this->request->http_ver;
        }

        $this->repos->clients->save($this->request->client);
    }

    public function updateClient(Client &$client) {
        if ($client->ClientUserAgentID) {
            $userAgent = $this->repos->clientUseragents->load($client->ClientUserAgentID);
            if (!($userAgent->String === $this->userAgentString)) {
                $client->ClientUserAgentID = 0;
            }
        }
        if (!$client->ClientUserAgentID) {
            $userAgent = $this->httpUserAgent();
            $client->ClientUserAgentID = $userAgent->ID;
        }

        if ($client->ClientAcceptID) {
            $accept = $this->repos->clientAccepts->load($client->ClientAcceptID);
            $headers = $this->getAcceptHeaders();
            if (!($accept->Accept         === $headers['string']  ) ||
                !($accept->AcceptCharset  === $headers['charset'] ) ||
                !($accept->AcceptEncoding === $headers['encoding']) ||
                !($accept->AcceptLanguage === $headers['language'])
            ) {
                $client->ClientAcceptID = 0;
            }
        }
        if (!$client->ClientAcceptID) {
            $accept = $this->httpAccept();
            $client->ClientAcceptID = $accept->ID;
        }

        if (is_null($client->UserID) && $this->request->user instanceof User) {
            $client->UserID = $this->request->user->ID;
        } elseif ($this->request->user instanceof User) {
            # duplicate user or hijacked account.
            # Log them out and generate a StaffPM
            if (!($this->request->user->ID === $client->UserID)) {
                $user = $this->repos->users->load($client->UserID);
                $minStaffLevel = $this->repos->permissions->getMinStaffLevel();
                # Allow staff to have multiple accounts
                if ($user->class->Level < $minStaffLevel && $this->request->userperm->Level < $minStaffLevel) {
                    $this->auth->unauthenticate();
                    $subject = 'Duplicate account!';
                    $message = $this->render->template('bbcode/shared_browser.twig', [
                        'users'  => [$user, $this->request->user],
                    ]);

                    # Find the first staff class with IP permission
                    $staffClass = $this->repos->permissions->getMinClassPermission('users_view_ips');
                    if (!($staffClass === false)) {
                        send_staff_pm($subject, $message, $staffClass->Level);
                    }
                    throw new UserError('CID cookie corrupted');
                }
            }
        }

        $client->Updated = new \DateTime;
        return $client;
    }

    public function operatingSystem($userAgentString) {
        # Legacy function, slightly refactored
        if (empty($userAgentString)) {
            return 'Hidden';
        }
        $info = $this->parseUserAgent($userAgentString);
        $return = ($info && array_key_exists('platform', $info)) ? $info['platform'] : 'Unknown';
        return $return;
    }

    public function mobile($userAgentString) {
        # Legacy function

        if (strpos($userAgentString, 'iPad')) {
            return false;
        }

        # Mobi catches Mobile
        if (strpos($userAgentString, 'Device') || strpos($userAgentString, 'Mobi') || strpos($userAgentString, 'Mini') || strpos($userAgentString, 'webOS')) {
            return true;
        }

        return false;
    }

    public function browser(&$userAgentString) {
        # Legacy function, slightly refactored
        if (empty($userAgentString)) {
            return 'Hidden';
        }
        $info = $this->parseUserAgent($userAgentString);
        $return = ($info && array_key_exists('browser', $info)) ? $info['browser'] : 'Unknown';

        if ($this->mobile($userAgentString)) {
            $return .= ' Mobile';
        }

        return $return;
    }

    public function checkRemoteUpdate($src, $dst, $status = true) {
        $fileMTime = (int)@filemtime($dst);
        $headers = $this->getHttpRemoteHeaders($src);

        # Failed to fetch resource headers, skip this file.
        if ($headers === false) {
            print_r("Could not stat {$src}".PHP_EOL);
            return false;
        }

        # Check remote file time against local, if equal or less then
        # there's no update to fetch so skip it.
        if (array_key_exists('last-modified', $headers)) {
            $modified = new \DateTime($headers['last-modified']);
            if ($modified->getTimestamp() <= $fileMTime) {
                print_r("No update available for {$src}".PHP_EOL);
                return false;
            }
        } else {
            print_r("Failed to get last update time for {$src}".PHP_EOL);
            return false;
        }

        return true;
    }

    public function getHttpRemoteHeaders($url) {
        $ch = curl_init($url);

        # curl options
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER         => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];
        curl_setopt_array($ch, $options);

        $headers = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($responseCode === 200) {
            return $this->getHeadersFromCurlResponse($headers);
        } else {
            return false;
        }
    }

    public function getHttpRemoteFile($url, $path = null) {
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER         => false,
            CURLOPT_NOBODY         => false,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];

        if (!is_null($path)) {
            $out = fopen($path, "wb");
            $options[CURLOPT_FILE] = $out;
        }

        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        curl_close($ch);

        if ($out === false) {
            return $body;
        } else {
            fclose($out);
            return;
        }
    }

    private function getHeadersFromCurlResponse($response) {
        $headers = [];
        $headerText = substr($response, 0, strpos($response, "\r\n\r\n"));
        foreach (explode("\r\n", $headerText) as $i => $line) {
            if ($i === 0)
                $headers['http_code'] = $line;
            else {
                list ($key, $value) = explode(': ', $line);
                $headers[strtolower($key)] = $value;
            }
        }

        return $headers;
    }
}
