# Installation Guide

## Installation in a DDEV WordPress Setup

For DDEV WordPress installations, you have a few options:

### Option 1: Install in WordPress Project Directory (Recommended)

This installs push directly in your WordPress project, making it available both inside and outside the DDEV container.

1. **Navigate to your WordPress project root** (where `wp-config.php` is located):
```bash
cd /path/to/your/wordpress/project
```

2. **Clone or copy the push tool** into your project:
```bash
# Option A: Clone as a subdirectory
git clone <repository-url> .push-tool
cd .push-tool
composer install
cd ..

# Option B: Copy the tool directory
cp -r /path/to/push .push-tool
cd .push-tool
composer install
cd ..
```

3. **Create a wrapper script** in your WordPress root:
```bash
cat > push << 'EOF'
#!/bin/bash
# Use push from inside DDEV container if available, otherwise use host
if command -v ddev &> /dev/null && ddev describe &> /dev/null; then
    ddev exec php .push-tool/push.php "$@"
else
    php .push-tool/push.php "$@"
fi
EOF
chmod +x push
```

4. **Or create a symlink** (if you want to use it from host):
```bash
ln -s $(pwd)/.push-tool/push.php push
chmod +x push
```

### Option 2: Install Inside DDEV Container

Install push inside the DDEV container so it's available when you `ddev ssh`.

1. **SSH into your DDEV container**:
```bash
ddev ssh
```

2. **Install push globally** (inside container):
```bash
cd /tmp
git clone <repository-url> push
cd push
composer install
sudo ln -s $(pwd)/push.php /usr/local/bin/push
exit
```

3. **Use it from inside container**:
```bash
ddev ssh
push version
```

### Option 3: Install on Host Machine (Use with --path)

Install push globally on your host machine and use it with the `--path` option.

1. **Install push globally**:
```bash
cd /path/to/push
composer install
sudo ln -s $(pwd)/push.php /usr/local/bin/push
```

2. **Use with DDEV WordPress**:
```bash
push version --path=/path/to/your/wordpress/project
```

Or navigate to the WordPress directory:
```bash
cd /path/to/your/wordpress/project
push version
```

## Quick Test

After installation, test that it works:

```bash
# If installed in project directory
./push version

# If installed globally
push version --path=/path/to/wordpress

# If using DDEV wrapper
ddev exec php .push-tool/push.php version
```

## DDEV-Specific Notes

- **WordPress root**: In DDEV, your WordPress root is typically where `wp-config.php` is located
- **Database**: Push will automatically connect to the DDEV database when WordPress is loaded
- **Aliases**: Alias files will be stored in `.push/aliases.yml` in your WordPress root directory

