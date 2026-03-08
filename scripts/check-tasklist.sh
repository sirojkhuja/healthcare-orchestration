#!/usr/bin/env bash
set -euo pipefail

tasklist="docs/project/tasklist.md"

if [[ ! -f "$tasklist" ]]; then
  echo "Missing task list: $tasklist" >&2
  exit 1
fi

progress_line="$(grep -m1 '^Overall Progress:' "$tasklist" || true)"
active_line="$(grep -m1 '^Active Task:' "$tasklist" || true)"

if [[ -z "$progress_line" ]]; then
  echo "Missing Overall Progress line in $tasklist" >&2
  exit 1
fi

if [[ -z "$active_line" ]]; then
  echo "Missing Active Task line in $tasklist" >&2
  exit 1
fi

read -r recorded_percent recorded_done recorded_total < <(
  sed -E 's/^Overall Progress: `?([0-9]+)% \(([0-9]+)\/([0-9]+) tasks completed\)`?$/\1 \2 \3/' <<<"$progress_line"
)

if [[ -z "${recorded_percent:-}" || -z "${recorded_done:-}" || -z "${recorded_total:-}" ]]; then
  echo "Could not parse Overall Progress line: $progress_line" >&2
  exit 1
fi

readarray -t task_rows < <(awk -F'|' '
  /^\| T[0-9][0-9][0-9] / {
    id=$2
    status=$3
    gsub(/^[ \t]+|[ \t]+$/, "", id)
    gsub(/^[ \t]+|[ \t]+$/, "", status)
    print id "|" status
  }
' "$tasklist")

if [[ "${#task_rows[@]}" -eq 0 ]]; then
  echo "No task rows found in $tasklist" >&2
  exit 1
fi

total=0
done_count=0
in_progress_count=0
in_progress_id=""

for row in "${task_rows[@]}"; do
  IFS='|' read -r task_id status <<<"$row"
  total=$((total + 1))
  case "$status" in
    Todo|Blocked)
      ;;
    Done)
      done_count=$((done_count + 1))
      ;;
    "In Progress")
      in_progress_count=$((in_progress_count + 1))
      in_progress_id="$task_id"
      ;;
    *)
      echo "Invalid task status '$status' for $task_id" >&2
      exit 1
      ;;
  esac
done

if [[ "$in_progress_count" -gt 1 ]]; then
  echo "Only one task may be In Progress; found $in_progress_count" >&2
  exit 1
fi

expected_percent=$((done_count * 100 / total))

if [[ "$recorded_done" -ne "$done_count" || "$recorded_total" -ne "$total" || "$recorded_percent" -ne "$expected_percent" ]]; then
  echo "Task progress mismatch. Expected ${expected_percent}% (${done_count}/${total}), found ${recorded_percent}% (${recorded_done}/${recorded_total})." >&2
  exit 1
fi

active_task="$(sed -E 's/^Active Task: `?([^`]+)`?$/\1/' <<<"$active_line")"

if [[ "$in_progress_count" -eq 0 ]]; then
  if [[ "$active_task" != "None" ]]; then
    echo "Active Task must be None when no task is In Progress; found '$active_task'" >&2
    exit 1
  fi
else
  if [[ "$active_task" != "$in_progress_id" ]]; then
    echo "Active Task must match the In Progress task '$in_progress_id'; found '$active_task'" >&2
    exit 1
  fi
fi

echo "Task list validated: ${done_count}/${total} done, ${in_progress_count} in progress, ${expected_percent}% complete."
