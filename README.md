# GEM Mailer

GEM Mailer is a WordPress plugin that powers email notifications for new topics and other events. The main plugin file `gem-mailer.php` lives at the repository root and loads individual modules from the `modules/` directory.

## Logging

The plugin uses PHP's `error_log()` function for diagnostics. You can redirect these logs to a specific file by setting a custom error log path:

```php
ini_set('error_log', '/path/to/gem-mailer.log');
```

## Testmail versturen

Open in WordPress de beheerpagina **Settings → GEM Mailer** (`/wp-admin/admin.php?page=gem-mailer`). Bovenaan de pagina staan drie knoppen om een testmail naar je eigen beheerdersadres te sturen:

- `wp-admin/admin-post.php?action=gem_mailer_send_test&type=new-topic` – test voor een nieuw onderwerp
- `wp-admin/admin-post.php?action=gem_mailer_send_test&type=topic-reaction` – test voor een reactie op een onderwerp
- `wp-admin/admin-post.php?action=gem_mailer_send_test&type=reaction-reply` – test voor een reactie op een reactie

WordPress voegt automatisch een beveiligingsnonce aan deze links toe, zodat je de knoppen rechtstreeks vanuit de instellingenpagina kunt gebruiken.

## Configuratie

From WordPress 6.0 and up the plugin adds a read-only settings help page under **Settings → GEM Mailer**. The page lists every option key the modules rely on, including which JetEngine relation ID should be stored on each field (for example the relation between a Thema and its gebruikers). Use this overview to verify that the slugs in JetEngine Options Pages exactly match the expected option names.

## Contributors

- cfreer

## License

This project is licensed under the [GNU General Public License v2 or later](LICENSE).
