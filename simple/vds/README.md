# VDS Kurulum Rehberi - Dosya Paylaşım Sistemi (Simple Version)

## Özellikler

- Yükleme barı ile ilerleme gösterimi
- Parçalı upload (chunked upload) desteği
- Dosya boyutu limiti seçilebilir (100MB - 10GB arası veya sınırsız)
- Şifre korumalı dosya paylaşımı
- Admin paneli
- Drag & Drop yükleme

## Gereksinimler

- Ubuntu 22.04 veya 24.04 LTS
- Minimum 2GB RAM
- Root erişimi
- Domain (DNS A kaydı sunucu IP'sine yönlendirilmiş olmalı)

---

## Hızlı Kurulum (Tek Komut)

```bash
# 1. Dosyaları sunucuya yükle
scp -r dosya-paylasim root@SUNUCU_IP:/root/

# 2. Sunucuya bağlan
ssh root@SUNUCU_IP

# 3. Kurulumu başlat
cd /root/dosya-paylasim/vds
chmod +x *.sh
bash install-all.sh
```

Kurulum sırasında sorulacaklar:
- Domain adı
- SSL için e-posta
- Site adı
- Admin kullanıcı adı
- Admin şifresi
- **Maksimum dosya boyutu** (100MB, 500MB, 1GB, 2GB, 5GB, 10GB veya sınırsız)

---

## Adım Adım Kurulum

```bash
bash 01-install.sh        # Temel kurulum
bash 02-nginx-config.sh   # Nginx (dosya limiti burada sorulur)
bash 03-php-config.sh     # PHP ayarları
bash 04-ssl-setup.sh      # SSL sertifikası
bash 05-deploy.sh         # Dosyaları kopyala (site bilgileri sorulur)
bash 06-security-hardening.sh  # Güvenlik
```

---

## Dosya Boyutu Limiti

Kurulum sırasında seçenekler:

| Seçenek | Boyut | Kullanım |
|---------|-------|----------|
| 1 | 100 MB | Dökümanlar için |
| 2 | 500 MB | Resimler, küçük videolar |
| 3 | 1 GB | Orta boyutlu dosyalar |
| 4 | 2 GB | Büyük dosyalar (varsayılan) |
| 5 | 5 GB | Çok büyük dosyalar |
| 6 | 10 GB | Çok büyük dosyalar |
| 7 | Sınırsız | Sunucu limitine bağlı |

**Not:** Sınırsız seçeneğinde sunucunun disk alanı ve PHP timeout ayarları belirleyici olur.

---

## Dizin Yapısı

```
/var/www/dosya.domain.tr/
├── index.php           # Ana sayfa (upload)
├── admin.php           # Admin panel
├── download.php        # İndirme sayfası
├── upload_handler.php  # Chunked upload handler
├── config.php          # Ayarlar
├── style.css           # Stiller
├── files/              # Yüklenen dosyalar
├── data/               # files.json
└── temp/               # Geçici chunk dosyaları
```

---

## Kurulum Sonrası

### Test

```bash
# Site erişimi
curl -I https://dosya.domain.tr

# Güvenlik testi - bunlar 403 dönmeli:
curl https://dosya.domain.tr/data/files.json
curl https://dosya.domain.tr/config.php
curl https://dosya.domain.tr/temp/
```

### Limit Değiştirme (Sonradan)

```bash
# Nginx
nano /etc/nginx/sites-available/dosya.domain.tr
# client_max_body_size değerini değiştir

# PHP
nano /etc/php/8.x/fpm/php.ini
# upload_max_filesize ve post_max_size değiştir

# Yeniden başlat
systemctl reload nginx
systemctl restart php8.x-fpm
```

---

## Güvenlik

- files/ dizininde PHP çalışmaz
- data/ dizini dışarıdan erişilemez
- temp/ dizini dışarıdan erişilemez
- config.php dışarıdan erişilemez
- Fail2ban brute force koruması
- Rate limiting aktif
- CSRF koruması

---

## Sorun Giderme

```bash
# Nginx durumu
systemctl status nginx
nginx -t

# PHP durumu
systemctl status php8.1-fpm

# Loglar
tail -f /var/log/nginx/dosya.domain.tr_error.log
tail -f /var/log/php_errors.log

# İzinler
ls -la /var/www/dosya.domain.tr/
ls -la /var/www/dosya.domain.tr/files/
ls -la /var/www/dosya.domain.tr/temp/
```

---

## Yedekleme

```bash
# Manuel yedek
tar -czf backup.tar.gz /var/www/dosya.domain.tr/files /var/www/dosya.domain.tr/data

# Geri yükleme
tar -xzf backup.tar.gz -C /
```
