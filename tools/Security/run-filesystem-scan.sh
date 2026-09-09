#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
trivy_binary="${repository_root}/.build/security-tools/bin/trivy"
mode="${1:-repository}"
target="${2:-${repository_root}}"
report_root="${repository_root}/build/reports"

if [[ ! -x "${trivy_binary}" ]]; then
  echo "Pinned Trivy binary is unavailable. Run tools/security/install-tools.sh on Linux x86_64." >&2
  exit 2
fi
if [[ ! -d "${target}" ]]; then
  echo "Filesystem scan target is not a directory." >&2
  exit 64
fi

php "${repository_root}/tools/security/scan-trivy.php" "${mode}" "${target}"
