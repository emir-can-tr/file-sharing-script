#!/bin/bash
# =============================================================================
# DOSYALARI SUNUCUYA KOPYALA (DEPLOY)
# =============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Root kontrolü
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}HATA: Bu scripti root olarak çalıştırın!${NC}"
    exit 1
fi

# Domain bilgisi al
if [ -f /var/www/*/.install_info ]; then
    source /var/www/*/.install_info
else
    read -p "Domain adını girin (örn: dosya.emircan.tr): " DOMAIN
fi

if [ -z "$DOMAIN" ]; then
    echo -e "${RED}HATA: Domain bulunamadı!${NC}"
    exit 1
fi

WEB_ROOT="/var/www/${DOMAIN}"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  DOSYA DEPLOY${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "Hedef: ${YELLOW}${WEB_ROOT}${NC}"

# Kaynak dizin kontrolü
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$(dirname "$SCRIPT_DIR")"

echo -e "Kaynak: ${YELLOW}${SOURCE_DIR}${NC}"

# =============================================================================
# Site bilgilerini al
# =============================================================================
echo -e "\n${YELLOW}Site bilgilerini girin:${NC}\n"

read -p "Site adı (örn: Emir Can Dosya): " SITE_NAME
if [ -z "$SITE_NAME" ]; then
    SITE_NAME="Dosya Paylaşım"
fi

read -p "Admin kullanıcı adı [admin]: " ADMIN_USER
if [ -z "$ADMIN_USER" ]; then
    ADMIN_USER="admin"
fi

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

# Dosya boyutu için byte değeri hesapla
case $MAX_FILE_SIZE in
    "100M") MAX_FILE_SIZE_BYTES="104857600"; MAX_FILE_SIZE_DISPLAY="100 MB" ;;
    "500M") MAX_FILE_SIZE_BYTES="524288000"; MAX_FILE_SIZE_DISPLAY="500 MB" ;;
    "1G") MAX_FILE_SIZE_BYTES="1073741824"; MAX_FILE_SIZE_DISPLAY="1 GB" ;;
    "2G") MAX_FILE_SIZE_BYTES="2147483648"; MAX_FILE_SIZE_DISPLAY="2 GB" ;;
    "5G") MAX_FILE_SIZE_BYTES="5368709120"; MAX_FILE_SIZE_DISPLAY="5 GB" ;;
    "10G") MAX_FILE_SIZE_BYTES="10737418240"; MAX_FILE_SIZE_DISPLAY="10 GB" ;;
    "0") MAX_FILE_SIZE_BYTES="0"; MAX_FILE_SIZE_DISPLAY="Sınırsız" ;;
    *) MAX_FILE_SIZE_BYTES="2147483648"; MAX_FILE_SIZE_DISPLAY="2 GB" ;;
esac

# =============================================================================
echo -e "\n${YELLOW}[1/4] Dosyalar kopyalanıyor...${NC}"
# =============================================================================

cp -v ${SOURCE_DIR}/index.php ${WEB_ROOT}/
cp -v ${SOURCE_DIR}/config.php ${WEB_ROOT}/
cp -v ${SOURCE_DIR}/admin.php ${WEB_ROOT}/
cp -v ${SOURCE_DIR}/download.php ${WEB_ROOT}/
cp -v ${SOURCE_DIR}/upload_handler.php ${WEB_ROOT}/
cp -v ${SOURCE_DIR}/style.css ${WEB_ROOT}/
[ -f ${SOURCE_DIR}/robots.txt ] && cp -v ${SOURCE_DIR}/robots.txt ${WEB_ROOT}/

# =============================================================================
echo -e "\n${YELLOW}[2/4] Config ayarlanıyor...${NC}"
# =============================================================================

# Config dosyasını güncelle
sed -i "s|define('SITE_NAME', '.*');|define('SITE_NAME', '${SITE_NAME}');|" ${WEB_ROOT}/config.php
sed -i "s|define('SITE_URL', '.*');|define('SITE_URL', 'https://${DOMAIN}');|" ${WEB_ROOT}/config.php
sed -i "s|define('ADMIN_USERNAME', '.*');|define('ADMIN_USERNAME', '${ADMIN_USER}');|" ${WEB_ROOT}/config.php
sed -i "s|define('ADMIN_PASSWORD', '.*');|define('ADMIN_PASSWORD', '${ADMIN_PASS}');|" ${WEB_ROOT}/config.php
sed -i "s|define('MAX_FILE_SIZE', .*);|define('MAX_FILE_SIZE', ${MAX_FILE_SIZE_BYTES});|" ${WEB_ROOT}/config.php

# index.php'deki hint metnini güncelle
sed -i "s|Sunucu limitine kadar yükleyebilirsiniz|${MAX_FILE_SIZE_DISPLAY}'a kadar yükleyebilirsiniz|" ${WEB_ROOT}/index.php

# download.php'deki /simple/ yolunu kaldır
sed -i "s|/simple/download.php|/download.php|g" ${WEB_ROOT}/index.php

echo -e "${GREEN}Config ayarlandı.${NC}"

# =============================================================================
echo -e "\n${YELLOW}[3/4] Dizinler oluşturuluyor...${NC}"
# =============================================================================

mkdir -p ${WEB_ROOT}/files
mkdir -p ${WEB_ROOT}/data
mkdir -p ${WEB_ROOT}/temp

# data/files.json oluştur
if [ ! -f ${WEB_ROOT}/data/files.json ]; then
    echo "[]" > ${WEB_ROOT}/data/files.json
fi

# =============================================================================
echo -e "\n${YELLOW}[4/4] İzinler ayarlanıyor...${NC}"
# =============================================================================

chown -R www-data:www-data ${WEB_ROOT}
find ${WEB_ROOT} -type d -exec chmod 755 {} \;
find ${WEB_ROOT} -type f -exec chmod 644 {} \;
chmod 770 ${WEB_ROOT}/files
chmod 770 ${WEB_ROOT}/data
chmod 770 ${WEB_ROOT}/temp
chmod 660 ${WEB_ROOT}/data/files.json
chmod 640 ${WEB_ROOT}/config.php

# Nginx yeniden yükle
systemctl reload nginx

# PHP-FPM yeniden başlat
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
systemctl restart php${PHP_VERSION}-fpm

echo -e "\n${GREEN}========================================${NC}"
echo -e "${GREEN}  DEPLOY TAMAMLANDI!${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e ""
echo -e "Site: ${YELLOW}https://${DOMAIN}${NC}"
echo -e "Admin: ${YELLOW}https://${DOMAIN}/admin.php${NC}"
echo -e ""
echo -e "${CYAN}Giriş Bilgileri:${NC}"
echo -e "  Kullanıcı: ${GREEN}${ADMIN_USER}${NC}"
echo -e "  Şifre: ${GREEN}(girdiğiniz şifre)${NC}"
echo -e ""
echo -e "${CYAN}Ayarlar:${NC}"
echo -e "  Dosya Limiti: ${GREEN}${MAX_FILE_SIZE_DISPLAY}${NC}"
echo -e ""
echo -e "${YELLOW}Test edin:${NC}"
echo -e "  curl -I https://${DOMAIN}"
echo -e ""
