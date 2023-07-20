#!/usr/bin/env bash

constraints_file=${1:-"constraints.txt"}
lock_file=${2:-"../composer.lock"}

# Flag to track if a match is found
match_found=0

# Read the constraints file
while IFS=: read -r package version; do
  # Skip lines starting with '#'
  if [[ $package == "#"* ]]; then
    continue
  fi

  # Remove leading/trailing whitespace
  package=$(echo "$package" | tr -d '[:space:]')
  version=$(echo "$version" | tr -d '[:space:]')

  # Extract package version from composer.lock
  package_version=$(jq -r ".packages[] | select(.name == \"$package\") | .version" "$lock_file")

  # Check if the package name matches
  if [[ "$package_version" ]]; then
    if [[ "$package_version" != "$version" ]]; then
      echo "Version mismatch for package $package. Found: $package_version, Expected: $version"
      exit 1
    fi
    match_found=1
  else
    echo "No match found for package: $package"
  fi
done < "$constraints_file"

if [[ $match_found -eq 1 ]]; then
  echo "Packages are compliant with DoF version policy ✅"
  exit 0
else
  echo "No packages found in this project that are listed in DoF build policy ✅"
  exit 0
fi
