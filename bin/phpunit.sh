#!/usr/bin/env bash

# Script should be run with "composer test"

dir=`pwd`

"$dir/vendor/bin/phpunit" --configuration="$dir/phpunit.xml.dist" --colors=always "$@"
