<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;
use Luminance\Entities\Client;

class ClientRepository extends Repository {

    protected $entityName = 'Client';

    public function getByCID($CID) {
        $hexCID = bin2hex($CID);
        $client = $this->get('`CID` = ?', [$CID], "_query_Client_CID_{$hexCID}");
        return $client;
    }

    public function new($CID, $ip) {
        $hexCID = bin2hex($CID);
        $client = new Client;
        $client->CID = $CID;
        $client->IPID = $ip->ID;
        $client->Created = new \DateTime;
        $client->TLSVersion = $this->master->request->TLSVersion ?? '';
        $client->HTTPVersion = $this->master->request->HTTPVersion ?? '';
        $this->uncache($client);
        return $client;
    }

    /**
     * Delete Client entity from cache
     * @param int|Entity $client client to uncache
     *
     */
    public function uncache($client) {
        $client = $this->load($client);
        if ($client instanceof Client) {
            parent::uncache($client);
            $hexCID = bin2hex($client->CID);
            $this->cache->deleteValue("_query_Client_CID_{$hexCID}");
        }
    }
}
