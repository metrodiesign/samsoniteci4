#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

WORKSPACE='/Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation'
METADATA_FILE="$WORKSPACE/task-7-browser-manual-runtime.env"
RESOURCE_PREFIX_BASE='samsonite-task7-browser-manual-'
SHARED_PROJECT_PREFIX='samsonitetracking-ci4-migration-'

if (( $# != 0 )); then
    printf 'Usage: %s\n' "$0" >&2
    exit 64
fi

listing_has_prefix() {
    local prefix="$1"
    local listing="$2"
    local name

    while IFS= read -r name; do
        [[ "$name" == "$prefix"* ]] && return 0
    done <<< "$listing"
    return 1
}

assert_shared_project_running() {
    local container
    local listing

    listing="$(docker ps --format '{{.Names}}')"
    for container in \
        "${SHARED_PROJECT_PREFIX}ci4-1" \
        "${SHARED_PROJECT_PREFIX}web-1" \
        "${SHARED_PROJECT_PREFIX}db-1"; do
        if ! grep -Fxq "$container" <<< "$listing"; then
            printf 'ERROR: shared container is not running: %s\n' "$container" >&2
            return 1
        fi
    done
}

assert_no_prefixed_resources() {
    local prefix="$1"
    local found=0
    local listing

    listing="$(docker ps -a --format '{{.Names}}')"
    if listing_has_prefix "$prefix" "$listing"; then
        printf 'ERROR: prefixed container remains: %s\n' "$prefix" >&2
        found=1
    fi
    listing="$(docker network ls --format '{{.Name}}')"
    if listing_has_prefix "$prefix" "$listing"; then
        printf 'ERROR: prefixed network remains: %s\n' "$prefix" >&2
        found=1
    fi
    listing="$(docker volume ls --format '{{.Name}}')"
    if listing_has_prefix "$prefix" "$listing"; then
        printf 'ERROR: prefixed volume remains: %s\n' "$prefix" >&2
        found=1
    fi
    listing="$(docker image ls --format '{{.Repository}}:{{.Tag}}')"
    if listing_has_prefix "$prefix" "$listing"; then
        printf 'ERROR: prefixed image remains: %s\n' "$prefix" >&2
        found=1
    fi

    (( found == 0 ))
}

if [[ ! -e "$METADATA_FILE" ]]; then
    assert_no_prefixed_resources "$RESOURCE_PREFIX_BASE"
    assert_shared_project_running
    printf 'CLEAN: no Task 7 manual runtime metadata or Docker resources remain.\n'
    exit 0
fi
if [[ ! -f "$METADATA_FILE" || -L "$METADATA_FILE" ]]; then
    printf 'ERROR: runtime metadata is not a regular file.\n' >&2
    exit 1
fi

STATE=''
OWNER_PID=''
RESOURCE_PREFIX=''
CANDIDATE_TREE=''
APP_URL=''
LOGIN_URL=''
CENTRAL_USERNAME=''
BRANCH_USERNAME=''
ORDER_FIXTURE_ID=''
ORDER_FIXTURE_IMAGE=''
APP_CONTAINER=''
DB_CONTAINER=''
MIGRATE_CONTAINER=''
NETWORK=''
VOLUME=''
APP_IMAGE=''

while IFS='=' read -r key value; do
    case "$key" in
        state) STATE="$value" ;;
        owner_pid) OWNER_PID="$value" ;;
        resource_prefix) RESOURCE_PREFIX="$value" ;;
        candidate_tree) CANDIDATE_TREE="$value" ;;
        app_url) APP_URL="$value" ;;
        login_url) LOGIN_URL="$value" ;;
        central_username) CENTRAL_USERNAME="$value" ;;
        branch_username) BRANCH_USERNAME="$value" ;;
        order_fixture_id) ORDER_FIXTURE_ID="$value" ;;
        order_fixture_image) ORDER_FIXTURE_IMAGE="$value" ;;
        app_container) APP_CONTAINER="$value" ;;
        db_container) DB_CONTAINER="$value" ;;
        migrate_container) MIGRATE_CONTAINER="$value" ;;
        network) NETWORK="$value" ;;
        volume) VOLUME="$value" ;;
        app_image) APP_IMAGE="$value" ;;
        '') ;;
        *)
            printf 'ERROR: unexpected runtime metadata key: %s\n' "$key" >&2
            exit 1
            ;;
    esac
done < "$METADATA_FILE"

if [[ ! "$RESOURCE_PREFIX" =~ ^samsonite-task7-browser-manual-[0-9]{8}t[0-9]{6}z-[0-9]+$ ]]; then
    printf 'ERROR: unsafe resource prefix in runtime metadata.\n' >&2
    exit 1
fi
[[ "$STATE" == starting || "$STATE" == ready ]]
[[ "$OWNER_PID" =~ ^[0-9]+$ ]]
[[ "$APP_CONTAINER" == "${RESOURCE_PREFIX}-app" ]]
[[ "$DB_CONTAINER" == "${RESOURCE_PREFIX}-db" ]]
[[ "$MIGRATE_CONTAINER" == "${RESOURCE_PREFIX}-migrate" ]]
[[ "$NETWORK" == "${RESOURCE_PREFIX}-network" ]]
[[ "$VOLUME" == "${RESOURCE_PREFIX}-db-data" ]]
[[ "$APP_IMAGE" == "${RESOURCE_PREFIX}-app:latest" ]]
if [[ "$STATE" == starting ]] && kill -0 "$OWNER_PID" 2>/dev/null; then
    printf 'ERROR: startup owner %s is still running; cleanup refused.\n' "$OWNER_PID" >&2
    exit 1
fi

metadata_still_owned() {
    [[ -f "$METADATA_FILE" && ! -L "$METADATA_FILE" ]] \
        && grep -Fxq "resource_prefix=$RESOURCE_PREFIX" "$METADATA_FILE" \
        && grep -Fxq "owner_pid=$OWNER_PID" "$METADATA_FILE"
}

for container in "$APP_CONTAINER" "$MIGRATE_CONTAINER" "${RESOURCE_PREFIX}-hash" "${RESOURCE_PREFIX}-key" "$DB_CONTAINER"; do
    if docker container inspect "$container" >/dev/null 2>&1; then
        docker rm -f "$container" >/dev/null
    fi
done
if docker network inspect "$NETWORK" >/dev/null 2>&1; then
    docker network rm "$NETWORK" >/dev/null
fi
if docker volume inspect "$VOLUME" >/dev/null 2>&1; then
    docker volume rm "$VOLUME" >/dev/null
fi
if docker image inspect "$APP_IMAGE" >/dev/null 2>&1; then
    docker image rm "$APP_IMAGE" >/dev/null
fi

assert_no_prefixed_resources "$RESOURCE_PREFIX"
assert_shared_project_running
if ! metadata_still_owned; then
    printf 'ERROR: runtime metadata ownership changed during cleanup.\n' >&2
    exit 1
fi
rm -f -- "$METADATA_FILE"

printf 'CLEAN: removed only %s resources; shared project remains running.\n' "$RESOURCE_PREFIX"
