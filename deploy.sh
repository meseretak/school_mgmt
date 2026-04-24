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
echo "═══════════════════════════════════════"

# 1. Install dependencies
echo "[1/7] Installing Apache, PHP, MariaDB..."
sudo apt-get update -qq
sudo apt-get install -y -qq apache2 php8.1 php8.1-mysql php8.1-mbstring php8.1-xml php8.1-curl php8.1-zip php8.1-gd libapache2-mod-php8.1 mariadb-server git unzip

# 2. Start services
echo "[2/7] Starting services..."
sudo systemctl start apache2 mariadb
sudo systemctl enable apache2 mariadb

# 3. Setup database
echo "[3/7] Setting up database..."
sudo mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
sudo mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;"

# 4. Clone or update repo
echo "[4/7] Deploying application..."
if [ -d "$WEB_DIR/.git" ]; then
    echo "  Updating existing installation..."
    cd $WEB_DIR && sudo git pull origin main
else
    echo "  Fresh installation..."
    sudo rm -rf $WEB_DIR
    sudo git clone $REPO $WEB_DIR
fi

# 5. Import database
echo "[5/7] Importing database..."
mysql -u $DB_USER -p$DB_PASS $DB_NAME < $WEB_DIR/database.sql 2>/dev/null || true
mysql -u $DB_USER -p$DB_PASS $DB_NAME < $WEB_DIR/migrate_all.sql 2>/dev/null || true
mysql -u $DB_USER -p$DB_PASS $DB_NAME < $WEB_DIR/seed_full.sql 2>/dev/null || true

# 6. Configure application
echo "[6/7] Configuring application..."
sudo python3 -c "
content = open('$WEB_DIR/includes/config.php').read()
content = content.replace(\"getenv('DB_HOST') ?: '127.0.0.1'\", \"'127.0.0.1'\")
content = content.replace(\"getenv('DB_USER') ?: 'root'\", \"'$DB_USER'\")
content = content.replace(\"getenv('DB_PASS') ?: ''\", \"'$DB_PASS'\")
content = content.replace(\"getenv('DB_NAME') ?: 'school_mgmt'\", \"'$DB_NAME'\")
content = content.replace(\"getenv('BASE_URL') ?: 'http://localhost/school_mgmt'\", \"'http://$VM_IP/school_mgmt'\")
open('$WEB_DIR/includes/config.php', 'w').write(content)
print('Config updated')
"

# Configure Apache
sudo bash -c "cat > /etc/apache2/sites-available/school_mgmt.conf << 'EOF'
<VirtualHost *:80>
    DocumentRoot $WEB_DIR
    <Directory $WEB_DIR>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF"

sudo a2ensite school_mgmt.conf 2>/dev/null || true
sudo a2enmod rewrite 2>/dev/null || true
sudo systemctl restart apache2

# 7. Set permissions
echo "[7/7] Setting permissions..."
sudo chown -R www-data:www-data $WEB_DIR
sudo chmod -R 755 $WEB_DIR
sudo mkdir -p $WEB_DIR/uploads/documents
sudo chmod -R 777 $WEB_DIR/uploads

echo ""
echo "═══════════════════════════════════════"
echo " DEPLOYMENT COMPLETE!"
echo " URL: http://$VM_IP/school_mgmt"
echo " Admin: admin@school.com / password"
echo "═══════════════════════════════════════"
