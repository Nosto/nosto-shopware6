#!/usr/bin/env bash

# Script for running unit tests without Shopware integration
# Usage: ./bin/phpunit-unit.sh [test-path]

dir=`pwd`
./vendor/bin/phpunit --configuration="$dir/phpunit-simple.xml" --colors=always "$@" 