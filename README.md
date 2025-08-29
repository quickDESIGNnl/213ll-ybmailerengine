# GEM Mailer

GEM Mailer is a WordPress plugin that powers email notifications for new topics and other events. The main plugin file `gem-mailer.php` lives at the repository root and loads individual modules from the `modules/` directory.

## Logging

The plugin uses PHP's `error_log()` function for diagnostics. You can redirect these logs to a specific file by setting a custom error log path:

```php
ini_set('error_log', '/path/to/gem-mailer.log');
```

## Contributors

- cfreer

## License

This project is licensed under the [GNU General Public License v2 or later](LICENSE).
