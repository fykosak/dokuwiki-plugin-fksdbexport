<?php

use dokuwiki\Extension\SyntaxPlugin;
use Fykosak\FKSDBDownloaderCore\Requests\EventListRequest;
use Fykosak\FKSDBDownloaderCore\Requests\EventRequest;
use Fykosak\FKSDBDownloaderCore\Requests\ExportRequest;
use Fykosak\FKSDBDownloaderCore\Requests\OrganizersRequest;
use Fykosak\FKSDBDownloaderCore\Requests\Request;
use Fykosak\FKSDBDownloaderCore\Requests\Results\ResultsCumulativeRequest;
use Fykosak\FKSDBDownloaderCore\Requests\Results\ResultsDetailRequest;

/**
 * DokuWiki Plugin fksdbexport (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */
class syntax_plugin_fksdbexport extends SyntaxPlugin {

    public const REFRESH_AUTO = 'auto';
    public const REFRESH_MANUAL = 'manual';

    public const TEMPLATE_DOKUWIKI = 'dokuwiki';
    public const TEMPLATE_XSLT = 'xslt';

    public const SOURCE_EXPORT = 'export';
    public const SOURCE_EXPORT1 = 'export1';
    public const SOURCE_EXPORT2 = 'export2';

    public const SOURCE_ORGANIZERS = 'orgs';
    public const SOURCE_EVENT_LIST = 'event.list';

    public const SOURCE_EVENT_DETAIL = 'event.detail';
    public const SOURCE_EVENT_PARTICIPANTS = 'event.participants';

    public const SOURCE_RESULT_DETAIL = 'results.detail';
    public const SOURCE_RESULT_CUMMULATIVE = 'results.cummulative';
    public const SOURCE_RESULT_SCHOOL_CUMMULATIVE = 'results.school-cummulative';

    private helper_plugin_fksdbexport $downloader;

    public function __construct() {
        $this->downloader = $this->loadHelper('fksdbexport');
    }

    /**
     * @return string Syntax mode type
     */
    public function getType(): string {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType(): string {
        return 'block';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort(): int {
        return 165;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode): void {
        $this->Lexer->addSpecialPattern('<fksdbexport\b.*?>.*?</fksdbexport>', $mode, 'plugin_fksdbexport');
    }

    /**
     * Handle matches of the fksdbexport syntax
     *
     * @param string $match The match of the syntax
     * @param int $state The state of the handler
     * @param int $pos The position in the document
     * @param Doku_Handler $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler): array {
        $match = substr($match, 13, -14);              // strip markup (including space after "<fksdbexport ")
        [$parameterString, $templateString] = preg_split('/>/u', $match, 2);

        $params = $this->parseParameters($parameterString);
        $request = $this->createRequest($params);

        if ($params['refresh'] === self::REFRESH_MANUAL) {
            $source = $this->manualRefresh($params, $request);
        } else {
            $source = $this->autoRefresh($params, $request);
        }

        $content = $this->prepareContent($params, $source, $templateString);

        return [$params, $templateString, $content];
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string $mode Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer|Doku_Renderer_metadata|Doku_Renderer_xhtml $renderer The renderer
     * @param array $data The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data): bool {
        [$params, $template, $content] = $data;
        $request = $this->createRequest($params);
        if ($mode == 'xhtml') {
            if ($params['refresh'] == self::REFRESH_AUTO) {
                $content = $this->prepareContent($params, $this->autoRefresh($params, $request), $template);
            }

            if ($content === null) {
                $renderer->doc .= $this->getLang('missing_data');
                $renderer->nocache();
                return true;
            }

            if ($params['template'] == self::TEMPLATE_DOKUWIKI) {
                $renderer->doc .= p_render($mode, $content, $info);
            } elseif ($params['template'] == self::TEMPLATE_XSLT) {
                $renderer->doc .= $content;
            }

            return true;
        } elseif ($mode == 'metadata') {
            if ($params['refresh'] == self::REFRESH_MANUAL && $content !== null) {
                $renderer->meta[$this->getPluginName()][$request->getCacheKey()]['version'] = $params['version'];
            } elseif ($params['refresh'] == self::REFRESH_AUTO) {
                $expiration = $params['expiration'] !== null ? $params['expiration'] : $this->getConf('expiration');
                if (isset($renderer->meta['date']['valid']['age'])) {
                    $renderer->meta['date']['valid']['age'] = min($renderer->meta['date']['valid']['age'], $expiration);
                } else {
                    $renderer->meta['date']['valid']['age'] = $expiration;
                }
            }
            if ($params['template_file']) {
                $templateFile = wikiFN($params['template_file']);
                if (isset($renderer->meta['relation']['fksdbexport'])) {
                    $renderer->meta['relation']['fksdbexport'][] = $templateFile;
                } else {
                    $renderer->meta['relation']['fksdbexport'] = [$templateFile];
                }
            }

            return true;
        }
        return false;
    }

    private function parseParameters(string $parameterString): array {
        //----- default parameter settings
        $params = [
            'qid' => null,
            'parameters' => [
                'contest' => $this->getConf('contest'),
            ],
            'refresh' => self::REFRESH_AUTO,
            'version' => 0,
            'expiration' => null,
            'template' => self::TEMPLATE_DOKUWIKI,
            'template_file' => null,
            'source' => self::SOURCE_EXPORT1,
        ];

        //----- parse parameteres into name="value" pairs
        preg_match_all("/(\w+?)=\"(.*?)\"/", $parameterString, $regexMatches, PREG_SET_ORDER);
        //debout($parameterStr);
        //debout($regexMatches);
        for ($i = 0; $i < count($regexMatches); $i++) {
            $name = strtolower($regexMatches[$i][1]);  // first subpattern: name of attribute in lowercase
            $value = $regexMatches[$i][2];              // second subpattern is value
            if (strcmp($name, 'qid') == 0) {
                $params['qid'] = trim($value);
            } elseif (strcmp(substr($name, 0, 6), 'param_') == 0) {
                $key = substr($name, 6);
                $params['parameters'][$key] = $value;
            } elseif (strcmp($name, 'refresh') == 0) {
                if ($value == self::REFRESH_AUTO) {
                    $params['refresh'] = self::REFRESH_AUTO;
                } elseif ($value == self::REFRESH_MANUAL) {
                    $params['refresh'] = self::REFRESH_MANUAL;
                } else {
                    msg(sprintf($this->getLang('unexpected_value'), $value), -1);
                }
            } elseif (strcmp($name, 'version') == 0) {
                $params['version'] = trim($value);
                $params['refresh'] = self::REFRESH_MANUAL; // implies manual refresh
            } elseif (strcmp($name, 'template_file') == 0) {
                $params['template_file'] = trim($value);
                $params['template'] = self::TEMPLATE_XSLT; // implies XSL transformation
            } elseif (strcmp($name, 'expiration') == 0) {
                if (!is_numeric($value)) {
                    msg($this->getLang('expected_number'), -1);
                }
                $params['expiration'] = trim($value);
            } elseif (strcmp($name, 'template') == 0) {
                if ($value == self::TEMPLATE_DOKUWIKI) {
                    $params['template'] = self::TEMPLATE_DOKUWIKI;
                } elseif ($value == self::TEMPLATE_XSLT) {
                    $params['template'] = self::TEMPLATE_XSLT;
                } else {
                    msg(sprintf($this->getLang('unexpected_value'), $value), -1);
                }
            } else {
                $found = false;
                foreach ($params as $paramName => $default) {
                    if (strcmp($name, $paramName) == 0) {
                        $params[$name] = trim($value);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    msg(sprintf($this->getLang('unexpected_value'), $name), -1);
                }
            }
        }
        // check validity
        if (in_array($params['source'], [self::SOURCE_RESULT_CUMMULATIVE, self::SOURCE_RESULT_DETAIL, self::SOURCE_RESULT_SCHOOL_CUMMULATIVE])) {
            foreach (['contest', 'year', 'series'] as $paramName) {
                if (!isset($params['parameters'][$paramName])) {
                    msg(sprintf($this->getLang('missing_parameter'), $paramName), -1);
                }
            }
            if ($params['source'] == self::SOURCE_RESULT_CUMMULATIVE || $params['source'] == self::SOURCE_RESULT_SCHOOL_CUMMULATIVE) {
                $params['series'] = explode(' ', $params['series']);
            }
        }
        return $params;
    }

    /**
     * @param array $params
     * @param string|null $content
     * @param string $templateString
     * @return array|string|null
     */
    private function prepareContent(array $params, ?string $content, string $templateString) {
        if ($content === null) {
            return null;
        }

        $xml = new DomDocument();
        $xml->loadXML($content);

        if ($params['template'] == self::TEMPLATE_DOKUWIKI) {
            $xpath = new DOMXPath($xml);
            $needles = [];
            preg_match('#^\s*(<header>(.*)</header>)?(.+)(<footer>(.*)</footer>)?\s*$#s', $templateString, $matches);
            $rowTemplate = trim($matches[3]);

            $header = $matches[2];
            $footer = $matches[5];
            if (in_array($params['source'], [self::SOURCE_EXPORT, self::SOURCE_EXPORT1, self::SOURCE_EXPORT2])) {
                foreach ($xpath->query('//column-definitions/column-definition') as $iter) {
                    $name = $iter->getAttribute('name');
                    $needles[] = '@' . $name . '@';
                }
                $needles[] = '@iterator0@';
                $needles[] = '@iterator@';

                $source = $header . "\n";
                $iterator = 0;
                foreach ($xpath->query('//data/row') as $row) {
                    $replacements = [];
                    foreach ($row->childNodes as $child) {
                        if (isset($child->tagName)) { /* XML content may be interleaved with text nodes */
                            $replacements[] = $child->textContent;
                        }
                    }
                    $replacements[] = $iterator++;
                    $replacements[] = $iterator;

                    $source .= str_replace($needles, $replacements, $rowTemplate) . "\n";
                }
                $source .= $footer . "\n";

                return p_get_instructions($source);
            } else {
                $source = $header . "\n";
                $iterator = 0;
                /** @var DOMElement $row */
                foreach ($xpath->query('SOAP-ENV:Body')->item(0)->firstChild->childNodes as $row) {
                    if (!isset($row->tagName)) { /* XML content may be interleaved with text nodes */
                        continue;
                    }
                    $replacements = [];
                    $needles = [];
                    /** @var DOMElement $child */
                    foreach ($row->childNodes as $child) {
                        if (isset($child->tagName)) { /* XML content may be interleaved with text nodes */
                            $needles[] = '@' . $child->tagName . '@';
                            $replacements[] = $child->textContent;
                        }
                    }
                    $needles[] = '@iterator0@';
                    $needles[] = '@iterator@';
                    $replacements[] = $iterator++;
                    $replacements[] = $iterator;
                    $source .= str_replace($needles, $replacements, $rowTemplate) . "\n";
                }
                $source .= $footer . "\n";

                return p_get_instructions($source);
            }
        } elseif ($params['template'] == self::TEMPLATE_XSLT) {
            if ($params['template_file']) {
                $templateFile = wikiFN($params['template_file']);
                $templateString = io_readFile($templateFile);
            }

            if (!class_exists('XSLTProcessor')) {
                msg($this->getLang('xslt_missing'), -1);
                return null;
            }

            $xsltproc = new XSLTProcessor();
            $xsl = new DomDocument();
            $xsl->loadXML(trim($templateString));
            //$xsltproc->registerPHPFunctions(); // TODO verify need of this
            $xsltproc->importStylesheet($xsl);
            $result = $xsltproc->transformToXml($xml);

            if ($result === false) {
                foreach (libxml_get_errors() as $e) {
                    msg($e->message, -1);
                }
                $e = libxml_get_last_error();
                if ($e) {
                    msg($e->message, -1);
                }
                $result = null;
            }
            return $result;
        }
    }

    private function autoRefresh(array $params, Request $request): ?string {
        $expiration = $params['expiration'] !== null ? $params['expiration'] : $this->getConf('expiration');
        return $this->downloader->download($request, $expiration);
    }

    private function manualRefresh(array $params, Request $request): ?string {
        global $ID;
        $desiredVersion = $params['version'];
        $key = $this->getPluginName() . ' ' . serialize($params);
        $metadata = p_get_metadata($ID, $key);
        $downloadedVersion = $metadata['version'];

        if ($downloadedVersion === null || $desiredVersion > $downloadedVersion) {
            return $this->downloader->download($request, helper_plugin_fksdbexport::EXPIRATION_FRESH);
        } else {
            return $this->downloader->download($request, helper_plugin_fksdbexport::EXPIRATION_NEVER);
        }
    }

    private function createRequest(array $params): ?Request {
        $parameters = $params['parameters'];
        switch ($params['source']) {
            case self::SOURCE_EXPORT:
            case self::SOURCE_EXPORT1:
            case self::SOURCE_EXPORT2:
                $version = ($params['source'] === self::SOURCE_EXPORT) ? 1 : (int)substr($params['source'], strlen(self::SOURCE_EXPORT));
                return new ExportRequest((string)$params['qid'], (array)$params['parameters'], (int)$version);
            case self::SOURCE_RESULT_DETAIL:
                return new ResultsDetailRequest((string)$parameters['contest'], (int)$parameters['year'], (int)$parameters['series']);
            case self::SOURCE_RESULT_CUMMULATIVE:
                return new ResultsCumulativeRequest((string)$parameters['contest'], (int)$parameters['year'], explode(' ', $parameters['series']));
            case self::SOURCE_RESULT_SCHOOL_CUMMULATIVE:
                msg('fksdownloader: ' . 'School results is deprecated', -1);
                return null;
            case self::SOURCE_ORGANIZERS:
                return new OrganizersRequest($parameters['contest'] == 'fykos' ? 1 : 2, $parameters['year'] ?? null);
            case self::SOURCE_EVENT_LIST:
                return new EventListRequest(explode(',', $parameters['event_type_ids']));
            case self::SOURCE_EVENT_PARTICIPANTS:
                msg('fksdownloader: ' . 'Use eventDetail type', -1);
            case self::SOURCE_EVENT_DETAIL:
                return new EventRequest((int)$parameters['event_id']);
            default:
                msg(sprintf($this->getLang('unexpected_value'), $params['source']), -1);
                return null;
        }
    }
}
