#!/bin/bash
ddev exec vendor/bin/phpunit --coverage-html var/coverage
ddev exec ln -sf /var/www/html/var/coverage /var/www/html/public/coverage
ddev launch /coverage/index.html
