#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
gitleaks_binary="${repository_root}/.build/security-tools/bin/gitleaks"
configuration="${repository_root}/tools/security/gitleaks.toml"
report_root="${repository_root}/build/reports"
mode="${1:-repository}"
target="${2:-${repository_root}}"

if [[ ! -x "${gitleaks_binary}" ]]; then
  echo "Pinned Gitleaks binary is unavailable. Run tools/security/install-tools.sh on Linux x86_64." >&2
  exit 2
fi

mkdir -p "${report_root}"
case "${mode}" in
  repository)
    "${gitleaks_binary}" git --no-banner --redact --config "${configuration}" --report-format json --report-path "${report_root}/gitleaks-history.json" "${repository_root}"
    "${gitleaks_binary}" dir --no-banner --redact --config "${configuration}" --report-format json --report-path "${report_root}/gitleaks-working-tree.json" "${repository_root}"
    ;;
  staging|artifact)
    if [[ ! -d "${target}" ]]; then
      echo "Secret scan target is not a directory." >&2
      exit 64
    fi
    "${gitleaks_binary}" dir --no-banner --redact --config "${configuration}" --report-format json --report-path "${report_root}/gitleaks-${mode}.json" "${target}"
    ;;
  *)
    echo "Usage: run-secret-scan.sh [repository|staging|artifact] [directory]" >&2
    exit 64
    ;;
esac

echo "Gitleaks ${mode} scan: PASS"
