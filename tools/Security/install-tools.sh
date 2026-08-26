#!/usr/bin/env bash

set -euo pipefail

if [[ "$(uname -s)" != "Linux" || "$(uname -m)" != "x86_64" ]]; then
  echo "Security tool installer supports Linux x86_64 only; current platform is unavailable." >&2
  exit 2
fi

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
manifest_path="${repository_root}/tools/security/tool-versions.json"
install_root="${repository_root}/.build/security-tools"
binary_root="${install_root}/bin"
temporary_root="$(mktemp -d)"
trap 'rm -rf -- "${temporary_root}"' EXIT

mkdir -p "${binary_root}"

manifest_value() {
  # The embedded PHP program must remain single-quoted so Bash does not expand PHP variables.
  # shellcheck disable=SC2016
  php -r '$data=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); $value=$data["tools"][$argv[2]][$argv[3]]??null; if(!is_string($value)||$value===""){exit(64);} echo $value;' "${manifest_path}" "$1" "$2"
}

install_tool() {
  local tool="$1"
  local archive url expected_hash binary_path download_path extraction_path
  archive="$(manifest_value "${tool}" archive)"
  url="$(manifest_value "${tool}" url)"
  expected_hash="$(manifest_value "${tool}" sha256)"
  binary_path="$(manifest_value "${tool}" binary_path)"
  download_path="${temporary_root}/${archive}"
  extraction_path="${temporary_root}/${tool}"

  case "${url}" in
    https://github.com/*/releases/download/*) ;;
    *) echo "Rejected non-approved tool source for ${tool}." >&2; exit 1 ;;
  esac

  curl --proto '=https' --tlsv1.2 --fail --location --retry 3 --output "${download_path}" "${url}"
  printf '%s  %s\n' "${expected_hash}" "${download_path}" | sha256sum --check --status
  mkdir -p "${extraction_path}"
  tar -xzf "${download_path}" -C "${extraction_path}"
  if [[ ! -f "${extraction_path}/${binary_path}" ]]; then
    echo "Verified ${tool} archive did not contain the expected binary." >&2
    exit 1
  fi
  install -m 0755 "${extraction_path}/${binary_path}" "${binary_root}/${tool}"
  echo "Installed ${tool} $(manifest_value "${tool}" version) from a verified archive."
}

for tool in actionlint gitleaks shellcheck trivy; do
  install_tool "${tool}"
done

echo "Security tools installed at ${binary_root}"
