#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

usage() {
  cat <<'EOF' >&2
Usage: bash scripts/release/build-changelog.sh --version <semver> --output <path>
EOF
}

version=''
output=''

while [[ $# -gt 0 ]]; do
  case "$1" in
    --version)
      version="${2:-}"
      shift 2
      ;;
    --output)
      output="${2:-}"
      shift 2
      ;;
    *)
      usage
      exit 1
      ;;
  esac
done

if [[ -z "$version" || -z "$output" ]]; then
  usage
  exit 1
fi

semver_regex='^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?(\+[0-9A-Za-z.-]+)?$'

if [[ ! "$version" =~ $semver_regex ]]; then
  echo "Release version must be semantic versioning compliant: $version" >&2
  exit 1
fi

cd "$ROOT_DIR"
mkdir -p "$(dirname "$output")"

current_tag="v$version"
previous_tag="$(
  git tag --merged HEAD --list 'v*.*.*' --sort=-version:refname \
    | grep -vx "$current_tag" \
    | head -n1 \
    || true
)"

if [[ -n "$previous_tag" ]]; then
  commit_range="${previous_tag}..HEAD"
  range_label="${previous_tag}..${current_tag}"
else
  commit_range='HEAD'
  range_label="repository-start..${current_tag}"
fi

changes="$(git log --no-merges --reverse --pretty=format:'- %s (%h)' "$commit_range" || true)"

if [[ -z "$changes" ]]; then
  changes='- No merged commits were found for this release range.'
fi

commit_count="$(grep -c '^- ' <<<"$changes" || true)"
generated_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
short_sha="$(git rev-parse --short HEAD)"

cat >"$output" <<EOF
# Release Notes

Version: \`$version\`
Generated At: \`$generated_at\`
Commit Range: \`$range_label\`

## Summary

- Semantic version: \`$version\`
- Included commits: \`$commit_count\`
- Source revision: \`$short_sha\`

## Included Changes

$changes
EOF

printf '%s\n' "$output"
