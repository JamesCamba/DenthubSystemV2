# Nixpacks Configuration for Railway

Railway's Nixpacks should automatically detect PHP and install required extensions.
If you need to manually specify extensions, use this approach instead.

## Alternative: Use Railway's Auto-Detection

Railway automatically detects PHP from `composer.json` and installs common extensions.
The `nixpacks.toml` file has been simplified to let Railway auto-detect.

## If Extensions Are Missing

If you still get "could not find driver" errors, Railway should install `pdo_pgsql` automatically.
If not, you may need to use a different buildpack approach.
