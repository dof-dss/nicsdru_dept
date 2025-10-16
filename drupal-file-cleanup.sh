#!/usr/bin/env bash
set -Eeuo pipefail

# Orphaned File Detection and Cleanup (Drupal 10.5 on Upsun)
# - Generates managed file list via Drush (file_managed → absolute filesystem paths)
# - Compares against what's on disk (public + private)
# - Produces a timestamped report in /tmp
# - Optional --delete to remove orphans after review
#
# Usage:
#   bash drupal_orphaned_files.sh [--delete] [--exclude "regex1|regex2"] [--root /app]
#
# Requirements: drush, php, find, sort, awk, sed, comm (optional), numfmt (optional)

DELETE=0
APP_ROOT="${APP_ROOT:-}"
EXCLUDES_DEFAULT='/(styles|css|js)/|/php/twig/|/ctools/css/|/embed_buttons(/|$)|(^|/)\.htaccess$'
EXCLUDES="$EXCLUDES_DEFAULT"

# ------------------------- arg parsing -------------------------
while [[ $# -gt 0 ]]; do
  case "$1" in
    --delete)
      DELETE=1; shift
      ;;
    --exclude)
      [[ $# -ge 2 ]] || { echo "ERROR: --exclude needs a value" >&2; exit 1; }
      EXCLUDES="$2"; shift 2
      ;;
    --root)
      [[ $# -ge 2 ]] || { echo "ERROR: --root needs a path" >&2; exit 1; }
      APP_ROOT="$2"; shift 2
      ;;
    *)
      echo "Unknown option: $1" >&2; exit 1
      ;;
  esac
done
# ---------------------------------------------------------------

if [[ -n "$APP_ROOT" ]]; then
  cd "$APP_ROOT"
fi

timestamp="$(date +%Y%m%d_%H%M%S)"
OUTDIR="/tmp/drupal_file_cleanup_${timestamp}"
mkdir -p "$OUTDIR"

ALL_FILES_TXT="$OUTDIR/all_files.txt"
DB_FILES_TXT="$OUTDIR/db_managed_paths.txt"
ORPHANS_TXT="$OUTDIR/orphaned_files.txt"

echo "Output directory: $OUTDIR"
echo "Excludes (regex): $EXCLUDES"
echo

# Helper to run small drush evals
drush_eval_single() {
  drush eval "$1" 2>/dev/null | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//'
}

echo "[1/6] Detecting Drupal file directories..."
PUBLIC_DIR="$(drush_eval_single 'echo \Drupal::service("file_system")->realpath("public://");')"
PRIVATE_DIR="$(drush_eval_single 'try { echo \Drupal::service("file_system")->realpath("private://"); } catch (\Throwable $e) { echo ""; }')"

if [[ -z "$PUBLIC_DIR" || ! -d "$PUBLIC_DIR" ]]; then
  echo "ERROR: Could not resolve public files directory via Drupal (public://)." >&2
  exit 1
fi
echo "  Public:  $PUBLIC_DIR"
if [[ -n "$PRIVATE_DIR" && -d "$PRIVATE_DIR" ]]; then
  echo "  Private: $PRIVATE_DIR"
else
  PRIVATE_DIR=""
  echo "  Private: (not configured)"
fi
echo

echo "[2/6] Exporting DB-managed files via Drush..."
drush eval '
$fs = \Drupal::service("file_system");
$uris = \Drupal::database()->query("SELECT uri FROM {file_managed}")->fetchCol();
foreach ($uris as $u) {
  try {
    $p = $fs->realpath($u);
    if ($p && is_string($p)) { echo $p, PHP_EOL; }
  } catch (\Throwable $e) {}
}
' > "$DB_FILES_TXT"

sed -i 's/\r$//' "$DB_FILES_TXT"
awk 'NF' "$DB_FILES_TXT" | sort -u > "$DB_FILES_TXT.sorted"
mv "$DB_FILES_TXT.sorted" "$DB_FILES_TXT"
echo "  Managed paths: $(wc -l < "$DB_FILES_TXT" | awk '{print $1}')"
echo

echo "[3/6] Scanning filesystem..."
{
  find "$PUBLIC_DIR" -type f 2>/dev/null
  if [[ -n "$PRIVATE_DIR" ]]; then
    find "$PRIVATE_DIR" -type f 2>/dev/null
  fi
} | sed 's/\r$//' | awk 'NF' | grep -Ev "$EXCLUDES" | sort -u > "$ALL_FILES_TXT"

echo "  Disk files (post-excludes): $(wc -l < "$ALL_FILES_TXT" | awk '{print $1}')"
echo

echo "[4/6] Comparing lists to find orphans..."
sort -u "$ALL_FILES_TXT" > "$ALL_FILES_TXT.sorted"
sort -u "$DB_FILES_TXT"  > "$DB_FILES_TXT.sorted"

if command -v comm >/dev/null 2>&1; then
  comm -23 "$ALL_FILES_TXT.sorted" "$DB_FILES_TXT.sorted" > "$ORPHANS_TXT"
else
  # Fallback if 'comm' is missing
  grep -Fvxf "$DB_FILES_TXT.sorted" "$ALL_FILES_TXT.sorted" > "$ORPHANS_TXT" || true
fi

orphans_count=$(wc -l < "$ORPHANS_TXT" | awk '{print $1}')
echo "  Orphaned files: $orphans_count"
echo "  Report: $ORPHANS_TXT"
echo

# ---------------- Option A: Size calculation via Drush/PHP ----------------
echo "[5/6] Calculating total size of orphaned files..."
SIZE_REPORT="$OUTDIR/orphans_size.txt"

drush eval "
\$list = '$ORPHANS_TXT';
\$sum = 0; \$count = 0;
if (is_file(\$list)) {
  \$fh = fopen(\$list, 'r');
  if (\$fh) {
    while (!feof(\$fh)) {
      \$line = fgets(\$fh);
      if (\$line === false) break;
      \$path = rtrim(\$line, \"\\r\\n\");
      if (\$path !== '' && is_file(\$path)) {
        \$size = @filesize(\$path);
        if (\$size !== false) { \$sum += \$size; \$count++; }
      }
    }
    fclose(\$fh);
  }
}
echo \$count, \" files\\n\";
echo \$sum, \" bytes\\n\";
" > "$SIZE_REPORT" || true

# Pretty print size if possible
bytes=$(awk '/ bytes$/{print $1}' "$SIZE_REPORT" 2>/dev/null || echo "")
count=$(awk 'NR==1{print $1}' "$SIZE_REPORT" 2>/dev/null || echo "")
if [[ -n "${bytes:-}" ]]; then
  if command -v numfmt >/dev/null 2>&1; then
    human=$(numfmt --to=iec --suffix=B "$bytes" 2>/dev/null || echo "${bytes}B")
    echo "  Total orphan size: ${human} (${bytes} bytes) across ${count:-0} files"
  else
    echo "  Total orphan size: ${bytes} bytes across ${count:-0} files"
  fi
else
  echo "  (Size calculation unavailable or no orphans listed.)"
fi
echo "  Size details: $SIZE_REPORT"
echo
# ------------------------------------------------------------------------

if [[ "$DELETE" -eq 1 ]]; then
  echo "[6/6] Deleting orphaned files..."
  if [[ "$orphans_count" -gt 0 ]]; then
    awk -v pub="$PUBLIC_DIR/" -v prv="$PRIVATE_DIR/" '
      { if (index($0, pub)==1 || (prv!="" && index($0, prv)==1)) print }
    ' "$ORPHANS_TXT" | xargs -r -I{} rm -f -- "{}"
    echo "  Deletion attempted for all listed orphans."
  else
    echo "  Nothing to delete."
  fi
  echo "Done."
else
  cat <<NOTE

READ-ONLY MODE:
- No files were deleted.
- Review:
    - Orphan list: $ORPHANS_TXT
    - Size report: $SIZE_REPORT
- Re-run with --delete to remove them.

NOTE
fi
