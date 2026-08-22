#!/usr/bin/env python3
"""Capture CI3 route, authentication, and authorization baseline over HTTP."""

from __future__ import annotations

import argparse
import fcntl
import hashlib
import http.cookiejar
import json
import os
import pathlib
import re
import secrets
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from typing import Any


DEFAULT_CI3_ROOT = pathlib.Path("/Users/king_developer/Desktop/Project/samsoniteci3")
DEFAULT_BASE_URL = "http://127.0.0.1:18404/"
DB_CONTAINER = "samsonitetracking-ci4-migration-db-1"
WEB_CONTAINER = "samsonitetracking-ci4-migration-web-1"
LOCK_PATH = pathlib.Path("/private/tmp/wp00c-route-auth.lock")
EXPECTED_PIN = "ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6"
USER_AGENT = "WP00C-ROUTE-AUTH-RUNNER/1"
ERROR_MARKERS = (
    "A Database Error Occurred",
    "Error Number:",
    "Fatal error",
    "Uncaught Exception",
    "A PHP Error was encountered",
    "Whoops\\",
)

PROTECTED_CONTROLLERS = {
    "background_web",
    "book",
    "branch",
    "branchtype",
    "brand",
    "condition",
    "estimateprice",
    "fixed",
    "menu",
    "order",
    "producttype",
    "provider",
    "statustype",
    "upload_excel",
    "user",
}

KNOWN_ROUTE_DEFECTS = {"bookListing/(:num)"}

# GET on these handlers either writes data, destroys a session, or consumes an upload.
# Keep them in the route inventory; execute them in their own mutation test slices.
SIDE_EFFECT_METHODS = {
    "addcontact",
    "addcontact_th",
    "addrating",
    "addnewbranch",
    "addnewbranchtype",
    "addnewbook",
    "addnewbrand",
    "addnewcondition",
    "addnewestimateprice",
    "addnewfixed",
    "addneworders",
    "addnewproducttype",
    "addnewprovider",
    "addnewstatustype",
    "addnewuser",
    "addbackground",
    "addmenu",
    "changepassword",
    "createpassworduser",
    "deletebackground",
    "deletebranch",
    "deletebranchtype",
    "deletebook",
    "deletebrand",
    "deletecondition",
    "deleteestimateprice",
    "deletefixed",
    "deleteorders",
    "deleteproducttype",
    "deleteprovider",
    "deletestatustype",
    "deleteuser",
    "do_upload_multi",
    "editbackground",
    "editbranch",
    "editbranchtype",
    "editbook",
    "editbrand",
    "editcondition",
    "editestimateprice",
    "editfixed",
    "editmenu",
    "editorders",
    "editproducttype",
    "editprovider",
    "editstatustype",
    "edituser",
    "excelconfirm",
    "exceldataadd",
    "excelneworderconfirm",
    "excelneworderdataadd",
    "excelpriceconfirm",
    "excelpricedataadd",
    "loginme",
    "logout",
    "resetpassworduser",
    "sendorder_deliver",
    "sendorderupdate",
    "sendorderupdatestatus",
}


@dataclass
class Response:
    status: int
    url: str
    body: str
    error: str | None = None


class Client:
    def __init__(self, base_url: str):
        self.base_url = base_url if base_url.endswith("/") else base_url + "/"
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.jar)
        )

    def request(self, method: str, path: str, data: dict[str, str] | None = None) -> Response:
        url = urllib.parse.urljoin(self.base_url, path.lstrip("/"))
        encoded = urllib.parse.urlencode(data or {}).encode() if data is not None else None
        request = urllib.request.Request(
            url,
            data=encoded,
            headers={"User-Agent": USER_AGENT},
            method=method,
        )
        try:
            with self.opener.open(request, timeout=30) as response:
                return Response(
                    status=response.status,
                    url=response.geturl(),
                    body=response.read().decode("utf-8", "replace"),
                )
        except urllib.error.HTTPError as exc:
            return Response(
                status=exc.code,
                url=exc.geturl(),
                body=exc.read().decode("utf-8", "replace"),
            )
        except urllib.error.URLError as exc:
            return Response(status=0, url=url, body="", error=str(exc.reason))

    def get(self, path: str) -> Response:
        return self.request("GET", path)

    def post(self, path: str, data: dict[str, str]) -> Response:
        return self.request("POST", path, data)

    def session_id(self) -> str | None:
        for cookie in self.jar:
            if cookie.name == "ci_session":
                return cookie.value
        return None


def run(command: list[str], input_text: str | None = None) -> str:
    result = subprocess.run(
        command,
        input=input_text,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if result.returncode != 0:
        detail = result.stderr.strip()
        raise RuntimeError(f"command failed ({result.returncode}): {detail}")
    return result.stdout.strip()


def db(sql: str) -> str:
    return run(
        [
            "docker",
            "exec",
            "-i",
            DB_CONTAINER,
            "sh",
            "-lc",
            'exec mariadb --batch --raw --skip-column-names -u"$MARIADB_USER" '
            '-p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"',
        ],
        input_text=sql,
    )


def db_quote(value: str) -> str:
    return "0x" + value.encode().hex()


def validate_base_url(value: str) -> str:
    parsed = urllib.parse.urlsplit(value)
    if parsed.scheme not in {"http", "https"} or parsed.hostname not in {
        "127.0.0.1",
        "localhost",
        "::1",
    }:
        raise SystemExit("FAIL --base-url must target localhost/loopback")
    return value if value.endswith("/") else value + "/"


def acquire_lock() -> Any:
    lock_fd = os.open(LOCK_PATH, os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
    lock_file = os.fdopen(lock_fd, "r+")
    try:
        fcntl.flock(lock_file, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError as exc:
        lock_file.close()
        raise RuntimeError("another route/auth runner holds the local lock") from exc
    return lock_file


def table_checksums() -> dict[str, str]:
    tables = db("SHOW TABLES;").splitlines()
    if len(tables) != 31 or any(re.fullmatch(r"[A-Za-z0-9_]+", table) is None for table in tables):
        raise AssertionError(f"unexpected table inventory: {len(tables)}")
    result: dict[str, str] = {}
    for table in tables:
        result[table] = db(f"CHECKSUM TABLE `{table}`;").split("\t")[-1]
    return result


def scalar(sql: str) -> str:
    return db(sql).strip()


def user_snapshot() -> dict[int, dict[str, Any]]:
    rows = db(
        "SELECT userId,roleId,isDeleted,password FROM tbl_users "
        "WHERE userId BETWEEN 9001 AND 9004 ORDER BY userId;"
    ).splitlines()
    result: dict[int, dict[str, Any]] = {}
    for row in rows:
        user_id, role_id, deleted, password = row.split("\t")
        result[int(user_id)] = {
            "role_id": int(role_id),
            "is_deleted": int(deleted),
            "password": password,
        }
    if set(result) != {9001, 9002, 9003, 9004}:
        raise AssertionError("synthetic users missing")
    return result


def php_password_hash(password: str) -> str:
    return run(
        [
            "docker",
            "exec",
            "-i",
            WEB_CONTAINER,
            "php",
            "-r",
            "echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);",
        ],
        input_text=password,
    )


def set_test_password(password_hash: str) -> None:
    db(
        "UPDATE tbl_users SET password="
        + db_quote(password_hash)
        + " WHERE userId BETWEEN 9001 AND 9004;"
    )


def restore_users(original: dict[int, dict[str, Any]], login_max_id: int) -> None:
    statements = []
    for user_id, row in original.items():
        statements.append(
            "UPDATE tbl_users SET password={password},roleId={role},isDeleted={deleted} "
            "WHERE userId={user_id}".format(
                password=db_quote(row["password"]),
                role=row["role_id"],
                deleted=row["is_deleted"],
                user_id=user_id,
            )
        )
    statements.append(
        "DELETE FROM tbl_last_login "
        f"WHERE id>{login_max_id} AND userId BETWEEN 9001 AND 9004 "
        f"AND agentString={db_quote(USER_AGENT)}"
    )
    db(";".join(statements) + ";")


def extract_routes(source_root: pathlib.Path) -> list[dict[str, Any]]:
    route_file = source_root / "application/config/routes.php"
    pattern = re.compile(
        r"^\s*\$route\[['\"]([^'\"]+)['\"]\]\s*=\s*(['\"])(.*?)\2\s*;"
    )
    routes = []
    for line, source in enumerate(route_file.read_text(encoding="utf-8").splitlines(), 1):
        match = pattern.match(source)
        if match:
            routes.append(
                {"route": match.group(1), "target": match.group(3), "line": line}
            )
    return routes


def validate_source(source_root: pathlib.Path) -> list[dict[str, Any]]:
    if not source_root.is_absolute() or not source_root.is_dir():
        raise SystemExit("FAIL --source-root must be an existing absolute directory")
    pin = run(["git", "-C", str(source_root), "rev-parse", "HEAD"])
    dirty = run(["git", "-C", str(source_root), "status", "--porcelain"])
    if pin != EXPECTED_PIN or dirty:
        raise SystemExit(f"FAIL CI3 source identity mismatch: pin={pin} dirty={bool(dirty)}")
    routes = extract_routes(source_root)
    if len(routes) != 178:
        raise SystemExit(f"FAIL explicit CI3 route count is {len(routes)}, expected 178")
    return routes


def controller_methods(source_root: pathlib.Path) -> list[dict[str, Any]]:
    result = []
    pattern = re.compile(
        r"^\s*(?:(public|protected|private)\s+)?function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\("
    )
    for path in sorted((source_root / "application/controllers").glob("*.php")):
        controller = path.stem.lower()
        for line, source in enumerate(path.read_text(encoding="utf-8").splitlines(), 1):
            match = pattern.match(source)
            if match and match.group(2) != "__construct" and match.group(1) != "private":
                result.append(
                    {
                        "controller": controller,
                        "method": match.group(2),
                        "line": line,
                    }
                )
    return result


def target_pair(target: str) -> tuple[str, str]:
    parts = target.split("/")
    return parts[0].lower(), parts[1].lower() if len(parts) > 1 else "index"


def method_name(target: str) -> str:
    return target.split("/")[1].lower() if "/" in target else "index"


def concrete_path(route: str) -> str:
    values = iter(("SYNTHETIC-TOKEN-01", "wp00c-admin%40example.invalid"))
    parts = []
    for segment in route.split("/"):
        if segment == "(:num)":
            parts.append("1")
        elif segment in {"(:any)", "(:segment)"}:
            parts.append(next(values, "SYNTHETIC-TOKEN-01"))
        else:
            parts.append(segment)
    return "/".join(parts)


def side_effect(target: str) -> bool:
    return method_name(target) in SIDE_EFFECT_METHODS


def route_class(response: Response) -> str:
    body_lower = response.body.lower()
    if response.error:
        return "CLIENT_ERROR"
    if any(marker.lower() in body_lower for marker in ERROR_MARKERS):
        return "LEGACY_ERROR_PAGE"
    if response.status >= 500:
        return "HTTP_5XX"
    if urllib.parse.urlsplit(response.url).path.rstrip("/").lower() == "/login":
        return "LOGIN_REDIRECT"
    if 200 <= response.status < 300:
        return "HTTP_2XX"
    if 300 <= response.status < 400:
        return "HTTP_REDIRECT"
    return f"HTTP_{response.status}"


def response_record(route: str, target: str, path: str, response: Response) -> dict[str, Any]:
    errors = [marker for marker in ERROR_MARKERS if marker.lower() in response.body.lower()]
    return {
        "route": route,
        "target": target,
        "path": path,
        "status": response.status,
        "final_path": urllib.parse.urlsplit(response.url).path,
        "class": route_class(response),
        "body_bytes": len(response.body.encode("utf-8")),
        "body_sha256": hashlib.sha256(response.body.encode("utf-8")).hexdigest(),
        "errors": errors,
    }


def session_keys(client: Client) -> list[str]:
    session_id = client.session_id()
    if session_id is None or re.fullmatch(r"[A-Za-z0-9]+", session_id) is None:
        raise AssertionError("login did not establish a valid CI3 session cookie")
    raw = run(
        [
            "docker",
            "exec",
            WEB_CONTAINER,
            "cat",
            f"/var/www/html/application/sess/ci_session{session_id}",
        ]
    )
    decoded = run(
        [
            "docker",
            "exec",
            "-i",
            WEB_CONTAINER,
            "php",
            "-d",
            "session.save_path=/tmp",
            "-r",
            "$raw=stream_get_contents(STDIN); session_start(); "
            "session_decode($raw); echo json_encode(array_keys($_SESSION));",
        ],
        input_text=raw,
    )
    keys = json.loads(decoded)
    if not isinstance(keys, list):
        raise AssertionError("CI3 session decode did not return key list")
    return sorted(str(key) for key in keys)


def login(base_url: str, username: str, password: str) -> tuple[Client, Response]:
    client = Client(base_url)
    response = client.post("loginMe", {"username": username, "password": password})
    return client, response


def body_has(response: Response, marker: str) -> bool:
    return marker.lower() in response.body.lower()


def marker_record(client: Client, path: str, markers: list[str]) -> dict[str, Any]:
    response = client.get(path)
    return {
        "path": path,
        "status": response.status,
        "final_path": urllib.parse.urlsplit(response.url).path,
        "class": route_class(response),
        "body_sha256": hashlib.sha256(response.body.encode("utf-8")).hexdigest(),
        "markers": {marker: body_has(response, marker) for marker in markers},
    }


def check_session_matrix(base_url: str, password: str, results: dict[str, Any]) -> dict[str, Client]:
    clients: dict[str, Client] = {}
    login_history_before = int(
        scalar(
            "SELECT COUNT(*) FROM tbl_last_login "
            f"WHERE agentString={db_quote(USER_AGENT)}"
        )
        or "0"
    )
    admin, valid = login(base_url, "wp00c-admin", password)
    clients["role_1"] = admin
    required = {
        "userId",
        "role",
        "GroupID",
        "BranchID",
        "roleText",
        "name",
        "lastLogin",
        "isLoggedIn",
    }
    keys = session_keys(admin)
    login_history_after = int(
        scalar(
            "SELECT COUNT(*) FROM tbl_last_login "
            f"WHERE agentString={db_quote(USER_AGENT)}"
        )
        or "0"
    )
    admin_login = {
        "status": valid.status,
        "final_path": urllib.parse.urlsplit(valid.url).path,
        "class": route_class(valid),
        "session_keys": keys,
        "required_session_keys": sorted(required),
        "login_history_delta": login_history_after - login_history_before,
        "session_contract_pass": valid.status == 200
        and urllib.parse.urlsplit(valid.url).path.rstrip("/") == "/dashboard"
        and required.issubset(keys),
        "login_history_pass": login_history_after - login_history_before == 1,
    }
    admin_login["session_contract_pass"] = (
        admin_login["session_contract_pass"] and admin_login["login_history_pass"]
    )
    results["auth_login_001"] = admin_login

    invalid_cases = [
        ("wrong_password", "wp00c-admin", "wrong-password"),
        ("unknown_user", "wp00c-unknown", password),
        ("deleted_user", "wp00c-deleted", password),
    ]
    invalid_results = []
    for name, username, attempt in invalid_cases:
        before_invalid = int(
            scalar(
                "SELECT COUNT(*) FROM tbl_last_login "
                f"WHERE agentString={db_quote(USER_AGENT)}"
            )
            or "0"
        )
        client, response = login(base_url, username, attempt)
        dashboard = client.get("dashboard")
        after_invalid = int(
            scalar(
                "SELECT COUNT(*) FROM tbl_last_login "
                f"WHERE agentString={db_quote(USER_AGENT)}"
            )
            or "0"
        )
        invalid_results.append(
            {
                "case": name,
                "login_class": route_class(response),
                "login_final_path": urllib.parse.urlsplit(response.url).path,
                "dashboard_class": route_class(dashboard),
                "dashboard_final_path": urllib.parse.urlsplit(dashboard.url).path,
                "login_history_delta": after_invalid - before_invalid,
                "denied": route_class(dashboard) == "LOGIN_REDIRECT"
                and after_invalid == before_invalid,
            }
        )
    results["auth_login_002"] = invalid_results

    anonymous = Client(base_url)
    anonymous_dashboard = anonymous.get("dashboard")
    active_dashboard = admin.get("dashboard")
    expired = Client(base_url)
    cookie_domain = urllib.parse.urlsplit(base_url).hostname or "127.0.0.1"
    expired.jar.set_cookie(
        http.cookiejar.Cookie(
            version=0,
            name="ci_session",
            value="expiredsynthetic",
            port=None,
            port_specified=False,
            domain=cookie_domain,
            domain_specified=False,
            domain_initial_dot=False,
            path="/",
            path_specified=True,
            secure=False,
            expires=None,
            discard=True,
            comment=None,
            comment_url=None,
            rest={"HttpOnly": None},
            rfc2109=False,
        )
    )
    expired_dashboard = expired.get("dashboard")
    logout_client, _ = login(base_url, "wp00c-admin", password)
    logout_response = logout_client.get("logout")
    post_logout = logout_client.get("dashboard")
    results["auth_session_001"] = {
        "anonymous_dashboard": response_record("dashboard", "user", "dashboard", anonymous_dashboard),
        "active_dashboard": response_record("dashboard", "user", "dashboard", active_dashboard),
        "expired_dashboard": response_record("dashboard", "user", "dashboard", expired_dashboard),
        "logout": response_record("logout", "user/logout", "logout", logout_response),
        "post_logout_dashboard": response_record("dashboard", "user", "dashboard", post_logout),
    }

    branch_a, _ = login(base_url, "wp00c-a", password)
    branch_b, _ = login(base_url, "wp00c-b", password)
    db("UPDATE tbl_users SET isDeleted=0 WHERE userId=9004;")
    role_3, _ = login(base_url, "wp00c-deleted", password)
    clients["role_2_branch_1"] = branch_a
    clients["role_2_branch_2"] = branch_b
    clients["role_3_branch_1"] = role_3
    return clients


def route_inventory(
    base_url: str,
    routes: list[dict[str, Any]],
    implicit: list[dict[str, Any]],
    clients: dict[str, Client],
    results: dict[str, Any],
) -> None:
    explicit_records = []
    for route in routes:
        if route["route"] in {"default_controller", "404_override"}:
            explicit_records.append(
                {
                    "route": route["route"],
                    "target": route["target"],
                    "line": route["line"],
                    "probe": "RESERVED_RULE",
                    "reason": "reserved CI3 router rule; HTTP probe recorded in dedicated case",
                }
            )
            continue
        path = concrete_path(route["route"])
        if side_effect(route["target"]):
            explicit_records.append(
                {
                    "route": route["route"],
                    "target": route["target"],
                    "line": route["line"],
                    "path": path,
                    "probe": "SKIPPED_SIDE_EFFECT",
                    "reason": "mutation/transport/session action requires dedicated case",
                }
            )
            continue
        response = clients["role_1"].get(path)
        record = response_record(route["route"], route["target"], path, response)
        record["line"] = route["line"]
        record["probe"] = "GET"
        explicit_records.append(record)

    implicit_records = []
    for item in implicit:
        target = f"{item['controller']}/{item['method']}"
        path = f"{item['controller']}/{item['method']}"
        if item["side_effect"]:
            implicit_records.append(
                {
                    **item,
                    "path": path,
                    "probe": "SKIPPED_SIDE_EFFECT",
                    "reason": "mutation/transport/session action requires dedicated case",
                }
            )
            continue
        response = clients["role_1"].get(path)
        implicit_records.append(
            {
                **item,
                "path": path,
                "probe": "GET",
                **response_record(path, target, path, response),
            }
        )

    results["route_explicit_001"] = {
        "route_count": len(routes),
        "probed_get": sum(record.get("probe") == "GET" for record in explicit_records),
        "skipped_side_effect": sum(
            record.get("probe") == "SKIPPED_SIDE_EFFECT" for record in explicit_records
        ),
        "records": explicit_records,
    }
    results["route_implicit_001"] = {
        "candidate_count": len(implicit),
        "probed_get": sum(record.get("probe") == "GET" for record in implicit_records),
        "skipped_side_effect": sum(
            record.get("probe") == "SKIPPED_SIDE_EFFECT" for record in implicit_records
        ),
        "records": implicit_records,
    }

    authz_records = []
    anonymous = Client(base_url)
    for route in routes:
        controller, _ = target_pair(route["target"])
        if controller not in PROTECTED_CONTROLLERS or side_effect(route["target"]):
            continue
        path = concrete_path(route["route"])
        anonymous_response = anonymous.get(path)
        role_responses = {
            role: clients[role].get(path)
            for role in ("role_1", "role_2_branch_1", "role_2_branch_2", "role_3_branch_1")
        }
        authz_records.append(
            {
                "route": route["route"],
                "target": route["target"],
                "line": route["line"],
                "path": path,
                "anonymous": response_record(route["route"], route["target"], path, anonymous_response),
                "roles": {
                    role: response_record(route["route"], route["target"], path, response)
                    for role, response in role_responses.items()
                },
            }
        )
    results["authz_route_001"] = {
        "protected_read_only_route_count": len(authz_records),
        "records": authz_records,
    }


def branch_isolation(clients: dict[str, Client], results: dict[str, Any]) -> None:
    results["authz_branch_001"] = {
        "branch_a_listing": marker_record(
            clients["role_2_branch_1"],
            "userListing",
            ["wp00c-a", "wp00c-b", "SYNTHETIC OPERATOR A", "SYNTHETIC OPERATOR B"],
        ),
        "branch_b_listing": marker_record(
            clients["role_2_branch_2"],
            "userListing",
            ["wp00c-a", "wp00c-b", "SYNTHETIC OPERATOR A", "SYNTHETIC OPERATOR B"],
        ),
        "branch_a_cross_user_read": marker_record(
            clients["role_2_branch_1"],
            "editOld/9003",
            ["wp00c-b@example.invalid", "SYNTHETIC OPERATOR B"],
        ),
        "branch_b_cross_user_read": marker_record(
            clients["role_2_branch_2"],
            "editOld/9002",
            ["wp00c-a@example.invalid", "SYNTHETIC OPERATOR A"],
        ),
        "branch_a_order_listing": marker_record(
            clients["role_2_branch_1"],
            "ordersListing",
            [f"WP00C-TRACK-{number:03d}" for number in range(1, 10)],
        ),
        "branch_b_order_listing": marker_record(
            clients["role_2_branch_2"],
            "ordersListing",
            [f"WP00C-TRACK-{number:03d}" for number in range(1, 10)],
        ),
        "branch_a_cross_order_read": marker_record(
            clients["role_2_branch_1"],
            "editOrdersOld/91007",
            ["WP00C-TRACK-007", "SYNTHETIC CUSTOMER SEVEN"],
        ),
        "branch_b_cross_order_read": marker_record(
            clients["role_2_branch_2"],
            "editOrdersOld/91001",
            ["WP00C-TRACK-001", "SYNTHETIC CUSTOMER ONE"],
        ),
        "branch_a_cross_history_read": marker_record(
            clients["role_2_branch_1"],
            "login-history/9003",
            ["wp00c-b", "SYNTHETIC OPERATOR B"],
        ),
    }


def known_route_defects(admin: Client, results: dict[str, Any]) -> None:
    paths = [
        "rackstatus/1",
        "bookListing/2",
        "ReportTrackingListing/0/1",
        "reportsummary/0/1",
    ]
    results["route_defect_001"] = {
        "records": [
            {
                "path": path,
                **response_record(path, "legacy-route", path, admin.get(path)),
            }
            for path in paths
        ]
    }


def special_routes(base_url: str, admin: Client, results: dict[str, Any]) -> None:
    anonymous = Client(base_url)
    missing = "wp00c-missing-route-404"
    results["route_404_001"] = {
        "anonymous": response_record(missing, "error", missing, anonymous.get(missing)),
        "authenticated": response_record(missing, "error", missing, admin.get(missing)),
    }
    results["route_default"] = {
        "anonymous": response_record("/", "track/index", "/", anonymous.get("/")),
        "authenticated": response_record("/", "track/index", "/", admin.get("/")),
    }


def build_implicit(source_root: pathlib.Path, routes: list[dict[str, Any]]) -> list[dict[str, Any]]:
    explicit = {target_pair(route["target"]) for route in routes}
    explicit.update({("track", "index"), ("error", "index")})
    result = []
    for method in controller_methods(source_root):
        pair = (method["controller"], method["method"].lower())
        if pair in explicit:
            continue
        target = f"{method['controller']}/{method['method']}"
        result.append({**method, "side_effect": side_effect(target)})
    return result


def flatten_failures(results: dict[str, Any]) -> list[str]:
    failures = []

    def visit(value: Any, path: str = "") -> None:
        if isinstance(value, dict):
            if value.get("class") == "CLIENT_ERROR":
                failures.append(path or "response")
            for key, child in value.items():
                visit(child, f"{path}.{key}" if path else key)
        elif isinstance(value, list):
            for index, child in enumerate(value):
                visit(child, f"{path}[{index}]")

    visit(results)
    return failures


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source-root", type=pathlib.Path, default=DEFAULT_CI3_ROOT)
    parser.add_argument("--base-url", default=DEFAULT_BASE_URL)
    parser.add_argument("--output", type=pathlib.Path)
    args = parser.parse_args()

    lock_file = acquire_lock()
    base_url = validate_base_url(args.base_url)
    routes = validate_source(args.source_root.resolve())
    implicit = build_implicit(args.source_root.resolve(), routes)
    original_users = user_snapshot()
    login_max_id = int(scalar("SELECT COALESCE(MAX(id),0) FROM tbl_last_login;") or "0")
    initial_checksums = table_checksums()
    password = secrets.token_urlsafe(18)[:24]
    password_hash = php_password_hash(password)
    results: dict[str, Any] = {}
    cleanup_error: str | None = None
    final_checksums: dict[str, str] | None = None

    try:
        set_test_password(password_hash)
        clients = check_session_matrix(base_url, password, results)
        before_read_only = table_checksums()
        route_inventory(base_url, routes, implicit, clients, results)
        branch_isolation(clients, results)
        known_route_defects(clients["role_1"], results)
        special_routes(base_url, clients["role_1"], results)
        after_read_only = table_checksums()
        results["read_only_db_immutable"] = before_read_only == after_read_only
        results["read_only_changed_tables"] = sorted(
            table for table in before_read_only if before_read_only[table] != after_read_only[table]
        )
    finally:
        try:
            restore_users(original_users, login_max_id)
            final_checksums = table_checksums()
        except Exception as exc:
            cleanup_error = str(exc)

    if cleanup_error:
        raise SystemExit(f"FAIL cleanup: {cleanup_error}")
    if final_checksums != initial_checksums:
        changed = sorted(
            table for table in initial_checksums if initial_checksums[table] != final_checksums[table]
        )
        raise SystemExit(f"FAIL cleanup changed tables: {changed}")

    client_failures = flatten_failures(results)
    auth_contract_pass = bool(results["auth_login_001"]["session_contract_pass"])
    invalid_denials_pass = all(case["denied"] for case in results["auth_login_002"])
    authz_records = results["authz_route_001"]["records"]
    authz_failures = []
    for record in authz_records:
        if record["route"] in KNOWN_ROUTE_DEFECTS:
            continue
        if record["anonymous"]["class"] != "LOGIN_REDIRECT":
            authz_failures.append(f"anonymous:{record['route']}")
        for role, response in record["roles"].items():
            if response["class"] == "LOGIN_REDIRECT":
                authz_failures.append(f"{role}:{record['route']}")

    read_only_db_pass = bool(results.get("read_only_db_immutable", False))
    baseline_failures = list(client_failures) + list(authz_failures)
    if not auth_contract_pass:
        baseline_failures.append("AUTH-LOGIN-001")
    if not invalid_denials_pass:
        baseline_failures.append("AUTH-LOGIN-002")
    if not read_only_db_pass:
        baseline_failures.append("read_only_db_immutable")
    output: dict[str, Any] = {
        "verdict": "BASELINE_CAPTURED" if not baseline_failures else "BASELINE_FAILED",
        "closure": "OPEN_DOWNSTREAM_CI4_ABSENCE_PARITY",
        "ci3_pin": EXPECTED_PIN,
        "base_url": base_url,
        "image": "samsonitetracking-ci3:ee1c95e",
        "fixture": "WP00C synthetic users/orders only",
        "cases": {
            "ROUTE-EXPLICIT-001": "CAPTURED_WITH_SIDE_EFFECT_ROUTES_OPEN",
            "ROUTE-IMPLICIT-001": "CAPTURED_WITH_SIDE_EFFECT_ROUTES_OPEN",
            "ROUTE-404-001": "CAPTURED",
            "ROUTE-DEFECT-001": "CAPTURED",
            "AUTH-LOGIN-001": "PASS" if auth_contract_pass else "FAIL",
            "AUTH-LOGIN-002": "PASS" if invalid_denials_pass else "FAIL",
            "AUTH-SESSION-001": "CAPTURED",
            "AUTHZ-ROUTE-001": "PASS" if not authz_failures else "FAIL",
            "AUTHZ-BRANCH-001": "CAPTURED_KNOWN_SCOPE_AND_IDOR_BEHAVIOR",
        },
        "route_count": len(routes),
        "implicit_candidate_count": len(implicit),
        "read_only_db_immutable": results.get("read_only_db_immutable", False),
        "read_only_changed_tables": results.get("read_only_changed_tables", []),
        "client_failures": client_failures,
        "authorization_failures": authz_failures,
        "baseline_failures": baseline_failures,
        "results": results,
    }
    encoded = json.dumps(output, ensure_ascii=False, indent=2, sort_keys=True) + "\n"
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(encoded, encoding="utf-8")
    print(encoded, end="")
    return 0 if output["verdict"] == "BASELINE_CAPTURED" else 1


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(f"FAIL {exc}", file=sys.stderr)
        raise SystemExit(1)
