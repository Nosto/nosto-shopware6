#!/usr/bin/env bash

# Script should be run with "composer test"

dir=`pwd`
project_root="$(cd "$dir/../../.." && pwd)"

# Prefer the plugin's own PHPUnit install so the test runner matches the
# plugin's composer.lock, even when the Shopware root also has a phpunit binary.
if [ -x "$dir/vendor/bin/phpunit" ]; then
    phpunit_bin="$dir/vendor/bin/phpunit"
elif [ -x "$project_root/vendor/bin/phpunit" ]; then
    phpunit_bin="$project_root/vendor/bin/phpunit"
else
    echo "Could not find phpunit binary in plugin or project root vendor directories." >&2
    exit 1
fi

"$phpunit_bin" --configuration="$dir/phpunit.xml.dist" --colors=always "$@"
