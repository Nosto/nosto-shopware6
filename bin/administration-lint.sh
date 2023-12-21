#!/usr/bin/env bash

# Script should be run with the npm script

./node_modules/.bin/eslint \
  --config .eslintrc-administration.js \
  --ext .js,.vue ./src/Resources/app/administration \
  --resolve-plugins-relative-to ../../../vendor/shopware/administration/Resources/app/administration \
  "$@"
