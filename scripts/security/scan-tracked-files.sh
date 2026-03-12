#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

cd "$ROOT_DIR"

mapfile -t tracked_env_files < <(git ls-files '.env' '.env.*')
unexpected_env_files=()

for file in "${tracked_env_files[@]}"; do
  case "$file" in
    .env.example|.env.testing)
      ;;
    *)
      unexpected_env_files+=("$file")
      ;;
  esac
done

if (( ${#unexpected_env_files[@]} > 0 )); then
  echo "Unexpected tracked environment files detected:" >&2
  printf ' - %s\n' "${unexpected_env_files[@]}" >&2
  exit 1
fi

patterns=(
  'BEGIN (RSA |EC |OPENSSH |)?PRIVATE KEY'
  'github''_pat_'
  'ghp_[A-Za-z0-9]+'
  'glpat-[A-Za-z0-9_-]+'
  'xox[baprs]-[A-Za-z0-9-]+'
  'AKIA[0-9A-Z]{16}'
  'sk_live_[A-Za-z0-9]+'
)

matches=''
tracked_files=()

while IFS= read -r file; do
  [[ -f "$file" ]] && tracked_files+=("$file")
done < <(git ls-files)

for pattern in "${patterns[@]}"; do
  current_matches=''

  if (( ${#tracked_files[@]} > 0 )); then
    current_matches="$(printf '%s\0' "${tracked_files[@]}" | xargs -0 rg -n --no-heading --color never -e "$pattern" || true)"
  fi

  if [[ -n "$current_matches" ]]; then
    matches+="$current_matches"$'\n'
  fi
done

if [[ -n "$matches" ]]; then
  echo "Potential committed secret material detected:" >&2
  printf '%s' "$matches" >&2
  exit 1
fi
