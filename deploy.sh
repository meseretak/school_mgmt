#!/bin/bash
# ═══════════════════════════════════════════════════════════════
# EduManage Pro - Auto Deploy Script
# Run on Google Cloud VM: bash deploy.sh
# ═══════════════════════════════════════════════════════════════

set -e

REPO="https://github.com/meseretak/school_mgmt.git"
WEB_DIR="/var/www/html/school_mgmt"
DB_USER="school_user"
DB_PASS="SchoolPass2026!"
DB_NAME="school_mgmt"
VM_IP=$(curl -s ifconfig.me)

echo "═══════════════════════════════════════"
echo " EduManage Pro - Deployment Script"
echo " Server IP: $VM_IP"
echo "═══════════════════════════════════════"

# 1. Install dependencies
echo "[1/8] Installing Apache, PHP, MariaDB..."
sudo apt-get update -qq
sudo apt-get install -y -qq apache2 php8.1 php8.1-mysql php8.1-mbstring php8.1-xml php8.1-curl php8.1-zip php8.1-gd libapache2-mod-php8.1 mariadb-server git unzip

# 2. Start services
echo "[2/8] Starting services..."
sudo systemctl start apache2 mariadb
sudo systemctl enable apache2 mariadb

# 3. Setup database
echo "[3/8] Setting up database..."
sudo mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
sudo mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;"

# 4. Clone or update repo
echo "[4/8] Deploying application..."
if [ -d "$WEB_DIR/.git" ]; then
    echo "  Updating existing installation..."
    sudo chown -R $(whoami):$(whoami) $WEB_DIR
    git -C $WEB_DIR fetch origin
    git -C $WEB_DIR reset --hard origin/main
else
    echo "  Fresh installation..."
    sudo rm -rf $WEB_DIR
    sudo git clone $REPO $WEB_DIR
fi

# 5. Import database
echo "[5/8] Importing database..."
mysql -u $DB_USER -p$DB_PASS $DB_NAME < $WEB_DIR/database.sql 2>/dev/null || true
mysql -u $DB_USER -p$DB_PASS $DB_NAME < $WEB_DIR/migrate_all.sql 2>/dev/null || true
mysql -u $DB_USER -p$DB_PASS $DB_NAME < $WEB_DIR/seed_full.sql 2>/dev/null || true

# 6. Configure Apache with env vars (survives git pull forever)
echo "[6/8] Configuring Apache environment..."
sudo bash -c "cat > /etc/apache2/sites-available/school_mgmt.conf << EOF
<VirtualHost *:80>
    DocumentRoot $WEB_DIR
    SetEnv DB_HOST     127.0.0.1
    SetEnv DB_USER     $DB_USER
    SetEnv DB_PASS     $DB_PASS
    SetEnv DB_NAME     $DB_NAME
    SetEnv BASE_URL    http://$VM_IP/school_mgmt
    <Directory $WEB_DIR>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/school_mgmt_error.log
    CustomLog \${APACHE_LOG_DIR}/school_mgmt_access.log combined
</VirtualHost>
EOF"

sudo a2ensite school_mgmt.conf 2>/dev/null || true
sudo a2enmod rewrite 2>/dev/null || true
sudo systemctl restart apache2

# 7. Set permissions
echo "[7/8] Setting permissions..."
sudo chown -R www-data:www-data $WEB_DIR
sudo chmod -R 755 $WEB_DIR
sudo mkdir -p $WEB_DIR/uploads/documents
sudo chmod -R 777 $WEB_DIR/uploads
# Keep .git writable by current user
sudo chown -R $(whoami):$(whoami) $WEB_DIR/.git

# 8. Run library migrations (safe - uses IF NOT EXISTS / IF NOT EXISTS)
echo "[8/8] Running library migrations..."
sudo mysql $DB_NAME -e "
ALTER TABLE library_requests ADD COLUMN IF NOT EXISTS book_id INT NULL;
ALTER TABLE library_requests ADD COLUMN IF NOT EXISTS book_title VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE library_requests ADD COLUMN IF NOT EXISTS needed_by DATE NULL;
ALTER TABLE library_requests ADD COLUMN IF NOT EXISTS note TEXT;
ALTER TABLE library_requests ADD COLUMN IF NOT EXISTS borrow_id INT NULL;
ALTER TABLE library_requests ADD COLUMN IF NOT EXISTS reject_reason TEXT;
ALTER TABLE library_borrows ADD COLUMN IF NOT EXISTS damage_fee DECIMAL(8,2) DEFAULT 0.00;
ALTER TABLE library_borrows ADD COLUMN IF NOT EXISTS fine_paid TINYINT(1) DEFAULT 0;
ALTER TABLE library_borrows ADD COLUMN IF NOT EXISTS fine_paid_at TIMESTAMP NULL;
ALTER TABLE library_borrows ADD COLUMN IF NOT EXISTS fine_paid_by INT NULL;
ALTER TABLE library_borrows ADD COLUMN IF NOT EXISTS renewal_count INT DEFAULT 0;
ALTER TABLE library_borrows ADD COLUMN IF NOT EXISTS condition_on_return ENUM('Good','Damaged','Lost') NULL;
CREATE TABLE IF NOT EXISTS library_renewals (id INT AUTO_INCREMENT PRIMARY KEY, borrow_id INT NOT NULL, requested_by INT NULL, old_due_date DATE NOT NULL, new_due_date DATE NULL, status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending', requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, reviewed_by INT NULL, reviewed_at TIMESTAMP NULL, note TEXT);
CREATE TABLE IF NOT EXISTS library_settings (id INT AUTO_INCREMENT PRIMARY KEY, fine_per_day DECIMAL(8,2) DEFAULT 0.50, max_borrow_days INT DEFAULT 14, max_books_student INT DEFAULT 3, max_books_teacher INT DEFAULT 5, max_renewals INT DEFAULT 2, lost_penalty_multiplier DECIMAL(4,1) DEFAULT 1.5, lost_after_days INT DEFAULT 30, currency VARCHAR(10) DEFAULT 'USD');
INSERT IGNORE INTO library_settings (id) VALUES (1);
UPDATE users SET role='librarian' WHERE email='librarian@school.com' AND (role IS NULL OR role='');
" 2>/dev/null || true

echo ""
echo "═══════════════════════════════════════"
echo " DEPLOYMENT COMPLETE!"
echo " URL: http://$VM_IP/school_mgmt"
echo " Admin:     admin@school.com / password"
echo " Librarian: librarian@school.com / password"
echo "═══════════════════════════════════════"
