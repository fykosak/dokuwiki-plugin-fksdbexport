<?php

/**
 * DokuWiki Plugin fksdbexport (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

class fksdbexport_soap {

    /**
     * @var SoapClient
     */
    private $client;

    public function __construct($wsdl, $username, $password) {
        try {
            $this->client = new SoapClient($wsdl, array(
                'trace' => true,
                'exceptions' => true,
            ));
        } catch (SoapFault $e) {
            msg('fksdbexport: ' . $e->getMessage(), -1);
            return;
        }

        $credentials = new stdClass();
        $credentials->username = $username;
        $credentials->password = $password;

        $header = new SoapHeader('http://fykos.cz/xml/ws/service', 'AuthenticationCredentials', $credentials);
        $headers = array($header);
        $this->client->__setSoapHeaders($headers);
    }

    public function createRequest($qid, $parameters) {
        $parametersXML = array();
        foreach ($parameters as $name => $value) {
            $parametersXML[] = array(
                'name' => $name,
                '_' => $value,
            );
        }
        return array(
            'qid' => $qid,
            'parameter' => $parametersXML,
        );
    }

    /**
     * 
     * @param mixed $request
     * @return string response XML
     */
    public function getResponse($request) {
        try {
            $this->client->GetExport($request);
            return $this->client->__getLastResponse();
        } catch (SoapFault $e) {
            msg('fksdbexport: ' . $e->getMessage(), -1);
        }
    }

}
