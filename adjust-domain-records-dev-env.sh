#!/usr/bin/env bash

if [ "$PLATFORM_ENVIRONMENT_TYPE" == "production" ]; then
  echo "Error: This only runs on development environments, exiting."
  exit 1
fi

# Check if the environment variable is set
if [ -z "$PLATFORM_BRANCH" ]; then
    echo "Error: PLATFORM_BRANCH environment variable is not set."
    exit 1
fi

# Convert FEATURE_BRANCH_NAME to lowercase for the URL replacement.
feature_branch_name_lower=$(echo "$PLATFORM_BRANCH" | tr '[:upper:]' '[:lower:]')

# Define the file pattern to search for
file_pattern="config/development/config_split.patch.domain.record.*.yml"

# Loop through all files that match the pattern
for file in "/app/${file_pattern}"; do
    if [ -r "$file" ]; then
        echo "Processing file: $file"
        # Replace 'dept-edge' with the value of $FEATURE_BRANCH_NAME
        sed -i "s/dept-edge/$feature_branch_name_lower/g" "$file"
    else
        echo "No files matching the pattern were found."
    fi
done

echo "Domain replacements complete in ${file_pattern}."
