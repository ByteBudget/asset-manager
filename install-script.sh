sudo apt update

sudo apt upgrade 

sudo apt install apache2 mariadb-server mariadb-client php8.5 php8.5-cli php8.5-common php8.5-mysql php8.5-mbstring libapache2-mod-php8.5

sudo cp -r asset-manager/html/* /var/www/html*

sudo rm /var/www/html/index.html

sudo nano /etc/apache2/sites-available/000-default.conf

sudo a2enmod rewrite ssl

sudo systemctl restart apache2

sudo mariadb-secure-installation

sudo chown -R www-data:www-data /var/www/html/

mariadb -u root -p



