#!/usr/bin/env bash
set -euo pipefail

BOOTSTRAP_URL="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.css"
OUTPUT_FILE="css/bootstrap.custom.css"

curl -s "$BOOTSTRAP_URL" \
  | awk '
    BEGIN { skip=0; depth=0; selector="" }
    function should_skip(sel) {
      return (sel ~ /-moz-/ || sel ~ /-webkit-/ || sel ~ /:-moz/ || sel ~ /::-moz/)
    }
    {
      line=$0
      if (skip==0) {
        selector = selector line
        if (line ~ /\{/) {
          if (should_skip(selector)) {
            skip=1
          } else {
            print line
          }
          depth = gsub(/\{/,"{") - gsub(/\}/,"}")
          selector=""
          next
        }
        next
      }

      depth += gsub(/\{/,"{")
      depth -= gsub(/\}/,"}")
      if (depth <= 0) { skip=0; depth=0 }
    }
  ' \
  | grep -v "sourceMappingURL" \
  > "$OUTPUT_FILE"

echo "Wrote $OUTPUT_FILE"
