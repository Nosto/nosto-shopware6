#!/usr/bin/env bash

# Script should be run with the npm script

./node_modules/.bin/eslint \
  --config .eslintrc-storefront.js \
  --ext .js,.vue ./src/Resources/app/storefront \
  --resolve-plugins-relative-to ../../../vendor/shopware/storefront/Resources/app/storefront \
  "$@"