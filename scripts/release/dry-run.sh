#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

usage() {
  cat <<'EOF' >&2
Usage: bash scripts/release/dry-run.sh --version <semver> [--output-dir <path>] [--allow-existing-tag] [--skip-verify]
EOF
}

version=''
output_dir=''
allow_existing_tag=0
skip_verify=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --version)
      version="${2:-}"
      shift 2
      ;;
    --output-dir)
      output_dir="${2:-}"
      shift 2
      ;;
    --allow-existing-tag)
      allow_existing_tag=1
      shift
      ;;
    --skip-verify)
      skip_verify=1
      shift
      ;;
    *)
      usage
      exit 1
      ;;
  esac
done

if [[ -z "$version" ]]; then
  usage
  exit 1
fi

semver_regex='^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?(\+[0-9A-Za-z.-]+)?$'

if [[ ! "$version" =~ $semver_regex ]]; then
  echo "Release version must be semantic versioning compliant: $version" >&2
  exit 1
fi

cd "$ROOT_DIR"

tag="v$version"

if git rev-parse --verify --quiet "refs/tags/$tag" >/dev/null && (( allow_existing_tag == 0 )); then
  echo "Release tag already exists locally: $tag" >&2
  exit 1
fi

if [[ -z "$output_dir" ]]; then
  output_dir="build/release/$version"
fi

bash scripts/check-tasklist.sh
bash scripts/quality-gate.sh --docs-only
bash scripts/release/check-readiness.sh --version "$version"

if (( skip_verify == 0 )); then
  make verify
fi

mkdir -p "$output_dir"

changelog_path="$output_dir/CHANGELOG-$version.md"
bash scripts/release/build-changelog.sh --version "$version" --output "$changelog_path" >/dev/null

generated_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
git_sha="$(git rev-parse HEAD)"
skip_verify_json='false'

if (( skip_verify == 1 )); then
  skip_verify_json='true'
fi

cat >"$output_dir/release-manifest.json" <<EOF
{
  "version": "$version",
  "tag": "$tag",
  "dry_run": true,
  "skip_verify": $skip_verify_json,
  "git_sha": "$git_sha",
  "generated_at": "$generated_at",
  "changelog_path": "$changelog_path"
}
EOF

printf 'Release dry run passed for %s\n' "$version"
printf 'Artifacts: %s\n' "$output_dir"
