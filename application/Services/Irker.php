<?php
namespace Luminance\Services;

use Luminance\Core\Service;

use Luminance\Errors\SystemError;

use Luminance\Entities\User;

class Irker extends Service {

    protected static $useServices = [
        'options'  => 'Options',
    ];

    protected static $defaultOptions = [
        'IrkerEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 1,
            'displayCol' => 1,
            'type' => 'bool',
            'description' => 'Enable irker IRC bot'
        ],
        'IrkerHost' => [
            'value' => 'localhost',
            'section' => 'irker',
            'displayRow' => 1,
            'displayCol' => 2,
            'type' => 'string',
            'description' => 'Irker IRC bot host'
        ],
        'IrkerPort' => [
            'value' => 6659,
            'section' => 'irker',
            'displayRow' => 1,
            'displayCol' => 3,
            'type' => 'int',
            'description' => 'Irker IRC bot port'
        ],
        'ServerAddress' => [
            'value' => 'ircs://irc.example.com:6697',
            'section' => 'irker',
            'displayRow' => 1,
            'displayCol' => 4,
            'type' => 'string',
            'description' => 'IRC Server Address'
        ],
        'TorrentAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 2,
            'displayCol' => 1,
            'type' => 'bool',
            'description' => 'Enable torrent announce'
        ],
        'TorrentAnnounceChannel' => [
            'value' => '#announce',
            'section' => 'irker',
            'displayRow' => 2,
            'displayCol' => 2,
            'type' => 'string',
            'description' => 'IRC channel for torrent announce'
        ],
        'ReportAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 2,
            'displayCol' => 3,
            'type' => 'bool',
            'description' => 'Enable report announce for staff'
        ],
        'ReportAnnounceChannel' => [
            'value' => '#announce',
            'section' => 'irker',
            'displayRow' => 2,
            'displayCol' => 7,
            'type' => 'string',
            'description' => 'IRC channel for reports to staff'
        ],
        'AdminAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 3,
            'displayCol' => 1,
            'type' => 'bool',
            'description' => 'Enable Admin report announce'
        ],
        'AdminAnnounceChannel' => [
            'value' => '#Sr Staff announce',
            'section' => 'irker',
            'displayRow' => 3,
            'displayCol' => 2,
            'type' => 'string',
            'description' => 'IRC channel for Admin announce'
        ],
        'DebugAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 3,
            'displayCol' => 3,
            'type' => 'bool',
            'description' => 'Enable Debug report announce'
        ],
        'DebugAnnounceChannel' => [
            'value' => '#Debug announce',
            'section' => 'irker',
            'displayRow' => 3,
            'displayCol' => 4,
            'type' => 'string',
            'description' => 'IRC channel for Debug announce'
        ],
        'LabAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 4,
            'displayCol' => 1,
            'type' => 'bool',
            'description' => 'Enable Lab report announce'
        ],
        'LabAnnounceChannel' => [
            'value' => '#Lab announce',
            'section' => 'irker',
            'displayRow' => 4,
            'displayCol' => 2,
            'type' => 'string',
            'description' => 'IRC channel for Lab announce'
        ],
        'ForbiddenErrorAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 4,
            'displayCol' => 3,
            'type' => 'bool',
            'description' => 'Enable Forbidden Error announces'
        ],
        //Intended for debugging usage. This will cause a TON of channel traffic
        'StackErrorAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 5,
            'displayCol' => 1,
            'type' => 'bool',
            'description' => 'Enable Deep Debug Announces',
        ],
        'DebugPHPAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 5,
            'displayCol' => 2,
            'type' => 'bool',
            'description' => 'Enable PHP Error Announces'
        ],
        'DebugParseAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 5,
            'displayCol' => 3,
            'type' => 'bool',
            'description' => 'Enable Parse Announces'
        ],
        'StackErrorAnnounceChannel' => [
            'value' => '#MayCauseHeavyChannelTrafficHere',
            'section' => 'irker',
            'displayRow' => 5,
            'displayCol' => 4,
            'type' => 'string',
            'description' => 'IRC chan for Deep Dubug, PHP, Parse Error Announces'
        ],
        'ArticleAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 6,
            'displayCol' => 1,
            'type' => 'bool',
            'description' => 'Enable Artcle Announces to Staff chan'
        ],
        'WikiAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 6,
            'displayCol' => 2,
            'type' => 'bool',
            'description' => 'Enable Wiki Announces to Staff chan'
        ],
        'PublicAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 7,
            'displayCol' => 1,
            'type' => 'bool',
            'description' => 'Enable Public announces'
        ],
        'PublicAnnounceChannel' => [
            'value' => '#mtv',
            'section' => 'irker',
            'displayRow' => 7,
            'displayCol' => 2,
            'type' => 'string',
            'description' => 'IRC channel for Public announces'
        ],
        'RequestAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 7,
            'displayCol' => 3,
            'type' => 'bool',
            'description' => 'Enable Request announces'
        ],
        'RequestAnnounceChannel' => [
            'value' => '#mtv',
            'section' => 'irker',
            'displayRow' => 7,
            'displayCol' => 4,
            'type' => 'string',
            'description' => 'IRC channel for Request announces'
        ],
        'TorrentCommentAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 8,
            'displayCol' => 1,
            'type' => 'bool',
            'description' => 'Enable Torrent Comment announces to public channel'
        ],
        'RequestCommentAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 8,
            'displayCol' => 2,
            'type' => 'bool',
            'description' => 'Enable Request Comment announces to public channel'
        ],
        'CollageCommentAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 8,
            'displayCol' => 3,
            'type' => 'bool',
            'description' => 'Enable Collage Comment announces to public channel'
        ],
        'ForumAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 8,
            'displayCol' => 4,
            'type' => 'bool',
            'description' => 'Enable forum announces to public channel'
        ],
        'BlogAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 9,
            'displayCol' => 1,
            'type' => 'bool',
            'description' => 'Enable blog announces to public channel'
        ],
        'UserErrorAnnounceEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 9,
            'displayCol' => 2,
            'type' => 'bool',
            'description' => 'Enable User Errors announces to debug channel'
        ],
        'AuthUserEnable' => [
            'value' => false,
            'section' => 'irker',
            'displayRow' => 10,
            'displayCol' => 1,
            'type' => 'bool',
            'description' => 'Enable GroupServ Control'
        ],
        'AuthServiceNick' => [
            'value' => 'GroupServ',
            'section' => 'irker',
            'displayRow' => 10,
            'displayCol' => 2,
            'type' => 'string',
            'description' => 'IRC users group'
        ],
        'AuthUserGroup' => [
            'value' => '!users',
            'section' => 'irker',
            'displayRow' => 10,
            'displayCol' => 3,
            'type' => 'string',
            'description' => 'IRC users group'
        ],
        'AuthUserAlert' => [
            'value' => 'false',
            'section' => 'irker',
            'displayRow' => 10,
            'displayCol' => 4,
            'type' => 'bool',
            'description' => 'Alert IRC users upon IRC Auth'
        ],
    ];

    private static function formatPayload($destination, $privmsg) {
        return json_encode(
            [
                'to'      => $destination,
                'privmsg' => $privmsg,
            ]
        ) . "\n";
    }

    private function send($payload) {
        try {
            if ($this->options->IrkerEnable === true) {
                # Send to irker
                $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                $irkerHost = $this->options->IrkerHost;
                $irkerPort = $this->options->IrkerPort;

                if ($socket === false
                || socket_connect($socket, $irkerHost, $irkerPort) === false
                || socket_write($socket,  $payload) === false) {
                    throw new SystemError('Socket error: ' . socket_strerror(socket_last_error()));
                }
            }
        # Slightly hacky to catch an exception we've just thrown but it's the
        # best way to get a consistent backtrace and won't interfere with
        # the rest of the processing.
        } catch (SystemError $e) {
            error_log("Caught " . get_class($e) . ": ". $e->getMessage() . PHP_EOL . $e->getTraceAsString());
        }
    }

    public function announceTorrent($privmsg) {
        if ($this->options->TorrentAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->TorrentAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announceReport($privmsg) {
        if ($this->options->ReportAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->ReportAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announceRequest($privmsg) {
        if ($this->options->RequestAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->RequestAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announceAdmin($privmsg) {
        if ($this->options->AdminAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->AdminAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announceDebug($privmsg) {
        if ($this->options->DebugAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->DebugAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announceUserError($privmsg) {
        if ($this->options->UserErrorAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->DebugAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announceLab($privmsg) {
        if ($this->options->LabAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->LabAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announceStackErrors($privmsg) {
        if ($this->options->StackErrorAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->StackErrorAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announcePublic($privmsg) {
        if ($this->options->PublicAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->PublicAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announceTorrentComment($privmsg) {
        if ($this->options->TorrentCommentAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->PublicAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announceRequestComment($privmsg) {
        if ($this->options->RequestCommentAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->PublicAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announceCollageComment($privmsg) {
        if ($this->options->CollageCommentAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->PublicAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announceForum($privmsg) {
        if ($this->options->ForumAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->PublicAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announceBlog($privmsg) {
        if ($this->options->BlogAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->PublicAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announceArticle($privmsg) {
        if ($this->options->ArticleAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->ReportAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

    public function announceWiki($privmsg) {
        if ($this->options->WikiAnnounceEnable === true) {
            $server = $this->options->ServerAddress;
            $channel = $this->options->ReportAnnounceChannel;
            $destination = "{$server}/{$channel}";
            $this->send(self::formatPayload($destination, $privmsg));
        }
    }

//      IRC AUTH SECTION - Links Site Username to IRC Username

    public function authUser($nick) {
        if ($this->options->AuthUserEnable === true) {
            $server = $this->options->ServerAddress;
            $serviceNick = $this->options->AuthServiceNick;
            $userGroup = $this->options->AuthUserGroup;
            $destination = "{$server}/{$serviceNick},isnick";
            $privmsg = "FLAGS {$userGroup} {$nick} +cv";
            $channel = $this->options->DebugAnnounceChannel;
            $destinationDebug = "{$server}/{$channel}";
            $privmsgDebug = "User {$nick} added to IRC group {$userGroup}";
            $this->send(self::formatPayload($destination, $privmsg));
            $this->send(self::formatPayload($destinationDebug, $privmsgDebug));
            if ($this->options->AuthUserAlert === true) {
                $usrPm = "{$server}/{$nick},isnick";
                $usrmsg = "IRC Auth Success for {$nick}";
                $this->send(self::formatPayload($usrPm, $usrmsg));
            }
        }
    }

    public function deauthUser($nick) {
        if ($this->options->AuthUserEnable === true) {
            $server = $this->options->ServerAddress;
            $serviceNick = $this->options->AuthServiceNick;
            $userGroup = $this->options->AuthUserGroup;
            $destination = "{$server}/{$serviceNick},isnick";
            $privmsg = "FLAGS {$userGroup} {$nick} -*";
            $channel = $this->options->DebugAnnounceChannel;
            $destinationDebug = "{$server}/{$channel}";
            $privmsgDebug = "User {$nick} removed from IRC group {$userGroup}";
            $this->send(self::formatPayload($destination, $privmsg));
            $this->send(self::formatPayload($destinationDebug, $privmsgDebug));
            if ($this->options->AuthUserAlert === true) {
                $usrPm = "{$server}/{$nick},isnick";
                $usrmsg = "A user (maybe you) clicked the IRC Auth button unlinking your IRC nick, {$nick}";
                $this->send(self::formatPayload($usrPm, $usrmsg));
            }
        }
    }

//      Let's get the announce creations started

    public function deepDebugAnnounce($e) {
        if (!$this->request->cli) {
            if ($this->options->StackErrorAnnounceEnable) {
                $sslurl = $this->master->settings->main->ssl_site_url;
                $user = $this->request->user;
                if ($user instanceof User) {
                    if ($user->class->Level < $this->master->settings->users->level_admin) {
                        $message = ($sslurl . "/user.php?id=".$user->ID." received an error. URL: ".$this->request->url." ");
                        $message .= ("IP: " . $this->request->ip . "Agent: " . $this->request->agent);
                    }
                } else {
                    $message = ("Error on URL: ".$this->request->url." ");
                    $message .= ("IP: " . $this->request->ip . "Agent: " . $this->request->agent);
                }
                $message .= ("Begin Error\n" . $e);
                $this->announceStackErrors($message);
            }
        }
    }

    public function deepDebugPHPAnnounce($e) {
        if (!$this->request->cli) {
            if ($this->options->DebugPHPAnnounceEnable) {
                $sslurl = $this->master->settings->main->ssl_site_url;
                $user = $this->request->user;
                if ($user instanceof User) {
                    $message = ($sslurl . "/user.php?id=".$user->ID." received an error. URL: ".$this->request->url." ");
                } else {
                    $message = ("Unlogged user with IP ".$this->request->ip." received an error. URL: ".$this->request->url." ");
                }
                $message .= ("Agent: " . $this->request->agent . " ");
                $message .= ("Begin PHP Error\n" . $e);
                $this->announceStackErrors($message);
            }
        }
    }

    public function deepDebugParseAnnounce($e) {
        if (!$this->request->cli) {
            if ($this->options->DebugParseAnnounceEnable) {
                $sslurl = $this->master->settings->main->ssl_site_url;
                $user = $this->request->user;

                $message = ("Unlogged user with IP ".$this->request->ip." received an error. URL: ".$this->request->url." ");
                if ($user instanceof User) {
                    if ($user->class->Level < $this->master->settings->users->level_admin) {
                        $message = ($sslurl . "/user.php?id=".$user->ID." received an error. URL: ".$this->request->url." ");
                    }
                }
                $message .= ("Agent: " . $this->request->agent . " ");
                $message .= ("Begin Parse Error\n" . $e);

                $this->announceStackErrors($message);
            }
        }
    }

    public function forbiddenErrIrk() {
        if (!$this->request->cli) {
            if ($this->options->ForbiddenErrorAnnounceEnable) {
                $sslurl = $this->master->settings->main->ssl_site_url;
                $user = $this->request->user;
                if ($user instanceof User) {
                    if ($user->class->Level < $this->master->settings->users->level_admin) {
                        $message = ("User " . $user->Username . " " . $sslurl . "/user.php?id=".$user->ID . " just tried to view a forbidden page!(403) URL: " . $this->request->url . " ");
                        $message .= ("IP: " . $this->request->ip . "Agent:" . $this->request->agent);
                    }
                } else {
                    $message = ("Unlogged user with IP ".$this->request->ip." received an error. URL: ".$this->request->url." ");
                }
                $this->announceDebug($message);
            }
        }
    }

    public function userErrorIrker() {
        if (!$this->request->cli) {
            $sslurl = $this->master->settings->main->ssl_site_url;
            $user = $this->request->user;
            $message = ("Unlogged user with IP ".$this->request->ip." received an error. URL: ".$this->request->url." ");
            if ($user instanceof User) {
                if ($user->class->Level < $this->master->settings->users->level_admin) {
                    $message = ("User ".$user->Username." https://".$sslurl."/user.php?id=".$user->ID." just created a 400: Bad Request! URL: ".$this->request->url." ");
                    $message .= ("\nOffending IP: " . $this->request->ip . "Agent " . $this->request->agent . " Check the logs!");
                }
            }
            $this->announceUserError($message);
        }
    }

    public function userLostNeIrker() {
        if (!$this->request->cli) {
            $sslurl = $this->master->settings->main->ssl_site_url;
            $user = $this->request->user;
            if ($user instanceof User) {
                if ($user->class->Level < $this->master->settings->users->level_admin) {
                    $message = ("User ".$user->Username." https://".$sslurl."/user.php?id=".$user->ID." just tried to view a non existent user! URL: ".$this->request->url." ");
                    $message .= ("\nOffending IP: " . $this->request->ip . "Agent " . $this->request->agent . " Check the logs!");
                }
            } else {
                $message = ("Unlogged user with IP ".$this->request->ip." received an error. URL: ".$this->request->url." ");
            }
            $this->announceDebug($message);
            //if ($this->settings->site->debug_mode) {
            //$master->flasher->error($message);
            //}
        }
    }
}
