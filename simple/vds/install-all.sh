#!/bin/bash
# =============================================================================
# TEK KOMUTLA KURULUM (ALL-IN-ONE) - SIMPLE VERSION
# =============================================================================
# Kullanım: sudo bash install-all.sh
# Parçalama yok, direkt upload
# =============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# Banner
echo -e "${CYAN}"
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║         DOSYA PAYLAŞIM SİSTEMİ - OTOMATİK KURULUM            ║"
echo "║                    (Simple Version)                           ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Root kontrolü
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}HATA: Root olarak çalıştırın!${NC}"
    echo "Kullanım: sudo bash install-all.sh"
    exit 1
fi

# =============================================================================
# KULLANICIDAN BİLGİLERİ AL
# =============================================================================
echo -e "${YELLOW}Kurulum bilgilerini girin:${NC}\n"

# Domain
read -p "Domain (örn: dosya.emircan.tr): " DOMAIN
if [ -z "$DOMAIN" ]; then
    echo -e "${RED}HATA: Domain boş olamaz!${NC}"
    exit 1
fi

# Email
read -p "SSL için e-posta: " EMAIL
if [ -z "$EMAIL" ]; then
    echo -e "${RED}HATA: E-posta boş olamaz!${NC}"
    exit 1
fi

# Site adı
read -p "Site adı (örn: Emir Can Dosya): " SITE_NAME
if [ -z "$SITE_NAME" ]; then
    SITE_NAME="Dosya Paylaşım"
fi

# Admin kullanıcı adı
read -p "Admin kullanıcı adı [admin]: " ADMIN_USER
if [ -z "$ADMIN_USER" ]; then
    ADMIN_USER="admin"
fi

# Admin şifresi
while true; do
    read -s -p "Admin şifresi (min 8 karakter): " ADMIN_PASS
    echo ""
    if [ ${#ADMIN_PASS} -lt 8 ]; then
        echo -e "${RED}Şifre en az 8 karakter olmalı!${NC}"
        continue
    fi
    read -s -p "Şifreyi tekrar girin: " ADMIN_PASS2
    echo ""
    if [ "$ADMIN_PASS" != "$ADMIN_PASS2" ]; then
        echo -e "${RED}Şifreler eşleşmiyor!${NC}"
        continue
    fi
    break
done

# Dosya boyutu limiti
echo -e "\n${YELLOW}Maksimum dosya boyutu seçin:${NC}"
echo "  1) 100 MB"
echo "  2) 500 MB"
echo "  3) 1 GB"
echo "  4) 2 GB"
echo "  5) 5 GB"
echo "  6) 10 GB"
echo "  7) Sınırsız (sunucu limitine bağlı)"
read -p "Seçiminiz [4]: " FILE_LIMIT_CHOICE

case $FILE_LIMIT_CHOICE in
    1) MAX_FILE_SIZE="100M"; MAX_FILE_SIZE_BYTES="104857600"; MAX_FILE_SIZE_DISPLAY="100 MB" ;;
    2) MAX_FILE_SIZE="500M"; MAX_FILE_SIZE_BYTES="524288000"; MAX_FILE_SIZE_DISPLAY="500 MB" ;;
    3) MAX_FILE_SIZE="1G"; MAX_FILE_SIZE_BYTES="1073741824"; MAX_FILE_SIZE_DISPLAY="1 GB" ;;
    4|"") MAX_FILE_SIZE="2G"; MAX_FILE_SIZE_BYTES="2147483648"; MAX_FILE_SIZE_DISPLAY="2 GB" ;;
    5) MAX_FILE_SIZE="5G"; MAX_FILE_SIZE_BYTES="5368709120"; MAX_FILE_SIZE_DISPLAY="5 GB" ;;
    6) MAX_FILE_SIZE="10G"; MAX_FILE_SIZE_BYTES="10737418240"; MAX_FILE_SIZE_DISPLAY="10 GB" ;;
    7) MAX_FILE_SIZE="0"; MAX_FILE_SIZE_BYTES="0"; MAX_FILE_SIZE_DISPLAY="Sınırsız" ;;
    *) MAX_FILE_SIZE="2G"; MAX_FILE_SIZE_BYTES="2147483648"; MAX_FILE_SIZE_DISPLAY="2 GB" ;;
esac

# Özet
echo -e "\n${CYAN}════════════════════════════════════════${NC}"
echo -e "${YELLOW}KURULUM ÖZETİ:${NC}"
echo -e "${CYAN}════════════════════════════════════════${NC}"
echo -e "Domain:        ${GREEN}${DOMAIN}${NC}"
echo -e "Site URL:      ${GREEN}https://${DOMAIN}${NC}"
echo -e "Site Adı:      ${GREEN}${SITE_NAME}${NC}"
echo -e "E-posta:       ${GREEN}${EMAIL}${NC}"
echo -e "Admin:         ${GREEN}${ADMIN_USER}${NC}"
echo -e "Şifre:         ${GREEN}********${NC}"
echo -e "Dosya Limiti:  ${GREEN}${MAX_FILE_SIZE_DISPLAY}${NC}"
echo -e "${CYAN}════════════════════════════════════════${NC}"
echo ""

read -p "Devam etmek istiyor musunuz? (e/h): " CONFIRM
if [ "$CONFIRM" != "e" ]; then
    echo "İptal edildi."
    exit 0
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_ROOT="/var/www/${DOMAIN}"

# =============================================================================
echo -e "\n${CYAN}[1/8] Sistem güncelleniyor...${NC}"
# =============================================================================
apt update && apt upgrade -y

# =============================================================================
echo -e "\n${CYAN}[2/8] Paketler kuruluyor...${NC}"
# =============================================================================
apt install -y nginx php-fpm php-cli php-mbstring php-zip php-curl php-gd \
    php-json php-xml certbot python3-certbot-nginx ufw fail2ban unzip

PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo -e "PHP Versiyonu: ${GREEN}${PHP_VERSION}${NC}"

# =============================================================================
echo -e "\n${CYAN}[3/8] Dizinler oluşturuluyor...${NC}"
# =============================================================================
mkdir -p ${WEB_ROOT}/{files,data,temp}

# Dosyaları kopyala
SOURCE_DIR="$(dirname "$SCRIPT_DIR")"
cp ${SOURCE_DIR}/index.php ${WEB_ROOT}/
cp ${SOURCE_DIR}/config.php ${WEB_ROOT}/
cp ${SOURCE_DIR}/admin.php ${WEB_ROOT}/
cp ${SOURCE_DIR}/download.php ${WEB_ROOT}/
cp ${SOURCE_DIR}/upload_handler.php ${WEB_ROOT}/
cp ${SOURCE_DIR}/style.css ${WEB_ROOT}/
[ -f ${SOURCE_DIR}/robots.txt ] && cp ${SOURCE_DIR}/robots.txt ${WEB_ROOT}/

# files.json oluştur
echo "[]" > ${WEB_ROOT}/data/files.json

echo -e "${GREEN}Dosyalar kopyalandı.${NC}"

# =============================================================================
echo -e "\n${CYAN}[4/8] Config dosyası ayarlanıyor...${NC}"
# =============================================================================

# Config dosyasını güncelle
sed -i "s|define('SITE_NAME', '.*');|define('SITE_NAME', '${SITE_NAME}');|" ${WEB_ROOT}/config.php
sed -i "s|define('SITE_URL', '.*');|define('SITE_URL', 'https://${DOMAIN}');|" ${WEB_ROOT}/config.php
sed -i "s|define('ADMIN_USERNAME', '.*');|define('ADMIN_USERNAME', '${ADMIN_USER}');|" ${WEB_ROOT}/config.php
sed -i "s|define('ADMIN_PASSWORD', '.*');|define('ADMIN_PASSWORD', '${ADMIN_PASS}');|" ${WEB_ROOT}/config.php
sed -i "s|define('MAX_FILE_SIZE', .*);|define('MAX_FILE_SIZE', ${MAX_FILE_SIZE_BYTES});|" ${WEB_ROOT}/config.php

# index.php'deki hint metnini güncelle
sed -i "s|Sunucu limitine kadar yükleyebilirsiniz|${MAX_FILE_SIZE_DISPLAY}'a kadar yükleyebilirsiniz|" ${WEB_ROOT}/index.php

echo -e "${GREEN}Config ayarlandı.${NC}"

# İzinler
chown -R www-data:www-data ${WEB_ROOT}
find ${WEB_ROOT} -type d -exec chmod 755 {} \;
find ${WEB_ROOT} -type f -exec chmod 644 {} \;
chmod 770 ${WEB_ROOT}/files ${WEB_ROOT}/data ${WEB_ROOT}/temp
chmod 660 ${WEB_ROOT}/data/files.json
chmod 640 ${WEB_ROOT}/config.php

# =============================================================================
echo -e "\n${CYAN}[5/8] Nginx ayarlanıyor...${NC}"
# =============================================================================

cat > /etc/nginx/sites-available/${DOMAIN} << EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    root ${WEB_ROOT};
    index index.php index.html;

    # Güvenlik headerleri
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Dosya boyutu limiti
    client_max_body_size ${MAX_FILE_SIZE};
    client_body_timeout 300s;
    fastcgi_read_timeout 300s;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
    }

    # Güvenlik
    location ~ /\. { deny all; }
    location ~* ^/files/.*\.php\$ { deny all; return 403; }
    location ^~ /data/ { deny all; return 403; }
    location ^~ /temp/ { deny all; return 403; }
    location = /config.php { deny all; return 403; }

    access_log /var/log/nginx/${DOMAIN}_access.log;
    error_log /var/log/nginx/${DOMAIN}_error.log;
}
EOF

ln -sf /etc/nginx/sites-available/${DOMAIN} /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

echo -e "${GREEN}Nginx yapılandırıldı.${NC}"

# =============================================================================
echo -e "\n${CYAN}[6/8] PHP ayarlanıyor...${NC}"
# =============================================================================

PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"

# Dosya boyutu ayarları
sed -i "s/^upload_max_filesize = .*/upload_max_filesize = ${MAX_FILE_SIZE}/" $PHP_INI
sed -i "s/^post_max_size = .*/post_max_size = ${MAX_FILE_SIZE}/" $PHP_INI
sed -i 's/^max_execution_time = .*/max_execution_time = 300/' $PHP_INI
sed -i 's/^max_input_time = .*/max_input_time = 300/' $PHP_INI
sed -i 's/^memory_limit = .*/memory_limit = 512M/' $PHP_INI
sed -i 's/^expose_php = .*/expose_php = Off/' $PHP_INI
sed -i 's/^display_errors = .*/display_errors = Off/' $PHP_INI

systemctl restart php${PHP_VERSION}-fpm

echo -e "${GREEN}PHP yapılandırıldı.${NC}"

# =============================================================================
echo -e "\n${CYAN}[7/8] SSL kuruluyor...${NC}"
# =============================================================================

certbot --nginx -d ${DOMAIN} --non-interactive --agree-tos --email ${EMAIL} --redirect

echo -e "${GREEN}SSL aktif.${NC}"

# =============================================================================
echo -e "\n${CYAN}[8/8] Güvenlik ayarlanıyor...${NC}"
# =============================================================================

# Firewall
ufw --force enable
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 'Nginx Full'

# Fail2ban
systemctl enable fail2ban
systemctl start fail2ban

# Kurulum bilgilerini kaydet
cat > ${WEB_ROOT}/.install_info << EOF
DOMAIN=${DOMAIN}
PHP_VERSION=${PHP_VERSION}
MAX_FILE_SIZE=${MAX_FILE_SIZE}
INSTALL_DATE=$(date)
EOF

echo -e "${GREEN}Güvenlik aktif.${NC}"

# =============================================================================
# SONUÇ
# =============================================================================
echo -e "\n${CYAN}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║                    KURULUM TAMAMLANDI!                        ║${NC}"
echo -e "${CYAN}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo -e ""
echo -e "  ${GREEN}✓${NC} Site:  ${YELLOW}https://${DOMAIN}${NC}"
echo -e "  ${GREEN}✓${NC} Admin: ${YELLOW}https://${DOMAIN}/admin.php${NC}"
echo -e ""
echo -e "  ${CYAN}Giriş Bilgileri:${NC}"
echo -e "    Kullanıcı: ${GREEN}${ADMIN_USER}${NC}"
echo -e "    Şifre:     ${GREEN}(kurulumda girdiğiniz şifre)${NC}"
echo -e ""
echo -e "  ${CYAN}Ayarlar:${NC}"
echo -e "    Dosya Limiti: ${GREEN}${MAX_FILE_SIZE_DISPLAY}${NC}"
echo -e "    Web Root:     ${YELLOW}${WEB_ROOT}${NC}"
echo -e ""
echo -e "${GREEN}Kurulum başarıyla tamamlandı!${NC}"
echo -e ""
