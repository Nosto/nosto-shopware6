#!/usr/bin/env bash

# Script should be run with "composer test"

dir=`pwd`
project_root="$(cd "$dir/../../.." && pwd)"

# Prefer the Shopware root PHPUnit so the test runner matches the root
# Composer context used in CI. Fall back to the plugin-local binary for local
# runs when the root binary is not present.
if [ -x "$project_root/vendor/bin/phpunit" ]; then
    phpunit_bin="$project_root/vendor/bin/phpunit"
elif [ -x "$dir/vendor/bin/phpunit" ]; then
    phpunit_bin="$dir/vendor/bin/phpunit"
else
    echo "Could not find phpunit binary in plugin or project root vendor directories." >&2
    exit 1
fi

"$phpunit_bin" --configuration="$dir/phpunit.xml.dist" --colors=always "$@"
