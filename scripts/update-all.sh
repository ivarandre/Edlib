#!/bin/bash

source $HOME/.nvm/nvm.sh

# cd to this script directory
cd "$(dirname "$0")"

# Go to sourcecode folder
pushd ../sourcecode

# nvm and yarn repos
declare -a nodeRepos=(
  "apis/auth"
  "apis/doku"
  "apis/lti"
  "apis/resources"
  "apis/url"
  "proxies/auth"
  "proxies/recommendation"
  "proxies/resource"
  "proxies/lti"
  "proxies/admin"
  "proxies/doku"
  "proxies/url"
)

# java repos
declare -a javaRepos=(
  "not_migrated/metadataservice"
  "not_migrated/versionapi"
  "not_migrated/edlibfacade"
)

# php repos
declare -a phpRepos=(
  "not_migrated/h5pviewer"
  "not_migrated/licenseapi"
)

for i in "${nodeRepos[@]}"
do
  pushd $i
  nvm use
  git pull
  yarn
  popd
done

for i in "${javaRepos[@]}"
do
  pushd $i
  git pull
  mvn clean package -DskipTests
  popd
done

for i in "${phpRepos[@]}"
do
  pushd $i
  git pull
  nvm use 11
  php7.4 /usr/local/bin/composer install
  if test -f "package.json"; then
    npm i
    npm production
  fi
  popd
done

# Go back to initial folder
popd