#!/usr/bin/env bash
set -euo pipefail

# --- Usage & args -----------------------------------------------------------------
if [ "$#" -ne 3 ]; then
  echo "Usage: $0 <DRUPAL_DEPLOY_PATH> <PHPCS_CHECK_DIR> <IGNORE>"
  echo "  DRUPAL_DEPLOY_PATH: Path to Drupal deployment (e.g., /var/www/deploy)"
  echo "  PHPCS_CHECK_DIR: Directory to check (e.g., web/modules/custom)"
  echo "  IGNORE: Comma-separated directories to ignore, relative to DRUPAL_DEPLOY_PATH or absolute"
  echo "          (e.g., web/themes/custom/mytheme/node_modules,web/modules/custom/my_module/tests)"
  exit 1
fi

DRUPAL_DEPLOY_PATH=$1
PHPCS_CHECK_DIR=$2
USER_IGNORE=$3

# --- Binaries & constants ----------------------------------------------------------
PHPCS_PATH="${DRUPAL_DEPLOY_PATH}/vendor/bin/phpcs"
PHPCBF_PATH="${DRUPAL_DEPLOY_PATH}/vendor/bin/phpcbf"
PHPCS_EXTENSIONS="php,inc,module,theme"

# Excluded sniffs.
DRUPAL_EXCLUDED_SNIFFS=(
  Drupal.Commenting.DocComment
  Drupal.Commenting.ClassComment
)
DRUPAL_PRACTICE_EXCLUDED_SNIFFS=(
  DrupalPractice.Objects.StrictSchemaDisabled
)

# Extra frontend/toolchain directories to always ignore (add to user list).
ALWAYS_IGNORE_RELATIVE=(
  "web/themes/origins/node_modules"
  "web/themes/custom/nicsdru_dept_theme/node_modules"
)

# --- Helpers -----------------------------------------------------------------------
comma_join() {
  local IFS=","
  echo "$*"
}

resolve_ignore_csv() {
  local base="$1"
  local user_csv="$2"
  local resolved=()
  IFS=',' read -ra items <<< "$user_csv"
  for raw in "${items[@]}"; do
    local trimmed
    trimmed="$(echo "$raw" | xargs || true)"
    [[ -z "$trimmed" ]] && continue
    if [[ "$trimmed" = /* ]]; then
      resolved+=("$trimmed")
    else
      resolved+=("${base%/}/$trimmed")
    fi
  done
  for rel in "${ALWAYS_IGNORE_RELATIVE[@]}"; do
    resolved+=("${base%/}/$rel")
  done
  comma_join "${resolved[@]}"
}

die() {
  echo "Error: $*" >&2
  exit 1
}

# --- Sanity checks -----------------------------------------------------------------
[ -x "$PHPCS_PATH" ] || die "phpcs not found at $PHPCS_PATH. Run 'composer install' in ${DRUPAL_DEPLOY_PATH}."
[ -d "$DRUPAL_DEPLOY_PATH" ] || die "DRUPAL_DEPLOY_PATH does not exist: $DRUPAL_DEPLOY_PATH"
[ -d "$PHPCS_CHECK_DIR" ] || die "PHPCS_CHECK_DIR does not exist: $PHPCS_CHECK_DIR"

IGNORE_CSV="$(resolve_ignore_csv "$DRUPAL_DEPLOY_PATH" "$USER_IGNORE")"

echo "----------------------------------------------------------------------"
echo ">>> Running coding standard checks"
echo ">>> Deploy path:     ${DRUPAL_DEPLOY_PATH}"
echo ">>> Check directory: ${PHPCS_CHECK_DIR}"
echo ">>> Ignoring:        ${IGNORE_CSV}"
echo "----------------------------------------------------------------------"

# Configure PHPCS installed_paths for Drupal & Slevomat.
"${PHPCS_PATH}" --config-set installed_paths \
  "${DRUPAL_DEPLOY_PATH}/vendor/drupal/coder/coder_sniffer,${DRUPAL_DEPLOY_PATH}/vendor/slevomat/coding-standard" >/dev/null

# --- 1) Drupal coding standards ----------------------------------------------------
EXCLUDE=$(IFS=, ; echo "${DRUPAL_EXCLUDED_SNIFFS[*]}")
if ! "${PHPCS_PATH}" -nq \
  --standard=Drupal \
  --extensions="${PHPCS_EXTENSIONS}" \
  --exclude="${EXCLUDE}" \
  --ignore="${IGNORE_CSV}" \
  "${PHPCS_CHECK_DIR}"
then
  echo "🚫 Drupal coding standards checks failed, see above for details 🚫"
  exit 1
fi

# --- 2) Drupal best practices ------------------------------------------------------
EXCLUDE=$(IFS=, ; echo "${DRUPAL_PRACTICE_EXCLUDED_SNIFFS[*]}")
if ! "${PHPCS_PATH}" -nq \
  --standard=DrupalPractice \
  --extensions="${PHPCS_EXTENSIONS}" \
  --exclude="${EXCLUDE}" \
  --ignore="${IGNORE_CSV}" \
  "${PHPCS_CHECK_DIR}"
then
  echo "🚫 Drupal best practice checks failed, see above for details 🚫"
  exit 1
fi

# --- 3) Strict types enforcement ---------------------------------------------------
# Use Slevomat by absolute ruleset path to avoid "No sniffs were registered".
SLEVOMAT_RULESET="${DRUPAL_DEPLOY_PATH}/vendor/slevomat/coding-standard/SlevomatCodingStandard/ruleset.xml"
[ -r "${SLEVOMAT_RULESET}" ] || die "Slevomat ruleset not found at ${SLEVOMAT_RULESET}"

# Run strict-types with an emacs report so we can extract file paths cleanly.
set +e
STRICT_OUT="$("${PHPCS_PATH}" -n \
  --standard="${SLEVOMAT_RULESET}" \
  --sniffs=SlevomatCodingStandard.TypeHints.DeclareStrictTypes \
  --extensions="${PHPCS_EXTENSIONS}" \
  --ignore="${IGNORE_CSV}" \
  --report=emacs -s \
  "${PHPCS_CHECK_DIR}" 2>&1)"
STRICT_STATUS=$?
set -e

if [ "${STRICT_STATUS}" -ne 0 ]; then
  echo "🚫 Missing or mis-placed 'declare(strict_types=1);' detected."

  # Pull just the files flagged by the strict-types sniff, dedupe, and print.
  echo "Non-compliant files:"
  echo "${STRICT_OUT}" \
    | grep -E '\(SlevomatCodingStandard\.TypeHints\.DeclareStrictTypes' \
    | cut -d: -f1 \
    | sort -u \
    | sed 's/^/ - /'

  echo
  echo "Tip: auto-fix many issues with:"
  echo "  ${PHPCBF_PATH} --sniffs=SlevomatCodingStandard.TypeHints.DeclareStrictTypes ${PHPCS_CHECK_DIR}"
  exit 1
fi

echo "LGTM ✅"
