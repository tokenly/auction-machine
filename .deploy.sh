echo; echo "updating dependencies";
/usr/local/bin/composer.phar install -d lib --prefer-dist

echo; echo "updating bower dependencies";
$(cd public/public && bower -q install)

TARGET="var/cache/app-config"
echo; echo "clearing config";
if find "$TARGET" -mindepth 1 -print -quit | grep -q .; then
    /bin/rm -v var/cache/app-config/*
else
    echo; echo "The directory $TARGET is empty or non-existent";
fi

