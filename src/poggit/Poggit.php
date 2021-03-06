<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2017 Poggit
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace poggit;

use poggit\account\SessionUtils;
use poggit\errdoc\FoundPage;
use poggit\errdoc\NotFoundPage;
use poggit\module\AltModuleException;
use poggit\module\Module;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\MysqlUtils;
use poggit\utils\lang\GlobalVarStream;
use poggit\utils\lang\LangUtils;
use poggit\utils\Log;
use poggit\utils\OutputManager;
use RuntimeException;

final class Poggit {
    const POGGIT_VERSION = "1.0-beta";
    const GUEST = 0;
    const MEMBER = 1;
    const CONTRIBUTOR = 2;
    const MODERATOR = 3;
    const REVIEWER = 4;
    const ADM = 5;

    const DOMAIN_MAPS = [
        ".Internal" => ["poggit.pmmp.io"],
        "Google" => ["com.google.android.googlequicksearchbox", "google.com", "google.ac", "google.ad", "google.ae", "google.com.af", "google.com.ag", "google.com.ai", "google.al", "google.am", "google.co.ao", "google.com.ar", "google.as", "google.at", "google.com.au", "google.az", "google.ba", "google.com.bd", "google.be", "google.bf", "google.bg", "google.com.bh", "google.bi", "google.bj", "google.com.bn", "google.com.bo", "google.com.br", "google.bs", "google.bt", "google.co.bw", "google.by", "google.com.bz", "google.ca", "google.com.kh", "google.cc", "google.cd", "google.cf", "google.cat", "google.cg", "google.ch", "google.ci", "google.co.ck", "google.cl", "google.cm", "google.cn", "google.com.co", "google.co.cr", "google.com.cu", "google.cv", "google.cx", "google.com.cy", "google.cz", "google.de", "google.dj", "google.dk", "google.dm", "google.com.do", "google.dz", "google.com.ec", "google.ee", "google.com.eg", "google.es", "google.com.et", "google.eu", "google.fi", "google.com.fj", "google.fm", "google.fr", "google.ga", "google.ge", "google.gf", "google.gg", "google.com.gh", "google.com.gi", "google.gl", "google.gm", "google.gp", "google.gr", "google.com.gt", "google.gy", "google.com.hk", "google.hn", "google.hr", "google.ht", "google.hu", "google.co.id", "google.iq", "google.ie", "google.co.il", "google.im", "google.co.in", "google.io", "google.is", "google.it", "google.je", "google.com.jm", "google.jo", "google.co.jp", "google.co.ke", "google.ki", "google.kg", "google.co.kr", "google.com.kw", "google.kz", "google.la", "google.com.lb", "google.com.lc", "google.li", "google.lk", "google.co.ls", "google.lt", "google.lu", "google.lv", "google.com.ly", "google.co.ma", "google.md", "google.me", "google.mg", "google.mk", "google.ml", "google.com.mm", "google.mn", "google.ms", "google.com.mt", "google.mu", "google.mv", "google.mw", "google.com.mx", "google.com.my", "google.co.mz", "google.com.na", "google.ne", "google.nf", "google.com.ng", "google.com.ni", "google.nl", "google.no", "google.com.np", "google.nr", "google.nu", "google.co.nz", "google.com.om", "google.com.pk", "google.com.pa", "google.com.pe", "google.com.ph", "google.pl", "google.com.pg", "google.pn", "google.com.pr", "google.ps", "google.pt", "google.com.py", "google.com.qa", "google.ro", "google.rs", "google.ru", "google.rw", "google.com.sa", "google.com.sb", "google.sc", "google.se", "google.com.sg", "google.sh", "google.si", "google.sk", "google.com.sl", "google.sn", "google.sm", "google.so", "google.st", "google.sr", "google.com.sv", "google.td", "google.tg", "google.co.th", "google.com.tj", "google.tk", "google.tl", "google.tm", "google.to", "google.tn", "google.com.tr", "google.tt", "google.com.tw", "google.co.tz", "google.com.ua", "google.co.ug", "google.co.uk", "google.us", "google.com.uy", "google.co.uz", "google.com.vc", "google.co.ve", "google.vg", "google.co.vi", "google.com.vn", "google.vu", "google.ws", "google.co.za", "google.co.zm", "google.co.zw"],
        "Twitter" => ["t.co", "twitter.com"],
        "Facebook" => ["facebook.com"],
        "Yahoo" => ["search.yahoo.com", "search.yahoo.co.jp", "yahoo.cn"],
        "Bing" => ["bing.com"],
        "GitHub" => ["github.com"],
        "Freenode" => ["webchat.freenode.net", "botbot.me"],
        "YouTube" => ["youtube.com", "youtu.be"],
        "Naver" => ["naver.com"],
        "Slack" => ["com.slack", "slack.com"],
        "VK" => ["vk.com"],
        "Kik" => ["kik.com"],
        "GitLab" => ["gitlab.com"],
        "Leet" => ["leetforum.cc", "leet.cc"],
        "PMMP Forums" => ["forums.pmmp.io"],
        "PocketMine Forums" => ["forums.pocketmine.net"],
        "PMMP" => ["pmmp.io", "pmmp.readthedocs.io"],
        "ImagicalMine" => ["imgcl.co", "imagicalmine.gq"],
        "Minecraft Forum" => ["minecraftforum.net"],
    ];

    private static $ACCESS;
    public static $GIT_REF = "";
    public static $GIT_COMMIT;
    private static $log;
    private static $input;
    private static $requestId;
    private static $requestPath;
    private static $requestMethod;
    private static $moduleName;
    public static $onlineUsers;

    public static function init() {
        self::$ACCESS = json_decode(base64_decode("eyJzb2YzIjo1LCJhd3phdyI6NSwiZGt0YXBwcyI6NSwidGh1bmRlcjMzMzQ1Ijo0LCJqYWNrbm9vcmRodWlzIjo0LCJyb2Jza2UxMTAiOjQsInBlbWFwbW9kZGVyIjo0LCJ0aGVkZWlibyI6NH0="), true);

        if(file_exists(INSTALL_PATH . ".git/HEAD")) { //Found Git information!
            $ref = trim(file_get_contents(INSTALL_PATH . ".git/HEAD"));
            if(preg_match('/^[0-9a-f]{40}$/i', $ref)) {
                self::$GIT_COMMIT = strtolower($ref);
            } elseif(substr($ref, 0, 5) === "ref: ") {
                self::$GIT_REF = explode("/", $ref, 3)[2] ?? self::POGGIT_VERSION;
                $refFile = INSTALL_PATH . ".git/" . substr($ref, 5);
                if(is_file($refFile)) {
                    self::$GIT_COMMIT = strtolower(trim(file_get_contents($refFile)));
                }
            }
        }
        if(!isset(self::$GIT_COMMIT)) { //Unknown :(
            self::$GIT_COMMIT = str_repeat("00", 20);
        }

        if(isset($_SERVER["HTTP_CF_RAY"])) {
            Poggit::$requestId = substr(md5($_SERVER["HTTP_CF_RAY"]), 0, 4) . "-" . $_SERVER["HTTP_CF_RAY"];
        } else {
            Poggit::$requestId = bin2hex(random_bytes(8));
        }

        LangUtils::checkDeps();
        GlobalVarStream::register();
        Poggit::$log = new Log;
        Poggit::$input = file_get_contents("php://input");
    }

    public static function execute(string $path) {
        global $MODULES, $startEvalTime;

        include_once SOURCE_PATH . "modules.php";

        $referer = $_SERVER["HTTP_REFERER"] ?? "";
        if(!empty($referer)) {
            $host = strtolower(parse_url($referer, PHP_URL_HOST));
            if($host !== false and !LangUtils::startsWith($referer, Poggit::getSecret("meta.extPath"))) {
                // loop_maps
                foreach(self::DOMAIN_MAPS as $name => $knownDomains) {
                    foreach($knownDomains as $knownDomain) {
                        if($knownDomain === $host or LangUtils::endsWith($host, "." . $knownDomain)) {
                            $host = $name;
                            break 2; // loop_maps
                        }
                    }
                }
                MysqlUtils::query("INSERT INTO ext_refs (srcDomain) VALUES (?) ON DUPLICATE KEY UPDATE cnt = cnt + 1", "s", $host);
            }
        }
        Poggit::getLog()->i(sprintf("%s: %s %s", Poggit::getClientIP(), Poggit::$requestMethod = $_SERVER["REQUEST_METHOD"], Poggit::$requestPath = $path));

        $timings = [];
        $startEvalTime = microtime(true);

        $paths = array_filter(explode("/", $path, 2), "string_not_empty");
        if(count($paths) === 0) $paths[] = "home";
        if(count($paths) === 1) $paths[] = "";
        list($moduleName, $query) = $paths;

        Poggit::$moduleName = strtolower($moduleName);

        if(isset($MODULES[strtolower($moduleName)])) {
            $class = $MODULES[strtolower($moduleName)];
            $module = new $class($query);
        } else {
            $module = new NotFoundPage($path);
        }

        try {
            retry:
            Module::$currentPage = $module;
            $module->output();
        } catch(AltModuleException $ex) {
            $module = $ex->getAltModule();
            goto retry;
        }

        $endEvalTime = microtime(true);
        if(DO_TIMINGS) Poggit::getLog()->d("Timings: " . json_encode($timings));
        Poggit::getLog()->v("Safely completed: " . ((int) (($endEvalTime - $startEvalTime) * 1000)) . "ms");

        if(Poggit::isDebug()) Poggit::showStatus();
    }

    public static function getClientIP(): string {
        return $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"];
    }

    public static function hasLog(): bool {
        return isset(Poggit::$log);
    }

    public static function getLog(): Log {
        return Poggit::$log;
    }

    public static function getInput(): string {
        return Poggit::$input;
    }

    public static function getRequestId(): string {
        return Poggit::$requestId;
    }

    public static function getRequestPath(): string {
        return Poggit::$requestPath;
    }

    public static function getRequestMethod(): string {
        return Poggit::$requestMethod;
    }

    public static function getModuleName(): string {
        return Poggit::$moduleName;
    }

    public static function getTmpFile($ext = ".tmp"): string {
        $tmpDir = rtrim(Poggit::getSecret("meta.tmpPath", true) ?? sys_get_temp_dir(), "/") . "/";
        $file = tempnam($tmpDir, $ext);
//        do {
//            $file = $tmpDir . bin2hex(random_bytes(4)) . $ext;
//        } while(is_file($file));
//        register_shutdown_function("unlink", $file);
        return $file;
    }

    public static function showStatus() {
        global $startEvalTime;
        header("X-Poggit-Request-ID: " . Poggit::getRequestId());
        header("X-Status-Execution-Time: " . sprintf("%f", (microtime(true) - $startEvalTime)));
        header("X-Status-cURL-Queries: " . CurlUtils::$curlCounter);
        header("X-Status-cURL-HostNotResolved: " . CurlUtils::$curlRetries);
        header("X-Status-cURL-Time: " . sprintf("%f", CurlUtils::$curlTime));
        header("X-Status-cURL-Size: " . CurlUtils::$curlBody);
        header("X-Status-MySQL-Queries: " . CurlUtils::$mysqlCounter);
        header("X-Status-MySQL-Time: " . sprintf("%f", CurlUtils::$mysqlTime));
        if(isset(CurlUtils::$ghRateRemain)) header("X-GitHub-RateLimit-Remaining: " . CurlUtils::$ghRateRemain);
    }

    public static function addTimings($event) {
        global $timings, $startEvalTime;
        $timings[] = [$event, microtime(true) - $startEvalTime];
    }

    public static function getSecret(string $name, bool $supressMissing = false) {
        global $secretsCache;
        if(!isset($secretsCache)) $secretsCache = json_decode($path = file_get_contents(SECRET_PATH . "secrets.json"), true);
        $secrets = $secretsCache;
        if(isset($secrets[$name])) return $secrets[$name];
        $parts = array_filter(explode(".", $name), "string_not_empty");
        foreach($parts as $part) {
            if(!is_array($secrets) or !isset($secrets[$part])) {
                if($supressMissing) return null; else throw new RuntimeException("Unknown secret $part");
            }
            $secrets = $secrets[$part];
        }
        if(count($parts) > 1) $secretsCache[$name] = $secrets;
        return $secrets;
    }

    /**
     * Returns the internally absolute path to Poggit site.
     *
     * Example return value: <code>/poggit/</code>
     *
     * @return string
     */
    public static function getRootPath(): string {
        // by splitting into two trim calls, only one slash will be returned for empty meta.intPath value
        return rtrim("/" . ltrim(Poggit::getSecret("meta.intPath"), "/"), "/") . "/";
    }

    public static function getCurlTimeout(): int {
        return Poggit::getSecret("meta.curl.timeout");
    }

    public static function getCurlPerPage(): int {
        return Poggit::getSecret("meta.curl.perPage");
    }

    public static function isDebug(): bool {
        return Poggit::getSecret("meta.debug");
    }

    public static function getUserAccess(string $user = null): int {
        return Poggit::$ACCESS[strtolower($user ?? SessionUtils::getInstance()->getName())] ?? 0;
    }

    /**
     * Redirect user to a path under the Poggit root, without a leading slash
     *
     * @param string $target   default homepage
     * @param bool   $absolute default false
     */
    public static function redirect(string $target = "", bool $absolute = false) {

        header("Location: " . ($target = ($absolute ? "" : Poggit::getRootPath()) . $target));
        http_response_code(302);
        if(Poggit::isDebug()) Poggit::showStatus();
        OutputManager::terminateAll();
        (new FoundPage($target))->output();
        die;
    }

    private function __construct() {
    }
}
