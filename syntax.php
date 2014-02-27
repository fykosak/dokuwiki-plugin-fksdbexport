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

class syntax_plugin_fksdbexport extends DokuWiki_Syntax_Plugin {

    const REFRESH_AUTO = 'auto';
    const REFRESH_MANUAL = 'manual';
    const TEMPLATE_DOKUWIKI = 'dokuwiki';
    const TEMPLATE_XSLT = 'xslt';

    /**
     * @return string Syntax mode type
     */
    public function getType() {
        return 'substitution';
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
        return 400; //TODO experiment
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        echo __FUNCTION__;
        die('sdf');
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
        echo __FUNCTION__;
        $match = substr($match, 13, -14);              // strip markup (including space after "<fksdbexport ")
        list($parameterString, $templateString) = preg_split('/>/u', $match, 2);

        $params = $this->parseParameters($parameterString);

        // (If there are no choices inside the <doodle> tag, then doodle's data will be reset.)
        $choices = $this->parseChoices($templateString);

        $result = array('params' => $params, 'choices' => $choices);
        //debout('handle returns', $result);
        return $result;
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
        if ($mode != 'xhtml')
            return false;

        return true;
    }

    /**
     * @note Modified Doodle2 plugin.
     * 
     * @param type $parameterString
     */
    private function parseParameters($parameterString) {
        //----- default parameter settings
        $params = array(
            'name' => null,
            'parameters' => array(),
            'refresh' => self::REFRESH_AUTO,
            'version' => null,
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
            if (strcmp($name, "name") == 0) {
                $params['name'] = trim($value);
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

}

// vim:ts=4:sw=4:et:
