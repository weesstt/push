# Push

Enhanced WordPress CLI tool inspired by Drupal's Drush.

## Installation

### Development Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd push
```

2. Install dependencies:
```bash
composer install
```

3. Make the entry point executable:
```bash
chmod +x push.php
```

4. Create a symlink or add to PATH:
```bash
ln -s $(pwd)/push.php /usr/local/bin/push
```

### Installation in WordPress Project (DDEV, etc.)

For installing in a specific WordPress installation (like DDEV), see [INSTALL.md](INSTALL.md) for detailed instructions.

Quick setup for DDEV:
```bash
# In your WordPress project root
git clone <repository-url> .push-tool
cd .push-tool && composer install && cd ..
ln -s $(pwd)/.push-tool/push.php push
chmod +x push
```

## Usage

```bash
push [command] [options]
```

Or if installed in project directory:
```bash
./push [command] [options]
```

## Development Status

This project is in active development. Phase 1 (Foundation) is currently being implemented.

## License

MIT

