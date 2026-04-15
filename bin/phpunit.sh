#!/usr/bin/env bash

# Script should be run with "composer test"

dir=`pwd`
phpunit_bin="$dir/../../../vendor/bin/phpunit"

if [ ! -x "$phpunit_bin" ]; then
    phpunit_bin="$dir/vendor/bin/phpunit"
fi

exec "$phpunit_bin" --configuration="$dir/phpunit.xml.dist" --colors=always "$@"
