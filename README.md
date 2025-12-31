# Push

Enhanced WordPress CLI tool inspired by Drupal's Drush.

## Installation

### As a WordPress MU-Plugin (Recommended)

1. **Clone the repo into your mu-plugins directory:**

```bash
cd wp-content/mu-plugins/
git clone <repository-url> push
cd push && composer install
```

2. **Copy the loader to mu-plugins root:**

```bash
cp push/push-mu-loader.php .
```

> **Why?** WordPress only auto-loads PHP files directly in `mu-plugins/` - it doesn't scan subdirectories. The loader file simply includes the main plugin from the `push/` folder.

3. **Visit any WordPress admin page.** The plugin will:
   - Automatically create a `push` symlink in `~/.local/bin/` or `~/bin/`
   - Show an admin notice with the installation status
   - If automatic installation fails, manual instructions are provided

4. **Start using Push CLI:**

```bash
push version
push uli
```

### Manual Installation (Without MU-Plugin)

If you prefer not to use the mu-plugin integration:

```bash
git clone <repository-url> push
cd push && composer install
chmod +x push.php
ln -s $(pwd)/push.php ~/.local/bin/push
```

## Usage

```bash
push [command] [options]
```

All commands accept a `--path` option to specify the WordPress installation:

```bash
push version --path=/var/www/wordpress
```

## Commands

### `version`

Display WordPress version.

```bash
push version
```

### `db-prefix`

Display WordPress database table prefix.

```bash
push db-prefix
```

### `uli`

Generate a one-time login URL for a WordPress user.

```bash
push uli                    # Login as admin user
push uli admin              # Login as user with login "admin"
push uli 1                  # Login as user with ID 1
push uli user@example.com   # Login as user with email
push uli --name=editor      # Login as user with login "editor"
push uli -b                 # Generate URL and open in default browser
push uli --browser=chrome   # Generate URL and open in Chrome
push uli admin -b           # Login as admin and open in browser
```

**How it works:**
- Temporarily swaps the user's password with a secure random password
- Generates a data: URL that auto-submits the login form
- Password expires after 1 hour
- Original password is automatically restored by the mu-plugin on any page load

**Manual password restoration (if not using mu-plugin):**

```bash
push uli --restore          # Restore expired passwords only
push uli --restore-all      # Restore all passwords (including non-expired)
```

## MU-Plugin Features

When installed as a mu-plugin, Push provides:

1. **Automatic symlink creation**: On first admin page load, creates a `push` symlink in your user bin path (`~/.local/bin` or `~/bin`)

2. **Automatic password restoration**: Expired `push uli` password swaps are automatically restored on every WordPress page load

3. **Admin notices**: Shows installation status and manual instructions if needed

## Directory Structure

```
wp-content/mu-plugins/
├── push-mu-loader.php      # Loader file (copy from push/)
└── push/                   # This repository
    ├── push.php            # CLI entry point
    ├── push-loader.php     # WordPress integration (loaded by push-mu-loader.php)
    ├── push-mu-loader.php  # Source loader (copy to parent directory)
    ├── composer.json
    ├── src/
    │   ├── Application.php
    │   ├── Bootstrap.php
    │   ├── Command/
    │   └── ...
    └── vendor/
```

## Development Status

This project is in active development. Phase 1 (Foundation) is currently being implemented.

## License

MIT
