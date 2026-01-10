# Dosya Paylaşım Scripti

Modern ve güvenli PHP dosya paylaşım scripti. Büyük dosyaları parçalara bölerek yükler, şifre koruması ve admin paneli içerir.

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat-square&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)

[Türkçe](README.md) | [English](README.en.md) 

## İki Versiyon

| Versiyon | Açıklama | Dosya Limiti |
|----------|----------|--------------|
| **Ana (Chunk)** | Büyük dosyaları 25MB parçalara bölerek yükler | 10GB'a kadar |
| **Simple** | Klasik form upload, parçalama yok | Sunucu limitine bağlı |

## Özellikler

- **Chunk Upload** - Büyük dosyaları 25MB parçalara bölerek yükler (10GB'a kadar)
- **Şifre Koruması** - Dosyaları şifre ile koruyabilirsiniz
- **Admin Paneli** - Dosya yönetimi, istatistikler ve toplu silme
- **Modern Tasarım** - Koyu tema, responsive ve kullanıcı dostu arayüz
- **Drag & Drop** - Sürükle bırak ile dosya yükleme
- **İlerleme Çubuğu** - Yükleme hızı ve kalan süre gösterimi
- **Güvenlik** - CSRF koruması, XSS koruması, tehlikeli uzantı engelleme
- **SEO Kontrolü** - Ana sayfa indexlenir, indirme sayfaları indexlenmez

## Kurulum

### 1. Dosyaları Yükleyin
Tüm dosyaları sunucunuza yükleyin.

### 2. Ayarları Yapın
`config.php` dosyasını düzenleyin:

```php
define('SITE_NAME', 'Dosya Paylaşım');
define('SITE_URL', 'https://yourdomain.com'); // Domain adresiniz
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'guclu_sifreniz'); // Güçlü bir şifre belirleyin
```

### 3. Klasör İzinleri
Aşağıdaki klasörlere yazma izni verin (chmod 755 veya 775):

```
files/
data/
temp/
```

### 4. PHP Ayarları (İsteğe Bağlı)
Büyük dosya yüklemek için `.user.ini` veya `php.ini` oluşturun:

```ini
upload_max_filesize = 10G
post_max_size = 10G
max_execution_time = 7200
memory_limit = 512M
```

## Dosya Yapısı

```
├── index.php           # Ana sayfa (yükleme formu)
├── download.php        # İndirme sayfası
├── admin.php           # Admin paneli
├── upload_handler.php  # Chunk upload işleyicisi
├── config.php          # Ayarlar ve fonksiyonlar
├── style.css           # Stil dosyası
├── .htaccess           # Apache ayarları
├── robots.txt          # SEO ayarları
├── files/              # Yüklenen dosyalar
├── data/               # JSON veri dosyaları
├── temp/               # Geçici chunk dosyaları
└── simple/             # Basit versiyon (chunk yok)
    ├── index.php
    ├── download.php
    ├── config.php
    ├── style.css
    ├── .htaccess
    ├── robots.txt
    ├── files/
    └── data/
```

## Kullanım

### Dosya Yükleme
1. Ana sayfaya gidin
2. Dosyayı sürükleyip bırakın veya tıklayarak seçin
3. İsteğe bağlı şifre belirleyin
4. "Yükle" butonuna tıklayın
5. İndirme linkini kopyalayın

### Admin Paneli
1. `/admin.php` adresine gidin
2. Kullanıcı adı ve şifre ile giriş yapın
3. Dosyaları görüntüleyin, silin veya link kopyalayın

## Güvenlik Özellikleri

- **CSRF Token** - Form güvenliği
- **XSS Koruması** - Çıktı sanitizasyonu
- **Tehlikeli Uzantı Engelleme** - PHP, htaccess vb. dosyalar engellidir
- **Dosya Adı Sanitizasyonu** - Güvenli dosya isimleri
- **Header Injection Koruması** - İndirme header güvenliği
- **Session Güvenliği** - HttpOnly, Secure cookie ayarları

## Gereksinimler

- PHP 7.4 veya üzeri
- Apache (mod_rewrite aktif)
- Yazma izni (files, data, temp klasörleri)

## Lisans

MIT License - Özgürce kullanabilir ve değiştirebilirsiniz.

## Geliştirici

Made by [Emir Can](https://emircan.tr)
