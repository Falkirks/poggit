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

namespace poggit\module;

use poggit\account\SessionUtils;
use poggit\Poggit;
use poggit\utils\lang\LangUtils;
use const poggit\JS_DIR;
use const poggit\RES_DIR;

class ResModule extends Module {
    static $TYPES = [
        "html" => "text/html",
        "css" => "text/css",
        "js" => "application/javascript",
        "json" => "application/json",
        "png" => "image/png",
        "ico" => "image/x-icon",
        "map" => "text/css",
        "phar" => "application/octet-stream",
        "sh" => "text/x-shellscript",
        "php" => "text/x-php",
    ];
    static $BANNED = [
        "banned"
    ];

    public function getName(): string {
        return "res";
    }

    public function getAllNames(): array {
        return ["res", "js"];
    }

    public function output() {
        $query = $this->getQuery();

        if(!Poggit::isDebug() || LangUtils::endsWith($query, ".min")) header("Cache-Control: private, max-age=86400");

        $resDir = Poggit::getModuleName() === "js" ? JS_DIR : RES_DIR;

        if(LangUtils::startsWith($query, "revalidate-")) $query = substr($query, strlen("revalidate-"));
        if(isset(self::$BANNED[$query])) $this->errorAccessDenied();

        $path = realpath($resDir . $query);
        if(realpath(dirname($path)) === realpath($resDir) and is_file($path)) {
            $ext = strtolower(array_slice(explode(".", $path), -1)[0]);
            header("Content-Type: " . self::$TYPES[$ext]);
            $cont = file_get_contents($path);
            $cont = preg_replace_callback('@\$\{([a-zA-Z0-9_\.\-:\(\)]+)\}@', function ($match) {
                return $this->translateVar($match[1]);
            }, $cont);
            echo $cont;
        } else {
            $this->errorNotFound();
        }
    }

    protected function translateVar(string $key) {
        if($key === "path.relativeRoot") return Poggit::getRootPath();
        if($key === "app.clientId") return Poggit::getSecret("app.clientId");
        if($key === "session.antiForge") return SessionUtils::getInstance(false)->getAntiForge();
        if($key === "session.isLoggedIn") return SessionUtils::getInstance(false)->isLoggedIn() ? "true" : "false";
        if($key === "session.loginName") return SessionUtils::getInstance(false)->getName();
        if($key === "session.adminLevel") return Poggit::getUserAccess(SessionUtils::getInstance(false)->getName());
        if($key === "meta.isDebug") return Poggit::isDebug() ? "true" : "false";
        return '${' . $key . '}';
    }
}
