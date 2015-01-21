echo; echo "updating dependencies";
/usr/local/bin/composer.phar install -d lib --prefer-dist

echo; echo "updating bower dependencies";
$(cd public/public && bower -q install)

TARGET="var/cache/app-config"
echo; echo "clearing config";
if [ -d "$TARGET" ] && [ "$(ls $TARGET)" ]; then
    /bin/rm -v $TARGET/*
else
    echo; echo "The directory $TARGET is empty or non-existent";
fi

# make a log
echo; echo "creating new log directory"
mkdir -p var/log