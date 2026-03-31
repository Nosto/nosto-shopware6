#!/usr/bin/env bash

# Script should be run with "composer test"

dir=`pwd`

phpunit_args=("$@")

if [ -n "$CI" ]; then
    phpunit_args=("--no-output" "${phpunit_args[@]}")
fi

./vendor/bin/phpunit --configuration="$dir/phpunit.xml.dist" --colors=always "${phpunit_args[@]}"
