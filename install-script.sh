## Updates your package repos
sudo apt update

## Upgrades your packages
sudo apt upgrade 

## Installs Apache2, MariaDB, PHP8.5, and required PHP8.5 extensions
sudo apt install apache2 mariadb-server mariadb-client php8.5 php8.5-cli php8.5-common php8.5-mysql php8.5-mbstring libapache2-mod-php8.5

## Copies the web directory data over to the default web directory location for Apache2 on Debian based systems
sudo cp -r asset-manager/html/* /var/www/html*

## Removes the default index.html
sudo rm /var/www/html/index.html

## Opens the default Apache2 site configuration file so yuo can modify it 
sudo nano /etc/apache2/sites-available/000-default.conf

## Enables 'rewrite' and 'ssl' Apache mods
sudo a2enmod rewrite ssl

## Restart Apache2 after enabling mods
sudo systemctl restart apache2

## Initializes the MariaDB initial configuration setup
sudo mariadb-secure-installation

## Gives ownership of all web directories over to Apachw2
sudo chown -R www-data:www-data /var/www/html/

## Launches MariaDB as root user
mariadb -u root -p


## Create your database, database user, and their password. 
## Once complete, go to your servers' IP address in the browser '/install.php'
## Example: http://192.168.1.2/install.php
## Input the information and credentials you input into MariaDB and you're done! 
## Happy asset tracking / management!
