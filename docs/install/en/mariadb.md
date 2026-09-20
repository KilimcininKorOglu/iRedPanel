# Installing iRedPanel on an iRedMail MariaDB/MySQL Server

This guide installs iRedPanel for an iRedMail server that uses the MySQL/MariaDB backend. It covers two methods: a native install on the mail server, and a Docker container.

Other backends: [OpenLDAP](ldap.md), [PostgreSQL](postgresql.md). Turkish version: [../tr/mariadb.md](../tr/mariadb.md).

## Compatibility

| iRedPanel | Compatible iRedMail versions |
|-----------|------------------------------|
| 1.0.5     | 1.7.4, 1.8.0, 1.8.1, 1.8.2, 1.8.3, 1.8.4, 1.8.5, 1.8.6, 1.8.7, 1.8.8 |

[`compatibility.json`](../../../compatibility.json) in the repository is the authoritative list. The dashboard and the **System > iRedMail Compatibility** page read it. Check the iRedMail version of your server:

```bash
cat /etc/iredmail-release
```

When you upgrade, upgrade iRedMail first. Then install the iRedPanel version that lists the new iRedMail version.

## What the Panel Connects To

| Service | Default address on the mail server | Used for | Required |
|---------|------------------------------------|----------|----------|
| MariaDB `vmail` | `127.0.0.1:3306` | Domains, mailboxes, aliases, admins | Yes |
| MariaDB `iredadmin` | `127.0.0.1:3306` | Activity log, panel settings, API keys, domain ownership | Recommended |
| MariaDB `amavisd` | `127.0.0.1:3306` | Quarantine, mail log, spam policy, white/blacklist | Optional |
| Amavisd AM.PDP | `127.0.0.1:9998` | Release of quarantined mail | Optional |
| MariaDB `iredapd` | `127.0.0.1:3306` | Throttle, greylisting, rDNS, SenderScore | Optional |
| mlmmjadmin API | `127.0.0.1:7790` | Create, update and delete mailing lists | For mailing lists |
| SMTP submission | `587` | Newsletter confirmation and quarantine notification mail | Optional |

Every service listens on `127.0.0.1` after a standard iRedMail install. A native install on the mail server therefore needs no network change. A Docker container or another host needs the changes in [Network Access](#network-access).

## Step 1: Collect the Credentials

iRedAdmin, which the iRedMail installer sets up, stores every credential that the panel needs in `/opt/www/iredadmin/settings.py`. Print them on the mail server. The output contains passwords.

```bash
grep -E '^(vmail|iredadmin|amavisd|iredapd)_db_|^amavisd_quarantine_port|^mlmmjadmin_api_auth_token|^storage_base_directory' /opt/www/iredadmin/settings.py
```

| `settings.py` key | iRedPanel variable |
|-------------------|--------------------|
| `vmail_db_host`, `vmail_db_port`, `vmail_db_name`, `vmail_db_user`, `vmail_db_password` | `IREDPANEL_MYSQL_HOST`, `_PORT`, `_DATABASE`, `_USER`, `_PASSWORD` |
| `iredadmin_db_*` | `IREDPANEL_IREDADMIN_DB_HOST`, `_PORT`, `_NAME`, `_USER`, `_PASSWORD` |
| `amavisd_db_*` | `IREDPANEL_AMAVISD_DB_HOST`, `_PORT`, `_NAME`, `_USER`, `_PASSWORD` |
| `amavisd_quarantine_port` | `IREDPANEL_AMAVISD_QUARANTINE_PORT` |
| `iredapd_db_*` | `IREDPANEL_IREDAPD_DB_HOST`, `_PORT`, `_NAME`, `_USER`, `_PASSWORD` |
| `mlmmjadmin_api_auth_token` | `IREDPANEL_MLMMJADMIN_API_TOKEN` |
| `storage_base_directory` (`/var/vmail/vmail1`) | `IREDPANEL_VMAIL_PATH=/var/vmail`, `IREDPANEL_STORAGE_NODE=vmail1` |

The `vmailadmin` user has `SELECT, INSERT, UPDATE, DELETE` on `vmail`. The `iredadmin` user has all privileges on `iredadmin`. The panel creates its own tables (`panel_settings`, `panel_api_keys`) in the `iredadmin` database on first use.

The mlmmjadmin token is also one of `api_auth_tokens` in `/opt/mlmmjadmin/settings.py`. You can add a separate token for the panel to that list and restart `mlmmjadmin`.

## Step 2: Write the Configuration

Create the configuration from the template. The native install reads `.env`. The Docker install reads `.env.prod`.

```bash
cp .env.example .env          # native
cp .env.example .env.prod     # Docker
```

Set these values. Replace every `<...>` value with the value from Step 1.

```ini
IREDPANEL_BACKEND=mysql
# Generate with: openssl rand -hex 32
IREDPANEL_SECRET_KEY=<random string>

IREDPANEL_MYSQL_HOST=127.0.0.1
IREDPANEL_MYSQL_PORT=3306
IREDPANEL_MYSQL_DATABASE=vmail
IREDPANEL_MYSQL_USER=vmailadmin
IREDPANEL_MYSQL_PASSWORD=<vmail_db_password>
IREDPANEL_VMAIL_PATH=/var/vmail
IREDPANEL_STORAGE_NODE=vmail1

IREDPANEL_IREDADMIN_DB_HOST=127.0.0.1
IREDPANEL_IREDADMIN_DB_NAME=iredadmin
IREDPANEL_IREDADMIN_DB_USER=iredadmin
IREDPANEL_IREDADMIN_DB_PASSWORD=<iredadmin_db_password>

IREDPANEL_AMAVISD_ENABLED=true
IREDPANEL_AMAVISD_DB_HOST=127.0.0.1
IREDPANEL_AMAVISD_DB_NAME=amavisd
IREDPANEL_AMAVISD_DB_USER=amavisd
IREDPANEL_AMAVISD_DB_PASSWORD=<amavisd_db_password>

IREDPANEL_IREDAPD_ENABLED=true
IREDPANEL_IREDAPD_DB_HOST=127.0.0.1
IREDPANEL_IREDAPD_DB_NAME=iredapd
IREDPANEL_IREDAPD_DB_USER=iredapd
IREDPANEL_IREDAPD_DB_PASSWORD=<iredapd_db_password>

IREDPANEL_MLMMJADMIN_API_URL=http://127.0.0.1:7790/api
IREDPANEL_MLMMJADMIN_API_TOKEN=<mlmmjadmin_api_auth_token>

IREDPANEL_SMTP_HOST=mail.example.com
IREDPANEL_SMTP_PORT=587
IREDPANEL_SMTP_SECURITY=starttls
IREDPANEL_SMTP_USER=postmaster@example.com
IREDPANEL_SMTP_PASSWORD=<mailbox password>
IREDPANEL_SMTP_FROM=postmaster@example.com
IREDPANEL_PUBLIC_URL=https://panel.example.com
```

- Use the host name of the mail server certificate as `IREDPANEL_SMTP_HOST`, because `IREDPANEL_SMTP_TLS_VERIFY` defaults to `true`.
- Leave out the Amavisd, iRedAPD, mlmmjadmin or SMTP block when you do not use that feature.
- `.env.example` documents every other variable. The README lists them in tables.

## Method A: Native Install on the Mail Server

This method uses the Nginx and PHP-FPM that iRedMail already runs.

### A1. Install PHP Packages and Composer

iRedPanel needs PHP 8.1 or later. Check the version that PHP-FPM uses:

```bash
php -v
```

Install the extensions and Composer on Debian or Ubuntu:

```bash
apt install php-mysql php-mbstring composer git unzip
```

`php-mysql` provides `pdo_mysql`. On another distribution, install the packages that provide `pdo_mysql` for the PHP version of PHP-FPM, and install Composer from <https://getcomposer.org/>.

### A2. Install the Application

```bash
cd /opt/www
git clone https://github.com/KilimcininKorOglu/iRedPanel.git iredpanel
cd iredpanel
git checkout v1.0.5
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

Edit `.env` as in [Step 2](#step-2-write-the-configuration). Then restrict the file, because it holds passwords. PHP-FPM runs as `www-data` on iRedMail for Debian and Ubuntu.

```bash
chown root:www-data .env
chmod 640 .env
```

### A3. Configure Nginx

iRedMail defines the PHP-FPM upstream `php_workers` in `/etc/nginx/conf-enabled/php_fpm.conf` and the TLS settings in `/etc/nginx/templates/ssl.tmpl`. Create `/etc/nginx/sites-available/iredpanel.conf` for a separate host name:

```nginx
server {
    listen 443 ssl http2;
    server_name panel.example.com;

    root /opt/www/iredpanel/public;
    index index.php;

    include /etc/nginx/templates/ssl.tmpl;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass php_workers;
    }
}
```

The document root must be `public/`. The project root holds `.env` and must never be served.

Enable the site and reload Nginx:

```bash
ln -s /etc/nginx/sites-available/iredpanel.conf /etc/nginx/sites-enabled/iredpanel.conf
nginx -t && systemctl reload nginx
```

`ssl.tmpl` uses the iRedMail certificate (`/etc/ssl/certs/iRedMail.crt`). Replace it with a certificate for `panel.example.com` if the browser must trust the site.

### A4. Apache on a Separate Server

To run the panel with Apache on another server, install `apache2`, `libapache2-mod-php`, `php-mysql` and `php-mbstring`, run `a2enmod rewrite`, and point the virtual host at `public/` with `AllowOverride All`. `public/.htaccess` rewrites the URLs. That server reaches the mail server over the network, so apply [Network Access](#network-access) as well.

## Method B: Docker

The `Dockerfile` builds a PHP 8.4 and Apache image with the `ldap`, `pdo_mysql` and `pdo_pgsql` extensions. `docker-compose.prod.yml` runs it as the container `iredpanel`, publishes `127.0.0.1:8522`, and reads `.env.prod`.

### B1. Build and Start

```bash
git clone https://github.com/KilimcininKorOglu/iRedPanel.git
cd iRedPanel
git checkout v1.0.5
cp .env.example .env.prod
```

Edit `.env.prod` as in [Step 2](#step-2-write-the-configuration). The host values depend on where the container runs:

| Container location | Host value in `.env.prod` |
|--------------------|---------------------------|
| Another server | The address of the mail server |
| The mail server itself | `host.docker.internal` |

On the mail server itself, create `docker-compose.host.yml` so that `host.docker.internal` resolves to the host:

```yaml
services:
  app:
    extra_hosts:
      - "host.docker.internal:host-gateway"
```

Start the container:

```bash
make network
docker compose -f docker-compose.prod.yml -f docker-compose.host.yml up -d --build
```

On another server, `make prod-up` creates the network, builds the image and starts the container.

The container connects over TCP, so apply [Network Access](#network-access) on the mail server.

### B2. Publish the Panel

The container serves plain HTTP on `127.0.0.1:8522`. Put a TLS reverse proxy in front of it. On the mail server, add an Nginx site like the one in [A3](#a3-configure-nginx), with this `location` block instead of the PHP blocks:

```nginx
location / {
    proxy_pass http://127.0.0.1:8522;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

The panel sets the `secure` flag of the session cookie only when PHP sees HTTPS directly. It does not read `X-Forwarded-Proto`, so behind the proxy the cookie has no `secure` flag.

### B3. Features That the Container Does Not Cover

- The image has no `fail2ban-client`. The Fail2ban pages work only in a native install.
- The image has no `doveadm`. The `CRAM-MD5` and `NTLM` password schemes fall back to `SSHA`.
- `cli/deleteExpiredMailboxes.php` deletes maildirs. Run it where `/var/vmail` is mounted, see [Cron Jobs](#cron-jobs).

## Network Access

Apply this section when the panel runs in Docker or on another server. Replace `<panel address>` with the address that the connections come from:

- Docker on the mail server: the subnet of the `iredpanel` network. Print it with `docker network inspect iredpanel -f '{{(index .IPAM.Config 0).Subnet}}'`.
- Another server: the IP address of that server.

### MariaDB

iRedMail sets `bind-address = 127.0.0.1` in `/etc/mysql/mariadb.conf.d/50-server.cnf` and creates its users only for `localhost`. Set the listen address:

```ini
bind-address = 0.0.0.0
```

Restart MariaDB with `systemctl restart mariadb`. Then create the users for the panel address with the same passwords and privileges. MariaDB accepts a subnet as `address/netmask`, for example `'172.18.0.0/255.255.0.0'`.

```sql
CREATE USER 'vmailadmin'@'<panel address>' IDENTIFIED BY '<vmail_db_password>';
GRANT SELECT, INSERT, UPDATE, DELETE ON vmail.* TO 'vmailadmin'@'<panel address>';

CREATE USER 'iredadmin'@'<panel address>' IDENTIFIED BY '<iredadmin_db_password>';
GRANT ALL PRIVILEGES ON iredadmin.* TO 'iredadmin'@'<panel address>';

CREATE USER 'amavisd'@'<panel address>' IDENTIFIED BY '<amavisd_db_password>';
GRANT SELECT, INSERT, UPDATE, DELETE ON amavisd.* TO 'amavisd'@'<panel address>';

CREATE USER 'iredapd'@'<panel address>' IDENTIFIED BY '<iredapd_db_password>';
GRANT ALL PRIVILEGES ON iredapd.* TO 'iredapd'@'<panel address>';
```

### mlmmjadmin

Set `listen_address = '0.0.0.0'` in `/opt/mlmmjadmin/settings.py` and run `systemctl restart mlmmjadmin`. The API uses plain HTTP and trusts any request with a valid token.

### Amavisd Quarantine Release

In `/etc/amavis/conf.d/50-user`, add the address that the panel connects to (the mail server address, or the Docker bridge address for `host.docker.internal`) to `$inet_socket_bind`, and add the panel address to `@inet_acl`. Then run `systemctl restart amavis`. Without this change, every other Amavisd page works, and only the release of quarantined mail fails.

### Firewall

Allow TCP from the panel address to ports `3306`, `7790` and `9998` in the firewall of the mail server, and to no other address. Do not publish these ports to the internet.

## First Login

Open the panel URL and log in as a global admin. The iRedMail installer creates `postmaster@<first domain>` as a global admin. The dashboard shows the domain, user and admin counts. **System > iRedMail Compatibility** shows the compatible iRedMail versions of the installed release.

If the page shows "Configuration error. Check server logs.", a required variable is missing or the PHP extension of the backend is not loaded. The PHP error log names the cause. If the page shows the backend error page, the panel cannot reach MariaDB.

## Cron Jobs

Add the maintenance scripts to the crontab of `root` on the mail server. For Docker, run them with `docker exec iredpanel php cli/<script>`.

```cron
# Delete Amavisd quarantine and mail log records older than the retention setting
30 3 * * * cd /opt/www/iredpanel && php cli/cleanupAmavisdDb.php
# Delete the maildirs of mailboxes whose deferred deletion date has passed
40 3 * * * cd /opt/www/iredpanel && php cli/deleteExpiredMailboxes.php
# Send quarantine notices to mailboxes that enabled them
0 8 * * * cd /opt/www/iredpanel && php cli/notifyQuarantinedRecipients.php
# Replicate accounts from Active Directory (System > Account Resources)
* * * * * cd /opt/www/iredpanel && php cli/replicateAccounts.php
# Disable every mailbox, domain and admin whose expiry date has passed
10 4 * * * cd /opt/www/iredpanel && php cli/disableExpiredAccounts.php
```

`replicateAccounts.php` replicates the account resources of **System > Account Resources**. It runs every enabled resource whose replication interval has passed, and does nothing otherwise. It needs the iredadmin database settings and the PHP `sodium` extension. The panel must reach the domain controller on port 636 (LDAPS) or 389 (StartTLS). The bind password is encrypted with `IREDPANEL_SECRET_KEY`. After a change of that key, enter the bind password of every resource again.

`deleteExpiredMailboxes.php` needs write access to `IREDPANEL_VMAIL_PATH`. It keeps every record when the storage node directory (`vmail1`) is missing, because a missing mount makes every maildir look deleted. Run it with `--dry-run` first.

`disableExpiredAccounts.php` disables an account whose expiry date has passed. It only disables, so an account that gets a later date stays disabled until an admin enables it again. Run it with `--dry-run` first.

## Upgrade

1. Open **System > iRedMail Compatibility** and find the iRedPanel version that lists your target iRedMail version.
2. Upgrade iRedMail.
3. Upgrade iRedPanel:

```bash
# Native
cd /opt/www/iredpanel
git fetch --tags
git checkout v<new version>
composer install --no-dev --optimize-autoloader

# Docker
git fetch --tags
git checkout v<new version>
docker compose -f docker-compose.prod.yml -f docker-compose.host.yml up -d --build
```

Compare `.env.example` of the new version with your configuration file and add new variables.
