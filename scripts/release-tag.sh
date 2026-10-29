#!/usr/bin/env bash
# Tags a merged release PR (staging -> main) and publishes its GitHub Release.
#
# Usage: scripts/release-tag.sh <pr-number> [--repo owner/name] [--dry-run]
#
# Reads the version from the hidden marker "<!-- release-version: vX.Y.Z -->"
# in the PR body, creates the annotated tag vX.Y.Z on the merge commit in main
# and a GitHub Release with the PR notes. If the tag already exists it does
# nothing. Compatible with bash 3.2 (macOS). Requires git and gh.
set -euo pipefail

BASE_BRANCH="main"
REPO="${GITHUB_REPOSITORY:-}"
PR_NUMBER="${PR_NUMBER:-}"
DRY_RUN=0

while [ $# -gt 0 ]; do
  case "$1" in
    --repo)
      [ $# -ge 2 ] || { echo "--repo needs a value" >&2; exit 2; }
      REPO="$2"
      shift
      ;;
    --repo=*) REPO="${1#--repo=}" ;;
    --dry-run) DRY_RUN=1 ;;
    -h | --help) sed -n '2,9p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *[!0-9]*) echo "Unknown argument: $1" >&2; exit 2 ;;
    *) PR_NUMBER="$1" ;;
  esac
  shift
done

[ -n "$PR_NUMBER" ] || { echo "Missing PR number" >&2; exit 2; }
if [ -z "$REPO" ]; then
  REPO="$(gh repo view --json nameWithOwner --jq .nameWithOwner)"
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

IFS="$(printf '\t')" read -r STATE BASE MERGE_SHA <<INFO
$(gh pr view "$PR_NUMBER" --repo "$REPO" --json state,baseRefName,mergeCommit \
  --jq '[.state, .baseRefName, (.mergeCommit.oid // "")] | @tsv')
INFO
gh pr view "$PR_NUMBER" --repo "$REPO" --json body --jq .body >"$TMP_DIR/body.md"

if [ "$STATE" != "MERGED" ] || [ "$BASE" != "$BASE_BRANCH" ] || [ -z "$MERGE_SHA" ]; then
  echo "PR #$PR_NUMBER is not merged into $BASE_BRANCH (state $STATE, base $BASE)." >&2
  exit 1
fi

VERSION="$(sed -n 's/.*<!-- release-version: \(v[0-9]*\.[0-9]*\.[0-9]*\) -->.*/\1/p' "$TMP_DIR/body.md" | tail -n 1)"
if [ -z "$VERSION" ]; then
  echo "PR #$PR_NUMBER has no release-version marker." >&2
  exit 1
fi

if git ls-remote --exit-code --tags origin "refs/tags/$VERSION" >/dev/null 2>&1; then
  echo "Tag $VERSION already exists; nothing to do."
  exit 0
fi

# Release notes: the PR body up to the "---" footer, without the marker.
NOTES="$TMP_DIR/notes.md"
awk '$0 == "---" { exit } /<!-- release-version:/ { next } { print }' "$TMP_DIR/body.md" >"$NOTES"

if [ "$DRY_RUN" -eq 1 ]; then
  echo "Would tag $VERSION on $MERGE_SHA ($REPO) with notes:"
  echo
  cat "$NOTES"
  exit 0
fi

git fetch --quiet origin "$BASE_BRANCH"
git tag -a "$VERSION" -m "Release $VERSION" "$MERGE_SHA"
git push origin "refs/tags/$VERSION"
gh release create "$VERSION" --repo "$REPO" --verify-tag \
  --title "$VERSION" --notes-file "$NOTES"
echo "Released $VERSION from PR #$PR_NUMBER."
