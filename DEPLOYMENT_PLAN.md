
# دليل ديبلومينت Rose Academy الشامل - من الصفر للنهاية

## نظرة عامة على المشروع
- **المشروع**: Rose Academy
- **الدومين الرئيسي**: https://www.rose-academy.com/
- **API Subdomain**: https://api.rose-academy.com/
- **Backend**: Laravel API (موجود حالياً)
- **Frontend**: React (سيتم تثبيته)
- **قاعدة البيانات**: MySQL
- **Web Server**: Nginx
- **المنصة**: Replit

---

## 📋 المتطلبات والتجهيز الأولي

### 1. تحديث النظام
```bash
sudo apt update && sudo apt upgrade -y
```

### 2. تثبيت المتطلبات الأساسية
```bash
# تثبيت Nginx
sudo apt install -y nginx

# تثبيت MySQL
sudo apt install -y mysql-server

# تثبيت Redis
sudo apt install -y redis-server

# تثبيت PHP 8.2 والإضافات المطلوبة
sudo apt install -y php8.2-fpm php8.2-mysql php8.2-redis php8.2-mbstring \
php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl php8.2-gd \
php8.2-json php8.2-tokenizer php8.2-fileinfo php8.2-dom php8.2-simplexml

# تثبيت Node.js و npm
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# تثبيت Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 3. إعداد الخدمات
```bash
# تفعيل وتشغيل جميع الخدمات
sudo systemctl enable nginx
sudo systemctl enable mysql
sudo systemctl enable redis-server
sudo systemctl enable php8.2-fpm

sudo systemctl start nginx
sudo systemctl start mysql
sudo systemctl start redis-server
sudo systemctl start php8.2-fpm
```

---

## 🗄️ إعداد قاعدة البيانات MySQL

### 1. تأمين MySQL
```bash
sudo mysql_secure_installation
```

### 2. إنشاء قاعدة البيانات والمستخدم
```bash
sudo mysql -u root -p
```

```sql
-- إنشاء قاعدة البيانات
CREATE DATABASE rose_academy_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- إنشاء مستخدم مخصص
CREATE USER 'rose_admin'@'localhost' IDENTIFIED BY 'كلمة_مرور_قوية_هنا';
GRANT ALL PRIVILEGES ON rose_academy_prod.* TO 'rose_admin'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. تحسينات MySQL للإنتاج
```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

إضافة التحسينات التالية:
```ini
[mysqld]
innodb_buffer_pool_size = 1G
max_connections = 200
query_cache_size = 64M
query_cache_type = 1
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
```

```bash
sudo systemctl restart mysql
```

---

## 🚀 إعداد Laravel API للإنتاج

### 1. تحديث ملف البيئة
```bash
cd /home/runner/rose-academy
cp .env.example .env
nano .env
```

محتوى ملف `.env` للإنتاج:
```env
APP_NAME="Rose Academy"
APP_ENV=production
APP_KEY=base64:مفتاح_التطبيق_هنا
APP_DEBUG=false
APP_URL=https://api.rose-academy.com
FRONTEND_URL=https://www.rose-academy.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rose_academy_prod
DB_USERNAME=rose_admin
DB_PASSWORD=كلمة_مرور_قاعدة_البيانات

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### 2. تشغيل أوامر Laravel للإنتاج
```bash
cd /home/runner/rose-academy

# تثبيت التبعيات
composer install --optimize-autoloader --no-dev

# إنشاء مفتاح التطبيق
php artisan key:generate

# تنظيف الكاش
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# إنشاء كاش للإنتاج
php artisan config:cache
php artisan route:cache
php artisan view:cache

# تشغيل المايجريشن
php artisan migrate --force

# تشغيل Seeders (اختياري)
php artisan db:seed --force

# إعداد storage link
php artisan storage:link

# إعداد الصلاحيات
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## ⚛️ إعداد React Frontend

### 1. إنشاء مشروع React
```bash
cd /home/runner/rose-academy
npx create-react-app frontend --template typescript
cd frontend
npm install axios @types/axios
```

### 2. إعداد API Client
```bash
mkdir -p src/config src/types src/services
```

إنشاء ملف إعدادات API:
```typescript
// src/config/api.ts
export const API_BASE_URL = process.env.REACT_APP_API_URL || 'https://api.rose-academy.com/api';

export const API_ENDPOINTS = {
  auth: {
    login: '/auth/login',
    register: '/auth/register',
    logout: '/auth/logout',
    profile: '/auth/profile',
    refresh: '/auth/refresh',
  },
  courses: '/courses',
  lessons: '/lessons',
  subscriptions: '/subscriptions',
  users: '/users',
  notifications: '/notifications',
  comments: '/comments',
  ratings: '/ratings',
  favorites: '/favorites',
};

export const HEADERS = {
  'Content-Type': 'application/json',
  'Accept': 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
};
```

### 3. إعداد Axios Client
```typescript
// src/services/httpClient.ts
import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios';
import { API_BASE_URL, HEADERS } from '../config/api';

class HttpClient {
  private instance: AxiosInstance;

  constructor() {
    this.instance = axios.create({
      baseURL: API_BASE_URL,
      headers: HEADERS,
      timeout: 10000,
    });

    this.setupInterceptors();
  }

  private setupInterceptors(): void {
    // Request interceptor
    this.instance.interceptors.request.use(
      (config) => {
        const token = localStorage.getItem('auth_token');
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Response interceptor
    this.instance.interceptors.response.use(
      (response) => response,
      (error) => {
        if (error.response?.status === 401) {
          localStorage.removeItem('auth_token');
          window.location.href = '/login';
        }
        return Promise.reject(error);
      }
    );
  }

  public get<T = any>(url: string, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.instance.get(url, config);
  }

  public post<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.instance.post(url, data, config);
  }

  public put<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.instance.put(url, data, config);
  }

  public delete<T = any>(url: string, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.instance.delete(url, config);
  }
}

export default new HttpClient();
```

### 4. تحديث package.json
```bash
cd /home/runner/rose-academy/frontend
```

إضافة scripts للبناء:
```json
{
  "scripts": {
    "build:prod": "GENERATE_SOURCEMAP=false REACT_APP_API_URL=https://api.rose-academy.com/api npm run build",
    "build:staging": "REACT_APP_API_URL=https://api.rose-academy.com/api npm run build"
  }
}
```

### 5. بناء المشروع للإنتاج
```bash
cd /home/runner/rose-academy/frontend
npm run build:prod

# إعداد الصلاحيات
sudo chown -R www-data:www-data build/
```

---

## 🌐 إعداد Nginx مع Subdomains

### 1. إنشاء ملف تكوين Nginx
```bash
sudo nano /etc/nginx/sites-available/rose-academy
```

محتوى ملف التكوين:
```nginx
# إعدادات عامة
upstream php-fpm {
    server 127.0.0.1:9000;
}

# Rate Limiting
limit_req_zone $binary_remote_addr zone=api:10m rate=60r/m;
limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
limit_req_zone $binary_remote_addr zone=general:10m rate=100r/m;

# Main Website (Frontend)
server {
    listen 5000;
    server_name www.rose-academy.com rose-academy.com;
    root /home/runner/rose-academy/frontend/build;
    index index.html;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' https: data: blob: 'unsafe-inline' 'unsafe-eval'" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Rate limiting for general requests
    limit_req zone=general burst=50 nodelay;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_comp_level 6;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/json
        application/javascript
        application/xml+rss
        application/atom+xml
        image/svg+xml;

    # Static assets caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # React App routing
    location / {
        try_files $uri $uri/ /index.html;
        add_header Cache-Control "no-cache, no-store, must-revalidate";
    }

    # Health check
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }

    # Security: Block access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}

# API Subdomain
server {
    listen 5000;
    server_name api.rose-academy.com;
    root /home/runner/rose-academy/public;
    index index.php;

    # Security Headers for API
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # CORS Headers
    add_header Access-Control-Allow-Origin "https://www.rose-academy.com" always;
    add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS" always;
    add_header Access-Control-Allow-Headers "Authorization, Content-Type, Accept, X-Requested-With" always;
    add_header Access-Control-Allow-Credentials "true" always;
    add_header Access-Control-Max-Age "86400" always;

    # Handle preflight requests
    if ($request_method = 'OPTIONS') {
        add_header Access-Control-Allow-Origin "https://www.rose-academy.com" always;
        add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS" always;
        add_header Access-Control-Allow-Headers "Authorization, Content-Type, Accept, X-Requested-With" always;
        add_header Access-Control-Allow-Credentials "true" always;
        add_header Access-Control-Max-Age "86400" always;
        add_header Content-Length 0;
        add_header Content-Type text/plain;
        return 204;
    }

    # General API rate limiting
    limit_req zone=api burst=30 nodelay;

    # API routes
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Authentication endpoints with stricter rate limiting
    location /api/auth {
        limit_req zone=login burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_pass php-fpm;
        fastcgi_index index.php;
        fastcgi_hide_header X-Powered-By;
        
        # PHP-FPM timeout settings
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 180s;
        fastcgi_read_timeout 180s;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_temp_file_write_size 256k;
    }

    # Storage files (videos, images, documents)
    location /storage {
        alias /home/runner/rose-academy/storage/app/public;
        expires 1y;
        add_header Cache-Control "public, immutable";
        
        # Video streaming support
        location ~* \.(mp4|webm|ogg|avi|mov)$ {
            add_header Access-Control-Allow-Origin "https://www.rose-academy.com";
            add_header Cache-Control "public, max-age=31536000";
            # Enable range requests for video streaming
            add_header Accept-Ranges bytes;
        }
    }

    # API Health check
    location /api/health {
        access_log off;
        try_files $uri /index.php?$query_string;
    }

    # PHP-FPM status (for monitoring)
    location ~ ^/(status|ping)$ {
        access_log off;
        allow 127.0.0.1;
        deny all;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_pass php-fpm;
    }

    # Block access to sensitive files
    location ~ /\.(env|git|svn) {
        deny all;
        access_log off;
        log_not_found off;
    }

    location ~ /(vendor|tests|database|resources/views) {
        deny all;
        access_log off;
        log_not_found off;
    }
}
```

### 2. تفعيل الموقع
```bash
# إنشاء رابط رمزي
sudo ln -s /etc/nginx/sites-available/rose-academy /etc/nginx/sites-enabled/

# حذف الموقع الافتراضي
sudo rm -f /etc/nginx/sites-enabled/default

# اختبار التكوين
sudo nginx -t

# إعادة تشغيل Nginx
sudo systemctl restart nginx
```

---

## 🔧 تحسينات PHP-FPM

### 1. تحسين إعدادات PHP-FPM
```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

```ini
[www]
user = www-data
group = www-data

listen = 127.0.0.1:9000
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

pm.status_path = /status
ping.path = /ping
ping.response = pong

slowlog = /var/log/php8.2-fpm-slow.log
request_slowlog_timeout = 10s

catch_workers_output = yes
decorate_workers_output = no

env[HOSTNAME] = $HOSTNAME
env[PATH] = /usr/local/bin:/usr/bin:/bin
env[TMP] = /tmp
env[TMPDIR] = /tmp
env[TEMP] = /tmp

php_admin_value[sendmail_path] = /usr/sbin/sendmail -t -i -f www@my.domain.com
php_flag[display_errors] = off
php_admin_value[error_log] = /var/log/php8.2-fpm.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 300
php_admin_value[max_input_time] = 300
php_admin_value[post_max_size] = 100M
php_admin_value[upload_max_filesize] = 100M
```

### 2. تحسين إعدادات PHP العامة
```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

```ini
; تحسينات الأداء
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
max_input_vars = 3000
post_max_size = 100M
upload_max_filesize = 100M

; إعدادات OPcache
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
opcache.save_comments = 1

; إعدادات الجلسات
session.driver = redis
session.lifetime = 120
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict

; إعدادات الأمان
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
```

```bash
sudo systemctl restart php8.2-fpm
```

---

## 🔄 إعداد Redis

### 1. تحسين إعدادات Redis
```bash
sudo nano /etc/redis/redis.conf
```

```conf
# تحسينات الأداء
maxmemory 512mb
maxmemory-policy allkeys-lru

# إعدادات الحفظ
save 900 1
save 300 10
save 60 10000

# إعدادات الشبكة
bind 127.0.0.1
port 6379
timeout 300

# إعدادات الأمان
requirepass كلمة_مرور_redis_قوية

# تحسينات أخرى
tcp-keepalive 300
tcp-backlog 511
databases 16
```

```bash
sudo systemctl restart redis-server
```

---

## 🔒 إعداد الأمان والحماية

### 1. إعداد Firewall (UFW)
```bash
# تفعيل UFW
sudo ufw enable

# السماح بالمنافذ الأساسية
sudo ufw allow ssh
sudo ufw allow 5000/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# منع الوصول المباشر للخدمات الداخلية
sudo ufw deny 3306
sudo ufw deny 6379
sudo ufw deny 9000

# عرض حالة Firewall
sudo ufw status
```

### 2. إعداد Fail2Ban
```bash
sudo apt install -y fail2ban

sudo nano /etc/fail2ban/jail.local
```

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[nginx-http-auth]
enabled = true

[nginx-limit-req]
enabled = true
filter = nginx-limit-req
action = iptables-multiport[name=ReqLimit, port="http,https", protocol=tcp]
logpath = /var/log/nginx/*error.log
findtime = 600
bantime = 7200
maxretry = 10

[php-fpm]
enabled = true
port = http,https
filter = php-fpm
logpath = /var/log/php*fpm.log
maxretry = 5
```

```bash
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

---

## 📊 إعداد المراقبة والسجلات

### 1. إعداد Logrotate
```bash
sudo nano /etc/logrotate.d/rose-academy
```

```
/var/log/nginx/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 644 nginx nginx
    sharedscripts
    postrotate
        systemctl reload nginx
    endscript
}

/home/runner/rose-academy/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

### 2. إعداد Supervisor للـ Queue Workers
```bash
sudo apt install -y supervisor

sudo nano /etc/supervisor/conf.d/rose-academy-worker.conf
```

```ini
[program:rose-academy-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /home/runner/rose-academy/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/rose-academy-queue.log
stopwaitsecs=3600
startsecs=10
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start rose-academy-queue:*
```

---

## 🚀 سكريبتات الديبلومينت الآلي

### 1. سكريبت الإعداد الأولي
```bash
mkdir -p /home/runner/rose-academy/scripts
nano /home/runner/rose-academy/scripts/setup.sh
```

```bash
#!/bin/bash
set -e

echo "🚀 بدء الإعداد الأولي لـ Rose Academy..."

# ألوان للـ output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
    exit 1
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

# التحقق من أن السكريبت يتم تشغيله بصلاحيات sudo
if [ "$EUID" -ne 0 ]; then
    error "يرجى تشغيل السكريبت بصلاحيات sudo"
fi

log "تحديث النظام..."
apt update && apt upgrade -y

log "تثبيت المتطلبات الأساسية..."
apt install -y nginx mysql-server redis-server supervisor fail2ban \
    php8.2-fpm php8.2-mysql php8.2-redis php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath \
    php8.2-intl php8.2-gd php8.2-json php8.2-tokenizer \
    php8.2-fileinfo php8.2-dom php8.2-simplexml

log "تثبيت Node.js..."
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt-get install -y nodejs

log "تثبيت Composer..."
if [ ! -f /usr/local/bin/composer ]; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
fi

log "تفعيل الخدمات..."
systemctl enable nginx mysql redis-server php8.2-fpm supervisor fail2ban
systemctl start nginx mysql redis-server php8.2-fpm supervisor fail2ban

log "✅ تم إنجاز الإعداد الأولي بنجاح!"
```

### 2. سكريبت الديبلومينت الرئيسي
```bash
nano /home/runner/rose-academy/scripts/deploy.sh
```

```bash
#!/bin/bash
set -e

# متغيرات
PROJECT_DIR="/home/runner/rose-academy"
API_DIR="$PROJECT_DIR"
FRONTEND_DIR="$PROJECT_DIR/frontend"
BACKUP_DIR="/home/runner/backups"
DATE=$(date +%Y%m%d_%H%M%S)

# ألوان
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
    exit 1
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

# دالة للنسخ الاحتياطي
backup() {
    log "إنشاء نسخة احتياطية..."
    mkdir -p $BACKUP_DIR
    
    # نسخ احتياطي لقاعدة البيانات
    if command -v mysqldump &> /dev/null; then
        mysqldump -u rose_admin -p rose_academy_prod > "$BACKUP_DIR/db_$DATE.sql" 2>/dev/null || warning "فشل في إنشاء نسخة احتياطية لقاعدة البيانات"
    fi
    
    # نسخ احتياطي للملفات
    tar -czf "$BACKUP_DIR/files_$DATE.tar.gz" -C / home/runner/rose-academy --exclude='node_modules' --exclude='vendor' 2>/dev/null || warning "فشل في إنشاء نسخة احتياطية للملفات"
    
    # حذف النسخ القديمة (أكثر من 7 أيام)
    find $BACKUP_DIR -name "*.sql" -mtime +7 -delete 2>/dev/null || true
    find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete 2>/dev/null || true
}

# دالة لتحديث Laravel API
deploy_api() {
    log "📱 تحديث Laravel API..."
    cd $API_DIR
    
    # تحديث التبعيات
    composer install --optimize-autoloader --no-dev --no-interaction
    
    # وضع الموقع في وضع الصيانة
    php artisan down --render="errors::503" --secret="rose-academy-maintenance" || true
    
    # تنظيف الكاش
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan cache:clear
    
    # إنشاء كاش للإنتاج
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    
    # تشغيل المايجريشن
    php artisan migrate --force
    
    # إعادة تشغيل queue workers
    php artisan queue:restart
    
    # إعداد الصلاحيات
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R 775 storage bootstrap/cache
    
    # إخراج الموقع من وضع الصيانة
    php artisan up
    
    log "✅ تم تحديث Laravel API بنجاح"
}

# دالة لبناء React Frontend
deploy_frontend() {
    log "⚛️ بناء React Frontend..."
    cd $FRONTEND_DIR
    
    # تثبيت التبعيات
    npm ci --silent
    
    # بناء المشروع للإنتاج
    npm run build:prod
    
    # إعداد الصلاحيات
    chown -R www-data:www-data build/
    chmod -R 755 build/
    
    log "✅ تم بناء React Frontend بنجاح"
}

# دالة لإعادة تشغيل الخدمات
restart_services() {
    log "🔄 إعادة تشغيل الخدمات..."
    
    # اختبار إعدادات Nginx
    nginx -t || error "خطأ في إعدادات Nginx"
    
    # إعادة تشغيل الخدمات
    systemctl restart php8.2-fpm
    systemctl reload nginx
    systemctl restart redis-server
    
    # إعادة تشغيل supervisor workers
    supervisorctl restart rose-academy-queue:* 2>/dev/null || warning "فشل في إعادة تشغيل queue workers"
    
    log "✅ تم إعادة تشغيل الخدمات بنجاح"
}

# دالة للفحص الصحي
health_check() {
    log "🏥 فحص صحة النظام..."
    
    # فحص الخدمات
    services=("nginx" "php8.2-fpm" "mysql" "redis-server")
    for service in "${services[@]}"; do
        if systemctl is-active --quiet $service; then
            info "✅ $service: نشط"
        else
            error "❌ $service: غير نشط"
        fi
    done
    
    # فحص الاتصال بقاعدة البيانات
    cd $API_DIR
    if php artisan migrate:status >/dev/null 2>&1; then
        info "✅ قاعدة البيانات: متصلة"
    else
        error "❌ قاعدة البيانات: غير متصلة"
    fi
    
    # فحص الذاكرة والقرص
    memory_usage=$(free | grep Mem | awk '{printf "%.1f", $3/$2 * 100.0}')
    disk_usage=$(df -h / | awk 'NR==2{print $5}' | sed 's/%//')
    
    info "📊 استخدام الذاكرة: ${memory_usage}%"
    info "💾 استخدام القرص: ${disk_usage}%"
    
    if (( $(echo "$memory_usage > 90" | bc -l) )); then
        warning "استخدام الذاكرة مرتفع: ${memory_usage}%"
    fi
    
    if (( disk_usage > 90 )); then
        warning "استخدام القرص مرتفع: ${disk_usage}%"
    fi
}

# تشغيل الديبلومينت
main() {
    log "🚀 بدء عملية الديبلومينت..."
    
    # التحقق من المجلدات
    if [ ! -d "$API_DIR" ]; then
        error "مجلد API غير موجود: $API_DIR"
    fi
    
    # إنشاء نسخة احتياطية
    backup
    
    # تحديث API
    deploy_api
    
    # بناء Frontend (إذا كان موجود)
    if [ -d "$FRONTEND_DIR" ]; then
        deploy_frontend
    else
        warning "مجلد Frontend غير موجود، سيتم تخطي هذه الخطوة"
    fi
    
    # إعادة تشغيل الخدمات
    restart_services
    
    # فحص صحة النظام
    health_check
    
    log "🎉 تم إنجاز الديبلومينت بنجاح!"
    info "🌍 الموقع متاح على: https://www.rose-academy.com"
    info "🔗 API متاح على: https://api.rose-academy.com/api"
    info "📝 سجل النسخ الاحتياطية: $BACKUP_DIR"
}

# تشغيل السكريبت الرئيسي
main "$@"
```

### 3. سكريبت فحص الحالة
```bash
nano /home/runner/rose-academy/scripts/health-check.sh
```

```bash
#!/bin/bash

# ألوان
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

log "🔍 فحص حالة نظام Rose Academy..."

# فحص الموارد
echo ""
info "📊 معلومات النظام:"
echo "CPU Load: $(uptime | awk -F'load average:' '{ print $2 }')"
echo "Memory Usage: $(free -h | awk '/^Mem:/ {printf "%s/%s (%.1f%%)", $3, $2, $3/$2*100}')"
echo "Disk Usage: $(df -h / | awk 'NR==2{printf "%s/%s (%s)", $3, $2, $5}')"
echo "Uptime: $(uptime -p)"

# فحص الخدمات
echo ""
info "🔧 حالة الخدمات:"
services=("nginx" "php8.2-fpm" "mysql" "redis-server" "supervisor")
for service in "${services[@]}"; do
    if systemctl is-active --quiet $service; then
        echo -e "✅ $service: ${GREEN}نشط${NC}"
    else
        echo -e "❌ $service: ${RED}متوقف${NC}"
    fi
done

# فحص المنافذ
echo ""
info "🌐 حالة المنافذ:"
ports=("5000:Nginx" "3306:MySQL" "6379:Redis" "9000:PHP-FPM")
for port_service in "${ports[@]}"; do
    port=$(echo $port_service | cut -d: -f1)
    service=$(echo $port_service | cut -d: -f2)
    if netstat -tuln | grep -q ":$port "; then
        echo -e "✅ $service (Port $port): ${GREEN}مفتوح${NC}"
    else
        echo -e "❌ $service (Port $port): ${RED}مغلق${NC}"
    fi
done

# فحص قاعدة البيانات
echo ""
info "🗄️ حالة قاعدة البيانات:"
cd /home/runner/rose-academy
if php artisan migrate:status >/dev/null 2>&1; then
    echo -e "✅ قاعدة البيانات: ${GREEN}متصلة${NC}"
    
    # عرض عدد الجداول
    table_count=$(php artisan db:show --counts 2>/dev/null | grep -c "│" | awk '{print $1-2}' 2>/dev/null || echo "N/A")
    echo "📊 عدد الجداول: $table_count"
else
    echo -e "❌ قاعدة البيانات: ${RED}غير متصلة${NC}"
fi

# فحص Redis
echo ""
info "🔴 حالة Redis:"
if redis-cli ping >/dev/null 2>&1; then
    echo -e "✅ Redis: ${GREEN}متصل${NC}"
    memory_usage=$(redis-cli info memory | grep used_memory_human | cut -d: -f2 | tr -d '\r')
    echo "💾 استخدام الذاكرة: $memory_usage"
else
    echo -e "❌ Redis: ${RED}غير متصل${NC}"
fi

# فحص Supervisor Workers
echo ""
info "👷 حالة Queue Workers:"
if command -v supervisorctl &> /dev/null; then
    supervisorctl status rose-academy-queue:* 2>/dev/null || echo "لا توجد queue workers مُعرّفة"
else
    echo "Supervisor غير مثبت"
fi

# فحص السجلات الأخيرة
echo ""
info "📋 آخر الأخطاء:"
echo "Nginx Errors (آخر 5):"
tail -n 5 /var/log/nginx/error.log 2>/dev/null || echo "لا توجد أخطاء"

echo ""
echo "Laravel Errors (آخر 5):"
tail -n 5 /home/runner/rose-academy/storage/logs/laravel.log 2>/dev/null || echo "لا توجد أخطاء"

# فحص مساحة القرص المتبقية
echo ""
info "💾 تحليل مساحة القرص:"
df -h | grep -vE '^Filesystem|tmpfs|cdrom'

# فحص العمليات التي تستهلك موارد كثيرة
echo ""
info "🔥 العمليات التي تستهلك CPU أكثر:"
ps aux --sort=-%cpu | head -6

echo ""
info "🔥 العمليات التي تستهلك ذاكرة أكثر:"
ps aux --sort=-%mem | head -6

echo ""
log "✅ انتهى فحص الحالة"
```

### 4. جعل السكريبتات قابلة للتنفيذ
```bash
chmod +x /home/runner/rose-academy/scripts/*.sh
```

---

## 🔄 النسخ الاحتياطية التلقائية

### 1. سكريبت النسخ الاحتياطي
```bash
nano /home/runner/rose-academy/scripts/backup.sh
```

```bash
#!/bin/bash

BACKUP_DIR="/home/runner/backups"
DATE=$(date +%Y%m%d_%H%M%S)
KEEP_DAYS=7

# إنشاء مجلد النسخ الاحتياطية
mkdir -p $BACKUP_DIR

# نسخ احتياطي لقاعدة البيانات
echo "إنشاء نسخة احتياطية لقاعدة البيانات..."
mysqldump -u rose_admin -p'كلمة_المرور' rose_academy_prod | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

# نسخ احتياطي للملفات المهمة
echo "إنشاء نسخة احتياطية للملفات..."
tar -czf "$BACKUP_DIR/files_$DATE.tar.gz" \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='.git' \
    --exclude='storage/logs' \
    --exclude='storage/framework/cache' \
    --exclude='storage/framework/sessions' \
    --exclude='storage/framework/views' \
    /home/runner/rose-academy/

# نسخ احتياطي لإعدادات النظام
echo "إنشاء نسخة احتياطية لإعدادات النظام..."
tar -czf "$BACKUP_DIR/system_config_$DATE.tar.gz" \
    /etc/nginx/sites-available/rose-academy \
    /etc/php/8.2/fpm/pool.d/www.conf \
    /etc/redis/redis.conf \
    /etc/mysql/mysql.conf.d/mysqld.cnf \
    /etc/supervisor/conf.d/rose-academy-worker.conf \
    2>/dev/null

# حذف النسخ القديمة
echo "حذف النسخ الاحتياطية القديمة..."
find $BACKUP_DIR -name "*.sql.gz" -mtime +$KEEP_DAYS -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +$KEEP_DAYS -delete

# عرض حجم النسخ الاحتياطية
echo "حجم النسخ الاحتياطية:"
du -sh $BACKUP_DIR/*

echo "✅ تم إنجاز النسخ الاحتياطي: $DATE"
```

### 2. إعداد Cron للنسخ الاحتياطية التلقائية
```bash
crontab -e
```

إضافة الأسطر التالية:
```bash
# نسخ احتياطي يومي في الساعة 2:00 صباحاً
0 2 * * * /home/runner/rose-academy/scripts/backup.sh >> /var/log/backup.log 2>&1

# فحص صحة النظام كل ساعة
0 * * * * /home/runner/rose-academy/scripts/health-check.sh >> /var/log/health-check.log 2>&1

# تنظيف السجلات الأسبوعي
0 3 * * 0 find /home/runner/rose-academy/storage/logs -name "*.log" -mtime +7 -delete
```

---

## 🚀 تشغيل الديبلومينت النهائي

### 1. تنفيذ الإعداد الأولي (مرة واحدة فقط)
```bash
sudo /home/runner/rose-academy/scripts/setup.sh
```

### 2. تشغيل الديبلومينت الكامل
```bash
cd /home/runner/rose-academy
sudo ./scripts/deploy.sh
```

### 3. فحص حالة النظام
```bash
./scripts/health-check.sh
```

### 4. إنشاء نسخة احتياطية يدوية
```bash
./scripts/backup.sh
```

---

## 🔧 استكشاف الأخطاء وحلها

### 1. مشاكل شائعة

**مشكلة: خطأ 502 Bad Gateway**
```bash
# فحص حالة PHP-FPM
sudo systemctl status php8.2-fpm

# فحص سجلات الأخطاء
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/php8.2-fpm.log

# إعادة تشغيل PHP-FPM
sudo systemctl restart php8.2-fpm
```

**مشكلة: خطأ في الاتصال بقاعدة البيانات**
```bash
# فحص حالة MySQL
sudo systemctl status mysql

# اختبار الاتصال
mysql -u rose_admin -p rose_academy_prod

# فحص إعدادات Laravel
cd /home/runner/rose-academy
php artisan config:show database
```

**مشكلة: مشاكل CORS**
```bash
# فحص إعدادات CORS في Laravel
cd /home/runner/rose-academy
php artisan config:show cors

# إعادة بناء الكاش
php artisan config:cache
```

**مشكلة: بطء في الأداء**
```bash
# فحص استخدام الموارد
htop

# فحص حالة Redis
redis-cli info stats

# تنظيف الكاش
cd /home/runner/rose-academy
php artisan cache:clear
php artisan view:clear
php artisan config:cache
```

### 2. أوامر الطوارئ

**إيقاف جميع الخدمات:**
```bash
sudo systemctl stop nginx php8.2-fpm mysql redis-server
```

**إعادة تشغيل جميع الخدمات:**
```bash
sudo systemctl restart nginx php8.2-fpm mysql redis-server supervisor
```

**استعادة من النسخة الاحتياطية:**
```bash
# استعادة قاعدة البيانات
gunzip < /home/runner/backups/db_YYYYMMDD_HHMMSS.sql.gz | mysql -u rose_admin -p rose_academy_prod

# استعادة الملفات
cd /
sudo tar -xzf /home/runner/backups/files_YYYYMMDD_HHMMSS.tar.gz
```

---

## 📈 مراقبة الأداء والتحسين

### 1. مراقبة الموارد
```bash
# مراقبة استخدام CPU والذاكرة
top -p $(pgrep -d',' nginx,php-fpm,mysqld,redis-server)

# مراقبة استخدام القرص
iotop

# مراقبة حركة الشبكة
nethogs
```

### 2. تحسينات إضافية

**تحسين MySQL:**
```sql
-- تحليل الاستعلامات البطيئة
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;

-- تحسين الجداول
OPTIMIZE TABLE users, courses, lessons, subscriptions;
```

**تحسين Redis:**
```bash
# فحص إحصائيات Redis
redis-cli info stats

# تنظيف الكاش المنتهي الصلاحية
redis-cli FLUSHDB
```

---

## 📋 قائمة التحقق النهائية

### قبل النشر
- [ ] تحديث ملف `.env` للإنتاج
- [ ] تشغيل تستات Laravel
- [ ] بناء React frontend
- [ ] إعداد قاعدة البيانات
- [ ] تكوين Nginx مع Subdomains
- [ ] إعداد SSL certificates
- [ ] تكوين Firewall
- [ ] إعداد النسخ الاحتياطية
- [ ] تشغيل فحص الأمان

### بعد النشر
- [ ] فحص جميع الروابط
- [ ] اختبار API endpoints
- [ ] فحص أداء الموقع
- [ ] مراجعة السجلات
- [ ] اختبار النسخ الاحتياطية
- [ ] فحص مراقبة النظام

---

## 🎯 النتيجة النهائية

بعد اتباع جميع الخطوات في هذا الدليل، ستحصل على:

✅ **Frontend متاح على**: https://www.rose-academy.com  
✅ **API متاح على**: https://api.rose-academy.com/api  
✅ **نظام آمن ومحمي** مع Firewall وFail2Ban  
✅ **أداء عالي** مع تحسينات PHP-FPM وRedis  
✅ **نسخ احتياطية تلقائية** يومية  
✅ **مراقبة شاملة** للنظام والأداء  
✅ **سكريبتات آلية** للديبلومينت والصيانة  

---

## 📞 الدعم والصيانة

### الصيانة الدورية

**يومياً:**
- فحص سجلات الأخطاء
- مراقبة استخدام الموارد
- التحقق من النسخ الاحتياطية

**أسبوعياً:**
- تحديث النظام والحزم
- تنظيف السجلات القديمة
- فحص الأمان

**شهرياً:**
- مراجعة إعدادات الأداء
- تحليل إحصائيات الاستخدام
- تحديث كلمات المرور
- مراجعة وتحديث النسخ الاحتياطية

---

**🎉 تهانينا! نظام Rose Academy جاهز للإنتاج**

هذا الدليل يغطي جميع جوانب الديبلومينت من الصفر حتى النهاية ويضمن حصولك على نظام آمن وعالي الأداء ومجهز للإنتاج الفعلي.
