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

namespace poggit\utils\internet;

use Gajus\Dindent\Exception\InvalidArgumentException;
use poggit\Poggit;
use poggit\utils\lang\LangUtils;
use poggit\utils\lang\TemporalHeaderlessWriter;
use RuntimeException;
use stdClass;

final class CurlUtils {
    const GH_API_PREFIX = "https://api.github.com/";
    const TEMP_PERM_CACHE_KEY_PREFIX = "poggit.CurlUtils.testPermission.cache.";

    public static $curlBody = 0;
    public static $curlRetries = 0;
    public static $curlTime = 0;
    public static $curlCounter = 0;
    public static $lastCurlHeaders;
    public static $mysqlTime = 0;
    public static $mysqlCounter = 0;
    public static $lastCurlResponseCode;
    public static $ghRateRemain;

    private static $tempPermCache;

    public static function curl(string $url, string $postContents, string $method, string ...$extraHeaders) {
        return CurlUtils::iCurl($url, function ($ch) use ($method, $postContents) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if(strlen($postContents) > 0) curl_setopt($ch, CURLOPT_POSTFIELDS, $postContents);
        }, ...$extraHeaders);
    }

    public static function curlPost(string $url, $postFields, string ...$extraHeaders) {
        return CurlUtils::iCurl($url, function ($ch) use ($postFields) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        }, ...$extraHeaders);
    }

    public static function curlGet(string $url, string ...$extraHeaders) {
        return CurlUtils::iCurl($url, function () {
        }, ...$extraHeaders);
    }

    public static function curlGetMaxSize(string $url, int $maxBytes, string ...$extraHeaders) {
        return CurlUtils::iCurl($url, function ($ch) use ($maxBytes) {
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 128);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            /** @noinspection PhpUnusedParameterInspection */
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($ch, $dlSize, $dlAlready, $ulSize, $ulAlready) use ($maxBytes) {
                echo $dlSize, PHP_EOL;
                return $dlSize > $maxBytes ? 1 : 0;
            });
        }, ...$extraHeaders);
    }

    public static function curlToFile(string $url, string $file, int $maxBytes, string ...$extraHeaders) {
        $writer = new TemporalHeaderlessWriter($file);

        CurlUtils::iCurl($url, function ($ch) use ($maxBytes, $writer) {
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 1024);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            /** @noinspection PhpUnusedParameterInspection */
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($ch, $dlSize, $dlAlready, $ulSize, $ulAlready) use ($maxBytes) {
                return $dlSize > $maxBytes ? 1 : 0;
            });
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, [$writer, "write"]);
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, [$writer, "header"]);
        }, ...$extraHeaders);
        self::$lastCurlHeaders = $writer->close();

        if(filesize($file) > $maxBytes) {
            file_put_contents($file, "");
            @unlink($file);
            throw new RuntimeException("File too large");
        }
    }

    public static function iCurl(string $url, callable $configure, string ...$extraHeaders) {
        self::$curlCounter++;
        $headers = array_merge(["User-Agent: Poggit/" . Poggit::POGGIT_VERSION], $extraHeaders);
        retry:
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, Poggit::getCurlTimeout());
        curl_setopt($ch, CURLOPT_TIMEOUT, Poggit::getCurlTimeout());
        $configure($ch);
        $startTime = microtime(true);
        $ret = curl_exec($ch);
        $endTime = microtime(true);
        self::$curlTime += $tookTime = $endTime - $startTime;
        if(curl_error($ch) !== "") {
            $error = curl_error($ch);
            curl_close($ch);
            if(LangUtils::startsWith($error, "Could not resolve host: ")) {
                self::$curlRetries++;
                Poggit::getLog()->w("Could not resolve host " . parse_url($url, PHP_URL_HOST) . ", retrying");
                if(self::$curlRetries > 5) throw new CurlErrorException("More than 5 curl host resolve failures in a request");
                self::$curlCounter++;
                goto retry;
            }
            if(LangUtils::startsWith($error, "Operation timed out after ") or LangUtils::startsWith($error, "Resolving timed out after ")) {
                self::$curlRetries++;
                Poggit::getLog()->w("CURL request timeout for $url");
                if(self::$curlRetries > 5) throw new CurlTimeoutException("More than 5 curl timeouts in a request");
                self::$curlCounter++;
                goto retry;
            }
            throw new CurlErrorException($error);
        }
        self::$lastCurlResponseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerLength = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        if(is_string($ret)) {
            self::$lastCurlHeaders = substr($ret, 0, $headerLength);
            $ret = substr($ret, $headerLength);
        }
        self::$curlBody += strlen($ret);
        Poggit::getLog()->v("cURL access to $url, took $tookTime, response code " . self::$lastCurlResponseCode);
        return $ret;
    }

    public static function ghApiCustom(string $url, string $customMethod, $postFields, string $token = "", bool $nonJson = false, array $moreHeaders = ["Accept: application/vnd.github.v3+json"]) {
        $moreHeaders[] = "Authorization: bearer " . ($token === "" ? Poggit::getSecret("app.defaultToken") : $token);
        $data = CurlUtils::curl("https://api.github.com/" . $url, json_encode($postFields), $customMethod, ...$moreHeaders);
        return CurlUtils::processGhApiResult($data, $url, $token, $nonJson);
    }

    public static function ghApiPost(string $url, $postFields, string $token = "", bool $nonJson = false, array $moreHeaders = ["Accept: application/vnd.github.v3+json"]) {
        $moreHeaders[] = "Authorization: bearer " . ($token === "" ? Poggit::getSecret("app.defaultToken") : $token);
        $data = CurlUtils::curlPost("https://api.github.com/" . $url, $encodedPost = json_encode($postFields, JSON_UNESCAPED_SLASHES), ...$moreHeaders);
        return CurlUtils::processGhApiResult($data, $url, $token, $nonJson);
    }

    /**
     * @param string $url
     * @param string $token
     * @param array  $moreHeaders
     * @return array|stdClass|string
     */
    public static function ghApiGet(string $url, string $token, array $moreHeaders = ["Accept: application/vnd.github.v3+json"]) {
        $moreHeaders[] = "Authorization: bearer " . ($token === "" ? Poggit::getSecret("app.defaultToken") : $token);
        $curl = CurlUtils::curlGet(self::GH_API_PREFIX . $url, ...$moreHeaders);
        return CurlUtils::processGhApiResult($curl, $url, $token);
    }

    public static function processGhApiResult($curl, string $url, string $token, bool $nonJson = false) {
        if(is_string($curl)) {
            $recvHeaders = CurlUtils::parseGhApiHeaders();
            if($nonJson) {
                return $curl;
            }
            $data = json_decode($curl);
            if(is_object($data)) {
                if(self::$lastCurlResponseCode < 400) return $data;
                throw new GitHubAPIException($url, $data);
            }
            if(is_array($data)) {
                if(isset($recvHeaders["Link"]) and preg_match('%<(https://[^>]+)>; rel="next"%', $recvHeaders["Link"], $match)) {
                    $link = $match[1];
                    assert(LangUtils::startsWith($link, self::GH_API_PREFIX));
                    $link = substr($link, strlen(self::GH_API_PREFIX));
                    $data = array_merge($data, CurlUtils::ghApiGet($link, $token));
                }
                return $data;
            }
            throw new RuntimeException("Malformed data from GitHub API: " . json_encode($data));
        }
        throw new RuntimeException("Failed to access data from GitHub API: $url, ", substr($token, 0, 7), ", ", json_encode($curl));
    }

    public static function parseGhApiHeaders() {
        $headers = [];
        foreach(array_filter(explode("\n", self::$lastCurlHeaders), "string_not_empty") as $header) {
            $kv = explode(": ", $header);
            if(count($kv) !== 2) continue;
            $headers[$kv[0]] = $kv[1];
        }
        if(isset($headers["X-RateLimit-Remaining"])) {
            self::$ghRateRemain = $headers["X-RateLimit-Remaining"];
        }
        return $headers;
    }

    public static function testPermission(int $repoId, string $token, string $user, string $permName): bool {
        $user = strtolower($user);
        if($permName !== "admin" && $permName !== "push" && $permName !== "pull") throw new InvalidArgumentException;


        $internalKey = "$user@$repoId";
        $apcuKey = self::TEMP_PERM_CACHE_KEY_PREFIX . $internalKey;
        if(isset(self::$tempPermCache[$internalKey])) {
            return self::$tempPermCache[$internalKey]->{$permName};
        }

        if(apcu_exists($apcuKey)) {
            self::$tempPermCache[$internalKey] = apcu_fetch($apcuKey);
            return self::$tempPermCache[$internalKey]->{$permName};
        }

        try {
            $repository = CurlUtils::ghApiGet("repositories/$repoId", $token);
            $value = $repository->permissions ?? ["admin" => false, "push" => false, "pull" => true];
        } catch(GitHubAPIException$e) {
            $value = ["admin" => false, "push" => false, "pull" => false];
        }

        self::$tempPermCache[$internalKey] = $value;
        apcu_store($apcuKey, $value, 86400);
        return $value->{$permName};
    }
}
