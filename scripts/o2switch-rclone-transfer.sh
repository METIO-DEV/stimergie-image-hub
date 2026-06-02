#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${TRANSFER_ENV:-${SCRIPT_DIR}/o2switch-transfer.env}"
OVERRIDE_MODE="${MODE:-}"
OVERRIDE_BATCH_SIZE="${BATCH_SIZE:-}"
OVERRIDE_BATCH_OFFSET="${BATCH_OFFSET:-}"
OVERRIDE_BATCH_FILE="${BATCH_FILE:-}"
OVERRIDE_LIMIT_PATH="${LIMIT_PATH:-}"
OVERRIDE_MAX_TRANSFER_GB="${MAX_TRANSFER_GB:-}"
OVERRIDE_DEST_PREFIX="${DEST_PREFIX:-}"
OVERRIDE_VERIFY_AFTER_COPY="${VERIFY_AFTER_COPY:-}"
OVERRIDE_ALLOW_SYNC_DELETE="${ALLOW_SYNC_DELETE:-}"
OVERRIDE_LOG_FILE="${LOG_FILE:-}"

if [[ -f "${ENV_FILE}" ]]; then
  # shellcheck disable=SC1090
  source "${ENV_FILE}"
fi

apply_override() {
  local name="$1"
  local value="$2"

  if [[ -n "${value}" ]]; then
    printf -v "${name}" '%s' "${value}"
  fi
}

apply_override MODE "${OVERRIDE_MODE}"
apply_override BATCH_SIZE "${OVERRIDE_BATCH_SIZE}"
apply_override BATCH_OFFSET "${OVERRIDE_BATCH_OFFSET}"
apply_override BATCH_FILE "${OVERRIDE_BATCH_FILE}"
apply_override LIMIT_PATH "${OVERRIDE_LIMIT_PATH}"
apply_override MAX_TRANSFER_GB "${OVERRIDE_MAX_TRANSFER_GB}"
apply_override DEST_PREFIX "${OVERRIDE_DEST_PREFIX}"
apply_override VERIFY_AFTER_COPY "${OVERRIDE_VERIFY_AFTER_COPY}"
apply_override ALLOW_SYNC_DELETE "${OVERRIDE_ALLOW_SYNC_DELETE}"
apply_override LOG_FILE "${OVERRIDE_LOG_FILE}"

RCLONE_BIN="${RCLONE_BIN:-rclone}"
RCLONE_REMOTE="${RCLONE_REMOTE:-scaleway}"
SOURCE_TYPE="${SOURCE_TYPE:-ftp}"
SOURCE_REMOTE="${SOURCE_REMOTE:-o2switch}"
SOURCE_DIR="${SOURCE_DIR:-/collabspace.veni6445.odns.fr/photos}"
DEST_PREFIX="${DEST_PREFIX:-photos}"
MODE="${MODE:-dry-run}"
TRANSFERS="${TRANSFERS:-6}"
CHECKERS="${CHECKERS:-12}"
S3_ACL="${S3_ACL:-public-read}"
SCALEWAY_ENDPOINT="${SCALEWAY_ENDPOINT:-https://s3.fr-par.scw.cloud}"
SCALEWAY_REGION="${SCALEWAY_REGION:-fr-par}"
LOG_FILE="${LOG_FILE:-${SCRIPT_DIR}/rclone-transfer.log}"
BATCH_SIZE="${BATCH_SIZE:-10}"
BATCH_OFFSET="${BATCH_OFFSET:-0}"
BATCH_FILE="${BATCH_FILE:-}"
VERIFY_AFTER_COPY="${VERIFY_AFTER_COPY:-true}"
ALLOW_SYNC_DELETE="${ALLOW_SYNC_DELETE:-false}"

required_vars=(
  "SCALEWAY_ACCESS_KEY_ID"
  "SCALEWAY_SECRET_KEY"
  "SCALEWAY_BUCKET"
)

if [[ "${SOURCE_TYPE}" == "ftp" ]]; then
  required_vars+=(
    "FTP_HOST"
    "FTP_USER"
  )

  if [[ -z "${FTP_PASSWORD:-}" && -z "${FTP_PASSWORD_OBSCURED:-}" ]]; then
    echo "Missing required variable: FTP_PASSWORD or FTP_PASSWORD_OBSCURED" >&2
    echo "Create ${ENV_FILE} from scripts/o2switch-transfer.env.example and fill it." >&2
    exit 1
  fi
fi

for var_name in "${required_vars[@]}"; do
  if [[ -z "${!var_name:-}" ]]; then
    echo "Missing required variable: ${var_name}" >&2
    echo "Create ${ENV_FILE} from scripts/o2switch-transfer.env.example and fill it." >&2
    exit 1
  fi
done

if [[ ! -x "${RCLONE_BIN}" ]]; then
  echo "rclone not found or not executable: ${RCLONE_BIN}" >&2
  exit 1
fi

remote_env_name="$(printf '%s' "${RCLONE_REMOTE}" | tr '[:lower:]-' '[:upper:]_')"
export "RCLONE_CONFIG_${remote_env_name}_TYPE=s3"
export "RCLONE_CONFIG_${remote_env_name}_PROVIDER=Other"
export "RCLONE_CONFIG_${remote_env_name}_ACCESS_KEY_ID=${SCALEWAY_ACCESS_KEY_ID}"
export "RCLONE_CONFIG_${remote_env_name}_SECRET_ACCESS_KEY=${SCALEWAY_SECRET_KEY}"
export "RCLONE_CONFIG_${remote_env_name}_ENDPOINT=${SCALEWAY_ENDPOINT}"
export "RCLONE_CONFIG_${remote_env_name}_REGION=${SCALEWAY_REGION}"

if [[ "${SOURCE_TYPE}" == "ftp" ]]; then
  ftp_remote_env_name="$(printf '%s' "${SOURCE_REMOTE}" | tr '[:lower:]-' '[:upper:]_')"
  ftp_password_obscured="${FTP_PASSWORD_OBSCURED:-$("${RCLONE_BIN}" obscure "${FTP_PASSWORD}")}"

  export "RCLONE_CONFIG_${ftp_remote_env_name}_TYPE=ftp"
  export "RCLONE_CONFIG_${ftp_remote_env_name}_HOST=${FTP_HOST}"
  export "RCLONE_CONFIG_${ftp_remote_env_name}_USER=${FTP_USER}"
  export "RCLONE_CONFIG_${ftp_remote_env_name}_PASS=${ftp_password_obscured}"
  export "RCLONE_CONFIG_${ftp_remote_env_name}_PORT=${FTP_PORT:-21}"
  export "RCLONE_CONFIG_${ftp_remote_env_name}_EXPLICIT_TLS=${FTP_EXPLICIT_TLS:-true}"
  export "RCLONE_CONFIG_${ftp_remote_env_name}_NO_CHECK_CERTIFICATE=${FTP_NO_CHECK_CERTIFICATE:-true}"

  source_path="${SOURCE_REMOTE}:${SOURCE_DIR#/}"
else
  source_path="${SOURCE_DIR}"
fi

dest_path="${RCLONE_REMOTE}:${SCALEWAY_BUCKET}/${DEST_PREFIX}"

if [[ -n "${LIMIT_PATH:-}" ]]; then
  clean_limit_path="${LIMIT_PATH#/}"
  if [[ "${SOURCE_TYPE}" == "ftp" ]]; then
    source_path="${SOURCE_REMOTE}:${SOURCE_DIR#/}/${clean_limit_path}"
  else
    source_path="${SOURCE_DIR}/${clean_limit_path}"
  fi
  dest_path="${RCLONE_REMOTE}:${SCALEWAY_BUCKET}/${DEST_PREFIX}/${clean_limit_path}"
fi

common_args=(
  "--transfers" "${TRANSFERS}"
  "--checkers" "${CHECKERS}"
  "--s3-acl" "${S3_ACL}"
  "--progress"
  "--stats" "30s"
  "--stats-one-line"
  "--log-file" "${LOG_FILE}"
)

if [[ -n "${MAX_TRANSFER_GB:-}" ]]; then
  common_args+=("--max-transfer" "${MAX_TRANSFER_GB}G")
fi

if [[ -n "${BW_LIMIT:-}" ]]; then
  common_args+=("--bwlimit" "${BW_LIMIT}")
fi

if [[ -n "${INCLUDE_FROM:-}" ]]; then
  common_args+=("--include-from" "${INCLUDE_FROM}")
fi

echo "Mode: ${MODE}"
echo "Source: ${source_path}"
echo "Destination: ${dest_path}"
echo "Log: ${LOG_FILE}"

list_source_dirs() {
  if [[ -n "${BATCH_FILE}" ]]; then
    grep -vE '^\s*(#|$)' "${BATCH_FILE}"
    return
  fi

  local list_path="${SOURCE_DIR}"

  if [[ "${SOURCE_TYPE}" == "ftp" ]]; then
    list_path="${SOURCE_REMOTE}:${SOURCE_DIR#/}"
  fi

  "${RCLONE_BIN}" lsf "${list_path}" --dirs-only \
    | sed 's#/$##' \
    | sort \
    | tail -n "+$((BATCH_OFFSET + 1))" \
    | head -n "${BATCH_SIZE}"
}

copy_dir() {
  local dir_name="$1"
  local source_dir="${SOURCE_DIR}/${dir_name}"
  local target_dir="${RCLONE_REMOTE}:${SCALEWAY_BUCKET}/${DEST_PREFIX}/${dir_name}"

  if [[ "${SOURCE_TYPE}" == "ftp" ]]; then
    source_dir="${SOURCE_REMOTE}:${SOURCE_DIR#/}/${dir_name}"
  fi

  echo "Copy: ${source_dir} -> ${target_dir}"
  "${RCLONE_BIN}" copy "${source_dir}" "${target_dir}" "${common_args[@]}"
}

sync_dir() {
  local dir_name="$1"
  local source_dir="${SOURCE_DIR}/${dir_name}"
  local target_dir="${RCLONE_REMOTE}:${SCALEWAY_BUCKET}/${DEST_PREFIX}/${dir_name}"

  if [[ "${SOURCE_TYPE}" == "ftp" ]]; then
    source_dir="${SOURCE_REMOTE}:${SOURCE_DIR#/}/${dir_name}"
  fi

  if [[ "${ALLOW_SYNC_DELETE}" != "true" ]]; then
    echo "Refusing sync because ALLOW_SYNC_DELETE is not true." >&2
    echo "sync can delete destination files that are not present in source." >&2
    exit 1
  fi

  echo "Sync: ${source_dir} -> ${target_dir}"
  "${RCLONE_BIN}" sync "${source_dir}" "${target_dir}" "${common_args[@]}"
}

dry_run_dir() {
  local dir_name="$1"
  local source_dir="${SOURCE_DIR}/${dir_name}"
  local target_dir="${RCLONE_REMOTE}:${SCALEWAY_BUCKET}/${DEST_PREFIX}/${dir_name}"

  if [[ "${SOURCE_TYPE}" == "ftp" ]]; then
    source_dir="${SOURCE_REMOTE}:${SOURCE_DIR#/}/${dir_name}"
  fi

  echo "Dry-run: ${source_dir} -> ${target_dir}"
  "${RCLONE_BIN}" copy "${source_dir}" "${target_dir}" --dry-run "${common_args[@]}"
}

verify_dir() {
  local dir_name="$1"
  local source_dir="${SOURCE_DIR}/${dir_name}"
  local target_dir="${RCLONE_REMOTE}:${SCALEWAY_BUCKET}/${DEST_PREFIX}/${dir_name}"

  if [[ "${SOURCE_TYPE}" == "ftp" ]]; then
    source_dir="${SOURCE_REMOTE}:${SOURCE_DIR#/}/${dir_name}"
  fi

  echo "Verify: ${source_dir} -> ${target_dir}"
  "${RCLONE_BIN}" check "${source_dir}" "${target_dir}" --one-way "${common_args[@]}"
}

run_batch() {
  local action="$1"
  local processed=0

  while IFS= read -r dir_name; do
    [[ -z "${dir_name}" ]] && continue
    processed=$((processed + 1))

    case "${action}" in
      dry-run)
        dry_run_dir "${dir_name}"
        ;;
      copy)
        copy_dir "${dir_name}"
        if [[ "${VERIFY_AFTER_COPY}" == "true" ]]; then
          verify_dir "${dir_name}"
        fi
        ;;
      sync)
        sync_dir "${dir_name}"
        if [[ "${VERIFY_AFTER_COPY}" == "true" ]]; then
          verify_dir "${dir_name}"
        fi
        ;;
      verify)
        verify_dir "${dir_name}"
        ;;
    esac
  done < <(list_source_dirs)

  if [[ "${processed}" -eq 0 ]]; then
    echo "No directory selected for this batch." >&2
    exit 1
  fi
}

case "${MODE}" in
  check)
    "${RCLONE_BIN}" size "${source_path}"
    "${RCLONE_BIN}" lsf "${RCLONE_REMOTE}:${SCALEWAY_BUCKET}" --max-depth 1 || true
    ;;
  list-dirs)
    list_source_dirs
    ;;
  dry-run)
    "${RCLONE_BIN}" copy "${source_path}" "${dest_path}" --dry-run "${common_args[@]}"
    ;;
  copy)
    "${RCLONE_BIN}" copy "${source_path}" "${dest_path}" "${common_args[@]}"
    ;;
  sync)
    if [[ "${ALLOW_SYNC_DELETE}" != "true" ]]; then
      echo "Refusing sync because ALLOW_SYNC_DELETE is not true." >&2
      echo "sync can delete destination files that are not present in source." >&2
      exit 1
    fi
    "${RCLONE_BIN}" sync "${source_path}" "${dest_path}" "${common_args[@]}"
    ;;
  batch-dry-run)
    run_batch dry-run
    ;;
  batch-copy)
    run_batch copy
    ;;
  batch-sync)
    run_batch sync
    ;;
  batch-verify)
    run_batch verify
    ;;
  *)
    echo "Unsupported MODE=${MODE}. Use check, list-dirs, dry-run, copy, sync, batch-dry-run, batch-copy, batch-sync, or batch-verify." >&2
    exit 1
    ;;
esac
