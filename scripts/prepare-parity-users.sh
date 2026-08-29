#!/usr/bin/env bash
set -euo pipefail

: "${WP00C_TEST_PASSWORD:?WP00C_TEST_PASSWORD is required}"

# Dedicated, same-profile users keep CI3 and CI4 login-history mutations isolated.
# No credential is printed; the synthetic rows are safe to reuse between runs.
hash=$(WP00C_TEST_PASSWORD="$WP00C_TEST_PASSWORD" php -r 'echo password_hash(getenv("WP00C_TEST_PASSWORD"), PASSWORD_BCRYPT);')
quoted_hash=${hash//\'/\'\'}

docker compose exec -T db sh -lc 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD"' <<SQL
USE samsonitetracking;
INSERT INTO tbl_users
    (userId,email,username,password,name,mobile,group_id,roleId,branch_id,branch_type_id,isDeleted,createdBy,createdDtm,updatedBy,updatedDtm)
SELECT 9011,'wp00c-parity-ci3@example.invalid','wp00c-parity-ci3','${quoted_hash}',name,mobile,group_id,roleId,branch_id,branch_type_id,0,createdBy,'2024-01-01 00:00:00',NULL,NULL
FROM tbl_users WHERE userId=9001
ON DUPLICATE KEY UPDATE password=VALUES(password),name=VALUES(name),group_id=VALUES(group_id),roleId=VALUES(roleId),branch_id=VALUES(branch_id),isDeleted=0;

INSERT INTO tbl_last_login (userId,sessionData,machineIp,userAgent,agentString,platform,createdDtm)
SELECT 9011,sessionData,'127.0.0.1','Browser','Parity fixture','Unknown','2024-01-01 00:00:00'
FROM tbl_last_login WHERE userId=9001 ORDER BY id DESC LIMIT 1;
UPDATE tbl_last_login SET createdDtm='2024-01-01 00:00:00' WHERE userId=9011;

USE samsonite_ci4;
INSERT INTO ci4_users
    (id,email,username,display_name,password_hash,role_id,branch_id,group_id,role_text,is_active,session_version,created_at,updated_at)
SELECT 9012,'wp00c-parity-ci4@example.invalid','wp00c-parity-ci4',display_name,'${quoted_hash}',role_id,branch_id,group_id,role_text,1,session_version,'2024-01-01 00:00:00','2024-01-01 00:00:00'
FROM ci4_users WHERE id=9001
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),display_name=VALUES(display_name),role_id=VALUES(role_id),branch_id=VALUES(branch_id),group_id=VALUES(group_id),role_text=VALUES(role_text),is_active=1;

INSERT INTO tbl_last_login (userId,sessionData,machineIp,userAgent,agentString,platform,createdDtm)
SELECT 9012,sessionData,'127.0.0.1','Browser','Parity fixture','Unknown','2024-01-01 00:00:00'
FROM tbl_last_login WHERE userId=9001 ORDER BY id DESC LIMIT 1;
UPDATE tbl_last_login SET createdDtm='2024-01-01 00:00:00' WHERE userId=9012;
UPDATE ci4_rate_limit_buckets SET request_count=0;
SQL

echo 'parity_users=ready'
