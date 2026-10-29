#!/usr/bin/env bash
# Opens or updates the release PR (staging -> main) with the next semantic
# version computed from Conventional Commits.
#
# Usage: scripts/release-pr.sh [--dry-run] [--repo owner/name]
#
# Bump rules (commits in origin/staging that are not in origin/main):
#   - "type!:" or a "BREAKING CHANGE:" footer -> major
#     (while the version is 0.x a breaking change bumps the minor instead)
#   - feat                                   -> minor
#   - fix, perf, refactor, revert             -> patch
#   - anything else (docs, chore, test, ...)  -> patch
#
# Compatible with bash 3.2 (macOS). Requires git and gh.
set -euo pipefail

BASE_BRANCH="main"
HEAD_BRANCH="staging"
LABEL="release"
DRY_RUN=0
REPO="${GITHUB_REPOSITORY:-}"

usage() {
  sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'
}

while [ $# -gt 0 ]; do
  case "$1" in
    --dry-run) DRY_RUN=1 ;;
    --repo)
      [ $# -ge 2 ] || { echo "--repo needs a value" >&2; exit 2; }
      REPO="$2"
      shift
      ;;
    --repo=*) REPO="${1#--repo=}" ;;
    -h | --help) usage; exit 0 ;;
    *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
  esac
  shift
done

if [ -z "$REPO" ]; then
  REPO="$(gh repo view --json nameWithOwner --jq .nameWithOwner)"
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

git fetch --quiet --tags origin "$BASE_BRANCH" "$HEAD_BRANCH"
BASE_REF="origin/$BASE_BRANCH"
HEAD_REF="origin/$HEAD_BRANCH"

# --- Current version ---------------------------------------------------------
LAST_TAG="$(git describe --tags --abbrev=0 --match 'v[0-9]*.[0-9]*.[0-9]*' "$BASE_REF" 2>/dev/null || true)"
if [ -z "$LAST_TAG" ]; then
  LAST_TAG="v0.0.0"
fi
CURRENT="${LAST_TAG#v}"
MAJOR="${CURRENT%%.*}"
REST="${CURRENT#*.}"
MINOR="${REST%%.*}"
PATCH="${REST#*.}"
PATCH="${PATCH%%[-+]*}"

find_release_pr() {
  gh pr list --repo "$REPO" --base "$BASE_BRANCH" --head "$HEAD_BRANCH" \
    --label "$LABEL" --state open --json number --jq '.[0].number // empty'
}

# --- Pending commits ---------------------------------------------------------
COMMITS_FILE="$TMP_DIR/commits"
git log --reverse --no-merges --format='%H%x1f%s%x1f%b%x1e' "$BASE_REF..$HEAD_REF" >"$COMMITS_FILE"

if [ ! -s "$COMMITS_FILE" ]; then
  echo "No changes between $HEAD_REF and $BASE_REF (current $LAST_TAG)."
  if [ "$DRY_RUN" -eq 1 ]; then
    exit 0
  fi
  OPEN_PR="$(find_release_pr)"
  if [ -n "$OPEN_PR" ]; then
    gh pr close "$OPEN_PR" --repo "$REPO" \
      --comment "Sin cambios pendientes entre \`$HEAD_BRANCH\` y \`$BASE_BRANCH\`; se cierra el PR de release."
    echo "Closed release PR #$OPEN_PR."
  fi
  exit 0
fi

# Map every commit brought in by a "Merge pull request #n" to its PR number.
PR_MAP="$TMP_DIR/pr-map"
: >"$PR_MAP"
git log --merges --format='%H %s' "$BASE_REF..$HEAD_REF" | while read -r merge subject; do
  case "$subject" in
    "Merge pull request #"*)
      number="${subject#Merge pull request \#}"
      number="${number%% *}"
      git rev-list "$merge^1..$merge^2" | while read -r sha; do
        echo "$sha $number"
      done >>"$PR_MAP"
      ;;
  esac
done

# Classify: one line per commit -> group<TAB>breaking<TAB>sha<TAB>subject
CLASSIFIED="$TMP_DIR/classified"
awk 'BEGIN { RS = "\036"; FS = "\037" }
  {
    sha = $1; subject = $2; body = $3
    gsub(/^[\r\n]+/, "", sha)
    if (sha == "") next
    type = ""; breaking = 0
    if (match(subject, /^[A-Za-z]+(\([^)]*\))?!?:/)) {
      head = substr(subject, 1, RLENGTH - 1)
      if (head ~ /!$/) { breaking = 1; sub(/!$/, "", head) }
      sub(/\(.*$/, "", head)
      type = tolower(head)
    }
    if (body ~ /(^|\n)BREAKING[ -]CHANGE:/) breaking = 1
    if (type == "feat") group = "feat"
    else if (type == "fix" || type == "perf" || type == "revert") group = "fix"
    else if (type == "refactor") group = "refactor"
    else group = "other"
    printf "%s\t%d\t%s\t%s\n", group, breaking, sha, subject
  }' "$COMMITS_FILE" >"$CLASSIFIED"

# --- Bump --------------------------------------------------------------------
if awk -F'\t' '$2 == 1 { found = 1 } END { exit !found }' "$CLASSIFIED"; then
  BUMP="major"
elif awk -F'\t' '$1 == "feat" { found = 1 } END { exit !found }' "$CLASSIFIED"; then
  BUMP="minor"
else
  BUMP="patch"
fi

EFFECTIVE_BUMP="$BUMP"
if [ "$BUMP" = "major" ] && [ "$MAJOR" -eq 0 ]; then
  EFFECTIVE_BUMP="minor" # 0.x: breaking changes bump the minor
fi

case "$EFFECTIVE_BUMP" in
  major) MAJOR=$((MAJOR + 1)); MINOR=0; PATCH=0 ;;
  minor) MINOR=$((MINOR + 1)); PATCH=0 ;;
  patch) PATCH=$((PATCH + 1)) ;;
esac
NEXT="v$MAJOR.$MINOR.$PATCH"

# --- Notes -------------------------------------------------------------------
pr_for() {
  local sha="$1" subject="$2" number=""
  case "$subject" in
    *"(#"[0-9]*")") number="${subject##*(#}"; number="${number%)}" ;;
  esac
  if [ -z "$number" ]; then
    number="$(awk -v s="$sha" '$1 == s { print $2; exit }' "$PR_MAP")"
  fi
  printf '%s' "$number"
}

render_group() {
  local title="$1" filter="$2" printed=0 group breaking sha subject number line
  while IFS="$(printf '\t')" read -r group breaking sha subject; do
    case " $filter " in *" $group "*) ;; *) continue ;; esac
    if [ "$printed" -eq 0 ]; then
      printf '### %s\n\n' "$title"
      printed=1
    fi
    line="- "
    [ "$breaking" = "1" ] && line="$line**BREAKING** "
    line="$line$subject (\`${sha:0:7}\`"
    number="$(pr_for "$sha" "$subject")"
    [ -n "$number" ] && line="$line, [#$number](https://github.com/$REPO/pull/$number)"
    printf '%s)\n' "$line"
  done <"$CLASSIFIED"
  [ "$printed" -eq 1 ] && printf '\n'
  return 0
}

NOTES="$TMP_DIR/notes.md"
{
  printf '## %s\n\n' "$NEXT"
  # shellcheck disable=SC2016 # backticks are Markdown
  printf 'Bump **%s** desde `%s`' "$EFFECTIVE_BUMP" "$LAST_TAG"
  if [ "$EFFECTIVE_BUMP" != "$BUMP" ]; then
    printf ' (hay cambios incompatibles; en 0.x suben el minor)'
  fi
  printf '.\n\n'
  render_group "¿Qué hay de nuevo?" "feat"
  render_group "Correcciones" "fix"
  render_group "Otros" "refactor other"
} >"$NOTES"

BODY="$TMP_DIR/body.md"
{
  cat "$NOTES"
  # shellcheck disable=SC2016 # backticks are Markdown
  printf -- '---\n\nPR generado por `scripts/release-pr.sh`. Mergear con **merge commit** (no squash). Al mergearlo se crea el tag `%s` y la GitHub Release.\n\n' "$NEXT"
  printf '<!-- release-version: %s -->\n' "$NEXT"
} >"$BODY"

TITLE="release: $NEXT"

if [ "$DRY_RUN" -eq 1 ]; then
  echo "Repo:     $REPO"
  echo "Current:  $LAST_TAG"
  if [ "$EFFECTIVE_BUMP" = "$BUMP" ]; then
    echo "Bump:     $BUMP"
  else
    echo "Bump:     $EFFECTIVE_BUMP (detected $BUMP, 0.x rule)"
  fi
  echo "Next:     $NEXT"
  echo "Title:    $TITLE"
  echo
  cat "$BODY"
  exit 0
fi

# --- GitHub ------------------------------------------------------------------
gh label create "$LABEL" --repo "$REPO" --color "0E8A16" \
  --description "Release PR from staging to main" --force >/dev/null

OPEN_PR="$(find_release_pr)"
if [ -n "$OPEN_PR" ]; then
  gh pr edit "$OPEN_PR" --repo "$REPO" --title "$TITLE" --body-file "$BODY" >/dev/null
  echo "Updated release PR #$OPEN_PR -> $NEXT"
else
  URL="$(gh pr create --repo "$REPO" --base "$BASE_BRANCH" --head "$HEAD_BRANCH" \
    --title "$TITLE" --body-file "$BODY" --label "$LABEL")"
  echo "Created release PR $URL -> $NEXT"
fi
