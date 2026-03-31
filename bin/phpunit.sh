#!/usr/bin/env bash

# Script should be run with "composer test"

dir=`pwd`
project_root="$(cd "$dir/../../.." && pwd)"

if [ -x "$project_root/vendor/bin/phpunit" ]; then
    phpunit_bin="$project_root/vendor/bin/phpunit"
else
    phpunit_bin="$dir/vendor/bin/phpunit"
fi

"$phpunit_bin" --configuration="$dir/phpunit.xml.dist" --colors=always "$@"
