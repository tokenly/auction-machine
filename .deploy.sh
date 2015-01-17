echo; echo "updating dependencies";
/usr/local/bin/composer.phar install -d lib --prefer-dist

echo; echo "updating bower dependencies";
$(cd public/public && bower -q install)

echo; echo "clearing config";
/bin/rm -v var/cache/app-config/*

