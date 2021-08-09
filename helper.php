<?php

/**
 * DokuWiki Plugin fksdownloader (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */

use dokuwiki\Extension\Plugin;
use Fykosak\FKSDBDownloaderCore\FKSDBDownloader;
use Fykosak\FKSDBDownloaderCore\Requests\Request;

if (!defined('DOKU_INC')) {
    die();
}

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

class helper_plugin_fksdbexport extends Plugin
{

    public const EXPIRATION_FRESH = 0;
    public const EXPIRATION_NEVER = 0x7fffffff;

    private FKSDBDownloader $downloader;

    /**
     * @return FKSDBDownloader
     * @throws SoapFault
     */
    private function getSoap(): FKSDBDownloader
    {
        if (!isset($this->downloader)) {
            $this->downloader = new FKSDBDownloader(
                $this->getConf('wsdl'),
                $this->getConf('fksdb_login'),
                $this->getConf('fksdb_password')
            );
        }
        return $this->downloader;
    }

    public function download(Request $request, int $expiration): ?string
    {
        $cached = $this->getFromCache($request->getCacheKey(), $expiration);

        if (!$cached) {
            try {
                $content = $this->getSoap()->download($request);
            } catch (Throwable$exception) {
                msg($exception->getMessage());
                return null;
            }

            if ($content) {
                $this->putToCache($request->getCacheKey(), $content);
            }
            return $content;
        } else {
            return $cached;
        }
    }

    private function getFromCache(string $filename, int $expiration): ?string
    {
        $realFilename = $this->getCacheFilename($filename);
        if (file_exists($realFilename) && filemtime($realFilename) + $expiration >= time()) {
            return io_readFile($realFilename);
        } else {
            return null;
        }
    }

    private function putToCache(string $filename, string $content): void
    {
        $realFilename = $this->getCacheFilename($filename);
        io_saveFile($realFilename, $content);
    }

    public function getCacheFilename(string $filename): string
    {
        $id = $this->getPluginName() . ':' . $filename;
        return metaFN($id, '.xml');
    }
}
