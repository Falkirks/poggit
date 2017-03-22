ALTER TABLE projects ADD FOREIGN KEY (repoId) REFERENCES repos(repoId) ON DELETE CASCADE;
ALTER TABLE builds MODIFY projectId INT UNSIGNED;
ALTER TABLE builds ADD FOREIGN KEY (projectId) REFERENCES projects(projectId) ON DELETE CASCADE;
DELETE FROM builds_statuses WHERE buildId NOT IN (SELECT buildId FROM builds);
ALTER TABLE builds_statuses ADD FOREIGN KEY (buildId) REFERENCES builds(buildId) ON DELETE CASCADE;
ALTER TABLE class_occurrences MODIFY buildId BIGINT UNSIGNED;
ALTER TABLE class_occurrences ADD FOREIGN KEY (buildId) REFERENCES builds(buildId) ON DELETE CASCADE;
ALTER TABLE releases MODIFY buildId BIGINT UNSIGNED;
ALTER TABLE releases ADD FOREIGN KEY (projectId) REFERENCES projects(projectId) ON DELETE CASCADE;
ALTER TABLE releases ADD FOREIGN KEY (buildId) REFERENCES builds(buildId);
DELETE FROM release_categories WHERE projectId NOT IN (SELECT projectId FROM projects);
ALTER TABLE release_categories ADD FOREIGN KEY (projectId) REFERENCES projects(projectId) ON DELETE CASCADE;
DELETE FROM release_keywords WHERE projectId NOT IN (SELECT projectId FROM projects);
ALTER TABLE release_keywords ADD FOREIGN KEY (projectId) REFERENCES projects(projectId) ON DELETE CASCADE;
DELETE FROM release_spoons WHERE releaseId NOT IN (SELECT releaseId FROM releases);
ALTER TABLE release_spoons ADD FOREIGN KEY (releaseId) REFERENCES releases(releaseId) ON DELETE CASCADE;
DELETE FROM release_deps WHERE releaseId NOT IN (SELECT releaseId FROM releases);
ALTER TABLE release_deps ADD FOREIGN KEY (releaseId) REFERENCES releases(releaseId) ON DELETE CASCADE;
DELETE FROM release_reqr WHERE releaseId NOT IN (SELECT releaseId FROM releases);
ALTER TABLE release_reqr ADD FOREIGN KEY (releaseId) REFERENCES releases(releaseId) ON DELETE CASCADE;
DELETE FROM release_perms WHERE releaseId NOT IN (SELECT releaseId FROM releases);
ALTER TABLE release_perms ADD FOREIGN KEY (releaseId) REFERENCES releases(releaseId) ON DELETE CASCADE;
DELETE FROM release_reviews WHERE releaseId NOT IN (SELECT releaseId FROM releases);
ALTER TABLE release_reviews ADD FOREIGN KEY (releaseId) REFERENCES releases(releaseId) ON DELETE CASCADE;
DELETE FROM rsr_dl_ips WHERE resourceId NOT IN (SELECT resourceId FROM resources);
ALTER TABLE rsr_dl_ips ADD FOREIGN KEY (resourceId) REFERENCES resources(resourceId) ON DELETE CASCADE;