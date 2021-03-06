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

namespace poggit\release\details\review;

use poggit\account\SessionUtils;
use poggit\module\AjaxModule;
use poggit\Poggit;
use poggit\release\PluginRelease;
use poggit\utils\Config;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\MysqlUtils;

class ReviewAdminAjax extends AjaxModule {
    protected function impl() {
        // read post fields
        $action = $this->param("action");
        $relId = (int) $this->param("relId");

        $session = SessionUtils::getInstance();
        $user = $session->getName();
        $userLevel = Poggit::getUserAccess($user);
        $userUid = $session->getUid();
        $repoIdRows = MysqlUtils::query("SELECT repoId FROM releases
                INNER JOIN projects ON releases.projectId = projects.projectId
                WHERE releaseId = ? LIMIT 1",
            "i", $relId);
        if(!isset($repoIdRows[0])) $this->errorBadRequest("Nonexistent releaseId");
        $repoId = (int) $repoIdRows[0]["repoId"];

        switch($action) {
            case "add":
                $score = (int) $this->param("score");
                if(!(0 <= $score && $score <= 5)) $this->errorBadRequest("0 <= score <= 5");
                $message = $this->param("message");
                if(strlen($message) > Config::MAX_REVIEW_LENGTH && $userLevel < Poggit::MODERATOR) $this->errorBadRequest("Message too long");
                if(CurlUtils::testPermission($repoId, $session->getAccessToken(), $session->getName(), "push")) $this->errorBadRequest("You can't review your own release");
                MysqlUtils::query("INSERT INTO release_reviews (releaseId, user, criteria, type, cat, score, message) VALUES (?, ? ,? ,? ,? ,? ,?)",
                    "iiiiiis", $relId, $userUid, $_POST["criteria"] ?? PluginRelease::DEFAULT_CRITERIA, (int) $this->param("type"),
                    (int) $this->param("category"), $score, $message); // TODO support GFM
                break;
            case "delete" :
                $author = $this->param("author");
                $authorUid = ReviewUtils::getUIDFromName($author) ?? "";
                if(($userLevel >= Poggit::MODERATOR) || ($userUid == $authorUid)) { // Moderators up
                    MysqlUtils::query("DELETE FROM release_reviews WHERE (releaseId = ? AND user = ? AND criteria = ?)",
                        "iii", $relId, $authorUid, $_POST["criteria"] ?? PluginRelease::DEFAULT_CRITERIA);
                }
                break;
        }
    }

    public function getName(): string {
        return "review.admin";
    }

    protected function needLogin(): bool {
        return true;
    }
}
