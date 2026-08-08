#!/usr/bin/env bash
# 从两次 tag 之间的提交生成中文 Release Notes。
# 兼容本仓库 gitmoji：`:bug: (scope): 描述`，以及少量 conventional commits。
#
# 用法：
#   scripts/gen-changelog.sh [from_ref] [to_ref]
#   from_ref 省略时取上一 tag；to_ref 默认 HEAD。
set -euo pipefail

TO_REF="${2:-HEAD}"
FROM_REF="${1:-}"

if [ -z "$FROM_REF" ]; then
  if git describe --exact-match --tags "$TO_REF" >/dev/null 2>&1; then
    FROM_REF="$(git describe --tags --abbrev=0 "${TO_REF}^" 2>/dev/null || true)"
  else
    FROM_REF="$(git describe --tags --abbrev=0 "$TO_REF" 2>/dev/null || true)"
  fi
fi

if [ -n "$FROM_REF" ]; then
  RANGE="${FROM_REF}..${TO_REF}"
else
  RANGE="$TO_REF"
fi

FEATURES_FILE="$(mktemp)"
FIXES_FILE="$(mktemp)"
IMPROVE_FILE="$(mktemp)"
OTHERS_FILE="$(mktemp)"
trap 'rm -f "$FEATURES_FILE" "$FIXES_FILE" "$IMPROVE_FILE" "$OTHERS_FILE"' EXIT

classify() {
  raw="$1"
  key=""
  desc="$raw"

  case "$raw" in
    :*:*)
      key="${raw#*:}"
      key="${key%%:*}"
      desc="${raw#:*:}"
      desc="${desc# }"
      ;;
    *)
      # conventional: type(scope): msg / type: msg
      if echo "$raw" | grep -Eq '^[a-z]+(\([^)]*\))?!?:[[:space:]]'; then
        key="$(echo "$raw" | sed -E 's/^([a-z]+).*/\1/')"
        desc="$(echo "$raw" | sed -E 's/^[a-z]+(\([^)]*\))?!?:[[:space:]]*//')"
      fi
      ;;
  esac

  # 去掉 (scope): 前缀
  case "$desc" in
    \(*\):*)
      desc="$(echo "$desc" | sed -E 's/^\([^)]*\)[[:space:]]*:[[:space:]]*//')"
      ;;
  esac

  case "$key" in
    bookmark|release) return 0 ;;
  esac

  [ -z "$desc" ] && return 0

  case "$key" in
    sparkles|feat|feature)
      printf '%s\n' "$desc" >>"$FEATURES_FILE"
      ;;
    bug|fix|ambulance|bugfix)
      printf '%s\n' "$desc" >>"$FIXES_FILE"
      ;;
    recycle|refactor|lipstick|wrench|arrow_up|fire|memo|docs|style|chore|perf|zap|hammer|art|truck|mute|construction|green_heart|alembic|mag|label|seedling|card_file_box|building_construction|iphone|children_crossing|page_facing_up|bulb|goal_net|dizzy|twisted_rightwards_arrows|rewind|heavy_plus_sign|heavy_minus_sign|see_no_evil)
      printf '%s\n' "$desc" >>"$IMPROVE_FILE"
      ;;
    *)
      printf '%s\n' "$desc" >>"$OTHERS_FILE"
      ;;
  esac
}

emit_section() {
  title="$1"
  file="$2"
  [ -s "$file" ] || return 0
  echo "### ${title}"
  echo
  while IFS= read -r item; do
    [ -n "$item" ] && echo "- ${item}"
  done <"$file"
  echo
}

while IFS= read -r c; do
  [ -z "$c" ] && continue
  classify "$c"
done < <(git log "$RANGE" --pretty=format:'%s' --no-merges)

VERSION_LABEL="$(git describe --tags --exact-match "$TO_REF" 2>/dev/null || echo "$TO_REF")"
VERSION_LABEL="${VERSION_LABEL#v}"

echo "## ${VERSION_LABEL}"
echo
if [ -n "$FROM_REF" ]; then
  echo "> 变更范围：\`${FROM_REF}\` → \`${TO_REF}\`"
else
  echo "> 变更范围：初始发布 → \`${TO_REF}\`"
fi
echo

emit_section "新功能" "$FEATURES_FILE"
emit_section "修复" "$FIXES_FILE"
emit_section "改进" "$IMPROVE_FILE"
emit_section "其他" "$OTHERS_FILE"

if [ ! -s "$FEATURES_FILE" ] && [ ! -s "$FIXES_FILE" ] && [ ! -s "$IMPROVE_FILE" ] && [ ! -s "$OTHERS_FILE" ]; then
  echo "_本版本无归类提交（可能仅含版本号 bump）。_"
  echo
fi

REPO="$(git remote get-url origin 2>/dev/null | sed -E 's#^git@github\.com:#https://github.com/#; s#\.git$##')"
case "$REPO" in
  https://github.com/*)
    if [ -n "$FROM_REF" ]; then
      echo "**Full Changelog**: ${REPO}/compare/${FROM_REF}...${TO_REF}"
    fi
    ;;
esac
