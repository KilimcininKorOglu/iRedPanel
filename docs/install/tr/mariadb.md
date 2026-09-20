# iRedPanel Kurulumu: iRedMail MariaDB/MySQL Sunucusu

Bu doküman, MySQL/MariaDB backend kullanan bir iRedMail sunucusu için iRedPanel kurulumunu anlatır. İki yöntem vardır: mail sunucusuna native kurulum ve Docker container.

Diğer backend'ler: [OpenLDAP](ldap.md), [PostgreSQL](postgresql.md). İngilizce sürüm: [../en/mariadb.md](../en/mariadb.md).

## Uyumluluk

| iRedPanel | Uyumlu iRedMail sürümleri |
|-----------|---------------------------|
| 1.0.5     | 1.7.4, 1.8.0, 1.8.1, 1.8.2, 1.8.3, 1.8.4, 1.8.5, 1.8.6, 1.8.7, 1.8.8 |

Geçerli liste, repodaki [`compatibility.json`](../../../compatibility.json) dosyasıdır. Dashboard ve **System > iRedMail Compatibility** sayfası bu dosyayı okur. Sunucunuzdaki iRedMail sürümünü kontrol edin:

```bash
cat /etc/iredmail-release
```

Yükseltmede önce iRedMail'i yükseltin. Sonra yeni iRedMail sürümünü listeleyen iRedPanel sürümünü kurun.

## Panelin Bağlandığı Servisler

| Servis | Mail sunucusundaki varsayılan adres | Kullanım | Gerekli |
|--------|-------------------------------------|----------|---------|
| MariaDB `vmail` | `127.0.0.1:3306` | Domain, mailbox, alias, admin | Evet |
| MariaDB `iredadmin` | `127.0.0.1:3306` | Activity log, panel ayarları, API key, domain sahipliği | Önerilir |
| MariaDB `amavisd` | `127.0.0.1:3306` | Karantina, mail log, spam policy, white/blacklist | İsteğe bağlı |
| Amavisd AM.PDP | `127.0.0.1:9998` | Karantinadaki mail'i serbest bırakma | İsteğe bağlı |
| MariaDB `iredapd` | `127.0.0.1:3306` | Throttle, greylisting, rDNS, SenderScore | İsteğe bağlı |
| mlmmjadmin API | `127.0.0.1:7790` | Mailing list oluşturma, güncelleme ve silme | Mailing list için |
| SMTP submission | `587` | Newsletter onay mail'i ve karantina bildirimi | İsteğe bağlı |

Standart bir iRedMail kurulumunda her servis `127.0.0.1` adresini dinler. Bu yüzden mail sunucusuna native kurulum ağ değişikliği gerektirmez. Docker container veya başka bir sunucu için [Ağ Erişimi](#ağ-erişimi) bölümünü uygulayın.

## Adım 1: Erişim Bilgilerini Toplayın

iRedMail installer iRedAdmin'i kurar. iRedAdmin, panelin ihtiyaç duyduğu bütün erişim bilgilerini `/opt/www/iredadmin/settings.py` dosyasında tutar. Bu bilgileri mail sunucusunda yazdırın. Çıktıda parolalar vardır.

```bash
grep -E '^(vmail|iredadmin|amavisd|iredapd)_db_|^amavisd_quarantine_port|^mlmmjadmin_api_auth_token|^storage_base_directory' /opt/www/iredadmin/settings.py
```

| `settings.py` key | iRedPanel değişkeni |
|-------------------|---------------------|
| `vmail_db_host`, `vmail_db_port`, `vmail_db_name`, `vmail_db_user`, `vmail_db_password` | `IREDPANEL_MYSQL_HOST`, `_PORT`, `_DATABASE`, `_USER`, `_PASSWORD` |
| `iredadmin_db_*` | `IREDPANEL_IREDADMIN_DB_HOST`, `_PORT`, `_NAME`, `_USER`, `_PASSWORD` |
| `amavisd_db_*` | `IREDPANEL_AMAVISD_DB_HOST`, `_PORT`, `_NAME`, `_USER`, `_PASSWORD` |
| `amavisd_quarantine_port` | `IREDPANEL_AMAVISD_QUARANTINE_PORT` |
| `iredapd_db_*` | `IREDPANEL_IREDAPD_DB_HOST`, `_PORT`, `_NAME`, `_USER`, `_PASSWORD` |
| `mlmmjadmin_api_auth_token` | `IREDPANEL_MLMMJADMIN_API_TOKEN` |
| `storage_base_directory` (`/var/vmail/vmail1`) | `IREDPANEL_VMAIL_PATH=/var/vmail`, `IREDPANEL_STORAGE_NODE=vmail1` |

`vmailadmin` kullanıcısının `vmail` üzerinde `SELECT, INSERT, UPDATE, DELETE` yetkisi vardır. `iredadmin` kullanıcısının `iredadmin` üzerinde bütün yetkileri vardır. Panel kendi tablolarını (`panel_settings`, `panel_api_keys`) ilk kullanımda `iredadmin` database'inde oluşturur.

mlmmjadmin token'ı, `/opt/mlmmjadmin/settings.py` içindeki `api_auth_tokens` listesinde de bulunur. Panel için bu listeye ayrı bir token ekleyebilirsiniz. Sonra `mlmmjadmin` servisini yeniden başlatın.

## Adım 2: Yapılandırmayı Yazın

Yapılandırmayı şablondan oluşturun. Native kurulum `.env` dosyasını okur. Docker kurulumu `.env.prod` dosyasını okur.

```bash
cp .env.example .env          # native
cp .env.example .env.prod     # Docker
```

Aşağıdaki değerleri girin. Her `<...>` değerini Adım 1'deki değerle değiştirin.

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

- `IREDPANEL_SMTP_HOST` için mail sunucusu sertifikasındaki host adını kullanın. `IREDPANEL_SMTP_TLS_VERIFY` varsayılan olarak `true` değerindedir.
- Kullanmadığınız özelliğin bloğunu (Amavisd, iRedAPD, mlmmjadmin veya SMTP) yazmayın.
- Diğer bütün değişkenler `.env.example` dosyasında açıklanır. README bu değişkenleri tablolarda listeler.

## Yöntem A: Mail Sunucusuna Native Kurulum

Bu yöntem, iRedMail'in çalıştırdığı Nginx ve PHP-FPM'i kullanır.

### A1. PHP Paketlerini ve Composer'ı Kurun

iRedPanel PHP 8.1 veya üstünü ister. PHP-FPM'in kullandığı sürümü kontrol edin:

```bash
php -v
```

Debian veya Ubuntu üzerinde extension'ları ve Composer'ı kurun:

```bash
apt install php-mysql php-mbstring composer git unzip
```

`php-mysql` paketi `pdo_mysql` extension'ını kurar. Başka bir dağıtımda, PHP-FPM'in PHP sürümü için `pdo_mysql` sağlayan paketleri kurun. Composer'ı <https://getcomposer.org/> adresinden kurun.

### A2. Uygulamayı Kurun

```bash
cd /opt/www
git clone https://github.com/KilimcininKorOglu/iRedPanel.git iredpanel
cd iredpanel
git checkout v1.0.5
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

`.env` dosyasını [Adım 2](#adım-2-yapılandırmayı-yazın) bölümündeki gibi düzenleyin. Dosya parola içerir, bu yüzden erişimini kısıtlayın. Debian ve Ubuntu üzerindeki iRedMail'de PHP-FPM `www-data` kullanıcısıyla çalışır.

```bash
chown root:www-data .env
chmod 640 .env
```

### A3. Nginx'i Yapılandırın

iRedMail, PHP-FPM upstream'i `php_workers` değerini `/etc/nginx/conf-enabled/php_fpm.conf` dosyasında tanımlar. TLS ayarları `/etc/nginx/templates/ssl.tmpl` dosyasındadır. Ayrı bir host adı için `/etc/nginx/sites-available/iredpanel.conf` dosyasını oluşturun:

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

Document root `public/` olmalıdır. Proje kökü `.env` dosyasını içerir. Proje kökünü asla yayınlamayın.

Site'ı etkinleştirin ve Nginx'i yeniden yükleyin:

```bash
ln -s /etc/nginx/sites-available/iredpanel.conf /etc/nginx/sites-enabled/iredpanel.conf
nginx -t && systemctl reload nginx
```

`ssl.tmpl` iRedMail sertifikasını (`/etc/ssl/certs/iRedMail.crt`) kullanır. Tarayıcının site'a güvenmesi gerekiyorsa bunu `panel.example.com` için alınmış bir sertifikayla değiştirin.

### A4. Ayrı Bir Sunucuda Apache

Paneli başka bir sunucuda Apache ile çalıştırmak için `apache2`, `libapache2-mod-php`, `php-mysql` ve `php-mbstring` paketlerini kurun. `a2enmod rewrite` komutunu çalıştırın. Virtual host'u `AllowOverride All` ile `public/` dizinine yönlendirin. `public/.htaccess` URL'leri rewrite eder. Bu sunucu mail sunucusuna ağ üzerinden bağlanır, bu yüzden [Ağ Erişimi](#ağ-erişimi) bölümünü de uygulayın.

## Yöntem B: Docker

`Dockerfile`, `ldap`, `pdo_mysql` ve `pdo_pgsql` extension'larını içeren bir PHP 8.4 ve Apache image'ı oluşturur. `docker-compose.prod.yml` bu image'ı `iredpanel` container'ı olarak çalıştırır, `127.0.0.1:8522` adresini yayınlar ve `.env.prod` dosyasını okur.

### B1. Oluşturun ve Başlatın

```bash
git clone https://github.com/KilimcininKorOglu/iRedPanel.git
cd iRedPanel
git checkout v1.0.5
cp .env.example .env.prod
```

`.env.prod` dosyasını [Adım 2](#adım-2-yapılandırmayı-yazın) bölümündeki gibi düzenleyin. Host değerleri container'ın çalıştığı yere bağlıdır:

| Container'ın yeri | `.env.prod` içindeki host değeri |
|-------------------|----------------------------------|
| Başka bir sunucu | Mail sunucusunun adresi |
| Mail sunucusunun kendisi | `host.docker.internal` |

Mail sunucusunun kendisinde `docker-compose.host.yml` dosyasını oluşturun. Bu dosya `host.docker.internal` adını host'a yönlendirir:

```yaml
services:
  app:
    extra_hosts:
      - "host.docker.internal:host-gateway"
```

Container'ı başlatın:

```bash
make network
docker compose -f docker-compose.prod.yml -f docker-compose.host.yml up -d --build
```

Başka bir sunucuda `make prod-up` komutu network'ü oluşturur, image'ı build eder ve container'ı başlatır.

Container TCP üzerinden bağlanır. Bu yüzden mail sunucusunda [Ağ Erişimi](#ağ-erişimi) bölümünü uygulayın.

### B2. Paneli Yayınlayın

Container, `127.0.0.1:8522` üzerinde şifresiz HTTP sunar. Önüne bir TLS reverse proxy koyun. Mail sunucusunda [A3](#a3-nginxi-yapılandırın) bölümündeki gibi bir Nginx site'ı ekleyin. PHP blokları yerine bu `location` bloğunu kullanın:

```nginx
location / {
    proxy_pass http://127.0.0.1:8522;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

Panel, session cookie'sine `secure` flag'ini yalnızca PHP HTTPS'i doğrudan gördüğünde ekler. Panel `X-Forwarded-Proto` header'ını okumaz. Bu yüzden proxy arkasında cookie `secure` flag'i almaz.

### B3. Container'ın Karşılamadığı Özellikler

- Image içinde `fail2ban-client` yoktur. Fail2ban sayfaları yalnızca native kurulumda çalışır.
- Image içinde `doveadm` yoktur. `CRAM-MD5` ve `NTLM` parola şemaları `SSHA` şemasına düşer.
- `cli/deleteExpiredMailboxes.php` maildir siler. Bu script'i `/var/vmail` dizininin mount edildiği yerde çalıştırın. [Cron Job'lar](#cron-joblar) bölümüne bakın.

## Ağ Erişimi

Panel Docker'da veya başka bir sunucuda çalışıyorsa bu bölümü uygulayın. `<panel address>` değerini, bağlantıların geldiği adresle değiştirin:

- Mail sunucusundaki Docker: `iredpanel` network'ünün subnet'i. Subnet'i `docker network inspect iredpanel -f '{{(index .IPAM.Config 0).Subnet}}'` komutuyla yazdırın.
- Başka bir sunucu: o sunucunun IP adresi.

### MariaDB

iRedMail, `/etc/mysql/mariadb.conf.d/50-server.cnf` dosyasında `bind-address = 127.0.0.1` değerini ayarlar. Kullanıcılarını da yalnızca `localhost` için oluşturur. Dinleme adresini değiştirin:

```ini
bind-address = 0.0.0.0
```

MariaDB'yi `systemctl restart mariadb` komutuyla yeniden başlatın. Sonra panel adresi için kullanıcıları aynı parolalar ve yetkilerle oluşturun. MariaDB, subnet'i `address/netmask` biçiminde kabul eder. Örnek: `'172.18.0.0/255.255.0.0'`.

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

`/opt/mlmmjadmin/settings.py` dosyasında `listen_address = '0.0.0.0'` değerini ayarlayın. Sonra `systemctl restart mlmmjadmin` komutunu çalıştırın. API şifresiz HTTP kullanır. Geçerli token'ı olan her isteği kabul eder.

### Amavisd Karantina Serbest Bırakma

`/etc/amavis/conf.d/50-user` dosyasında, panelin bağlandığı adresi `$inet_socket_bind` listesine ekleyin. Bu adres mail sunucusunun adresidir. `host.docker.internal` kullanıyorsanız Docker bridge adresidir. Panel adresini `@inet_acl` listesine ekleyin. Sonra `systemctl restart amavis` komutunu çalıştırın. Bu değişiklik olmadan diğer bütün Amavisd sayfaları çalışır. Yalnızca karantinadaki mail'i serbest bırakma başarısız olur.

### Firewall

Mail sunucusunun firewall'unda, panel adresinden `3306`, `7790` ve `9998` port'larına TCP erişimine izin verin. Başka hiçbir adrese izin vermeyin. Bu port'ları internete açmayın.

## İlk Giriş

Panel URL'ini açın ve bir global admin ile giriş yapın. iRedMail installer, `postmaster@<ilk domain>` hesabını global admin olarak oluşturur. Dashboard domain, kullanıcı ve admin sayılarını gösterir. **System > iRedMail Compatibility** sayfası, kurulu sürümün uyumlu olduğu iRedMail sürümlerini gösterir.

Sayfa "Configuration error. Check server logs." mesajını gösterirse, gerekli bir değişken eksiktir veya backend'in PHP extension'ı yüklü değildir. Nedeni PHP error log'unda yazar. Sayfa backend hata sayfasını gösterirse, panel MariaDB'ye bağlanamıyordur.

## Cron Job'lar

Bakım script'lerini mail sunucusunda `root` kullanıcısının crontab'ına ekleyin. Docker'da script'leri `docker exec iredpanel php cli/<script>` komutuyla çalıştırın.

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

`replicateAccounts.php`, **Sistem > Hesap Kaynakları** sayfasındaki hesap kaynaklarını replike eder. Replikasyon aralığı dolmuş her etkin kaynağı çalıştırır. Aralığı dolmamış bir kaynakta hiçbir şey yapmaz. Script iredadmin veritabanı ayarlarını ve PHP `sodium` extension'ını ister. Panel domain controller'a port 636 (LDAPS) veya 389 (StartTLS) üzerinden bağlanabilmelidir. Bind parolası `IREDPANEL_SECRET_KEY` ile şifrelenir. Bu key değişirse her kaynağın bind parolasını yeniden girin.

`deleteExpiredMailboxes.php`, `IREDPANEL_VMAIL_PATH` dizinine yazma izni ister. Storage node dizini (`vmail1`) yoksa script bütün kayıtları tutar, çünkü mount edilmemiş bir dizin her maildir'i silinmiş gibi gösterir. Script'i önce `--dry-run` ile çalıştırın.

`disableExpiredAccounts.php`, sona erme tarihi geçmiş hesabı devre dışı bırakır. Yalnız devre dışı bırakır, bu yüzden tarihi ileri alınan bir hesabı admin kendisi açar. Önce `--dry-run` ile çalıştırın.

## Yükseltme

1. **System > iRedMail Compatibility** sayfasını açın. Hedef iRedMail sürümünüzü listeleyen iRedPanel sürümünü bulun.
2. iRedMail'i yükseltin.
3. iRedPanel'i yükseltin:

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

Yeni sürümün `.env.example` dosyasını yapılandırma dosyanızla karşılaştırın. Yeni değişkenleri ekleyin.
