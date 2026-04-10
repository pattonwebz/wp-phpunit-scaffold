# Quick Setup Guide

## Steps

1. **Copy the scaffold files** into your plugin project root:
   ```bash
   cp -r wp-phpunit-scaffold/scaffold/. /path/to/your-plugin/
   cp wp-phpunit-scaffold/setup.sh /path/to/your-plugin/
   ```

2. **Run setup.sh** from your plugin root — it replaces `my-plugin` with your plugin slug everywhere:
   ```bash
   cd /path/to/your-plugin
   bash setup.sh
   ```
   Or edit manually: replace `my-plugin`, `MY_PLUGIN`, `My Plugin`, and `wordpress` (DB credentials) across the copied files.

3. **Set DB credentials** — copy `.env.example` to `.env` and fill in `DB_PASSWORD`:
   ```bash
   cp .env.example .env
   ```

4. **Install WordPress and run tests:**
   ```bash
   bash setup-phpunit.sh
   ```

5. **Run PHPUnit directly** once the Docker stack is up:
   ```bash
   vendor/bin/phpunit
   # or
   docker compose exec phpunit vendor/bin/phpunit
   ```

---

## Running tests

```bash
composer test                    # run the test suite
composer test:coverage           # coverage report in terminal
composer test:coverage-html      # HTML report in coverage/ directory
```

See [README.md](README.md) for full documentation.
