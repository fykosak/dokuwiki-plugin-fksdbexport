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

require_once(__DIR__ . '/inc/soap.php');

class syntax_plugin_fksdbexport extends DokuWiki_Syntax_Plugin {

    const REFRESH_AUTO = 'auto';
    const REFRESH_MANUAL = 'manual';
    const TEMPLATE_DOKUWIKI = 'dokuwiki';
    const TEMPLATE_XSLT = 'xslt';

    /**
     * @return string Syntax mode type
     */
    public function getType() {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType() {
        return 'normal';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() {
        return 165; //just copied Doodle or whatever
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<fksdbexport\b.*?>.+?</fksdbexport>', $mode, 'plugin_fksdbexport');
    }

    /**
     * Handle matches of the fksdbexport syntax
     *
     * @param string $match The match of the syntax
     * @param int    $state The state of the handler
     * @param int    $pos The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler &$handler) {
        $match = substr($match, 13, -14);              // strip markup (including space after "<fksdbexport ")
        list($parameterString, $templateString) = preg_split('/>/u', $match, 2);

        $params = $this->parseParameters($parameterString);

        $qid = $params['qid'];
        $queryParameters = $params['parameters'];
        $filename = $this->getDataFilename($qid, $queryParameters);
        $exportId = $this->getExportId($qid, $queryParameters);

        if ($params['refresh'] == self::REFRESH_AUTO) {
            $this->autoRefresh($params, $filename);
        } else if ($params['refresh'] == self::REFRESH_MANUAL) {
            $this->manualRefresh($params, $filename);
        }

        if (file_exists($filename)) { //download succeeded
            $content = $this->prepareContent($params, $filename, $templateString);
        } else {
            $content = null;
        }

        $downloadResult = array($params, $templateString, $filename, $exportId, $content);
        return $downloadResult;
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $mode      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer &$renderer, $data) {
        list($params, $template, $filename, $exportId, $content) = $data;

        if ($mode == 'xhtml') {
            if ($params['refresh'] == self::REFRESH_AUTO) {
                $this->autoRefresh($params, $filename);
                $content = file_exists($filename) ? $this->prepareContent($params, $filename, $template) : null;
            }

            if ($content === null) {
                $renderer->doc .= $this->getLang('missing_data');
                $renderer->nocache();
                return true;
            }

            if ($params['template'] == self::TEMPLATE_DOKUWIKI) {
                $renderer->doc .= p_render($mode, $content, $info);
            } else if ($params['template'] == self::TEMPLATE_XSLT) {
                $renderer->doc .= $content;
            }

            return true;
        } else if ($mode == 'metadata') {
            if ($params['refresh'] == self::REFRESH_MANUAL && file_exists($filename)) {
                $renderer->meta[$this->getPluginName()][$exportId]['version'] = $params['version'];
            } else if ($params['refresh'] == self::REFRESH_AUTO) {
                $expiration = $params['expiration'] !== null ? $params['expiration'] : $this->getConf('expiration');
                if (isset($renderer->meta['date']['valid']['age'])) {
                    $renderer->meta['date']['valid']['age'] = min($renderer->meta['date']['valid']['age'], $expiration);
                } else {
                    $renderer->meta['date']['valid']['age'] = $expiration;
                }
            }

            return true;
        }
        return false;
    }

    /**
     * @note Modified Doodle2 plugin.
     * 
     * @param type $parameterString
     */
    private function parseParameters($parameterString) {
        //----- default parameter settings
        $params = array(
            'qid' => null,
            'parameters' => array(),
            'refresh' => self::REFRESH_AUTO,
            'version' => 0,
            'expiration' => null,
            'template' => self::TEMPLATE_DOKUWIKI,
        );

        //----- parse parameteres into name="value" pairs  
        preg_match_all("/(\w+?)=\"(.*?)\"/", $parameterString, $regexMatches, PREG_SET_ORDER);
        //debout($parameterStr);
        //debout($regexMatches);
        for ($i = 0; $i < count($regexMatches); $i++) {
            $name = strtolower($regexMatches[$i][1]);  // first subpattern: name of attribute in lowercase
            $value = $regexMatches[$i][2];              // second subpattern is value
            if (strcmp($name, "qid") == 0) {
                $params['qid'] = trim($value);
            } else if (strcmp(substr($name, 0, 6), "param_") == 0) {
                $key = substr($name, 6);
                $params['parameters'][$key] = $value;
            } else if (strcmp($name, "refresh") == 0) {
                if ($value == self::REFRESH_AUTO) {
                    $params['refresh'] = self::REFRESH_AUTO;
                } else if ($value == self::REFRESH_AUTO) {
                    $params['refresh'] = self::REFRESH_AUTO;
                } else {
                    msg(sprintf($this->getLang('unexpected_value'), $value), -1);
                }
            } else if (strcmp($name, "version") == 0) {
                $params['version'] = trim($value);
            } else if (strcmp($name, "expiration") == 0) {
                if (!is_numeric($value)) {
                    msg($this->getLang('expected_number'), -1);
                }
                $params['expiration'] = trim($value);
            } else if (strcmp($name, "template") == 0) {
                if ($value == self::TEMPLATE_DOKUWIKI) {
                    $params['template'] = self::TEMPLATE_DOKUWIKI;
                } else if ($value == self::TEMPLATE_XSLT) {
                    $params['template'] = self::TEMPLATE_XSLT;
                } else {
                    msg(sprintf($this->getLang('unexpected_value'), $value), -1);
                }
            } else {
                msg(sprintf($this->getLang('unexpected_value'), $name), -1);
            }
        }
        return $params;
    }

    private function downloadData($qid, $parameters) {
        $soap = new fksdbexport_soap($this->getConf('wsdl'), $this->getConf('user'), $this->getConf('password'));
        $request = $soap->createRequest($qid, $parameters);
        $xml = $soap->getResponse($request);

        if (!$xml) {
            msg('fksdbexport: ' . sprintf($this->getLang('download_failed'), $qid), -1);
        } else {
            $filename = $this->getDataFilename($qid, $parameters);
            io_saveFile($filename, $xml);
        }
    }

    private function getExportId($qid, $parameters) {
        $hash = md5(serialize($parameters));
        return $qid . '_' . $hash;
    }

    private function getDataFilename($qid, $parameters) {
        global $ID;

        $dataId = $ID . '.' . $this->getExportId($qid, $parameters);
        return metaFN($dataId, '.xml');
    }

    private function prepareContent($params, $filename, $templateString) {
        $xml = new DomDocument;
        $xml->loadXML(io_readFile($filename));

        if ($params['template'] == self::TEMPLATE_DOKUWIKI) {
            $xpath = new DOMXPath($xml);
            $needles = array();
            //preg_match('#\s*(<header\s*>(.*)</header>)?(.*?)(<footer\s*>(.*)</footer>)?#', $templateString, $matches);
            $m = preg_match('#^\s*(<header>(.*)</header>)?(.+)(<footer>(.*)</footer>)?\s*$#s', $templateString, $matches);
            $rowTemplate = trim($matches[3]);
            $header = trim($matches[2]);
            $footer = trim($matches[5]);

            foreach ($xpath->query('//column-definitions/column-definition') as $iter) {
                $name = $iter->getAttribute('name');
                $needles[] = '@' . $name . '@';
            }
            $needles[] = '@iterator0@';
            $needles[] = '@iterator@';

            $source = $header . "\n";
            $iterator = 0;
            foreach ($xpath->query('//data/row') as $row) {
                $replacements = array();
                foreach ($row->childNodes as $child) {
                    if ($child->nodeName == 'col') {
                        $replacements[] = $child->textContent;
                    }
                }
                $replacements[] = $iterator++;
                $replacements[] = $iterator;

                $source .= str_replace($needles, $replacements, $rowTemplate) . "\n";
            }
            $source .= $footer . "\n";

            return p_get_instructions($source);
        } else if ($params['template'] == self::TEMPLATE_XSLT) {
            $xsltproc = new XsltProcessor();
            $xsl = new DomDocument;
            $xsl->loadXML(trim($templateString));
            //$xsltproc->registerPHPFunctions(); // TODO verify need of this
            $xsltproc->importStyleSheet($xsl);
            $result = $xsltproc->transformToXML($xml);
            if ($result === false) {
                foreach (libxml_get_errors() as $e) {
                    msg($e->message, -1);
                }
                $e = libxml_get_last_error();
                msg($e->message, -1);
            }
            return $result;
        }
    }

    private function autoRefresh($params, $filename) {
        $expiration = $params['expiration'] !== null ? $params['expiration'] : $this->getConf('expiration');
        if (!file_exists($filename) || filemtime($filename) + $expiration < time()) {
            $this->downloadData($params['qid'], $params['parameters']);
        }
    }

    private function manualRefresh($params, $filename) {
        global $ID;
        $desiredVersion = $params['version'];
        $key = $this->getPluginName() . ' ' . $this->getExportId($params['qid'], $params['parameters']) . ' version';
        $downloadedVersion = p_get_metadata($ID, $key);

        if (!file_exists($filename) || $downloadedVersion === null || $desiredVersion > $downloadedVersion) {
            $this->downloadData($params['qid'], $params['parameters']);
        }
    }

}

// vim:ts=4:sw=4:et:
