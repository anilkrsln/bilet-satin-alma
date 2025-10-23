# 🎫 BUBilet – Bilet Satın Alma Platformu

Bu proje, Bilgisayar Mühendisliği 2. sınıf öğrencisi olarak **hiç PHP veya SQL bilgim olmadan**, 20 gün içerisinde adım adım öğrenerek geliştirdiğim bir otobüs bileti satın alma platformudur.

Uygulama; kullanıcı, firma yöneticisi ve admin rollerine sahip çok katmanlı bir bilet satış sistemini, modern bir arayüz ve güvenli bir veritabanı yapısı ile bir araya getirir.
Tamamen PHP, SQLite, Bootstrap 5 ve Docker kullanılarak geliştirilmiştir.

## 🚀 Özellikler
### 👥 Kullanıcı

Sefer arama ve koltuk seçerek bilet satın alma

Kupon kodu kullanarak sabit ₺ indirimi uygulama

Bakiye takibi ve aktif bilet görüntüleme

Otomatik bakiye güncelleme ve bilet iptali sonrası iade

### 🏢 Firma Admini

Kendi firmasına ait seferleri ve satılmış biletleri görüntüleme

Aktif biletleri iptal etme (iade işlemi otomatik yapılır)

Firma bazlı kupon tanımlama

### 🛠️ Sistem Admini

Tüm firmaları ve kullanıcıları yönetme

Kuponları ve firma kayıtlarını görüntüleme

Tüm aktif seferleri listeleme

### ⚙️ Kullanılan Teknolojiler

PHP 8.2 – Sunucu tarafı kodlama
SQLite – Hafif, taşınabilir veritabanı
Bootstrap 5 (Dark Tema) – Responsive arayüz tasarımı
Docker (php:8.2-apache) – Uygulamanın taşınabilir şekilde çalışması
Git & GitHub – Versiyon kontrolü ve proje teslimi

### 🧱 Veritabanı Yapısı

Veritabanı, proje ile birlikte gelen Database klasörü içinde otomatik oluşturulur.

User: Kullanıcı bilgileri, roller, bakiyeler
Bus_Company: Firmalar
Trips: Sefer bilgileri
Tickets: Satılan biletler
Booked_Seats: Koltuk kayıtları
Coupons / User_Coupons: İndirim kuponları ve kullanım geçmişi

## 🐳 Docker ile Çalıştırma

Projeyi klonlayın
git clone https://github.com/anilkrsln/bilet-satin-alma.git

cd bilet-satin-alma

Docker başlatın
docker compose up --build

Tarayıcınızdan şu adrese gidin
http://localhost:8080

## 💡 Öğrenme Süreci

Bu projeyi geliştirirken:

PHP ve SQL'i sıfırdan öğrenerek uygulamalı şekilde geliştirdim.

Veritabanı ilişkileri, PDO kullanımı, oturum yönetimi ve transaction yapısını deneyimledim.

Docker ile yazılımı her ortamda tek komutla çalışabilir hale getirdim.

Git ve GitHub üzerinden versiyon kontrolü yapmayı öğrendim.

Bu proje benim için sadece bir ödev değil, aynı zamanda tam anlamıyla bir öğrenme yolculuğu oldu.

## 📦 Proje Geliştirme Süresi

Toplam Süre: Yaklaşık 20 gün
Çalışma Ortamı: XAMPP (lokal geliştirme) + Docker (taşınabilir teslim)
Öğrenilen Teknolojiler: PHP, SQLite, PDO, Docker, GitHub

## 👨‍💻 Geliştirici

Anıl Karaaslan
Bilgisayar Mühendisliği 2. Sınıf Öğrencisi
GitHub: https://github.com/anilkrsln
Linkedin: www.linkedin.com/in/anıl-bayram-karaaslan-a0b81a305
## 📚 Kurulum Özeti

docker compose up --build → Uygulamayı başlatır
docker compose down → Servisleri kapatır
docker exec -it bubilet_app bash → Konteyner içine girer
php -m | grep sqlite → SQLite modül kontrolü

### 🏁 Sonuç

Bu proje, 20 gün boyunca sıfırdan öğrenme süreciyle, PHP ve SQLite teknolojileriyle geliştirilmiş tam işlevsel bir bilet satın alma sistemidir.
Uygulama Docker üzerinde taşınabilir, Bootstrap dark temalı modern bir arayüze sahip ve üç farklı kullanıcı rolü ile gerçek bir senaryoyu modellemektedir.

**Proje şuan tam haliyle bitmiş değil. İlerleyen süreçte daha iyi bir hale getirmeye çalışacağım.**