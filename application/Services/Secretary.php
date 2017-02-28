<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Entities\Client;
use Luminance\Entities\ClientUserAgent;
use Luminance\Entities\ClientAccept;
use Luminance\Entities\ClientScreen;
use Luminance\Entities\Session;
use Luminance\Errors\InternalError;
use Luminance\Errors\UserError;


class Secretary extends Service {

    public $ipChanged = null;
    public $Info;
    public $UserAgentString;

    protected static $useServices = [
        'crypto'   => 'Crypto',
        'settings' => 'Settings',
    ];

    protected static $useRepositories = [
        'clients'    => 'ClientRepository',
        'useragents' => 'ClientUserAgentRepository',
        'accepts'    => 'ClientAcceptRepository',
        'screens'    => 'ClientScreenRepository',
        'ips'        => 'IPRepository',
    ];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->request = $this->master->request;
        $this->server = $this->master->server;
        if ($this->master->request->method != 'CLI' &&
                array_key_exists('HTTP_USER_AGENT', $this->server)) {
            $this->Info = $this->parse_user_agent($this->server['HTTP_USER_AGENT']);
            $this->UserAgentString = $this->server['HTTP_USER_AGENT'];
        } else {
            $this->Info = ['platform'=>null, 'browser'=>null, 'version'=>null];
            $this->UserAgentString = '';
        }
    }

    public function getToken($action = '') {
        if (is_null($this->CID)) {
            throw new UserError('CID cookie not found or invalid');
        }
        $token = $this->crypto->generateAuthToken('token', $this->CID, $action);
        return $token;
    }

    public function checkToken($token, $action = '', $duration = 86400) {
        if (is_null($this->CID)) {
            throw new UserError('CID cookie not found or invalid');
        }
        if ($this->crypto->checkAuthToken('token', $token, $this->CID, $action, $duration)) {
            return true;
        }
        throw new UserError('Authorization token expired or invalid');
    }

    public function checkClient() {
        $this->request->ip = $this->ips->get_or_new($this->master->server['REMOTE_ADDR']);
        $client = null;
        if (array_key_exists('cid', $this->request->cookie)) {
            $CID = $this->crypto->decrypt($this->request->cookie['cid'], 'cookie');
            if ($CID) {
                $client = $this->clients->getByCID($CID);
            }
        }
        if ($client) {
            $this->timecheckClient($client);
        } else {
            $client = $this->createClient();
        }

        // Ensure global CID is set and cookie is set/updated
        $this->CID = $client->CID;
        $this->request->set_cookie('cid', $this->crypto->encrypt($client->CID, 'cookie'), time()+(60*60*24)*28, true);
        $this->request->client = $client;
    }

    protected function createClient() {
        $client = new Client();
        $client->CID = $this->crypto->random_bytes(8);
        $client->IPID = $this->request->ip->ID;
        $this->updateClient($client);
        $this->clients->save($client);
        return $client;
    }

    public function timecheckClient(Client $client) {
        $compDate = new \DateTime('2 minutes ago');
        $ipChanged = false;

        if ($this->request->ip->ID != $client->IPID) {
            $client->IPID = $this->request->ip->ID;
            $this->ipChanged = true;
        } else {
            $this->ipChanged = false;
        }

        if ($client->Updated <= $compDate || $this->ipChanged) {
            $client->Updated = new \DateTime();
            $this->updateClient($client);
            $this->clients->save($client);
        }
        return $client;
    }

    public function send_email($To, $Subject, $Body, $From='noreply', $ContentType='text/plain') {
        # Legacy function
        $Headers = 'MIME-Version: 1.0' . "\r\n";
        $Headers.='Content-type: ' . $ContentType . '; charset=iso-8859-1' . "\r\n";
        $Headers.='From: ' . $this->settings->main->site_name;
        $Headers.=' <' . $From . '@' . $this->settings->main->site_url . '>' . "\r\n";
        $Headers.='Reply-To: ' . $From . '@' . $this->settings->main->site_url . "\r\n";
        $Headers.='X-Mailer: Project Luminance' . "\r\n";
        $Headers.='Message-Id: <' . make_secret() . '@' . $this->settings->main->site_url . ">\r\n";
        $Headers.='X-Priority: 3' . "\r\n";
        mail($To, $Subject, $Body, $Headers, "-f " . $From . "@" . $this->settings->main->site_url);
    }

    public function parse_user_agent($UserAgentString) {
        # This just wraps the function from the provided library
        $Info = ['platform'=>null, 'browser'=>null, 'version'=>null];
        $Info = array_merge($Info, parse_user_agent($UserAgentString));
        return $Info;
    }

    public function http_user_agent() {
        $UserAgentString = (array_key_exists('HTTP_USER_AGENT', $this->server)) ? $this->server['HTTP_USER_AGENT'] : '';
        $UserAgent = $this->useragents->get_by_string($UserAgentString);
        if (!$UserAgent) {
            $UserAgent = new ClientUserAgent();
            $UserAgent->String = $UserAgentString;
            $UserAgent->Platform = $this->Info['platform'];
            $UserAgent->Browser = $this->Info['browser'];
            $UserAgent->Version = $this->Info['version'];
            $this->useragents->save($UserAgent);
        }
        return $UserAgent;
    }

    public function get_accept_headers() {
        $Headers = [];
        $Headers['string'] = (array_key_exists('HTTP_ACCEPT', $this->server)) ? $this->server['HTTP_ACCEPT'] : null;
        $Headers['charset'] = (array_key_exists('HTTP_ACCEPT_CHARSET', $this->server)) ? $this->server['HTTP_ACCEPT_CHARSET'] : null;
        $Headers['encoding'] = (array_key_exists('HTTP_ACCEPT_ENCODING', $this->server)) ? $this->server['HTTP_ACCEPT_ENCODING'] : null;
        $Headers['language'] = (array_key_exists('HTTP_ACCEPT_LANGUAGE', $this->server)) ? $this->server['HTTP_ACCEPT_LANGUAGE'] : null;
        return $Headers;
    }

    public function http_accept() {
        $Headers = $this->get_accept_headers();
        $Accept = $this->accepts->get_by_values($Headers['string'], $Headers['charset'], $Headers['encoding'], $Headers['language']);
        if (!$Accept) {
            $Accept = new ClientAccept();
            $Accept->Accept = $Headers['string'];
            $Accept->AcceptCharset = $Headers['charset'];
            $Accept->AcceptEncoding = $Headers['encoding'];
            $Accept->AcceptLanguage = $Headers['language'];
            $this->accepts->save($Accept);
        }
        return $Accept;
    }

    public function updateClientInfo($options) {
        $screen = $this->screens->get_by_values($options['width'], $options['height'], $options['colordepth']);
        if (!$screen) {
            $screen = new ClientScreen();
            $screen->Width = $options['width'];
            $screen->Height = $options['height'];
            $screen->ColorDepth = $options['colordepth'];
            $this->screens->save($screen);
        }
        $this->request->client->ClientScreenID = $screen->ID;
        $this->request->client->TimezoneOffset = $options['timezoneoffset'];
        $this->clients->save($this->request->client);
    }

    public function updateClient(Client $Client) {
        if ($Client->ClientUserAgentID) {
            $UserAgent = $this->useragents->load($Client->ClientUserAgentID);
            if ($UserAgent->String != $this->UserAgentString) {
                $Client->ClientUserAgentID = 0;
            }
        }
        if (!$Client->ClientUserAgentID) {
            $UserAgent = $this->http_user_agent();
            $Client->ClientUserAgentID = $UserAgent->ID;
        }

        if ($Client->ClientAcceptID) {
            $Accept = $this->accepts->load($Client->ClientAcceptID);
            $Headers = $this->get_accept_headers();
            if (
                $Accept->Accept != $Headers['string'] ||
                $Accept->AcceptCharset != $Headers['charset'] ||
                $Accept->AcceptEncoding != $Headers['encoding'] ||
                $Accept->AcceptLanguage != $Headers['language']
            ) {
                $Client->ClientAcceptID = 0;
            }
        }
        if (!$Client->ClientAcceptID) {
            $Accept = $this->http_accept();
            $Client->ClientAcceptID = $Accept->ID;
        }
        return $Client;
    }

    public function operating_system($UserAgentString) {
        # Legacy function, slightly refactored
        if (empty($UserAgentString)) {
            return 'Hidden';
        }
        $Info = $this->parse_user_agent($UserAgentString);
        $Return = ($Info && array_key_exists('platform', $Info)) ? $Info['platform'] : 'Unknown';
        return $Return;
    }

    public function mobile($UserAgentString) {
        # Legacy function

        if (strpos($UserAgentString, 'iPad')) {
            return false;
        }

        //Mobi catches Mobile
        if (strpos($UserAgentString, 'Device') || strpos($UserAgentString, 'Mobi') || strpos($UserAgentString, 'Mini') || strpos($UserAgentString, 'webOS')) {
            return true;
        }

        return false;
    }

    public function browser(&$UserAgentString) {
        # Legacy function, slightly refactored
        if (empty($UserAgentString)) {
            return 'Hidden';
        }
        $Info = $this->parse_user_agent($UserAgentString);
        $Return = ($Info && array_key_exists('browser', $Info)) ? $Info['browser'] : 'Unknown';

        if ($this->mobile($UserAgentString)) {
            $Return .= ' Mobile';
        }

        return $Return;
    }
}
