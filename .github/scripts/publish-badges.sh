#!/usr/bin/env bash
# Commits coverage and tests badges to the orphan `badges` branch.
# Usage: publish-badges.sh <coverage-percent> <tests-passed>
# Needs GH_TOKEN (GITHUB_TOKEN with contents: write). staging writes to the
# branch root; any other branch (main) writes under <branch>/.
set -euo pipefail

coverage="$1"
tests="$2"
ref="${GITHUB_REF_NAME:?}"
script_dir="$(cd "$(dirname "$0")" && pwd)"
prefix=""
[ "$ref" = "staging" ] || prefix="$ref/"

work="$(mktemp -d)"
cd "$work"
git init -q
git remote add origin "https://x-access-token:${GH_TOKEN:?}@github.com/${GITHUB_REPOSITORY:?}.git"
if git fetch -q --depth=1 origin badges 2>/dev/null; then
  git checkout -q -b badges FETCH_HEAD
else
  git checkout -q --orphan badges
fi

mkdir -p "./${prefix}"
python3 "$script_dir/badge.py" coverage "${coverage}%" > "${prefix}coverage.svg"
python3 "$script_dir/badge.py" tests "${tests} passed" brightgreen > "${prefix}tests.svg"

git add -A
if git diff --cached --quiet; then
  echo "Badges unchanged"
  exit 0
fi
git -c user.name="github-actions[bot]" \
    -c user.email="41898283+github-actions[bot]@users.noreply.github.com" \
    commit -q -m "chore: update badges for ${ref}@${GITHUB_SHA:0:7}"
git push -q origin badges
echo "Badges published: coverage ${coverage}%, ${tests} tests (${ref})"
