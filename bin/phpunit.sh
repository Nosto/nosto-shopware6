#!/usr/bin/env bash

# Script should be run with "composer test"

dir=`pwd`
project_root="$(cd "$dir/../../.." && pwd)"

# Prefer the Shopware-root PHPUnit in CI so the runner and event classes come from one install.
if [ -x "$project_root/vendor/bin/phpunit" ]; then
    phpunit_bin="$project_root/vendor/bin/phpunit"
else
    phpunit_bin="$dir/vendor/bin/phpunit"
fi

phpunit_args=("$@")

if [ -n "$CI" ]; then
    phpunit_args=("--no-progress" "${phpunit_args[@]}")
fi

"$phpunit_bin" --configuration="$dir/phpunit.xml.dist" --colors=always "${phpunit_args[@]}"
phpunit_exit_code=$?

if [ -n "$CI" ]; then
    if [ "$phpunit_exit_code" -eq 0 ]; then
        printf '\033[32mPHPUnit passed\033[0m\n'
    else
        printf '\033[31mPHPUnit failed\033[0m\n'
    fi
fi

exit "$phpunit_exit_code"
